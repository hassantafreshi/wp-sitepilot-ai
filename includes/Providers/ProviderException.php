<?php
declare(strict_types=1);

namespace SitePilot\Providers;

/**
 * Provider error carrying a stable, safe code (never contains secrets).
 */
final class ProviderException extends \RuntimeException {

	private string $error_code;

	public function __construct( string $error_code, string $message ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	/** A safe, actionable message for the UI; technical details stay in logs. */
	public function user_message(): string {
		return match ( $this->error_code ) {
			'provider_unauthorized' => __( 'The AI provider rejected the API key. Check the selected key in SitePilot AI → Settings, save it, then test the connection.', 'sitepilot-ai' ),
			'provider_rate_limited' => __( 'The AI provider is temporarily rate-limiting requests. Please wait a moment and try again.', 'sitepilot-ai' ),
			'provider_unreachable', 'stt_unreachable' => __( 'SitePilot could not reach the AI provider. Check the API base URL, server internet access, and the provider status, then test the connection in Settings.', 'sitepilot-ai' ),
			'provider_not_configured' => __( 'No usable AI provider is configured. Add an API key, base URL, and model in SitePilot AI → Settings, then test the connection.', 'sitepilot-ai' ),
			default => __( 'The AI provider could not complete this request. Check that the configured base URL and model are supported by your provider, then test the connection in SitePilot AI → Settings. No changes were made.', 'sitepilot-ai' ),
		};
	}
}
