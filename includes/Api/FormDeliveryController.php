<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;
use WP_REST_Response;

/**
 * Admin-only delivery diagnostic for independent AISAAutoPilot Forms.
 *
 * This deliberately sends no visitor data. A successful response means that
 * WordPress accepted the message for delivery; it cannot guarantee a mailbox
 * provider will ultimately accept it.
 */
final class FormDeliveryController {

	private const RATE_WINDOW = 300;

	public function test(): WP_REST_Response {
		$recipient = Settings::form_notification_email();
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return $this->respond(
				false,
				'notification_email_not_configured',
				__( 'Save a valid AISAAutoPilot Forms notification email before sending a test.', 'ai-site-assistant-autopilot' ),
				422
			);
		}

		$user_id = get_current_user_id();
		$key     = 'aisaautopilot_form_delivery_test_' . $user_id;
		if ( get_transient( $key ) ) {
			return $this->respond(
				false,
				'rate_limited',
				__( 'A delivery test was sent recently. Please wait five minutes before trying again.', 'ai-site-assistant-autopilot' ),
				429
			);
		}

		set_transient( $key, 1, self::RATE_WINDOW );
		/* translators: %s: the site name. */
		$subject = sprintf( __( '[%s] AISAAutoPilot Forms delivery test', 'ai-site-assistant-autopilot' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$body    = __( "This is a AISAAutoPilot Forms delivery test.\n\nNo visitor data is included in this message.", 'ai-site-assistant-autopilot' );

		try {
			$accepted = wp_mail( $recipient, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		} catch ( \Throwable $e ) {
			$accepted = false;
		}

		if ( ! $accepted ) {
			delete_transient( $key );
			( new Logger() )->error( 'form.delivery_test_failed', 'WordPress did not accept the AISAAutoPilot Forms delivery test.' );
			return $this->respond( false, 'mail_not_accepted', __( 'WordPress could not send the test message. Check your site mail configuration and try again.', 'ai-site-assistant-autopilot' ), 502 );
		}

		( new Logger() )->info( 'form.delivery_test_accepted', 'WordPress accepted the AISAAutoPilot Forms delivery test.' );
		return $this->respond( true, 'accepted', __( 'WordPress accepted the test email. Check the inbox (and spam folder) for the configured address.', 'ai-site-assistant-autopilot' ) );
	}

	private function respond( bool $ok, string $code, string $message, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => $ok,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}
}
