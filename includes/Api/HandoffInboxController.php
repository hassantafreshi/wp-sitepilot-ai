<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Support\HandoffInbox;

/** Protected endpoints for the visitor-initiated handoff inbox. */
final class HandoffInboxController {

	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'code'  => 'ok',
				'items' => ( new HandoffInbox() )->list( absint( $request->get_param( 'limit' ) ?: 50 ), absint( $request->get_param( 'offset' ) ) ),
			)
		);
	}

	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$item = ( new HandoffInbox() )->get( absint( $request->get_param( 'id' ) ) );
		if ( null === $item ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'not_found',
					'message' => __( 'Handoff request not found.', 'ai-site-assistant-autopilot' ),
				),
				404
			);
		}
		return new \WP_REST_Response(
			array(
				'ok'   => true,
				'code' => 'ok',
				'item' => $item,
			)
		);
	}

	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id <= 0 || ! ( new HandoffInbox() )->set_status( $id, sanitize_key( (string) $request->get_param( 'status' ) ) ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'update_failed',
					'message' => __( 'Could not update the handoff status.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		return new \WP_REST_Response(
			array(
				'ok'   => true,
				'code' => 'ok',
			)
		);
	}
}
