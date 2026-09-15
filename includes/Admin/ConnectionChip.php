<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * Derives the header-bar connection chip (mockup `connChip`) from real state.
 *
 * Tones map 1:1 to the mockup: green "{provider} · {model}" when configured,
 * amber "Generation paused — cap reached" when the monthly budget is exhausted, red
 * "No AI service yet" while setup is incomplete.
 */
final class ConnectionChip {

	/**
	 * @return array{tone:string,label:string}
	 */
	public static function derive(): array {
		if ( ! ( new ProviderManager() )->is_configured() ) {
			return array(
				'tone'  => 'err',
				'label' => __( 'No AI service yet', 'ai-site-assistant-autopilot' ),
			);
		}

		$tracker = new UsageTracker();
		if ( $tracker->is_exhausted() ) {
			return array(
				'tone'  => 'warn',
				'label' => __( 'Generation paused — cap reached', 'ai-site-assistant-autopilot' ),
			);
		}

		$preset = Settings::preset( (string) Settings::get( 'provider', '' ) );
		$label  = (string) ( $preset['label'] ?? '' );
		$model  = (string) Settings::get( 'model', '' );
		$text   = '' !== $model ? $label . ' · ' . $model : $label;

		return array(
			'tone'  => 'ok',
			'label' => $text,
		);
	}
}
