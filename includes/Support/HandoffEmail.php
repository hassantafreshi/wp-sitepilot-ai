<?php
declare(strict_types=1);

namespace SitePilot\Support;

/**
 * Send handoff via Email using wp_mail.
 */
class HandoffEmail {

	public function send( string $to, string $subject, string $html_body ): array {
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>' );
		$result  = wp_mail( $to, $subject, $html_body, $headers );

		return array(
			'ok'    => $result,
			'to'    => $to,
			'error' => $result ? '' : 'wp_mail returned false',
		);
	}

	public function is_configured(): bool {
		$to = \SitePilot\Core\Settings::get( 'widget_handoff_email', get_option( 'admin_email' ) );
		return is_string( $to ) && '' !== trim( $to );
	}

	public function get_recipient(): string {
		$email = \SitePilot\Core\Settings::get( 'widget_handoff_email', '' );
		return is_string( $email ) && '' !== trim( $email ) ? $email : (string) get_option( 'admin_email' );
	}
}
