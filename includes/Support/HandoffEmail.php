<?php
declare(strict_types=1);
namespace SitePilot\Support;

final class HandoffEmail {
	public function send( string $to, array $messages, string $pdf_html, string $name = '' ): bool {
		$subject = sprintf( '[SitePilot] Support request from %s', $name ?: 'a visitor' );
		$body = '<h2>New Support Request</h2><p>From: '.esc_html( $name ?: 'Unknown' ).'</p>';
		$body .= '<p>Time: '.gmdate( 'Y-m-d H:i:s' ).' UTC</p><hr>';
		foreach ( $messages as $m ) {
			$role = ( $m['role'] ?? 'user' ) === 'user' ? '👤 User' : '🤖 AI';
			$body .= '<p><strong>'.$role.':</strong> '.esc_html( $m['content'] ?? '' ).'</p>';
		}
		$body .= '<hr><p>Full conversation attached below.</p>'.$pdf_html;
		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		$ok = wp_mail( $to, $subject, $body );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		return $ok;
	}
	public function html_content_type(): string { return 'text/html'; }
}
