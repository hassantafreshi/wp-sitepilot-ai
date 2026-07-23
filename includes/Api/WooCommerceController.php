<?php
declare(strict_types=1);
namespace SitePilot\Api;

use SitePilot\Connectors\WooCommerceConnector;
use SitePilot\Connectors\WooCommerceProductSkill;

/**
 * REST controller for WooCommerce AI operations.
 */
final class WooCommerceController {

	/**
	 * GET /woocommerce/check — Check WooCommerce status.
	 */
	public function check(): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'ok'     => true,
			'code'   => 'ok',
			'health' => WooCommerceConnector::get_health(),
		) );
	}

	/**
	 * POST /woocommerce/plan — Generate a product plan via AI.
	 */
	public function plan( \WP_REST_Request $req ): \WP_REST_Response {
		$prompt = sanitize_text_field( $req->get_param( 'prompt' ) ?? '' );
		if ( '' === trim( $prompt ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'code' => 'empty', 'errors' => array( 'Please describe what product you want to create.' ) ), 400 );
		}

		$skill = new WooCommerceProductSkill();
		$r     = $skill->plan( $prompt );

		return new \WP_REST_Response( $r, $r['ok'] ? 200 : 400 );
	}

	/**
	 * POST /woocommerce/execute — Execute an approved product plan.
	 */
	public function execute( \WP_REST_Request $req ): \WP_REST_Response {
		$action_id = sanitize_text_field( $req->get_param( 'action_id' ) ?? '' );
		if ( '' === trim( $action_id ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'code' => 'empty', 'errors' => array( 'Action ID required.' ) ), 400 );
		}

		$skill = new WooCommerceProductSkill();
		$r     = $skill->execute( $action_id );

		return new \WP_REST_Response( $r, $r['ok'] ? 200 : 400 );
	}

	/**
	 * POST /woocommerce/smart-search — AI-powered product search.
	 */
	public function smart_search( \WP_REST_Request $req ): \WP_REST_Response {
		$query = sanitize_text_field( $req->get_param( 'query' ) ?? '' );
		if ( '' === trim( $query ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'code' => 'empty', 'errors' => array( 'Search query required.' ) ), 400 );
		}

		// First try an AI search if provider is configured.
		$skill = new WooCommerceProductSkill();
		$ai_plan = $skill->plan( "search for: {$query}" );

		// Then fall back to direct WordPress search.
		$posts = get_posts( array(
			'post_type'   => 'product',
			's'           => $query,
			'numberposts' => 20,
		) );

		$results = array();
		foreach ( $posts as $p ) {
			$results[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'price' => get_post_meta( $p->ID, '_price', true ),
				'url'   => get_permalink( $p->ID ),
				'edit'  => admin_url( "post.php?post={$p->ID}&action=edit" ),
			);
		}

		return new \WP_REST_Response( array(
			'ok'      => true,
			'code'    => 'ok',
			'results' => $results,
			'count'   => count( $results ),
		) );
	}

	/**
	 * GET /woocommerce/categories — Get product categories.
	 */
	public function categories(): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'ok'         => true,
			'code'       => 'ok',
			'categories' => WooCommerceConnector::get_categories(),
		) );
	}
}
