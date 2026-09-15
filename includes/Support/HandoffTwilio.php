<?php
declare(strict_types=1);
namespace AISAAutoPilot\Support;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

class HandoffTwilio {
	public const API_BASE   = 'https://api.twilio.com/2010-04-01/Accounts';
	public const MAX_LENGTH = 1600;
	public const TIMEOUT    = 15;
	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger(); }

	/** Get Account SID from settings. */
	public function get_sid(): string {
		return Settings::get_handoff_secret( 'handoff_twilio_sid' ); }

	/** Get Auth Token from settings. */
	public function get_token(): string {
		return Settings::get_handoff_secret( 'handoff_twilio_token' ); }

	/** Get "From" number from settings. */
	public function get_from(): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? (string) ( $widget['handoff_twilio_from'] ?? '' ) : ''; }

	/** Get "To" number from settings. */
	public function get_to(): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? (string) ( $widget['handoff_twilio_to'] ?? '' ) : ''; }

	/** Get Message Service SID (optional). */
	public function get_msg_svc(): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? (string) ( $widget['handoff_twilio_msg_svc'] ?? '' ) : ''; }

	public function is_configured(): bool {
		return '' !== $this->get_sid() && '' !== $this->get_token() && '' !== $this->get_from() && '' !== $this->get_to();
	}

	public function send( string $text, string $pdf_url = '' ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'    => false,
				'error' => 'Twilio not configured.',
			);}
		$sid     = $this->get_sid();
		$token   = $this->get_token();
		$from    = $this->get_from();
		$to      = $this->get_to();
		$msg_svc = $this->get_msg_svc();
		$chunks  = $this->split_message( $text );
		$result  = array(
			'ok'           => true,
			'message_sids' => array(),
		);
		foreach ( $chunks as $i => $chunk ) {
			try {
				$body = array(
					'To'   => $to,
					'From' => $from,
					'Body' => mb_substr( $chunk, 0, 1600 ),
				);
				if ( '' !== $msg_svc ) {
					$body['MessagingServiceSid'] = $msg_svc;
					unset( $body['From'] );}
				// Append PDF as media URL if provided (first message only).
				if ( 0 === $i && '' !== $pdf_url ) {
					$body['MediaUrl'] = $pdf_url;}
				$auth     = base64_encode( "{$sid}:{$token}" );
				$response = wp_remote_post(
					self::API_BASE . "/{$sid}/Messages.json",
					array(
						'timeout'            => self::TIMEOUT,
						'headers'            => array(
							'Authorization' => "Basic {$auth}",
							'Content-Type'  => 'application/x-www-form-urlencoded',
						),
						'body'               => $body,
						'redirection'        => 0,
						'reject_unsafe_urls' => true,
					)
				);
				if ( is_wp_error( $response ) ) {
					throw new \RuntimeException( $response->get_error_message() );}
				$code    = wp_remote_retrieve_response_code( $response );
				$raw     = wp_remote_retrieve_body( $response );
				$decoded = json_decode( $raw, true );
				if ( 401 === $code ) {
					return array(
						'ok'    => false,
						'error' => 'Twilio: invalid Account SID or Auth Token.',
					);}
				if ( 400 === $code ) {
					$msg = isset( $decoded['message'] ) ? $decoded['message'] : 'Bad request.';
					$this->logger->error( 'handoff_twilio', $msg );
					return array(
						'ok'    => false,
						'error' => "Twilio: {$msg}",
					);}
				if ( 403 === $code ) {
					return array(
						'ok'    => false,
						'error' => 'Twilio: account suspended or number not verified.',
					);}
				if ( 429 === $code ) {
					$this->logger->info( 'handoff_twilio', 'Twilio rate limited the handoff request.' );
					return array(
						'ok'    => false,
						'error' => 'Twilio is temporarily rate-limiting delivery.',
					);}
				if ( $code < 200 || $code >= 300 ) {
					throw new \RuntimeException( "HTTP {$code}: " . substr( $raw, 0, 200 ) );}
				if ( isset( $decoded['sid'] ) ) {
					$result['message_sids'][] = $decoded['sid'];}
				$this->logger->info( 'handoff_twilio', "Message {$i} sent: {$decoded['sid']}" );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'handoff_twilio', $e->getMessage() );
				$result['ok']    = false;
				$result['error'] = $e->getMessage();}
		}
		return $result;
	}

	public function split_message( string $text ): array {
		if ( mb_strlen( $text ) <= self::MAX_LENGTH ) {
			return array( $text );}
		$chunks  = array();
		$lines   = explode( "\n", $text );
		$current = '';
		foreach ( $lines as $line ) {
			if ( mb_strlen( $line ) > self::MAX_LENGTH ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}$chunks = array_merge( $chunks, str_split( $line, self::MAX_LENGTH ) );
				continue;}
			$candidate = '' === $current ? $line : $current . "\n" . $line;
			if ( mb_strlen( $candidate ) > self::MAX_LENGTH ) {
				$chunks[] = $current;
				$current  = $line;
			} else {
				$current = $candidate;}
		}
		if ( '' !== $current ) {
			$chunks[] = $current;}
		return $chunks;
	}

	public function test_connection(): array {
		$sid   = $this->get_sid();
		$token = $this->get_token();
		if ( '' === $sid || '' === $token ) {
			return array(
				'ok'      => false,
				'message' => 'Twilio not configured.',
			);}
		try {
			$auth     = base64_encode( "{$sid}:{$token}" );
			$response = wp_remote_get(
				self::API_BASE . "/{$sid}/Accounts/{$sid}.json",
				array(
					'timeout'            => self::TIMEOUT,
					'headers'            => array( 'Authorization' => "Basic {$auth}" ),
					'redirection'        => 0,
					'reject_unsafe_urls' => true,
				)
			);
			if ( is_wp_error( $response ) ) {
				return array(
					'ok'      => false,
					'message' => 'Network: ' . $response->get_error_message(),
				);}
			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 === $code ) {
				return array(
					'ok'      => true,
					'message' => 'Connected as ' . ( $body['friendly_name'] ?? 'Twilio' ) . ' (' . ( $body['type'] ?? 'trial' ) . ')',
				);}
			if ( 401 === $code ) {
				return array(
					'ok'      => false,
					'message' => 'Invalid Twilio credentials.',
				);}
			return array(
				'ok'      => false,
				'message' => "HTTP {$code}",
			);
		} catch ( \Throwable $e ) {
			return array(
				'ok'      => false,
				'message' => $e->getMessage(),
			);}
	}
}
