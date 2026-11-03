<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\ProductDescriptions;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Store Copilot > Descriptions: list products, draft a description, apply a reviewed
 * draft, undo. Store managers only (RestRoutes::can_manage_woocommerce), and only while
 * "Product descriptions" is on.
 */
final class ProductDescriptionsController {

	public function products( WP_REST_Request $request ): WP_REST_Response {
		if ( ! ProductDescriptions::enabled() ) {
			return $this->off();
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'products' => ( new ProductDescriptions() )->candidates( rest_sanitize_boolean( $request->get_param( 'only_short' ) ?? true ) ),
			)
		);
	}

	public function draft( WP_REST_Request $request ): WP_REST_Response {
		if ( ! ProductDescriptions::enabled() ) {
			return $this->off();
		}
		$result = ( new ProductDescriptions() )->draft( absint( $request->get_param( 'product_id' ) ), sanitize_key( (string) $request->get_param( 'tone' ) ) );

		return new WP_REST_Response( $result, $result['ok'] ? 200 : ( 'product_not_found' === $result['code'] ? 404 : 502 ) );
	}

	public function apply( WP_REST_Request $request ): WP_REST_Response {
		if ( ! ProductDescriptions::enabled() ) {
			return $this->off();
		}
		$result = ( new ProductDescriptions() )->apply( sanitize_text_field( (string) $request->get_param( 'action_id' ) ), (string) $request->get_param( 'description' ) );

		return new WP_REST_Response( $result, $result['ok'] ? 200 : ( 'changed_since_draft' === $result['code'] ? 409 : 400 ) );
	}

	public function undo( WP_REST_Request $request ): WP_REST_Response {
		if ( ! ProductDescriptions::enabled() ) {
			return $this->off();
		}
		$result = ( new ProductDescriptions() )->undo( absint( $request->get_param( 'product_id' ) ) );

		return new WP_REST_Response( $result, $result['ok'] ? 200 : 400 );
	}

	private function off(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => 'descriptions_disabled',
				'message' => __( 'Turn on Product descriptions in Store Copilot first.', 'ai-site-assistant-autopilot' ),
			),
			403
		);
	}
}
