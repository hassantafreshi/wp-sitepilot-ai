<?php
declare(strict_types=1);
namespace AISAAutoPilot\Support;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

class HandoffEmail {
	private Logger $logger;
	public function __construct() {
		$this->logger = new Logger(); }

	public function get_recipient(): string {
		$widget = Settings::get( 'widget', array() );
		$configured = is_array( $widget ) ? sanitize_email( (string) ( $widget['handoff_email_to'] ?? '' ) ) : '';
		return '' !== $configured ? $configured : sanitize_email( (string) get_option( 'admin_email', '' ) );
	}
	public function is_configured(): bool {
		return false !== is_email( $this->get_recipient() );}

	public function build_template( string $messages_html, string $blog_name, string $color = '#4f46e5' ): string {
		$headers = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:640px;margin:0 auto;padding:20px;background:#f9fafb;color:#1f2937;}
            .hdr{background:linear-gradient(135deg,' . $color . ',#7c3aed);color:white;padding:28px 24px;border-radius:12px 12px 0 0;}
            .hdr h1{margin:0;font-size:20px;}.hdr p{margin:6px 0 0;opacity:0.85;font-size:13px;}
            .body{background:white;padding:24px;border:1px solid #e5e7eb;border-top:none;}
            .ftr{background:#f3f4f6;padding:16px 24px;border-radius:0 0 12px 12px;font-size:12px;color:#6b7280;text-align:center;border:1px solid #e5e7eb;border-top:none;}
            .msg{margin:12px 0;padding:12px 16px;border-radius:8px;max-width:85%;}
            .msg-user{background:#eef2ff;margin-left:auto;border-bottom-right-radius:2px;}
            .msg-ai{background:#f3f4f6;margin-right:auto;border-bottom-left-radius:2px;}
            .msg-role{font-size:11px;font-weight:600;color:' . $color . ';margin-bottom:4px;}
            .msg-text{font-size:14px;line-height:1.6;white-space:pre-wrap;}
        </style></head><body>
        <div class="hdr"><h1>Support Request</h1><p>Blog: ' . esc_html( $blog_name ) . ' - ' . esc_html( gmdate( 'Y-m-d H:i T' ) ) . '</p></div>
        <div class="body"><h2 style="margin:0 0 16px;font-size:16px;">Conversation:</h2>' . $messages_html . '</div>
        <div class="ftr">Sent by AI Site Assistant AutoPilot - ' . esc_html( get_site_url() ) . '</div></body></html>';
		return $headers;
	}

	public function build_messages_html( array $messages ): string {
		$html = '';
		foreach ( $messages as $m ) {
			$role    = ( $m['role'] ?? 'user' ) === 'user' ? 'user' : 'ai';
			$caption = ucfirst( $role );
			$content = nl2br( esc_html( wp_strip_all_tags( (string) ( $m['content'] ?? '' ) ) ) );
			$html   .= '<div class="msg msg-' . $role . '"><div class="msg-role">' . esc_html( $caption ) . '</div><div class="msg-text">' . $content . '</div></div>';
		}
		return $html;
	}

	public function send( string $to, string $subject, string $html ): array {
		try {
			if ( ! is_email( $to ) ) {
				return array(
					'ok'    => false,
					'error' => 'Invalid email: ' . $to,
				);}
			$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>' );
			$result  = wp_mail( $to, $subject, $html, $headers );
			if ( ! $result ) {
				throw new \RuntimeException( 'wp_mail returned false' );}
			$this->logger->info( 'handoff_email', "Sent to {$to}" );
			return array(
				'ok'      => true,
				'message' => "Email sent to {$to}.",
			);
		} catch ( \Throwable $e ) {
			$this->logger->error( 'handoff_email', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}
}
