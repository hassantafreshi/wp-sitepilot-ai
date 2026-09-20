<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

use AISAAutoPilot\Usage\UsageTracker;

/**
 * Applies the locally configured token cap before billable AI operations.
 *
 * Health checks and model discovery intentionally remain available through the
 * wrapped provider, so an administrator can repair a provider connection
 * while generation is paused.
 */
final class UsageCappedProvider implements ProviderInterface {

	private ProviderInterface $provider;
	private UsageTracker $usage;

	public function __construct( ProviderInterface $provider, ?UsageTracker $usage = null ) {
		$this->provider = $provider;
		$this->usage    = $usage ?? new UsageTracker();
	}

	/**
	 * @throws ProviderException When the current token period is exhausted.
	 */
	public function generate_structured_output( string $system_prompt, string $user_prompt ): array {
		$this->assert_available();

		return $this->provider->generate_structured_output( $system_prompt, $user_prompt );
	}

	/**
	 * @throws ProviderException When the current token period is exhausted.
	 */
	public function generate_text( string $system_prompt, string $user_prompt ): array {
		$this->assert_available();

		return $this->provider->generate_text( $system_prompt, $user_prompt );
	}

	/**
	 * @throws ProviderException When the current token period is exhausted.
	 */
	public function generate_embedding( string $text ): array {
		$this->assert_available();

		return $this->provider->generate_embedding( $text );
	}

	/**
	 * @return array{ok:bool, message:string}
	 */
	public function health_check(): array {
		return $this->provider->health_check();
	}

	/**
	 * @throws ProviderException When the current token period is exhausted.
	 */
	private function assert_available(): void {
		if ( $this->usage->is_exhausted() ) {
			throw new ProviderException(
				'usage_cap_reached',
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constructor argument, never echoed: it reaches only Logger and wp_json_encode'd REST bodies. The UI string comes from ProviderException::user_message().
				__( 'The local AI usage cap has been reached.', 'ai-site-assistant-autopilot' )
			);
		}
	}
}
