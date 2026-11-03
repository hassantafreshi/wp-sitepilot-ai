<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Forms\FormOutbox;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Resend or delete a form submission kept because its email could not be sent.
 * Administrators only (RestRoutes::can_manage).
 */
final class FormOutboxController {

	public function resend( WP_REST_Request $request ): WP_REST_Response {
		$result = ( new FormOutbox() )->resend( (int) $request->get_param( 'id' ) );
		$status = $result['ok'] ? 200 : ( 'not_found' === $result['code'] ? 404 : 502 );
		$text   = array(
			'sent'         => __( 'Sent. The submission was removed from this list.', 'ai-site-assistant-autopilot' ),
			'not_found'    => __( 'This submission is no longer here.', 'ai-site-assistant-autopilot' ),
			'no_recipient' => __( 'There is no valid address to send it to. Set one under Advanced settings, Forms and delivery.', 'ai-site-assistant-autopilot' ),
			'send_failed'  => __( 'WordPress could not send the email again. Check the site\'s email setup (an SMTP plugin, for example) and try once more.', 'ai-site-assistant-autopilot' ),
		);

		return new WP_REST_Response(
			array(
				'ok'      => $result['ok'],
				'code'    => $result['code'],
				'message' => $text[ $result['code'] ] ?? '',
				'left'    => ( new FormOutbox() )->count(),
			),
			$status
		);
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$deleted = ( new FormOutbox() )->delete( (int) $request->get_param( 'id' ) );

		return new WP_REST_Response(
			array(
				'ok'      => $deleted,
				'code'    => $deleted ? 'deleted' : 'not_found',
				'message' => $deleted ? __( 'Deleted.', 'ai-site-assistant-autopilot' ) : __( 'This submission is no longer here.', 'ai-site-assistant-autopilot' ),
				'left'    => ( new FormOutbox() )->count(),
			),
			$deleted ? 200 : 404
		);
	}
}
