<?php
declare(strict_types=1);
namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\WooCommerceContentSkill;

/**
 * Dormant WooCommerce content handlers retained for a later Phase 2 surface.
 *
 * No `/woocommerce/content/*` route is registered in the current Store
 * Copilot release. The following method inventory is unreachable through the
 * plugin API and must not be exposed as usable Store UI:
 * - POST /woocommerce/content/description  — Generate description
 * - POST /woocommerce/content/seo          — Generate SEO
 * - POST /woocommerce/content/promo        — Generate promo text
 * - POST /woocommerce/content/bulk         — Bulk generate
 * - POST /woocommerce/content/apply        — Apply to product
 * - GET  /woocommerce/content/templates    — Get templates
 */
class WooCommerceContentController {

	private WooCommerceContentSkill $skill;

	public function __construct() {
		$this->skill = new WooCommerceContentSkill();
	}

	/**
	 * POST /woocommerce/content/description
	 */
	public function description( \WP_REST_Request $req ): \WP_REST_Response {
		$product_id = absint( $req->get_param( 'product_id' ) );
		$tone       = sanitize_text_field( $req->get_param( 'tone' ) ?? 'professional' );
		$length     = max( 50, min( 500, absint( $req->get_param( 'length' ) ?? 150 ) ) );

		if ( $product_id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'product_id is required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->generate_description( $product_id, $tone, $length );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * POST /woocommerce/content/seo
	 */
	public function seo( \WP_REST_Request $req ): \WP_REST_Response {
		$product_id = absint( $req->get_param( 'product_id' ) );

		if ( $product_id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'product_id is required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->generate_seo( $product_id );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * POST /woocommerce/content/promo
	 */
	public function promo( \WP_REST_Request $req ): \WP_REST_Response {
		$product_id = absint( $req->get_param( 'product_id' ) );
		$type       = sanitize_text_field( $req->get_param( 'type' ) ?? 'short' );

		if ( $product_id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'product_id is required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->generate_promo( $product_id, $type );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * POST /woocommerce/content/bulk
	 */
	public function bulk( \WP_REST_Request $req ): \WP_REST_Response {
		$product_ids = $req->get_param( 'product_ids' );
		$tone        = sanitize_text_field( $req->get_param( 'tone' ) ?? 'professional' );

		if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'product_ids array is required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->bulk_generate( array_map( 'absint', $product_ids ), $tone );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * POST /woocommerce/content/apply
	 */
	public function apply( \WP_REST_Request $req ): \WP_REST_Response {
		$product_id  = absint( $req->get_param( 'product_id' ) );
		$description = wp_kses_post( $req->get_param( 'description' ) ?? '' );
		$seo_title   = sanitize_text_field( $req->get_param( 'seo_title' ) ?? '' );
		$seo_desc    = sanitize_textarea_field( $req->get_param( 'seo_desc' ) ?? '' );

		if ( $product_id <= 0 || '' === $description ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'code'  => 'invalid',
					'error' => __( 'product_id and description are required.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = $this->skill->apply_to_product( $product_id, $description, $seo_title, $seo_desc );
		$status = $result['ok'] ? 200 : 400;
		return new \WP_REST_Response( array_merge( array( 'code' => $result['ok'] ? 'ok' : 'error' ), $result ), $status );
	}

	/**
	 * GET /woocommerce/content/templates
	 */
	public function templates(): \WP_REST_Response {
		$result = $this->skill->get_templates();
		return new \WP_REST_Response( array_merge( array( 'code' => 'ok' ), $result ) );
	}
}
