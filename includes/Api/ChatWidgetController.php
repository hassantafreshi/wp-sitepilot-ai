<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Support\HandoffService;

/** Public, deliberately limited API for the site assistant widget. */
final class ChatWidgetController {
	private const TEXT_LIMIT    = 20;
	private const HANDOFF_LIMIT = 5;

	public function settings(): \WP_REST_Response {
		$settings = Settings::all();
		$widget   = $settings['widget'] ?? Settings::widget_defaults();
		$color    = sanitize_hex_color( (string) ( $widget['color'] ?? '' ) ) ?: '#4f46e5';
		$bg       = sanitize_hex_color( (string) ( $widget['bg'] ?? '' ) ) ?: '#ffffff';

		// Never expose delivery credentials or exclusion rules to a site visitor.
		return new \WP_REST_Response(
			array(
				'ok'              => true,
				'code'            => 'ok',
				'enabled'         => ! empty( $widget['enabled'] ),
				'position'        => sanitize_key( (string) ( $widget['position'] ?? 'bottom-right' ) ),
				'theme'           => sanitize_key( (string) ( $widget['theme'] ?? 'light' ) ),
				'color'           => $color,
				'bg'              => $bg,
				'title'           => sanitize_text_field( (string) ( $widget['title'] ?? '' ) ),
				'greeting'        => sanitize_textarea_field( (string) ( $widget['greeting'] ?? '' ) ),
				'auto_open'       => absint( $widget['auto_open'] ?? 0 ),
				'voice_enabled'   => ! empty( $widget['voice_enabled'] ),
				'voice_auto'      => ! empty( $widget['voice_auto'] ),
				'voice_name'      => sanitize_text_field( (string) ( $widget['voice_name'] ?? '' ) ),
				'handoff_enabled' => ! empty( $widget['handoff_enabled'] ),
				'handoff_text'    => sanitize_text_field( (string) ( $widget['handoff_text'] ?? '' ) ),
			)
		);
	}

	public function ask( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_enabled() ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'widget_disabled',
					'message' => __( 'The assistant widget is disabled.', 'ai-site-assistant-autopilot' ),
				),
				403
			);
		}

		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === trim( $question ) ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'code'   => 'empty',
					'answer' => __( 'Please enter a question.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		if ( mb_strlen( $question ) > 1500 ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'question_too_long',
					'message' => __( 'Please shorten your message and try again.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		if ( ! $this->allow( 'text', self::TEXT_LIMIT ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'rate_limited',
					'message' => __( 'Please wait before sending another message.', 'ai-site-assistant-autopilot' ),
				),
				429
			);
		}

		try {
			$result = ( new ChatEngine( new KnowledgeBase() ) )->ask( $question );
			return new \WP_REST_Response( array_merge( array( 'ok' => true ), $result ) );
		} catch ( \Throwable $exception ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'assistant_unavailable',
					'message' => __( 'The assistant is temporarily unavailable. Please try again later.', 'ai-site-assistant-autopilot' ),
				),
				503
			);
		}
	}

	public function conversation( \WP_REST_Request $request ): \WP_REST_Response {
		// Visitor conversations are intentionally client-side only. Do not echo
		// arbitrary request data back from a public endpoint.
		return new \WP_REST_Response(
			array(
				'ok'       => true,
				'messages' => array(),
			)
		);
	}

	public function handoff( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_enabled() ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'widget_disabled',
					'message' => __( 'The assistant widget is disabled.', 'ai-site-assistant-autopilot' ),
				),
				403
			);
		}
		if ( ! $this->handoff_enabled() ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'handoff_disabled',
					'message' => __( 'Human handoff is not enabled.', 'ai-site-assistant-autopilot' ),
				),
				403
			);
		}
		if ( ! $this->allow( 'handoff', self::HANDOFF_LIMIT ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'rate_limited',
					'message' => __( 'Please wait before requesting another handoff.', 'ai-site-assistant-autopilot' ),
				),
				429
			);
		}

		$messages = $this->clean_handoff_messages( $request->get_param( 'messages' ) );
		$name     = mb_substr( sanitize_text_field( (string) $request->get_param( 'name' ) ), 0, 120 );
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( '' !== (string) $request->get_param( 'email' ) && ! is_email( $email ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'invalid_email',
					'message' => __( 'Please provide a valid email address.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}
		if ( empty( $messages ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'no_messages',
					'message' => __( 'No messages to send.', 'ai-site-assistant-autopilot' ),
				),
				400
			);
		}

		$result = ( new HandoffService() )->handoff( $messages, $name, $email );
		return new \WP_REST_Response(
			array(
				'ok'      => (bool) $result['ok'],
				'code'    => ! empty( $result['ok'] ) ? 'sent' : 'handoff_failed',
				'message' => ! empty( $result['ok'] )
					? __( 'Your request has been sent to the support team.', 'ai-site-assistant-autopilot' )
					: __( 'We could not send the request. Please try again later.', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	public function handoff_status(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'code'    => 'ok',
				'enabled' => $this->handoff_enabled(),
			)
		);
	}

	public function test_telegram(): \WP_REST_Response {
		$result = ( new \AISAAutoPilot\Support\HandoffTelegram() )->test_connection();
		return new \WP_REST_Response( array_merge( array( 'ok' => $result['ok'] ), $result ) );
	}

	public function test_twilio(): \WP_REST_Response {
		$result = ( new \AISAAutoPilot\Support\HandoffTwilio() )->test_connection();
		return new \WP_REST_Response( array_merge( array( 'ok' => $result['ok'] ), $result ) );
	}

	private function is_enabled(): bool {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) && ! empty( $widget['enabled'] );
	}

	private function handoff_enabled(): bool {
		$widget = Settings::get( 'widget', array() );
		if ( ! is_array( $widget ) || empty( $widget['handoff_enabled'] ) ) {
			return false;
		}

		$email = sanitize_email( (string) ( $widget['handoff_email_to'] ?? get_option( 'admin_email', '' ) ) );
		$has_email = ! empty( $widget['handoff_email_enabled'] ) && '' !== $email && is_email( $email );
		$has_telegram = ! empty( $widget['handoff_telegram_enabled'] )
			&& Settings::has_handoff_secret( 'handoff_telegram_token' )
			&& ! empty( $widget['handoff_telegram_chat'] );
		$has_twilio = ! empty( $widget['handoff_twilio_enabled'] )
			&& Settings::has_handoff_secret( 'handoff_twilio_sid' )
			&& Settings::has_handoff_secret( 'handoff_twilio_token' )
			&& ! empty( $widget['handoff_twilio_from'] )
			&& ! empty( $widget['handoff_twilio_to'] );

		return $has_email || $has_telegram || $has_twilio;
	}

	private function allow( string $kind, int $limit ): bool {
		return RateLimiter::allow( 'widget_' . $kind, $limit, HOUR_IN_SECONDS );
	}

	/** @return array<int,array{role:string,content:string}> */
	private function clean_handoff_messages( $messages ): array {
		if ( ! is_array( $messages ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $messages, -12 ) as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			$content = trim( sanitize_textarea_field( (string) ( $message['content'] ?? '' ) ) );
			if ( '' === $content ) {
				continue;
			}
			$clean[] = array(
				'role'    => ( 'assistant' === ( $message['role'] ?? '' ) ) ? 'assistant' : 'user',
				'content' => mb_substr( $content, 0, 1200 ),
			);
		}

		return $clean;
	}
}
