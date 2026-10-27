<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * WhatsApp Business delivery through Meta's WhatsApp Cloud API.
 *
 * The site owner's WhatsApp Business number sends the handoff to a team member's
 * WhatsApp. WhatsApp only lets a business start a conversation with an approved
 * message template: with a template name set, the summary goes in as its one body
 * variable ({{1}}); without one, a plain text message is sent, which WhatsApp
 * delivers only while the team member has written to the business number in the
 * last 24 hours.
 */
class HandoffWhatsApp {

	public const API_BASE = 'https://graph.facebook.com/';

	/** Graph API version; a site can move it forward with the `aisaautopilot_whatsapp_graph_version` filter. */
	public const API_VERSION = 'v26.0';

	/** WhatsApp's limit for a text message body. */
	public const MAX_LENGTH = 4096;

	/** Room left for the summary inside a template body (WhatsApp caps the whole body at 1,024 characters). */
	public const TEMPLATE_PARAM_LENGTH = 900;

	public const TIMEOUT = 15;

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	public function get_token(): string {
		return Settings::get_handoff_secret( 'handoff_whatsapp_token' );
	}

	public function get_phone_id(): string {
		return $this->widget_value( 'handoff_whatsapp_phone_id' );
	}

	/** The team member's number, digits only with the country code. */
	public function get_to(): string {
		return (string) preg_replace( '/\D+/', '', $this->widget_value( 'handoff_whatsapp_to' ) );
	}

	public function get_template(): string {
		return $this->widget_value( 'handoff_whatsapp_template' );
	}

	public function get_language(): string {
		$language = $this->widget_value( 'handoff_whatsapp_language' );
		return '' !== $language ? $language : 'en_US';
	}

	public function is_configured(): bool {
		return '' !== $this->get_token() && '' !== $this->get_phone_id() && '' !== $this->get_to();
	}

	/**
	 * @return array{ok:bool, error?:string, message_ids?:array<int,string>}
	 */
	public function send( string $text ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'    => false,
				'error' => 'WhatsApp not configured.',
			);
		}

		$result = array(
			'ok'          => true,
			'message_ids' => array(),
		);
		foreach ( $this->payloads( $text ) as $payload ) {
			$sent = $this->post( $payload );
			if ( ! $sent['ok'] ) {
				return $sent;
			}
			$result['message_ids'][] = $sent['id'];
		}

		return $result;
	}

	/**
	 * The request bodies for one handoff: one template message, or the text in as many
	 * messages as it needs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function payloads( string $text ): array {
		$to = $this->get_to();
		if ( '' !== $this->get_template() ) {
			return array(
				array(
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
					'to'                => $to,
					'type'              => 'template',
					'template'          => array(
						'name'       => $this->get_template(),
						'language'   => array( 'code' => $this->get_language() ),
						'components' => array(
							array(
								'type'       => 'body',
								'parameters' => array(
									array(
										'type' => 'text',
										'text' => HandoffText::one_line( $text, self::TEMPLATE_PARAM_LENGTH ),
									),
								),
							),
						),
					),
				),
			);
		}

		$payloads = array();
		foreach ( HandoffText::split( $text, self::MAX_LENGTH ) as $chunk ) {
			$payloads[] = array(
				'messaging_product' => 'whatsapp',
				'recipient_type'    => 'individual',
				'to'                => $to,
				'type'              => 'text',
				'text'              => array(
					'preview_url' => false,
					'body'        => $chunk,
				),
			);
		}

		return $payloads;
	}

	/**
	 * Read the business number's display name, which proves the token and phone number ID
	 * belong together. Nothing is sent.
	 *
	 * @return array{ok:bool, message:string}
	 */
	public function test_connection(): array {
		if ( '' === $this->get_token() || '' === $this->get_phone_id() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Enter the phone number ID and access token first.', 'ai-site-assistant-autopilot' ),
			);
		}

		$response = wp_remote_get(
			$this->endpoint( '?fields=display_phone_number,verified_name' ),
			array(
				'timeout'            => self::TIMEOUT,
				'headers'            => array( 'Authorization' => 'Bearer ' . $this->get_token() ),
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Network error: ', 'ai-site-assistant-autopilot' ) . $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && is_array( $body ) ) {
			return array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: 1: WhatsApp Business display name, 2: its phone number. */
					__( 'Connected as %1$s (%2$s)', 'ai-site-assistant-autopilot' ),
					(string) ( $body['verified_name'] ?? 'WhatsApp Business' ),
					(string) ( $body['display_phone_number'] ?? $this->get_phone_id() )
				),
			);
		}

		return array(
			'ok'      => false,
			'message' => $this->explain( is_array( $body ) ? $body : array(), $code ),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{ok:bool, id:string, error?:string}
	 */
	private function post( array $payload ): array {
		$response = wp_remote_post(
			$this->endpoint( '/messages' ),
			array(
				'timeout'            => self::TIMEOUT,
				'headers'            => array(
					'Authorization' => 'Bearer ' . $this->get_token(),
					'Content-Type'  => 'application/json',
				),
				'body'               => (string) wp_json_encode( $payload ),
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'handoff_whatsapp', $response->get_error_message() );
			return array(
				'ok'    => false,
				'id'    => '',
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();
		if ( $code >= 200 && $code < 300 && isset( $body['messages'][0]['id'] ) ) {
			$this->logger->info( 'handoff_whatsapp', 'Message sent: ' . (string) $body['messages'][0]['id'] );
			return array(
				'ok' => true,
				'id' => (string) $body['messages'][0]['id'],
			);
		}

		$error = $this->explain( $body, $code );
		$this->logger->error( 'handoff_whatsapp', $error );
		return array(
			'ok'    => false,
			'id'    => '',
			'error' => $error,
		);
	}

	/**
	 * Turn a Graph API error into what the site owner has to do about it.
	 *
	 * @param array<string, mixed> $body
	 */
	public function explain( array $body, int $status ): string {
		$error   = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$code    = (int) ( $error['code'] ?? 0 );
		$details = (string) ( $error['error_data']['details'] ?? $error['message'] ?? '' );

		switch ( $code ) {
			case 190:
				return __( 'WhatsApp rejected the access token. It may have expired: create a permanent system-user token in Meta Business Settings.', 'ai-site-assistant-autopilot' );
			case 131047:
				return __( 'WhatsApp only delivers a plain message within 24 hours of the recipient writing to your business number. Add an approved message template so handoffs always arrive.', 'ai-site-assistant-autopilot' );
			case 132000:
				return __( 'The message template must have exactly one variable ({{1}}) in its body.', 'ai-site-assistant-autopilot' );
			case 132001:
				return __( 'WhatsApp has no approved template with this name and language.', 'ai-site-assistant-autopilot' );
			case 131030:
				return __( 'This recipient is not on the allowed list of your WhatsApp test number. Add it in Meta, or use a live business number.', 'ai-site-assistant-autopilot' );
			case 100:
				return __( 'WhatsApp did not accept the phone number ID or recipient number. Check both.', 'ai-site-assistant-autopilot' );
		}

		return '' !== $details
			/* translators: 1: HTTP status code, 2: error text from WhatsApp. */
			? sprintf( __( 'WhatsApp returned an error (HTTP %1$d): %2$s', 'ai-site-assistant-autopilot' ), $status, mb_substr( $details, 0, 200 ) )
			/* translators: %d: HTTP status code. */
			: sprintf( __( 'WhatsApp returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $status );
	}

	private function endpoint( string $path ): string {
		$version = (string) apply_filters( 'aisaautopilot_whatsapp_graph_version', self::API_VERSION );
		if ( ! preg_match( '/^v\d+\.\d+$/', $version ) ) {
			$version = self::API_VERSION;
		}

		return self::API_BASE . $version . '/' . rawurlencode( $this->get_phone_id() ) . $path;
	}

	private function widget_value( string $key ): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? trim( (string) ( $widget[ $key ] ?? '' ) ) : '';
	}
}
