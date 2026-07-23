<?php
declare(strict_types=1);

namespace SitePilot\Support;

/**
 * Orchestrates human handoff across all configured channels.
 * Each channel runs independently (try/catch isolated).
 */
class HandoffService {

	private PdfExporter $pdf;

	public function __construct() {
		$this->pdf = new PdfExporter();
	}

	/**
	 * Send conversation history via all configured channels.
	 *
	 * @param array<int, array{role:string, content:string, sources?:array}> $messages
	 * @param string $visitor_name
	 * @param string $visitor_email
	 * @return array<string, array{ok:bool, error:string}>
	 */
	public function handoff( array $messages, string $visitor_name = '', string $visitor_email = '' ): array {
		$results = array();
		$html    = $this->pdf->export_html( $messages, 'Support Request: ' . get_bloginfo( 'name' ) );
		$text    = $this->pdf->export_text( $messages );
		$summary = "🧑‍💼 Support Request — " . get_bloginfo( 'name' ) . "\n"
			. "Visitor: $visitor_name\n"
			. "Email: " . ( $visitor_email ?: 'N/A' ) . "\n"
			. "Time: " . gmdate( 'Y-m-d H:i:s T' ) . "\n\n"
			. "--- Messages ---\n" . $text;

		// Email
		if ( ( new HandoffEmail() )->is_configured() ) {
			$to                = ( new HandoffEmail() )->get_recipient();
			$results['email']  = ( new HandoffEmail() )->send( $to, '🧑‍💼 Support Request: ' . get_bloginfo( 'name' ), $html );
		}

		// Telegram
		if ( ( new HandoffTelegram() )->is_configured() ) {
			$results['telegram'] = ( new HandoffTelegram() )->send( $summary );
		}

		// Twilio WhatsApp
		if ( ( new HandoffTwilio() )->is_configured() ) {
			$results['twilio'] = ( new HandoffTwilio() )->send( $summary );
		}

		// Webhook
		$webhook_url = \SitePilot\Core\Settings::get( 'widget_handoff_webhook', '' );
		if ( is_string( $webhook_url ) && '' !== trim( $webhook_url ) ) {
			$results['webhook'] = $this->send_webhook( $webhook_url, $messages, $visitor_name, $visitor_email );
		}

		return $results;
	}

	private function send_webhook( string $url, array $messages, string $name, string $email ): array {
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'site'     => get_bloginfo( 'name' ),
				'url'      => get_site_url(),
				'visitor'  => array( 'name' => $name, 'email' => $email ),
				'channel'  => 'sitepilot-ai-handoff',
				'time'     => gmdate( 'c' ),
				'messages' => $messages,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return array( 'ok' => $code >= 200 && $code < 300, 'error' => "HTTP $code" );
	}
}
