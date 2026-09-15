<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;

/**
 * Restricted, non-destructive updates for the compact Answer Desk Widget UI.
 */
final class AdminWidgetSettingsController {

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $request->has_param( 'enabled' ) || ! $request->has_param( 'color' ) ) {
			return $this->error(
				'widget_settings_incomplete',
				__( 'The widget visibility and accent colour are required.', 'ai-site-assistant-autopilot' )
			);
		}

		$color = sanitize_hex_color( (string) $request->get_param( 'color' ) );
		if ( ! is_string( $color ) || '' === $color ) {
			return $this->error(
				'widget_color_invalid',
				__( 'Choose a valid hexadecimal accent colour.', 'ai-site-assistant-autopilot' )
			);
		}

		Settings::update(
			array(
				'widget' => array(
					'enabled' => rest_sanitize_boolean( $request->get_param( 'enabled' ) ),
					'color'   => $color,
				),
			)
		);

		$widget = (array) Settings::get( 'widget', array() );

		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'code'   => 'widget_settings_updated',
				'widget' => array(
					'enabled' => ! empty( $widget['enabled'] ),
					'color'   => (string) ( $widget['color'] ?? '#202a8d' ),
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
