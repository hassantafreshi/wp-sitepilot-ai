<?php
declare(strict_types=1);
namespace SitePilot\Support;

final class HandoffTwilio {
	public function send( string $sid, string $token, string $from, string $to, array $messages, string $pdf_html ): bool {
		$summary = '🤖 SitePilot Support Request';
		foreach ( array_slice( $messages, -3 ) as $m ) {
			$role = ( $m['role'] ?? '' ) === 'user' ? 'User' : 'AI';
			$summary .= "\n{$role}: ".mb_substr( $m['content'] ?? '', 0, 200 );
		}
		$url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
		$r = wp_remote_post( $url, array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$token}" ) ),
			'body' => array( 'From' => $from, 'To' => $to, 'Body' => mb_substr( $summary, 0, 1600 ) ),
			'timeout' => 15,
		) );
		return ! is_wp_error( $r ) && ( $r['response']['code'] ?? 0 ) === 201;
	}
}
