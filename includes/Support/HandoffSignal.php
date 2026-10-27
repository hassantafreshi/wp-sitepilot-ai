<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * Signal delivery through a signal-cli REST API server the site owner runs
 * (github.com/bbernhard/signal-cli-rest-api). Signal has no official API for
 * businesses, so the plugin talks to that server, which sends from a Signal number
 * registered or linked on it.
 *
 * The server usually runs on the same machine or network as WordPress, so unlike the
 * other channels the URL may be a local address. Only an administrator can set it,
 * and it is never taken from a visitor. An optional token is sent as a Bearer header
 * for servers kept behind an authenticating proxy.
 */
class HandoffSignal {

	/** Characters per message; longer messages are folded behind "read more" by Signal apps. */
	public const MAX_LENGTH = 2000;

	public const TIMEOUT = 15;

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	public function get_url(): string {
		return untrailingslashit( $this->widget_value( 'handoff_signal_url' ) );
	}

	public function get_from(): string {
		return $this->widget_value( 'handoff_signal_from' );
	}

	public function get_to(): string {
		return $this->widget_value( 'handoff_signal_to' );
	}

	public function get_token(): string {
		return Settings::get_handoff_secret( 'handoff_signal_token' );
	}

	public function is_configured(): bool {
		return self::is_server_url( $this->get_url() ) && self::is_number( $this->get_from() ) && self::is_recipient( $this->get_to() );
	}

	/** An http(s) server address with no credentials, query or fragment in it. */
	public static function is_server_url( string $url ): bool {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || '' === (string) ( $parts['host'] ?? '' ) ) {
			return false;
		}

		return in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
			&& array() === array_intersect_key( $parts, array_flip( array( 'user', 'pass', 'query', 'fragment' ) ) );
	}

	/** A phone number in international format, as Signal registers it: +, then 7 to 15 digits. */
	public static function is_number( string $number ): bool {
		return 1 === preg_match( '/^\+[1-9]\d{6,14}$/', $number );
	}

	/** A recipient: a phone number, or a group as signal-cli names it ("group." and its ID). */
	public static function is_recipient( string $recipient ): bool {
		return self::is_number( $recipient ) || 1 === preg_match( '#^group\.[A-Za-z0-9+/=_-]{8,}$#', $recipient );
	}

	/**
	 * @return array{ok:bool, error?:string, sent?:int}
	 */
	public function send( string $text ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'    => false,
				'error' => 'Signal not configured.',
			);
		}

		$sent = 0;
		foreach ( HandoffText::split( $text, self::MAX_LENGTH ) as $chunk ) {
			$response = $this->request(
				'POST',
				'/v2/send',
				array(
					'message'    => $chunk,
					'number'     => $this->get_from(),
					'recipients' => array( $this->get_to() ),
				)
			);
			if ( is_wp_error( $response ) ) {
				$this->logger->error( 'handoff_signal', $response->get_error_message() );
				return array(
					'ok'    => false,
					'error' => $response->get_error_message(),
				);
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				$error = $this->explain( $response );
				$this->logger->error( 'handoff_signal', $error );
				return array(
					'ok'    => false,
					'error' => $error,
				);
			}
			++$sent;
		}

		return array(
			'ok'   => true,
			'sent' => $sent,
		);
	}

	/**
	 * Ask the server which numbers it can send from. Nothing is sent.
	 *
	 * @return array{ok:bool, message:string}
	 */
	public function test_connection(): array {
		if ( ! self::is_server_url( $this->get_url() ) || ! self::is_number( $this->get_from() ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Enter the Signal API address and the sending number first.', 'ai-site-assistant-autopilot' ),
			);
		}

		$response = $this->request( 'GET', '/v1/accounts' );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Network error: ', 'ai-site-assistant-autopilot' ) . $response->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			return array(
				'ok'      => false,
				'message' => __( 'The Signal API refused the access token.', 'ai-site-assistant-autopilot' ),
			);
		}
		$accounts = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $accounts ) ) {
			return array(
				'ok'      => false,
				'message' => $this->explain( $response ),
			);
		}
		if ( ! in_array( $this->get_from(), array_map( 'strval', $accounts ), true ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: Signal phone number. */
					__( 'The Signal API answered, but %s is not registered or linked on it.', 'ai-site-assistant-autopilot' ),
					$this->get_from()
				),
			);
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: Signal phone number. */
				__( 'Connected. The Signal API can send from %s.', 'ai-site-assistant-autopilot' ),
				$this->get_from()
			),
		);
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null ) {
		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== $this->get_token() ) {
			$headers['Authorization'] = 'Bearer ' . $this->get_token();
		}
		$args = array(
			'method'             => $method,
			'timeout'            => self::TIMEOUT,
			'headers'            => $headers,
			'redirection'        => 0,
			// The owner's own server, often on localhost or the local network: see the class note.
			'reject_unsafe_urls' => false,
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		return wp_remote_request( $this->get_url() . $path, $args );
	}

	/**
	 * @param array<string, mixed> $response
	 */
	private function explain( array $response ): string {
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$detail  = is_array( $decoded ) ? (string) ( $decoded['error'] ?? '' ) : '';

		return '' !== $detail
			/* translators: 1: HTTP status code, 2: error text from the Signal API. */
			? sprintf( __( 'The Signal API returned an error (HTTP %1$d): %2$s', 'ai-site-assistant-autopilot' ), $code, mb_substr( $detail, 0, 200 ) )
			/* translators: %d: HTTP status code. */
			: sprintf( __( 'The Signal API returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $code );
	}

	private function widget_value( string $key ): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? trim( (string) ( $widget[ $key ] ?? '' ) ) : '';
	}
}
