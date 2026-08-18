<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Support\HandoffEmail;
use AISAAutoPilot\Support\HandoffTelegram;
use AISAAutoPilot\Support\HandoffTwilio;

/**
 * WP-11 — connections & health.
 *
 * Every dependency AISAAutoPilot relies on, and whether it is working right now.
 * Diagnostics run on this site; the only outbound call is one short request to
 * the administrator's own AI provider.
 */
final class ConnectionsPage {

	private const STAMP_OPTION = 'aisaautopilot_last_diagnostics';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$manager        = new ProviderManager();
		$provider_ready = $manager->is_configured();
		$semantic_on    = $manager->embeddings_enabled();

		$efb       = new EasyFormBuilderConnector();
		$efb_ready = $efb->is_available();
		$woo       = WooCommerceConnector::get_health();

		$kb        = new KnowledgeBase();
		$kb_total  = $kb->table_exists() ? (int) ( $kb->get_stats()['total'] ?? 0 ) : 0;
		$embedded  = ( $semantic_on && $kb->table_exists() ) ? $kb->count_with_embeddings( $manager->embedding_model() ) : 0;

		$widget    = (array) Settings::get( 'widget', array() );
		$mail_on   = ! empty( $widget['handoff_email_enabled'] );
		$tg_on     = ! empty( $widget['handoff_telegram_enabled'] );
		$tw_on     = ! empty( $widget['handoff_twilio_enabled'] );
		$mail_conf = ( new HandoffEmail() )->is_configured();
		$tg_conf   = ( new HandoffTelegram() )->is_configured();
		$tw_conf   = ( new HandoffTwilio() )->is_configured();

		$stamp     = (string) get_option( self::STAMP_OPTION, '' );
		$logs_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS );
		$widget_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIDGET );

		$problems = ( $provider_ready ? 0 : 1 );
		$warnings = ( $efb_ready ? 0 : 1 ) + ( $woo['active'] ? 0 : 1 ) + ( $kb_total > 0 ? 0 : 1 );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-connections>
			<section class="sp-screen">
				<div class="sp-page-head">
					<div>
						<?php Ui::title( __( 'Connections & health', 'ai-site-assistant-autopilot' ), 'plug' ); ?>
						<p class="sp-lead sp-mb0"><?php esc_html_e( 'Every dependency AISAAutoPilot relies on, and whether it is working right now.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<button type="button" id="sp-run-diagnostics" class="sp-btn sp-btn--r8" style="padding:10px 18px">
						<?php echo Ui::icon( 'refresh', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php esc_html_e( 'Run diagnostics', 'ai-site-assistant-autopilot' ); ?></span>
					</button>
				</div>

				<div class="sp-alert sp-alert--<?php echo $problems > 0 ? 'err' : ( $warnings > 0 ? 'warn' : 'ok' ); ?>" id="sp-diag-summary">
					<?php echo Ui::icon( 'shield', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
					<span style="min-width:0">
						<?php if ( $problems > 0 ) : ?>
							<?php esc_html_e( 'One critical problem: the AI provider is not reachable, so no request can be built.', 'ai-site-assistant-autopilot' ); ?>
						<?php elseif ( $warnings > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: number of optional dependencies that are not configured. */
								esc_html( _n( '%d optional dependency is not configured. The core flow still works.', '%d optional dependencies are not configured. The core flow still works.', $warnings, 'ai-site-assistant-autopilot' ) ),
								(int) $warnings
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Every required dependency is healthy.', 'ai-site-assistant-autopilot' ); ?>
						<?php endif; ?>
					</span>
				</div>

				<div class="sp-cols sp-cols--16">
					<div class="sp-col sp-col--520">

						<div class="sp-card sp-card--bare sp-card--flat">
							<div class="sp-card__head">
								<?php echo Ui::icon( 'spark', 19 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span><?php esc_html_e( 'AI provider', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<?php
							Ui::health_row(
								'spark',
								__( 'Provider connection', 'ai-site-assistant-autopilot' ),
								$provider_ready
									? Ui::health( 'ok', __( 'Healthy', 'ai-site-assistant-autopilot' ), sprintf( '%s · %s', (string) Settings::get( 'provider', '' ), (string) Settings::get( 'model', '' ) ) )
									: Ui::health( 'err', __( 'Not connected', 'ai-site-assistant-autopilot' ), __( 'Add an API key, base URL and model in Settings.', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'lock',
								__( 'Key vault', 'ai-site-assistant-autopilot' ),
								Settings::has_api_key() || WpConnectors::is_available()
									? Ui::health( 'ok', __( 'Encrypted', 'ai-site-assistant-autopilot' ), WpConnectors::get_key_source_label() )
									: Ui::health( 'off', __( 'Empty', 'ai-site-assistant-autopilot' ), __( 'No API key has been stored yet.', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'search',
								__( 'Semantic search', 'ai-site-assistant-autopilot' ),
								$this->embedding_state( $semantic_on, $embedded, $kb_total )
							);
							?>
						</div>

						<div class="sp-card sp-card--bare sp-card--flat">
							<div class="sp-card__head">
								<?php echo Ui::icon( 'form', 19 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span><?php esc_html_e( 'Connectors', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<?php
							Ui::health_row(
								'form',
								'AISAAutoPilot Forms',
								Ui::health( 'ok', __( 'Available', 'ai-site-assistant-autopilot' ), __( 'Built in, always active', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'form',
								'Easy Form Builder',
								$efb_ready
									? Ui::health( 'ok', __( 'Available', 'ai-site-assistant-autopilot' ), __( 'Installed and compatible. Generated forms are editable directly in EFB.', 'ai-site-assistant-autopilot' ) )
									: Ui::health( 'warn', __( 'Not available', 'ai-site-assistant-autopilot' ), __( 'Not installed, or the version does not match the supported 4.1+ storage contract.', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'cart',
								'WooCommerce',
								$woo['active']
									? Ui::health(
										'ok',
										__( 'Active', 'ai-site-assistant-autopilot' ),
										sprintf(
											/* translators: 1: WooCommerce version, 2: product count. */
											__( 'Version %1$s · %2$s products', 'ai-site-assistant-autopilot' ),
											(string) ( $woo['version'] ?: '—' ),
											number_format_i18n( (int) $woo['product_count'] )
										)
									)
									: Ui::health( 'off', __( 'Inactive', 'ai-site-assistant-autopilot' ), __( 'WooCommerce is not installed or active', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'database',
								__( 'Knowledge base', 'ai-site-assistant-autopilot' ),
								$kb_total > 0
									? Ui::health(
										'ok',
										__( 'Indexed', 'ai-site-assistant-autopilot' ),
										sprintf(
											/* translators: %s: number of indexed items. */
											__( '%s public items indexed', 'ai-site-assistant-autopilot' ),
											number_format_i18n( $kb_total )
										)
									)
									: Ui::health( 'warn', __( 'Empty', 'ai-site-assistant-autopilot' ), __( 'Nothing indexed yet; chat answers will not be accurate', 'ai-site-assistant-autopilot' ) )
							);
							?>
						</div>

						<div class="sp-card sp-card--bare sp-card--flat">
							<div class="sp-card__head">
								<?php echo Ui::icon( 'mail', 19 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span><?php esc_html_e( 'Handoff channels', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<?php
							Ui::health_row(
								'mail',
								__( 'Email', 'ai-site-assistant-autopilot' ),
								$this->channel_state( $mail_on, $mail_conf, (string) ( $widget['handoff_email_to'] ?? '' ) ?: Settings::form_notification_email() )
							);
							Ui::health_row(
								'send',
								__( 'Telegram', 'ai-site-assistant-autopilot' ),
								$this->channel_state( $tg_on, $tg_conf, __( 'Bot token and chat ID', 'ai-site-assistant-autopilot' ) )
							);
							Ui::health_row(
								'phone',
								__( 'Twilio (SMS)', 'ai-site-assistant-autopilot' ),
								$this->channel_state( $tw_on, $tw_conf, __( 'Account SID, auth token and numbers', 'ai-site-assistant-autopilot' ) )
							);
							?>
							<div class="sp-health__foot">
								<?php esc_html_e( 'At least one channel must be configured, otherwise the handoff button is hidden from visitors.', 'ai-site-assistant-autopilot' ); ?>
								<a href="<?php echo esc_url( $widget_url ); ?>"><?php esc_html_e( 'Open widget settings', 'ai-site-assistant-autopilot' ); ?></a>
							</div>
						</div>
					</div>

					<aside class="sp-side">
						<div class="sp-card">
							<div class="sp-row-wrap sp-row-wrap--sm" style="margin-bottom:14px">
								<?php echo Ui::icon( 'shield', 19 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span class="sp-h2 sp-h2--sm sp-mb0"><?php esc_html_e( 'Visitor assistant limits', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<div class="sp-stack sp-stack--lg">
								<div>
									<div class="sp-bar-head">
										<span class="sp-kv__k"><?php esc_html_e( 'Messages per visitor / hour', 'ai-site-assistant-autopilot' ); ?></span>
										<span style="font-weight:600"><?php echo esc_html( number_format_i18n( 30 ) ); ?></span>
									</div>
									<?php Ui::bar( 38, 'sp-bar--thin' ); ?>
								</div>
								<div>
									<div class="sp-bar-head">
										<span class="sp-kv__k"><?php esc_html_e( 'Voice requests / hour', 'ai-site-assistant-autopilot' ); ?></span>
										<span style="font-weight:600"><?php echo esc_html( number_format_i18n( 10 ) ); ?></span>
									</div>
									<?php Ui::bar( 20, 'sp-bar--thin', 'sp-bar__fill--violet' ); ?>
								</div>
								<div class="sp-hr"></div>
								<div class="sp-kv__row">
									<span class="sp-kv__k"><?php esc_html_e( 'Public assistant', 'ai-site-assistant-autopilot' ); ?></span>
									<span class="sp-kv__v"><?php echo esc_html( Settings::get( 'public_assistant_enabled', false ) ? __( 'Enabled', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) ); ?></span>
								</div>
							</div>
						</div>

						<div class="sp-card sp-card--soft">
							<div class="sp-h3" style="font-size:13.5px"><?php esc_html_e( 'Nothing here reaches outside', 'ai-site-assistant-autopilot' ); ?></div>
							<p class="sp-help sp-help--12"><?php esc_html_e( 'Diagnostics run on this site only. The single outbound call is one short request to your own AI provider.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<div class="sp-card sp-card--16 sp-card--flat">
							<div class="sp-h3" style="font-size:13.5px;margin-bottom:10px"><?php esc_html_e( 'Last checked', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-mono sp-help" id="sp-diag-stamp"><?php echo esc_html( '' !== $stamp ? $stamp : __( 'Not run yet', 'ai-site-assistant-autopilot' ) ); ?></div>
							<a class="sp-btn sp-btn--ghost sp-btn--block" style="margin-top:12px;padding:9px" href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'Open activity log', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					</aside>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-conn-i18n',
			array(
				'checking' => __( 'Checking ten dependencies…', 'ai-site-assistant-autopilot' ),
				'button'   => __( 'Run diagnostics', 'ai-site-assistant-autopilot' ),
				'busy'     => __( 'Checking…', 'ai-site-assistant-autopilot' ),
				'ok'       => __( 'The AI provider answered. Reloading the report…', 'ai-site-assistant-autopilot' ),
				'failed'   => __( 'The AI provider did not answer.', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	/**
	 * @return array{tone:string,badge:string,note:string}
	 */
	private function embedding_state( bool $enabled, int $embedded, int $total ): array {
		if ( ! $enabled ) {
			return Ui::health( 'off', __( 'Off', 'ai-site-assistant-autopilot' ), __( 'Keyword ranking is used. Enable it in Settings for meaning-based search.', 'ai-site-assistant-autopilot' ) );
		}
		if ( $total > 0 && $embedded < $total ) {
			return Ui::health(
				'warn',
				__( 'Incomplete', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: 1: items with an embedding, 2: total indexed items. */
					__( '%1$s of %2$s vectors built — run indexing to finish', 'ai-site-assistant-autopilot' ),
					number_format_i18n( $embedded ),
					number_format_i18n( $total )
				)
			);
		}

		return Ui::health( 'ok', __( 'Enabled', 'ai-site-assistant-autopilot' ), __( 'Knowledge is ranked by meaning', 'ai-site-assistant-autopilot' ) );
	}

	/**
	 * @return array{tone:string,badge:string,note:string}
	 */
	private function channel_state( bool $enabled, bool $configured, string $detail ): array {
		if ( ! $enabled ) {
			return Ui::health( 'off', __( 'Off', 'ai-site-assistant-autopilot' ), __( 'Enable it on the Widget page', 'ai-site-assistant-autopilot' ) );
		}
		if ( ! $configured ) {
			return Ui::health(
				'warn',
				__( 'Not configured', 'ai-site-assistant-autopilot' ),
				sprintf(
					/* translators: %s: the credentials this channel still needs. */
					__( 'Waiting for: %s', 'ai-site-assistant-autopilot' ),
					$detail
				)
			);
		}

		return Ui::health( 'ok', __( 'Configured', 'ai-site-assistant-autopilot' ), $detail );
	}

	/** Record when diagnostics last completed. Called from the REST test route. */
	public static function stamp_diagnostics(): void {
		update_option( self::STAMP_OPTION, gmdate( 'Y-m-d H:i:s' ) . ' UTC', false );
	}
}
