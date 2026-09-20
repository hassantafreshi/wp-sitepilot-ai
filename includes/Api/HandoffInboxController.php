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
				'items' => ( new HandoffInbox() )->list( absint( $request->get_param( 'limit' ) ?: 50 ), absint( $request->get_param( 'offset' ) ), rest_sanitize_boolean( $request->get_param( 'archived' ) ) ),
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
		$id     = absint( $request->get_param( 'id' ) );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		// An unknown status is refused rather than silently turned back into "new".
		if ( ! in_array( $status, HandoffInbox::STATUSES, true ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'invalid_status',
					'message' => __( 'Choose New, In progress, Resolved or Delivery failed.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		$inbox = new HandoffInbox();
		if ( $id <= 0 || null === $inbox->get( $id ) || ! $inbox->set_status( $id, $status ) ) {
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

	/** Archives a thread (archived true) or moves it back to the inbox (archived false). The status is kept. */
	public function archive( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = absint( $request->get_param( 'id' ) );
		$archived = rest_sanitize_boolean( $request->get_param( 'archived' ) );
		$inbox    = new HandoffInbox();
		if ( $id <= 0 || null === $inbox->get( $id ) || ! $inbox->set_archived( $id, $archived ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'update_failed',
					'message' => __( 'Could not archive this thread. Please try again.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		$item = $inbox->get( $id );
		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'code'        => $archived ? 'archived' : 'restored',
				'archived'    => null !== ( $item['archived_at'] ?? null ),
				'archived_on' => self::moment( (string) ( $item['archived_at'] ?? '' ) ),
			)
		);
	}

	/** Archives every resolved thread still in the inbox. */
	public function archive_resolved(): \WP_REST_Response {
		$result = ( new HandoffInbox() )->archive_resolved();
		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'code'        => 'archived',
				'count'       => $result['count'],
				'archived_on' => self::moment( $result['archived_at'] ),
			)
		);
	}

	/** A stored UTC datetime in the site's date and time format, or '' when there is none. */
	public static function moment( string $utc ): string {
		$timestamp = '' !== $utc ? (int) strtotime( $utc . ' UTC' ) : 0;
		return $timestamp > 0 ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '';
	}
}
