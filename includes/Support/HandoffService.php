<?php
declare(strict_types=1);
namespace SitePilot\Support;
use SitePilot\Core\Settings;

final class HandoffService {
	public function send( array $messages, string $email = '', string $name = '' ): bool {
		$s = Settings::all()['widget'] ?? array();
		$pdf = ( new PdfExporter() )->generate( $messages, $name );
		$context = array( 'messages' => $messages, 'pdf' => $pdf, 'name' => $name, 'email' => $email, 'time' => gmdate( 'c' ) );
		$ok = false;
		if ( ! empty( $s['handoff_email_enabled'] ) && ! empty( $s['handoff_email'] ) ) {
			$ok = ( new HandoffEmail() )->send( $s['handoff_email'], $messages, $pdf, $name ) || $ok;
		}
		if ( ! empty( $s['handoff_telegram_enabled'] ) && ! empty( $s['handoff_telegram_token'] ) && ! empty( $s['handoff_telegram_chat'] ) ) {
			$ok = ( new HandoffTelegram() )->send( $s['handoff_telegram_token'], $s['handoff_telegram_chat'], $messages, $pdf ) || $ok;
		}
		if ( ! empty( $s['handoff_twilio_enabled'] ) && ! empty( $s['handoff_twilio_sid'] ) && ! empty( $s['handoff_twilio_token'] ) && ! empty( $s['handoff_twilio_to'] ) ) {
			$ok = ( new HandoffTwilio() )->send( $s['handoff_twilio_sid'], $s['handoff_twilio_token'], $s['handoff_twilio_from'] ?? '', $s['handoff_twilio_to'], $messages, $pdf ) || $ok;
		}
		if ( ! empty( $s['handoff_webhook_enabled'] ) && ! empty( $s['handoff_webhook_url'] ) ) {
			$ok = $this->send_webhook( $s['handoff_webhook_url'], $context ) || $ok;
		}
		return $ok;
	}

	private function send_webhook( string $url, array $data ): bool {
		$r = wp_remote_post( $url, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ), 'timeout' => 15 ) );
		return ! is_wp_error( $r ) && ( $r['response']['code'] ?? 0 ) < 400;
	}
}
