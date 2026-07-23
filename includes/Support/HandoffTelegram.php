<?php
declare(strict_types=1);
namespace SitePilot\Support;

final class HandoffTelegram {
	public function send( string $token, string $chat_id, array $messages, string $pdf_html ): bool {
		$summary = '🤖 *SitePilot Support Request*'."\n".'Time: '.gmdate( 'Y-m-d H:i:s' )." UTC\n\n";
		foreach ( array_slice( $messages, -6 ) as $m ) {
			$role = ( $m['role'] ?? '' ) === 'user' ? '👤 User' : '🤖 AI';
			$text = mb_substr( $m['content'] ?? '', 0, 200 );
			$summary .= "*{$role}:* {$text}\n\n";
		}
		$url = "https://api.telegram.org/bot{$token}/sendMessage";
		$r = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'chat_id' => $chat_id, 'text' => $summary, 'parse_mode' => 'Markdown' ) ),
			'timeout' => 15,
		) );
		return ! is_wp_error( $r ) && ( $r['response']['code'] ?? 0 ) === 200;
	}
}
