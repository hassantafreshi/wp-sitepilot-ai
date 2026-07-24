<?php
declare(strict_types=1);

namespace SitePilot\Providers;

use SitePilot\Core\Settings;
use SitePilot\Core\WpConnectors;

/**
 * Builds a configured provider from saved settings. In standalone mode this
 * resolves the local BYOK key and OpenAI-compatible endpoint.
 *
 * Supports two key management modes:
 * - "keyvault" (default): SitePilot's own encrypted storage (XSalsa20/AES-256-GCM)
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
		$embedding_key = Settings::has_embedding_key()
			? Settings::get_embedding_key()
			: $this->resolve_api_key();

		return new OpenAiCompatibleProvider(
			(string) Settings::get( 'base_url' ),
			(string) Settings::get( 'model' ),
			$this->resolve_api_key(),
			(float) Settings::get( 'temperature', 0.2 ),
			45,
			(string) Settings::get( 'embedding_base_url', '' ),
			(string) Settings::get( 'embedding_model', '' ),
			$embedding_key
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
