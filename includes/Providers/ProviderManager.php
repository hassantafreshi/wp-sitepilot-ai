<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;

/**
 * Builds a configured provider from saved settings. In standalone mode this
 * resolves the local BYOK key and OpenAI-compatible endpoint.
 *
 * Supports two key management modes:
 * - "keyvault" (default): AISAAutoPilot's own encrypted storage (XSalsa20/AES-256-GCM)
 * - "wp_connectors": WordPress 7.0+ native Connectors API (Settings → Connectors)
 */
final class ProviderManager {

	public function is_configured(): bool {
		return '' !== $this->resolve_api_key()
			&& '' !== (string) Settings::get( 'base_url' )
			&& '' !== (string) Settings::get( 'model' );
	}

	public function make(): ProviderInterface {
		// Separate embedding key if the user set one, otherwise the effective
		// provider key (connectors-aware) — so the fallback works in every mode.
		// Anthropic is the exception: its key cannot buy embeddings anywhere, so
		// reusing it would only turn a clear "set an embedding provider" message
		// into a confusing 401 from someone else's endpoint.
		$embedding_key = Settings::has_embedding_key()
			? Settings::get_embedding_key()
			: ( 'anthropic' === Settings::provider_api() ? '' : $this->resolve_api_key() );

		return self::build(
			array(
				'provider'           => (string) Settings::get( 'provider', '' ),
				'base_url'           => (string) Settings::get( 'base_url' ),
				'model'              => (string) Settings::get( 'model' ),
				'api_key'            => $this->resolve_api_key(),
				'temperature'        => (float) Settings::get( 'temperature', 0.2 ),
				'embedding_base_url' => (string) Settings::get( 'embedding_base_url', '' ),
				'embedding_model'    => (string) Settings::get( 'embedding_model', '' ),
				'embedding_api_key'  => $embedding_key,
			)
		);
	}

	/**
	 * Build a provider from an explicit configuration.
	 *
	 * The preset decides the wire format: Anthropic speaks its own Messages API,
	 * everyone else speaks OpenAI Chat Completions. Callers that let an
	 * administrator try an unsaved endpoint (the Settings test button) go through
	 * here too, so a test always exercises the same code path as real traffic.
	 *
	 * @param array<string, mixed> $config
	 */
	public static function build( array $config ): ProviderInterface {
		$preset = Settings::preset( (string) ( $config['provider'] ?? '' ) );

		$base_url    = (string) ( $config['base_url'] ?? '' );
		$model       = (string) ( $config['model'] ?? '' );
		$api_key     = (string) ( $config['api_key'] ?? '' );
		$temperature = (float) ( $config['temperature'] ?? 0.2 );
		$timeout     = (int) ( $config['timeout'] ?? 45 );
		$embed_url   = (string) ( $config['embedding_base_url'] ?? '' );
		$embed_model = (string) ( $config['embedding_model'] ?? '' );
		$embed_key   = (string) ( $config['embedding_api_key'] ?? '' );

		if ( 'anthropic' === $preset['api'] ) {
			return new AnthropicProvider( $base_url, $model, $api_key, $temperature, $timeout, $embed_url, $embed_model, $embed_key );
		}

		return new OpenAiCompatibleProvider(
			$base_url,
			$model,
			$api_key,
			$temperature,
			$timeout,
			$embed_url,
			$embed_model,
			$embed_key,
			(string) $preset['auth']
		);
	}

	/**
	 * Whether semantic (embeddings) retrieval is turned on and configurable.
	 */
	public function embeddings_enabled(): bool {
		return $this->is_configured()
			&& (bool) Settings::get( 'embeddings_enabled', false )
			&& '' !== (string) Settings::get( 'embedding_model', '' );
	}

	/**
	 * The embedding model that will be used (for staleness checks).
	 */
	public function embedding_model(): string {
		$model = (string) Settings::get( 'embedding_model', '' );
		return '' !== $model ? $model : 'text-embedding-3-small';
	}

	/**
	 * Resolve the API key from the active key management system.
	 */
	private function resolve_api_key(): string {
		return WpConnectors::get_effective_api_key();
	}

	/**
	 * Get a human-readable label for the active key source.
	 */
	public function get_key_source_label(): string {
		return WpConnectors::get_key_source_label();
	}
}
