<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\Settings;

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

		if ( 'email' === $channel ) {
			$error = $this->email_update( $request, $update );
		} elseif ( 'telegram' === $channel ) {
			$error = $this->telegram_update( $request, $update );
		} else {
			$error = $this->twilio_update( $request, $update );
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
		return $this->update_secret( $request, 'telegram_token', 'handoff_telegram_token' );
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
		$error                         = $this->update_secret( $request, 'twilio_sid', 'handoff_twilio_sid' );
		if ( null !== $error ) {
			return $error;
		}

		return $this->update_secret( $request, 'twilio_token', 'handoff_twilio_token' );
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
		$email = sanitize_email( (string) ( $widget['handoff_email_to'] ?? get_option( 'admin_email', '' ) ) );
		if ( ! empty( $widget['handoff_email_enabled'] ) && '' !== $email && is_email( $email ) ) {
			return true;
		}

		if ( ! empty( $widget['handoff_telegram_enabled'] )
			&& Settings::has_handoff_secret( 'handoff_telegram_token' )
			&& '' !== trim( (string) ( $widget['handoff_telegram_chat'] ?? '' ) ) ) {
			return true;
		}

		return ! empty( $widget['handoff_twilio_enabled'] )
			&& Settings::has_handoff_secret( 'handoff_twilio_sid' )
			&& Settings::has_handoff_secret( 'handoff_twilio_token' )
			&& '' !== trim( (string) ( $widget['handoff_twilio_from'] ?? '' ) )
			&& '' !== trim( (string) ( $widget['handoff_twilio_to'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $widget
	 * @return array<string, mixed>
	 */
	private function channel_response( string $channel, array $widget ): array {
		$enabled = ! empty( $widget[ self::ENABLE_KEYS[ $channel ] ] );
		if ( 'email' === $channel ) {
			$recipient = sanitize_email( (string) ( $widget['handoff_email_to'] ?? get_option( 'admin_email', '' ) ) );
			return array(
				'id'         => $channel,
				'enabled'    => $enabled,
				'configured' => '' !== $recipient && is_email( $recipient ),
			);
		}

		if ( 'telegram' === $channel ) {
			return array(
				'id'         => $channel,
				'enabled'    => $enabled,
				'configured' => Settings::has_handoff_secret( 'handoff_telegram_token' ) && '' !== trim( (string) ( $widget['handoff_telegram_chat'] ?? '' ) ),
				'has_secret' => Settings::has_handoff_secret( 'handoff_telegram_token' ),
			);
		}

		return array(
			'id'         => $channel,
			'enabled'    => $enabled,
			'configured' => Settings::has_handoff_secret( 'handoff_twilio_sid' )
				&& Settings::has_handoff_secret( 'handoff_twilio_token' )
				&& '' !== trim( (string) ( $widget['handoff_twilio_from'] ?? '' ) )
				&& '' !== trim( (string) ( $widget['handoff_twilio_to'] ?? '' ) ),
			'has_sid'    => Settings::has_handoff_secret( 'handoff_twilio_sid' ),
			'has_token'  => Settings::has_handoff_secret( 'handoff_twilio_token' ),
		);
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
