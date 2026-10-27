<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffSignal;
use AISAAutoPilot\Support\HandoffSlack;

/**
 * Restricted, channel-scoped updates for the Answer Desk Human handoff tab.
 *
 * Credentials are accepted only when a manager deliberately submits a new
 * value. They are stored by Settings outside the normal settings option and
 * never included in this endpoint's response.
 */
final class AdminHandoffSettingsController {

	/** @var array<string, string> */
	private const ENABLE_KEYS = array(
		'email'    => 'handoff_email_enabled',
		'telegram' => 'handoff_telegram_enabled',
		'twilio'   => 'handoff_twilio_enabled',
		'whatsapp' => 'handoff_whatsapp_enabled',
		'slack'    => 'handoff_slack_enabled',
		'signal'   => 'handoff_signal_enabled',
	);

	/** The encrypted credential(s) of each channel, as sent by the form => stored key. */
	private const SECRET_FIELDS = array(
		'telegram' => array( 'telegram_token' => 'handoff_telegram_token' ),
		'twilio'   => array(
			'twilio_sid'   => 'handoff_twilio_sid',
			'twilio_token' => 'handoff_twilio_token',
		),
		'whatsapp' => array( 'whatsapp_token' => 'handoff_whatsapp_token' ),
		'slack'    => array( 'slack_webhook' => 'handoff_slack_webhook' ),
		'signal'   => array( 'signal_token' => 'handoff_signal_token' ),
	);

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );
		if ( ! array_key_exists( $channel, self::ENABLE_KEYS ) ) {
			return $this->error(
				'handoff_channel_invalid',
				__( 'Choose a supported handoff channel.', 'ai-site-assistant-autopilot' )
			);
		}

		if ( ! $request->has_param( 'enabled' ) ) {
			return $this->error(
				'handoff_channel_incomplete',
				__( 'The channel status is required.', 'ai-site-assistant-autopilot' )
			);
		}

		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$widget  = (array) Settings::get( 'widget', array() );
		$update  = array(
			self::ENABLE_KEYS[ $channel ] => $enabled,
		);

		switch ( $channel ) {
			case 'email':
				$error = $this->email_update( $request, $update );
				break;
			case 'telegram':
				$error = $this->telegram_update( $request, $update );
				break;
			case 'twilio':
				$error = $this->twilio_update( $request, $update );
				break;
			case 'whatsapp':
				$error = $this->whatsapp_update( $request, $update );
				break;
			case 'slack':
				$error = $this->slack_update( $request );
				break;
			default:
				$error = $this->signal_update( $request, $update );
		}

		if ( null !== $error ) {
			return $error;
		}

		$next = array_merge( $widget, $update );
		// The compact tab has no separate global switch in the mockup. Derive the
		// old global gate from the only controls it does expose: enabled channels.
		$next['handoff_enabled'] = $this->has_ready_channel( $next );
		Settings::update( array( 'widget' => $next ) );

		$saved = (array) Settings::get( 'widget', array() );
		return new \WP_REST_Response(
			array(
				'ok'                => true,
				'code'              => 'handoff_channel_updated',
				'handoff_enabled'   => ! empty( $saved['handoff_enabled'] ),
				'handoff_available' => Settings::answer_desk_handoff_available(),
				'channel'           => $this->channel_response( $channel, $saved ),
			)
		);
	}

	/**
	 * Saves which contact details the widget's handoff card asks for: each of name, email, phone and
	 * website is 'off', 'optional' or 'required'. All four must be sent, so a partial form never
	 * switches a detail off by accident.
	 */
	public function update_fields( \WP_REST_Request $request ): \WP_REST_Response {
		$raw     = $request->get_param( 'fields' );
		$message = __( 'Choose Off, Optional or Required for each detail.', 'ai-site-assistant-autopilot' );
		if ( ! is_array( $raw ) || array() !== array_diff( array_keys( $raw ), Settings::HANDOFF_FIELD_KEYS ) ) {
			return $this->error( 'handoff_fields_invalid', $message );
		}

		$fields = array();
		foreach ( Settings::HANDOFF_FIELD_KEYS as $key ) {
			$mode = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? $raw[ $key ] : '';
			if ( ! in_array( $mode, Settings::HANDOFF_FIELD_MODES, true ) ) {
				return $this->error( 'handoff_fields_invalid', $message );
			}
			$fields[ $key ] = $mode;
		}

		Settings::update( array( 'widget' => array( 'handoff_fields' => $fields ) ) );
		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'code'   => 'handoff_fields_updated',
				'fields' => Settings::handoff_fields(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function email_update( \WP_REST_Request $request, array &$update ): ?\WP_REST_Response {
		if ( ! $request->has_param( 'email_to' ) ) {
			return $this->error(
				'handoff_email_recipient_missing',
				__( 'The email recipient is required.', 'ai-site-assistant-autopilot' )
			);
		}

		$raw       = trim( (string) $request->get_param( 'email_to' ) );
		$recipient = sanitize_email( $raw );
		if ( '' !== $raw && ( '' === $recipient || ! is_email( $recipient ) ) ) {
			return $this->error(
				'handoff_email_recipient_invalid',
				__( 'Enter a valid email recipient or leave it empty to use the WordPress administration email.', 'ai-site-assistant-autopilot' )
			);
		}

		$update['handoff_email_to'] = $recipient;
		return null;
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function telegram_update( \WP_REST_Request $request, array &$update ): ?\WP_REST_Response {
		if ( ! $request->has_param( 'telegram_chat' ) ) {
			return $this->error(
				'handoff_telegram_chat_missing',
				__( 'The Telegram chat ID is required.', 'ai-site-assistant-autopilot' )
			);
		}

		$update['handoff_telegram_chat'] = $this->text( $request->get_param( 'telegram_chat' ), 120 );
		return $this->update_secrets( $request, 'telegram' );
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function twilio_update( \WP_REST_Request $request, array &$update ): ?\WP_REST_Response {
		foreach ( array( 'twilio_from', 'twilio_to' ) as $field ) {
			if ( ! $request->has_param( $field ) ) {
				return $this->error(
					'handoff_twilio_number_missing',
					__( 'Both Twilio phone numbers are required.', 'ai-site-assistant-autopilot' )
				);
			}
		}

		$update['handoff_twilio_from'] = $this->text( $request->get_param( 'twilio_from' ), 64 );
		$update['handoff_twilio_to']   = $this->text( $request->get_param( 'twilio_to' ), 64 );
		return $this->update_secrets( $request, 'twilio' );
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function whatsapp_update( \WP_REST_Request $request, array &$update ): ?\WP_REST_Response {
		foreach ( array( 'whatsapp_phone_id', 'whatsapp_to' ) as $field ) {
			if ( ! $request->has_param( $field ) ) {
				return $this->error(
					'handoff_whatsapp_number_missing',
					__( 'The phone number ID and the number to send to are both required.', 'ai-site-assistant-autopilot' )
				);
			}
		}

		$phone_id = $this->text( $request->get_param( 'whatsapp_phone_id' ), 32 );
		$to       = $this->text( $request->get_param( 'whatsapp_to' ), 32 );
		$template = strtolower( $this->text( $request->get_param( 'whatsapp_template' ), 512 ) );
		$language = $this->text( $request->get_param( 'whatsapp_language' ), 16 );
		if ( ( '' !== $phone_id && ! ctype_digit( $phone_id ) )
			|| ( '' !== $to && ! preg_match( '/^\+?[0-9][0-9 ()-]{6,24}$/', $to ) ) ) {
			return $this->error(
				'handoff_whatsapp_number_invalid',
				__( 'Use the numeric phone number ID from Meta, and the number to send to in international format.', 'ai-site-assistant-autopilot' )
			);
		}
		if ( ( '' !== $template && ! preg_match( '/^[a-z0-9_]+$/', $template ) )
			|| ( '' !== $language && ! preg_match( '/^[a-z]{2,3}(?:_[A-Za-z]{2,4})?$/', $language ) ) ) {
			return $this->error(
				'handoff_whatsapp_template_invalid',
				__( 'A template name has lowercase letters, digits and underscores; a language looks like en_US or fa.', 'ai-site-assistant-autopilot' )
			);
		}

		$update['handoff_whatsapp_phone_id'] = $phone_id;
		$update['handoff_whatsapp_to']       = $to;
		$update['handoff_whatsapp_template'] = $template;
		$update['handoff_whatsapp_language'] = '' !== $language ? $language : 'en_US';
		return $this->update_secrets( $request, 'whatsapp' );
	}

	private function slack_update( \WP_REST_Request $request ): ?\WP_REST_Response {
		$url = trim( (string) $request->get_param( 'slack_webhook' ) );
		if ( '' !== $url && ! rest_sanitize_boolean( $request->get_param( 'clear_slack_webhook' ) ) && ! HandoffSlack::is_webhook_url( $url ) ) {
			return $this->error(
				'handoff_slack_webhook_invalid',
				__( 'Paste the incoming webhook URL Slack gave you; it starts with https://hooks.slack.com/services/.', 'ai-site-assistant-autopilot' )
			);
		}

		return $this->update_secrets( $request, 'slack' );
	}

	/**
	 * @param array<string, mixed> $update
	 */
	private function signal_update( \WP_REST_Request $request, array &$update ): ?\WP_REST_Response {
		foreach ( array( 'signal_url', 'signal_from', 'signal_to' ) as $field ) {
			if ( ! $request->has_param( $field ) ) {
				return $this->error(
					'handoff_signal_field_missing',
					__( 'The Signal API address, the sending number and the recipient are all required.', 'ai-site-assistant-autopilot' )
				);
			}
		}

		$url  = esc_url_raw( trim( (string) $request->get_param( 'signal_url' ) ), array( 'http', 'https' ) );
		$from = (string) preg_replace( '/[\s()-]+/', '', $this->text( $request->get_param( 'signal_from' ), 32 ) );
		$to   = $this->text( $request->get_param( 'signal_to' ), 160 );
		$to   = str_starts_with( $to, 'group.' ) ? $to : (string) preg_replace( '/[\s()-]+/', '', $to );
		if ( ( '' !== $url && ! HandoffSignal::is_server_url( $url ) )
			|| ( '' !== $from && ! HandoffSignal::is_number( $from ) )
			|| ( '' !== $to && ! HandoffSignal::is_recipient( $to ) ) ) {
			return $this->error(
				'handoff_signal_field_invalid',
				__( 'Use the Signal API address (http or https), numbers in international format such as +15550194471, and for a group its "group." ID.', 'ai-site-assistant-autopilot' )
			);
		}

		$update['handoff_signal_url']  = untrailingslashit( $url );
		$update['handoff_signal_from'] = $from;
		$update['handoff_signal_to']   = $to;
		return $this->update_secrets( $request, 'signal' );
	}

	private function update_secrets( \WP_REST_Request $request, string $channel ): ?\WP_REST_Response {
		foreach ( self::SECRET_FIELDS[ $channel ] as $field => $key ) {
			$error = $this->update_secret( $request, $field, $key );
			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	private function update_secret( \WP_REST_Request $request, string $field, string $key ): ?\WP_REST_Response {
		$clear = rest_sanitize_boolean( $request->get_param( 'clear_' . $field ) );
		if ( $clear ) {
			if ( ! Settings::set_handoff_secret( $key, '' ) ) {
				return $this->error( 'handoff_secret_clear_failed', __( 'Could not remove the saved credential.', 'ai-site-assistant-autopilot' ) );
			}
			return null;
		}

		if ( ! $request->has_param( $field ) ) {
			return null;
		}

		$value = $this->text( $request->get_param( $field ), 512 );
		if ( '' !== $value && ! Settings::set_handoff_secret( $key, $value ) ) {
			return $this->error(
				'handoff_secret_save_failed',
				__( 'Could not store the credential securely. Please try again.', 'ai-site-assistant-autopilot' )
			);
		}

		return null;
	}

	/** @param array<string, mixed> $widget */
	private function has_ready_channel( array $widget ): bool {
		foreach ( self::ENABLE_KEYS as $channel => $enable_key ) {
			if ( ! empty( $widget[ $enable_key ] ) && Settings::handoff_channel_ready( $channel, $widget ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $widget
	 * @return array<string, mixed>
	 */
	private function channel_response( string $channel, array $widget ): array {
		$response = array(
			'id'         => $channel,
			'enabled'    => ! empty( $widget[ self::ENABLE_KEYS[ $channel ] ] ),
			'configured' => Settings::handoff_channel_ready( $channel, $widget ),
		);
		// Which credentials are saved, never the credentials themselves.
		foreach ( self::SECRET_FIELDS[ $channel ] ?? array() as $field => $key ) {
			$response[ 'has_' . substr( $field, strlen( $channel ) + 1 ) ] = Settings::has_handoff_secret( $key );
		}
		// Kept from earlier releases: Telegram reported its token as has_secret.
		if ( 'telegram' === $channel ) {
			$response['has_secret'] = $response['has_token'];
			unset( $response['has_token'] );
		}

		return $response;
	}

	private function text( $value, int $limit ): string {
		return mb_substr( sanitize_text_field( (string) $value ), 0, $limit );
	}

	private function error( string $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			400
		);
	}
}
