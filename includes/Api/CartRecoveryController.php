<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\CartRecovery;

/**
 * Store Copilot > Orders > Abandoned carts: the store manager's two actions on a kept
 * cart. POST /woocommerce/carts/{id}/remind sends the one reminder email;
 * POST /woocommerce/carts/{id}/delete deletes the cart. Store managers only
 * (RestRoutes::can_manage_woocommerce); nothing is sent without this request.
 */
final class CartRecoveryController {

	public function remind( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! CartRecovery::enabled() ) {
			return $this->reply(
				array(
					'ok'   => false,
					'code' => 'off',
				)
			);
		}
		$result = ( new CartRecovery() )->remind( (int) $request->get_param( 'id' ) );

		return $this->reply( $result );
	}

	public function delete( \WP_REST_Request $request ): \WP_REST_Response {
		$deleted = ( new CartRecovery() )->delete( (int) $request->get_param( 'id' ) );

		return $this->reply(
			array(
				'ok'   => $deleted,
				'code' => $deleted ? 'deleted' : 'not_found',
			)
		);
	}

	/** @param array{ok:bool, code:string} $result */
	private function reply( array $result ): \WP_REST_Response {
		$messages          = array(
			'reminded'         => __( 'Reminder sent. No other email will be sent for this cart.', 'ai-site-assistant-autopilot' ),
			'deleted'          => __( 'Cart deleted.', 'ai-site-assistant-autopilot' ),
			'already_reminded' => __( 'A reminder was already sent for this cart.', 'ai-site-assistant-autopilot' ),
			'not_found'        => __( 'This cart is no longer kept. The customer may have ordered or declined.', 'ai-site-assistant-autopilot' ),
			'mail_failed'      => __( 'The reminder could not be sent. Check that this site can send email (Advanced settings > Forms and delivery has a test).', 'ai-site-assistant-autopilot' ),
			'off'              => __( 'Abandoned cart reminders are switched off under What it can do.', 'ai-site-assistant-autopilot' ),
		);
		$result['message'] = $messages[ $result['code'] ] ?? '';
		$status            = $result['ok'] ? 200 : ( 'not_found' === $result['code'] ? 404 : ( 'already_reminded' === $result['code'] ? 409 : 400 ) );

		return new \WP_REST_Response( $result, $status );
	}
}
