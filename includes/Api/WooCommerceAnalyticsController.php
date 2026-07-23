<?php
declare(strict_types=1);
namespace SitePilot\Api;

use SitePilot\Connectors\WooCommerceAnalyticsSkill;

/**
 * REST controller for WooCommerce Analytics.
 *
 * Endpoints:
 * - GET /woocommerce/analytics/sales         — Sales report
 * - GET /woocommerce/analytics/top-products  — Top selling products
 * - GET /woocommerce/analytics/low-stock     — Low stock alerts
 * - GET /woocommerce/analytics/customers/{id} — Customer history
 * - GET /woocommerce/analytics/categories    — Revenue by category
 * - GET /woocommerce/analytics/dashboard     — Dashboard summary
 */
class WooCommerceAnalyticsController {

	private WooCommerceAnalyticsSkill $skill;

	public function __construct() {
		$this->skill = new WooCommerceAnalyticsSkill();
	}

	/**
	 * GET /woocommerce/analytics/sales
	 */
	public function sales( \WP_REST_Request $req ): \WP_REST_Response {
		$period = sanitize_text_field( $req->get_param( 'period' ) ?? 'month' );
		$result = $this->skill->sales_report( $period );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/analytics/top-products
	 */
	public function top_products( \WP_REST_Request $req ): \WP_REST_Response {
		$limit = min( absint( $req->get_param( 'limit' ) ?? 10 ), 50 );
		$period = sanitize_text_field( $req->get_param( 'period' ) ?? 'month' );
		$result = $this->skill->top_products( $limit, $period );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/analytics/low-stock
	 */
	public function low_stock( \WP_REST_Request $req ): \WP_REST_Response {
		$threshold = max( 1, absint( $req->get_param( 'threshold' ) ?? 5 ) );
		$result = $this->skill->low_stock( $threshold );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/analytics/customers/{id}
	 */
	public function customer_history( \WP_REST_Request $req ): \WP_REST_Response {
		$id = absint( $req->get_param( 'id' ) );
		$limit = min( absint( $req->get_param( 'limit' ) ?? 20 ), 100 );
		if ( $id <= 0 ) {
			return new \WP_REST_Response( array( 'ok' => false, 'code' => 'invalid_id', 'error' => __( 'Invalid customer ID.', 'sitepilot-ai' ) ), 400 );
		}
		$result = $this->skill->customer_history( $id, $limit );
		$status = $result['ok'] ? 200 : 404;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/analytics/categories
	 */
	public function categories( \WP_REST_Request $req ): \WP_REST_Response {
		$period = sanitize_text_field( $req->get_param( 'period' ) ?? 'month' );
		$result = $this->skill->category_breakdown( $period );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/analytics/dashboard
	 */
	public function dashboard(): \WP_REST_Response {
		$result = $this->skill->dashboard();
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}
}
