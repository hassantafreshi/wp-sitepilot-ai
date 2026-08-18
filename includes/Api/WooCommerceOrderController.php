<?php
declare(strict_types=1);
namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\WooCommerceOrderSkill;
use AISAAutoPilot\Connectors\WooCommerceConnector;
/**
 * REST controller for WooCommerce Order Intelligence.
 *
 * Endpoints:
 * - GET  /woocommerce/orders/search   — Search orders
 * - GET  /woocommerce/orders/recent   — Recent orders
 * - GET  /woocommerce/orders/{id}     — Single order detail
 * - POST /woocommerce/orders/update-status — Update order status
 * - POST /woocommerce/orders/refund   — Create refund
 * - GET  /woocommerce/orders/stats    — Order statistics
 */
class WooCommerceOrderController {

	private WooCommerceOrderSkill $skill;

	public function __construct() {
		$this->skill = new WooCommerceOrderSkill();
	}

	/**
	 * GET /woocommerce/orders/search
	 */
	public function search( \WP_REST_Request $req ): \WP_REST_Response {
		$args = array(
			'order_number'   => $req->get_param( 'order_number' ),
			'customer_id'    => $req->get_param( 'customer_id' ),
			'customer_email' => $req->get_param( 'customer_email' ),
			'status'         => $req->get_param( 'status' ),
			'date_from'      => $req->get_param( 'date_from' ),
			'date_to'        => $req->get_param( 'date_to' ),
			'limit'          => $req->get_param( 'limit' ) ?? 20,
			'page'           => $req->get_param( 'page' ) ?? 1,
			'orderby'        => $req->get_param( 'orderby' ) ?? 'date',
			'order'          => $req->get_param( 'order' ) ?? 'DESC',
		);

		$result = $this->skill->search_orders( array_filter( $args ) );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/orders/recent
	 */
	public function recent( \WP_REST_Request $req ): \WP_REST_Response {
		$limit  = min( absint( $req->get_param( 'limit' ) ?? 10 ), 50 );
		$result = $this->skill->recent_orders( $limit );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/orders/{id}
	 */
	public function get_order( \WP_REST_Request $req ): \WP_REST_Response {
		$id = absint( $req->get_param( 'id' ) );
		if ( $id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid_id',
					'error' => __( 'Invalid order ID.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		$result = $this->skill->get_order( $id );
		$status = $result['ok'] ? 200 : 404;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * POST /woocommerce/orders/update-status
	 */
	public function update_status( \WP_REST_Request $req ): \WP_REST_Response {
		$order_id = absint( $req->get_param( 'order_id' ) );
		$status   = sanitize_text_field( $req->get_param( 'status' ) ?? '' );
		$note     = sanitize_textarea_field( $req->get_param( 'note' ) ?? '' );

		if ( $order_id <= 0 || '' === $status ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'order_id and status are required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->update_status( $order_id, $status, $note );
		$http   = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $http );
	}

	/**
	 * POST /woocommerce/orders/refund
	 */
	public function refund( \WP_REST_Request $req ): \WP_REST_Response {
		$order_id = absint( $req->get_param( 'order_id' ) );
		$amount   = (float) ( $req->get_param( 'amount' ) ?? 0 );
		$reason   = sanitize_textarea_field( $req->get_param( 'reason' ) ?? '' );

		if ( $order_id <= 0 || $amount <= 0 ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'order_id and positive amount are required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->create_refund( $order_id, $amount, $reason );
		$http   = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $http );
	}

	/**
	 * GET /woocommerce/orders/stats
	 */
	public function stats( \WP_REST_Request $req ): \WP_REST_Response {
		$period = sanitize_text_field( $req->get_param( 'period' ) ?? 'month' );
		$result = $this->skill->get_stats( $period );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/orders/statuses — List valid order statuses.
	 */
	public function statuses(): \WP_REST_Response {
		if ( ! WooCommerceConnector::is_active() ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'wc_inactive',
					'error' => __( 'WooCommerce is not active.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		$statuses = wc_get_order_statuses();
		return new \WP_REST_Response(
			array(
				'ok'       => true,
				'code'     => 'ok',
				'statuses' => $statuses,
			)
		);
	}
}
