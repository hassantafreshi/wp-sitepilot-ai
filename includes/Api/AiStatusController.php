<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;
use WP_REST_Response;

/**
 * Whether an AI service is connected, for the Easy Form Builder copilot and Form Studio:
 * when the administrator comes back from setting one up in another tab, the assistant
 * checks again instead of asking for a reload. Names the service and model only; never the
 * key, the endpoint or how the key is stored.
 */
final class AiStatusController {

	public function status(): WP_REST_Response {
		$configured = ( new ProviderManager() )->is_configured();
		$provider   = (string) Settings::get( 'provider', '' );
		$preset     = Settings::preset( $provider );

		return new WP_REST_Response(
			array(
				'ok'          => true,
				'configured'  => $configured,
				'provider'    => $configured ? (string) ( $preset['label'] ?? $provider ) : '',
				'model'       => $configured ? (string) Settings::get( 'model', '' ) : '',
				// Form Studio's readiness checklist: its switch and where answers are emailed.
				'form_studio' => (bool) Settings::get( 'form_composer_enabled', true ),
				'recipient'   => Settings::form_notification_email(),
			),
			200
		);
	}
}
