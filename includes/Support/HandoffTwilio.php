<?php
declare(strict_types=1);

namespace SitePilot\Support;

/**
 * Send handoff via Twilio WhatsApp API.
 */
class HandoffTwilio {

	public function send( string $text ): array {
		$sid       = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_sid', '' );
		$token     = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_token', '' );
		$from      = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_from', '' );
		$to        = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_to', '' );

		if ( '' === $sid || '' === $token || '' === $from || '' === $to ) {
			return array( 'ok' => false, 'error' => 'Twilio not configured' );
		}

		$response = wp_remote_post( "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array(
			'timeout' => 15,
			'body'    => array(
				'From' => $from,
				'To'   => $to,
				'Body' => mb_substr( $text, 0, 1600 ),
			),
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$token}" ) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'ok'    => $code >= 200 && $code < 300,
			'error' => $code >= 200 && $code < 300 ? '' : "HTTP $code",
		);
	}

	public function is_configured(): bool {
		$sid   = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_sid', '' );
		$token = \SitePilot\Core\Settings::get( 'widget_handoff_twilio_token', '' );
		return is_string( $sid ) && '' !== trim( $sid ) && is_string( $token ) && '' !== trim( $token );
	}
}
