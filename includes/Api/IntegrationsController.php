<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\ConflictDetector;

/**
 * Integrations: POST /integrations/conflicts/dismiss hides the "another chat or AI plugin
 * is active" warning for the plugins it names, for the current administrator on this site.
 * Only plugins detected right now are accepted (ConflictDetector::dismiss).
 */
final class IntegrationsController {

	public function dismiss_conflicts( \WP_REST_Request $request ): \WP_REST_Response {
		$plugins = $request->get_param( 'plugins' );
		$plugins = is_array( $plugins ) ? array_slice( array_map( 'strval', $plugins ), 0, 20 ) : array();
		$hidden  = ( new ConflictDetector() )->dismiss( get_current_user_id(), $plugins );
		if ( array() === $hidden ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'not_detected',
					'message' => __( 'None of these plugins is active any more, so there is nothing to hide.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'hidden' => $hidden,
			),
			200
		);
	}
}
