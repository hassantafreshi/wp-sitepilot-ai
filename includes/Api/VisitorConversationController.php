<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Chat\VisitorConversationStore;

/** Protected read-only API for opt-in widget conversation records. */
final class VisitorConversationController {

	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'code'  => 'ok',
				'items' => ( new VisitorConversationStore() )->list( absint( $request->get_param( 'limit' ) ?: 50 ), absint( $request->get_param( 'offset' ) ) ),
			)
		);
	}

	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$item = ( new VisitorConversationStore() )->get( absint( $request->get_param( 'id' ) ) );
		if ( null === $item ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'not_found',
					'message' => __( 'Conversation not found.', 'ai-site-assistant-autopilot' ),
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
}
