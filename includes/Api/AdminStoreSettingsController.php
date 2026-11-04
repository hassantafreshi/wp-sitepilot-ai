<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;

/**
 * Restricted updates for the two real Store Copilot preferences.
 *
 * This route is intentionally available to a site administrator even when
 * WooCommerce is currently unavailable. It stores a preference only; it never
 * installs, activates, queries, or changes a WooCommerce product or order.
 */
final class AdminStoreSettingsController {

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $request->has_param( 'enabled' ) || ! $request->has_param( 'enable_search' ) ) {
			return $this->error(
				'store_settings_incomplete',
				__( 'Both Store Copilot settings are required.', 'ai-site-assistant-autopilot' )
			);
		}

		// Product descriptions joined later: a request without it keeps the saved choice.
		$saved        = WooCommerceConnector::get_settings();
		$descriptions = $request->has_param( 'enable_descriptions' )
			? rest_sanitize_boolean( $request->get_param( 'enable_descriptions' ) )
			: $saved['enable_descriptions'];
		$cart_funnel  = $request->has_param( 'cart_funnel' )
			? rest_sanitize_boolean( $request->get_param( 'cart_funnel' ) )
			: $saved['cart_funnel'];
		Settings::update(
			array(
				'woocommerce' => array(
					'enabled'             => rest_sanitize_boolean( $request->get_param( 'enabled' ) ),
					'auto_draft'          => true,
					'enable_search'       => rest_sanitize_boolean( $request->get_param( 'enable_search' ) ),
					'enable_edit'         => false,
					'enable_descriptions' => $descriptions,
					'cart_funnel'         => $cart_funnel,
				),
			)
		);

		$settings = WooCommerceConnector::get_settings();

		return new \WP_REST_Response(
			array(
				'ok'       => true,
				'code'     => 'store_settings_updated',
				'settings' => $settings,
			)
		);
	}

	private function error( string $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			400
		);
	}
}
