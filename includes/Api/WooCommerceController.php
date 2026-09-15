<?php
declare(strict_types=1);
namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Connectors\WooCommerceProductSkill;

/**
 * REST controller for WooCommerce AI operations.
 */
final class WooCommerceController {

	/**
	 * GET /woocommerce/check — Check WooCommerce status.
	 */
	public function check(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'code'   => 'ok',
				'health' => WooCommerceConnector::get_health(),
			)
		);
	}

	/**
	 * POST /woocommerce/plan — Generate a product plan via AI.
	 */
	public function plan( \WP_REST_Request $req ): \WP_REST_Response {
		$prompt = sanitize_text_field( $req->get_param( 'prompt' ) ?? '' );
		if ( '' === trim( $prompt ) ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'empty',
					'errors' => array( __( 'Please describe the product draft you want to create.', 'ai-site-assistant-autopilot' ) ),
				),
				400
			);
		}
		if ( mb_strlen( $prompt ) > 2000 ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'prompt_too_long',
					'errors' => array( __( 'Your product request is too long.', 'ai-site-assistant-autopilot' ) ),
				),
				400
			);
		}
		$skill = new WooCommerceProductSkill();
		$r     = $skill->plan( $prompt );

		$status = ! empty( $r['ok'] ) ? 200 : ( 'usage_cap_reached' === (string) ( $r['code'] ?? '' ) ? 429 : 400 );

		return new \WP_REST_Response( $r, $status );
	}

	/**
	 * POST /woocommerce/execute — Execute an approved product plan.
	 */
	public function execute( \WP_REST_Request $req ): \WP_REST_Response {
		$action_id = sanitize_text_field( $req->get_param( 'action_id' ) ?? '' );
		if ( '' === trim( $action_id ) ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'empty',
					'errors' => array( 'Action ID required.' ),
				),
				400
			);
		}

		$skill = new WooCommerceProductSkill();
		$r     = $skill->execute( $action_id );

		return new \WP_REST_Response( $r, $r['ok'] ? 200 : 400 );
	}

	/**
	 * POST /woocommerce/smart-search - protected, read-only product search.
	 */
	public function smart_search( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! WooCommerceConnector::is_smart_search_enabled() ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'feature_disabled',
					'errors' => array( __( 'Smart product search is disabled in Store Copilot settings.', 'ai-site-assistant-autopilot' ) ),
				),
				403
			);
		}

		$query = sanitize_text_field( $req->get_param( 'query' ) ?? '' );
		if ( '' === trim( $query ) ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'empty',
					'errors' => array( 'Search query required.' ),
				),
				400
			);
		}

		// The route is intentionally read-only and does not call a provider. It
		// remains available only when its explicit Store setting is enabled.
		$posts = get_posts(
			array(
				'post_type'   => 'product',
				's'           => $query,
				'numberposts' => 20,
			)
		);

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

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'code'    => 'ok',
				'results' => $results,
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * GET /woocommerce/categories — Get product categories.
	 */
	public function categories(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'         => true,
				'code'       => 'ok',
				'categories' => WooCommerceConnector::get_categories(),
			)
		);
	}
}
