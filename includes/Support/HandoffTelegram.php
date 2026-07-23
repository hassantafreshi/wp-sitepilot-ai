<?php
declare(strict_types=1);

namespace SitePilot\Support;

/**
 * Send handoff via Telegram Bot API.
 */
class HandoffTelegram {

	public function send( string $text ): array {
		$token  = \SitePilot\Core\Settings::get( 'widget_handoff_telegram_token', '' );
		$chat   = \SitePilot\Core\Settings::get( 'widget_handoff_telegram_chat', '' );

		if ( '' === $token || '' === $chat ) {
			return array( 'ok' => false, 'error' => 'Telegram not configured' );
		}

		$response = wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'chat_id'    => $chat,
				'text'       => mb_substr( $text, 0, 4096 ),
				'parse_mode' => 'Markdown',
			) ),
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
		$token = \SitePilot\Core\Settings::get( 'widget_handoff_telegram_token', '' );
		$chat  = \SitePilot\Core\Settings::get( 'widget_handoff_telegram_chat', '' );
		return is_string( $token ) && '' !== trim( $token ) && is_string( $chat ) && '' !== trim( $chat );
	}
}
