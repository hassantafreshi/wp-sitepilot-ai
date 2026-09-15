<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\ContentHooks;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Truthful read model for the eight Health checks in the redesign mockup.
 *
 * Only the saved provider configuration is ever tested here. The persisted
 * result deliberately contains an HMAC fingerprint, outcome code, timestamp
 * and latency only; it never stores a credential, endpoint, model or raw
 * provider response.
 */
final class HealthDiagnostics {

	public const OPTION = 'aisaautopilot_health_diagnostics';

	private const SNAPSHOT_VERSION = 1;

	/**
	 * Build the current eight-check report without making an external request.
	 *
	 * @return array{checked_at:int,checks:array<int,array{id:string,label:string,detail:string,state:string,tone:string}>}
	 */
	public function report(): array {
		$config   = $this->saved_provider_config();
		$snapshot = $this->last_provider_snapshot( $config );

		return array(
			'checked_at' => null === $snapshot ? 0 : $snapshot['checked_at'],
			'checks'     => array(
				$this->provider_row( $config, $snapshot ),
				$this->api_key_row( $config, $snapshot ),
				$this->key_storage_row( $config ),
				$this->embeddings_row(),
				$this->cron_row(),
				$this->index_freshness_row(),
				$this->rate_limit_row(),
				$this->runtime_row(),
			),
		);
	}

	/**
	 * Run the one permitted outbound provider request, persist its safe result,
	 * then return all eight checks from the current site state.
	 *
	 * @return array{checked_at:int,checks:array<int,array{id:string,label:string,detail:string,state:string,tone:string}>}
	 */
	public function run(): array {
		$config  = $this->saved_provider_config();
		$started = microtime( true );
		$health  = array(
			'ok'   => false,
			'code' => 'invalid_config',
		);

		if ( $this->provider_is_configured( $config ) ) {
			try {
				$health = ProviderManager::build( $config )->health_check();
			} catch ( \Throwable $error ) {
				// Do not persist or display arbitrary provider/server exceptions.
				$health = array(
					'ok'   => false,
					'code' => 'provider_check_failed',
				);
			}
		}

		$ok   = ! empty( $health['ok'] );
		$code = sanitize_key( (string) ( $health['code'] ?? ( $ok ? 'connected' : 'health_failed' ) ) );
		if ( '' === $code ) {
			$code = $ok ? 'connected' : 'health_failed';
		}

		update_option(
			self::OPTION,
			array(
				'version'     => self::SNAPSHOT_VERSION,
				'fingerprint' => $this->provider_fingerprint( $config ),
				'checked_at'  => time(),
				'provider'    => array(
					'ok'         => $ok,
					'code'       => $code,
					'latency_ms' => min( 60000, max( 0, (int) round( ( microtime( true ) - $started ) * 1000 ) ) ),
				),
			),
			false
		);

		return $this->report();
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 * @param array{ok:bool,code:string,latency_ms:int,checked_at:int}|null      $snapshot
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function provider_row( array $config, ?array $snapshot ): array {
		if ( ! $this->provider_is_configured( $config ) ) {
			return $this->row(
				'provider',
				__( 'Provider reachable', 'ai-site-assistant-autopilot' ),
				__( 'Add a provider, API key and model in Settings before it can be checked.', 'ai-site-assistant-autopilot' ),
				__( 'Failing', 'ai-site-assistant-autopilot' ),
				'bad'
			);
		}

		if ( null === $snapshot ) {
			return $this->row(
				'provider',
				__( 'Provider reachable', 'ai-site-assistant-autopilot' ),
				__( 'Configured, but not checked yet. Run all checks to test the saved provider.', 'ai-site-assistant-autopilot' ),
				__( 'Not checked', 'ai-site-assistant-autopilot' ),
				'idle'
			);
		}

		if ( $snapshot['ok'] ) {
			return $this->row(
				'provider',
				__( 'Provider reachable', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: 1: measured health-check latency in milliseconds, 2: local check timestamp. */
					__( 'The saved provider responded in %1$d ms on %2$s.', 'ai-site-assistant-autopilot' ),
					$snapshot['latency_ms'],
					$this->checked_label( $snapshot['checked_at'] )
				),
				__( 'Good', 'ai-site-assistant-autopilot' ),
				'ok'
			);
		}

		return $this->row(
			'provider',
			__( 'Provider reachable', 'ai-site-assistant-autopilot' ),
			$this->provider_failure_detail( $snapshot['code'] ),
			__( 'Failing', 'ai-site-assistant-autopilot' ),
			'bad'
		);
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 * @param array{ok:bool,code:string,latency_ms:int,checked_at:int}|null      $snapshot
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function api_key_row( array $config, ?array $snapshot ): array {
		if ( '' === $config['api_key'] ) {
			return $this->row(
				'api_key',
				__( 'API key', 'ai-site-assistant-autopilot' ),
				__( 'No effective API key is configured.', 'ai-site-assistant-autopilot' ),
				__( 'Failing', 'ai-site-assistant-autopilot' ),
				'bad'
			);
		}

		if ( null === $snapshot ) {
			return $this->row(
				'api_key',
				__( 'API key', 'ai-site-assistant-autopilot' ),
				__( 'A key is configured but has not been verified by the saved provider check.', 'ai-site-assistant-autopilot' ),
				__( 'Unknown', 'ai-site-assistant-autopilot' ),
				'idle'
			);
		}

		if ( $snapshot['ok'] ) {
			return $this->row(
				'api_key',
				__( 'API key', 'ai-site-assistant-autopilot' ),
				__( 'Accepted during the saved provider check.', 'ai-site-assistant-autopilot' ),
				__( 'Good', 'ai-site-assistant-autopilot' ),
				'ok'
			);
		}

		if ( in_array( $snapshot['code'], array( 'no_api_key', 'provider_unauthorized' ), true ) ) {
			return $this->row(
				'api_key',
				__( 'API key', 'ai-site-assistant-autopilot' ),
				__( 'The saved provider rejected the configured credential. Add or replace the key in Settings.', 'ai-site-assistant-autopilot' ),
				__( 'Failing', 'ai-site-assistant-autopilot' ),
				'bad'
			);
		}

		return $this->row(
			'api_key',
			__( 'API key', 'ai-site-assistant-autopilot' ),
			__( 'A key is configured, but the last provider check could not verify it.', 'ai-site-assistant-autopilot' ),
			__( 'Unknown', 'ai-site-assistant-autopilot' ),
			'warn'
		);
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function key_storage_row( array $config ): array {
		$mode   = WpConnectors::get_active_mode();
		$source = WpConnectors::get_effective_api_key_source();

		if ( 'none' === $mode || '' === $config['api_key'] ) {
			return $this->row(
				'key_storage',
				__( 'Key storage', 'ai-site-assistant-autopilot' ),
				__( 'No readable effective key is available to the saved provider.', 'ai-site-assistant-autopilot' ),
				__( 'Failing', 'ai-site-assistant-autopilot' ),
				'bad'
			);
		}

		if ( 'keyvault' === $mode && 'keyvault' === $source ) {
			return $this->row(
				'key_storage',
				__( 'Key storage', 'ai-site-assistant-autopilot' ),
				__( 'Encrypted in the AISAAutoPilot vault on this site.', 'ai-site-assistant-autopilot' ),
				__( 'Good', 'ai-site-assistant-autopilot' ),
				'ok'
			);
		}

		$detail = match ( $source ) {
			'environment' => __( 'Supplied by the AISAAUTOPILOT_API_KEY environment variable; the plugin does not store it.', 'ai-site-assistant-autopilot' ),
			'constant'    => __( 'Supplied by the AISAAUTOPILOT_API_KEY PHP constant; the plugin does not store it.', 'ai-site-assistant-autopilot' ),
			default       => __( 'Managed by WordPress Connectors; this plugin does not assert its storage encryption.', 'ai-site-assistant-autopilot' ),
		};

		return $this->row(
			'key_storage',
			__( 'Key storage', 'ai-site-assistant-autopilot' ),
			$detail,
			__( 'Good', 'ai-site-assistant-autopilot' ),
			'ok'
		);
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function embeddings_row(): array {
		$manager = new ProviderManager();

		if ( ! $manager->is_configured() ) {
			return $this->row(
				'embeddings',
				__( 'Embeddings', 'ai-site-assistant-autopilot' ),
				__( 'Configure the AI provider before embedding status can be checked.', 'ai-site-assistant-autopilot' ),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		if ( ! $manager->embeddings_enabled() ) {
			return $this->row(
				'embeddings',
				__( 'Embeddings', 'ai-site-assistant-autopilot' ),
				__( 'Off. Knowledge search falls back to keyword ranking.', 'ai-site-assistant-autopilot' ),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		$kb    = new KnowledgeBase();
		$model = $manager->embedding_model();
		if ( ! $kb->table_exists() ) {
			return $this->row(
				'embeddings',
				__( 'Embeddings', 'ai-site-assistant-autopilot' ),
				__( 'No Knowledge Base entries have been indexed yet.', 'ai-site-assistant-autopilot' ),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		$stats    = $kb->get_stats();
		$total    = max( 0, (int) ( $stats['total'] ?? 0 ) );
		$embedded = $kb->count_with_embeddings( $model );
		if ( 0 === $total ) {
			return $this->row(
				'embeddings',
				__( 'Embeddings', 'ai-site-assistant-autopilot' ),
				__( 'No Knowledge Base entries have been indexed yet.', 'ai-site-assistant-autopilot' ),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		if ( $embedded < $total ) {
			return $this->row(
				'embeddings',
				__( 'Embeddings', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: 1: vector count, 2: indexed-entry count, 3: embedding model identifier. */
					__( '%1$s of %2$s indexed entries have vectors for %3$s.', 'ai-site-assistant-autopilot' ),
					number_format_i18n( $embedded ),
					number_format_i18n( $total ),
					$model
				),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		return $this->row(
			'embeddings',
			__( 'Embeddings', 'ai-site-assistant-autopilot' ),
			sprintf(
				/* translators: 1: indexed-entry count, 2: embedding model identifier. */
				__( '%1$s indexed entries have vectors for %2$s.', 'ai-site-assistant-autopilot' ),
				number_format_i18n( $embedded ),
				$model
			),
			__( 'Good', 'ai-site-assistant-autopilot' ),
			'ok'
		);
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function cron_row(): array {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return $this->row(
				'cron',
				__( 'Background jobs (WP-Cron)', 'ai-site-assistant-autopilot' ),
				__( 'DISABLE_WP_CRON is enabled. AutoPilot cannot run deferred indexing on a WordPress schedule.', 'ai-site-assistant-autopilot' ),
				__( 'Failing', 'ai-site-assistant-autopilot' ),
				'bad'
			);
		}

		if ( ! (bool) Settings::get( 'kb_auto_index', true ) ) {
			return $this->row(
				'cron',
				__( 'Background jobs (WP-Cron)', 'ai-site-assistant-autopilot' ),
				__( 'WP-Cron is available, but automatic Knowledge Base indexing is switched off.', 'ai-site-assistant-autopilot' ),
				__( 'Limited', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		$next = wp_next_scheduled( ContentHooks::CRON_ACTION );
		if ( false !== $next ) {
			return $this->row(
				'cron',
				__( 'Background jobs (WP-Cron)', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: %s: local timestamp for the next deferred AutoPilot index job. */
					__( 'WP-Cron is enabled; an AutoPilot index job is queued for %s.', 'ai-site-assistant-autopilot' ),
					$this->checked_label( (int) $next )
				),
				__( 'Good', 'ai-site-assistant-autopilot' ),
				'ok'
			);
		}

		return $this->row(
			'cron',
			__( 'Background jobs (WP-Cron)', 'ai-site-assistant-autopilot' ),
			__( 'WP-Cron is enabled. No AutoPilot index job is currently queued.', 'ai-site-assistant-autopilot' ),
			__( 'Good', 'ai-site-assistant-autopilot' ),
			'ok'
		);
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function index_freshness_row(): array {
		$indexer = new ContentIndexer();
		$total   = max( 0, $indexer->get_total_posts() );
		$kb      = new KnowledgeBase();
		$stats   = $kb->table_exists()
			? $kb->get_stats()
			: array(
				'indexed'      => 0,
				'pending'      => 0,
				'last_indexed' => null,
			);
		$indexed = max( 0, (int) ( $stats['indexed'] ?? 0 ) );
		$pending = max( 0, (int) ( $stats['pending'] ?? 0 ) );

		if ( 0 === $total ) {
			return $this->row(
				'index_freshness',
				__( 'Index freshness', 'ai-site-assistant-autopilot' ),
				__( 'There are no published posts or pages in the current index scope.', 'ai-site-assistant-autopilot' ),
				__( 'Good', 'ai-site-assistant-autopilot' ),
				'ok'
			);
		}

		$covered = min( $indexed, $total );
		$behind  = max( 0, $total - $covered ) + $pending;
		if ( $behind > 0 ) {
			return $this->row(
				'index_freshness',
				__( 'Index freshness', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: 1: indexed current-scope count, 2: total current-scope count, 3: remaining/pending count. */
					__( '%1$s of %2$s current sources are indexed; %3$s still need attention.', 'ai-site-assistant-autopilot' ),
					number_format_i18n( $covered ),
					number_format_i18n( $total ),
					number_format_i18n( $behind )
				),
				__( 'Catching up', 'ai-site-assistant-autopilot' ),
				'warn'
			);
		}

		$last   = isset( $stats['last_indexed'] ) && is_string( $stats['last_indexed'] )
			? trim( $stats['last_indexed'] )
			: '';
		$detail = sprintf(
			/* translators: 1: indexed current-scope count, 2: total current-scope count. */
			__( '%1$s of %2$s current sources have index entries.', 'ai-site-assistant-autopilot' ),
			number_format_i18n( $covered ),
			number_format_i18n( $total )
		);
		if ( '' !== $last ) {
			$detail .= ' ' . sprintf(
				/* translators: %s: stored Knowledge Base index timestamp. */
				__( 'Last index update: %s.', 'ai-site-assistant-autopilot' ),
				$last
			);
		}

		return $this->row(
			'index_freshness',
			__( 'Index freshness', 'ai-site-assistant-autopilot' ),
			$detail,
			__( 'Good', 'ai-site-assistant-autopilot' ),
			'ok'
		);
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function rate_limit_row(): array {
		return $this->row(
			'rate_limit',
			__( 'Rate limit headroom', 'ai-site-assistant-autopilot' ),
			__( 'Provider quota telemetry is not available in this plugin, so headroom is not measured.', 'ai-site-assistant-autopilot' ),
			__( 'Not measured', 'ai-site-assistant-autopilot' ),
			'idle'
		);
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function runtime_row(): array {
		global $wp_version;

		$wordpress = is_string( $wp_version ) && '' !== $wp_version ? $wp_version : '—';

		return $this->row(
			'runtime',
			__( 'PHP and WordPress', 'ai-site-assistant-autopilot' ),
			sprintf(
				/* translators: 1: PHP version, 2: WordPress version. */
				__( 'PHP %1$s · WordPress %2$s.', 'ai-site-assistant-autopilot' ),
				PHP_VERSION,
				$wordpress
			),
			__( 'Good', 'ai-site-assistant-autopilot' ),
			'ok'
		);
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 * @return array{ok:bool,code:string,latency_ms:int,checked_at:int}|null
	 */
	private function last_provider_snapshot( array $config ): ?array {
		$snapshot = get_option( self::OPTION, array() );
		if ( ! is_array( $snapshot )
			|| self::SNAPSHOT_VERSION !== (int) ( $snapshot['version'] ?? 0 )
			|| ! isset( $snapshot['fingerprint'] )
			|| ! is_string( $snapshot['fingerprint'] )
			|| ! hash_equals( $this->provider_fingerprint( $config ), $snapshot['fingerprint'] )
			|| ! isset( $snapshot['provider'] )
			|| ! is_array( $snapshot['provider'] ) ) {
			return null;
		}

		$checked_at = max( 0, (int) ( $snapshot['checked_at'] ?? 0 ) );
		if ( 0 === $checked_at ) {
			return null;
		}

		return array(
			'ok'         => ! empty( $snapshot['provider']['ok'] ),
			'code'       => sanitize_key( (string) ( $snapshot['provider']['code'] ?? 'health_failed' ) ),
			'latency_ms' => min( 60000, max( 0, (int) ( $snapshot['provider']['latency_ms'] ?? 0 ) ) ),
			'checked_at' => $checked_at,
		);
	}

	/**
	 * @return array{provider:string,base_url:string,model:string,api_key:string}
	 */
	private function saved_provider_config(): array {
		return array(
			'provider' => (string) Settings::get( 'provider', '' ),
			'base_url' => (string) Settings::get( 'base_url', '' ),
			'model'    => (string) Settings::get( 'model', '' ),
			'api_key'  => WpConnectors::get_effective_api_key(),
		);
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 */
	private function provider_is_configured( array $config ): bool {
		return '' !== trim( $config['provider'] )
			&& '' !== trim( $config['base_url'] )
			&& '' !== trim( $config['model'] )
			&& '' !== trim( $config['api_key'] );
	}

	/**
	 * @param array{provider:string,base_url:string,model:string,api_key:string} $config
	 */
	private function provider_fingerprint( array $config ): string {
		$payload = implode(
			"\n",
			array(
				$config['provider'],
				$config['base_url'],
				$config['model'],
				$config['api_key'],
			)
		);

		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	private function checked_label( int $timestamp ): string {
		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);
	}

	private function provider_failure_detail( string $code ): string {
		return match ( $code ) {
			'no_api_key'               => __( 'No API key is configured for the saved provider.', 'ai-site-assistant-autopilot' ),
			'invalid_config'           => __( 'The saved provider configuration is incomplete.', 'ai-site-assistant-autopilot' ),
			'provider_unreachable'     => __( 'The saved provider did not respond during the last Health check.', 'ai-site-assistant-autopilot' ),
			'provider_unauthorized'    => __( 'The saved provider rejected the configured credential.', 'ai-site-assistant-autopilot' ),
			'model_not_found'          => __( 'The saved provider could not use the selected model.', 'ai-site-assistant-autopilot' ),
			'provider_payment_required' => __( 'The saved provider requires billing or available credit.', 'ai-site-assistant-autopilot' ),
			'provider_rate_limited'    => __( 'The saved provider rate-limited the last Health check.', 'ai-site-assistant-autopilot' ),
			default                    => __( 'The saved provider check did not complete. Review Settings and run all checks again.', 'ai-site-assistant-autopilot' ),
		};
	}

	/**
	 * @return array{id:string,label:string,detail:string,state:string,tone:string}
	 */
	private function row( string $id, string $label, string $detail, string $state, string $tone ): array {
		return array(
			'id'     => $id,
			'label'  => $label,
			'detail' => $detail,
			'state'  => $state,
			'tone'   => $tone,
		);
	}
}
