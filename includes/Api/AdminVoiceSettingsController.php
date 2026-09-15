<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;

/**
 * Restricted updates for the real browser-mediated voice controls.
 *
 * This deliberately does not promise or configure a provider realtime audio
 * stream. The public widget currently records one short clip for STT and can
 * optionally speak a completed text answer through the visitor's browser.
 */
final class AdminVoiceSettingsController {

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $request->has_param( 'voice_enabled' ) || ! $request->has_param( 'voice_auto' ) ) {
			return $this->error(
				'voice_settings_incomplete',
				__( 'Both voice input and reply playback settings are required.', 'ai-site-assistant-autopilot' )
			);
		}

		Settings::update(
			array(
				'widget' => array(
					'voice_enabled' => rest_sanitize_boolean( $request->get_param( 'voice_enabled' ) ),
					'voice_auto'    => rest_sanitize_boolean( $request->get_param( 'voice_auto' ) ),
				),
			)
		);

		$widget = (array) Settings::get( 'widget', array() );

		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'code'  => 'voice_settings_updated',
				'voice' => array(
					'enabled' => ! empty( $widget['voice_enabled'] ),
					'auto'    => ! empty( $widget['voice_auto'] ),
				),
			)
		);
	}

	private function error( string $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			400
		);
	}
}
