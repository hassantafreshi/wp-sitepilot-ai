<?php
declare(strict_types=1);

namespace SitePilot\Providers;

use SitePilot\Core\Settings;

/**
 * Builds a configured provider from saved settings. In standalone mode this
 * resolves the local BYOK key and OpenAI-compatible endpoint.
 */
final class ProviderManager {

	public function is_configured(): bool {
		return Settings::has_api_key()
			&& '' !== (string) Settings::get( 'base_url' )
			&& '' !== (string) Settings::get( 'model' );
	}

	public function make(): ProviderInterface {
		return new OpenAiCompatibleProvider(
			(string) Settings::get( 'base_url' ),
			(string) Settings::get( 'model' ),
			Settings::get_api_key(),
			(float) Settings::get( 'temperature', 0.2 )
		);
	}
}
