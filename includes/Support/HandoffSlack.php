<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * Slack delivery through an incoming webhook (one channel per webhook URL).
 *
 * The webhook URL is the credential: anyone who has it can post to the channel, so
 * it is stored encrypted with the other handoff secrets. Only Slack's own webhook
 * hosts are accepted, so a saved URL can never point the server somewhere else.
 */
class HandoffSlack {

	/** Hosts that serve Slack incoming webhooks (commercial and GovSlack). */
	private const HOSTS = array( 'hooks.slack.com', 'hooks.slack-gov.com' );

	/** Characters per message; Slack shortens a message's text well above this. */
	public const MAX_LENGTH = 3500;

	public const TIMEOUT = 15;

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	public function get_webhook(): string {
		return Settings::get_handoff_secret( 'handoff_slack_webhook' );
	}

	public function is_configured(): bool {
		return self::is_webhook_url( $this->get_webhook() );
	}

	/** Whether a URL is a Slack incoming-webhook URL: https, a Slack hook host, /services/…. */
	public static function is_webhook_url( string $url ): bool {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		return 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& in_array( strtolower( (string) ( $parts['host'] ?? '' ) ), self::HOSTS, true )
			&& array() === array_intersect_key( $parts, array_flip( array( 'port', 'user', 'pass', 'query', 'fragment' ) ) )
			&& 1 === preg_match( '#^/services/[A-Za-z0-9/_-]+$#', (string) ( $parts['path'] ?? '' ) );
	}

	/**
	 * Characters Slack reads as markup are escaped, so a visitor's message shows as typed.
	 */
	public static function escape( string $text ): string {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text );
	}

	/**
	 * @return array{ok:bool, error?:string, sent?:int}
	 */
	public function send( string $text ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'    => false,
				'error' => 'Slack not configured.',
			);
		}

		$sent = 0;
		foreach ( HandoffText::split( $text, self::MAX_LENGTH ) as $chunk ) {
			$posted = $this->post( self::escape( $chunk ) );
			if ( ! $posted['ok'] ) {
				return $posted;
			}
			++$sent;
		}

		return array(
			'ok'   => true,
			'sent' => $sent,
		);
	}

	/**
	 * A webhook has no read-only call, so the check posts one short line to the channel.
	 *
	 * @return array{ok:bool, message:string}
	 */
	public function test_connection(): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Enter a Slack incoming webhook URL first.', 'ai-site-assistant-autopilot' ),
			);
		}

		$posted = $this->post(
			self::escape(
				sprintf(
					/* translators: %s: site name. */
					__( 'Connection check from %s: visitor handoff requests will arrive in this channel.', 'ai-site-assistant-autopilot' ),
					wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
				)
			)
		);

		return array(
			'ok'      => $posted['ok'],
			'message' => $posted['ok'] ? __( 'Connection verified. A check message was posted to the channel.', 'ai-site-assistant-autopilot' ) : (string) ( $posted['error'] ?? '' ),
		);
	}

	/**
	 * @return array{ok:bool, error?:string}
	 */
	private function post( string $text ): array {
		$response = wp_remote_post(
			$this->get_webhook(),
			array(
				'timeout'            => self::TIMEOUT,
				'headers'            => array( 'Content-Type' => 'application/json' ),
				'body'               => (string) wp_json_encode( array( 'text' => $text ) ),
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'handoff_slack', $response->get_error_message() );
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( 200 === $code ) {
			return array( 'ok' => true );
		}

		$error = in_array( $code, array( 403, 404, 410 ), true )
			/* translators: %s: Slack's error code, for example channel_is_archived. */
			? sprintf( __( 'Slack refused the webhook (%s). It may have been removed, or its channel archived: create a new webhook.', 'ai-site-assistant-autopilot' ), mb_substr( $body, 0, 60 ) )
			/* translators: 1: HTTP status code, 2: Slack's error code. */
			: sprintf( __( 'Slack returned an error (HTTP %1$d): %2$s', 'ai-site-assistant-autopilot' ), $code, mb_substr( $body, 0, 120 ) );
		$this->logger->error( 'handoff_slack', $error );

		return array(
			'ok'    => false,
			'error' => $error,
		);
	}
}
