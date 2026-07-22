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
		return new OpenAiCompatibleProvider(
			(string) Settings::get( 'base_url' ),
			(string) Settings::get( 'model' ),
			$this->resolve_api_key(),
			(float) Settings::get( 'temperature', 0.2 )
		);
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
