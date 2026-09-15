<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

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

	/**
	 * HTTP status for API endpoints that can expose a provider failure.
	 */
	public function status_code(): int {
		return 'usage_cap_reached' === $this->error_code ? 429 : 502;
	}

	/** A safe, actionable message for the UI; technical details stay in logs. */
	public function user_message(): string {
		return match ( $this->error_code ) {
			'usage_cap_reached' => __( 'The local AI usage cap has been reached. Increase or reset the token cap in Usage & Cost before trying again.', 'ai-site-assistant-autopilot' ),
			'provider_unauthorized' => __( 'The AI provider rejected the API key. Check the selected key in AI Site Assistant AutoPilot → Settings, save it, then test the connection.', 'ai-site-assistant-autopilot' ),
			'provider_rate_limited' => __( 'The AI provider is temporarily rate-limiting requests. Please wait a moment and try again.', 'ai-site-assistant-autopilot' ),
			'provider_unreachable', 'stt_unreachable' => __( 'AISAAutoPilot could not reach the AI provider. Check the API base URL, server internet access, and the provider status, then test the connection in Settings.', 'ai-site-assistant-autopilot' ),
			'provider_not_configured' => __( 'No usable AI provider is configured. Add an API key, base URL, and model in AI Site Assistant AutoPilot → Settings, then test the connection.', 'ai-site-assistant-autopilot' ),
			default => __( 'The AI provider could not complete this request. Check that the configured base URL and model are supported by your provider, then test the connection in AI Site Assistant AutoPilot → Settings. No changes were made.', 'ai-site-assistant-autopilot' ),
		};
	}
}
