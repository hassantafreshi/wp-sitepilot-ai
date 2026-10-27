<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Chat\ConversationHistory;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Support\HandoffContact;
use AISAAutoPilot\Support\HandoffService;

/** Public, deliberately limited API for the site assistant widget. */
final class ChatWidgetController {
	private const TEXT_LIMIT    = 20;
	private const HANDOFF_LIMIT = 5;

	public function settings(): \WP_REST_Response {
		$settings = Settings::all();
		$widget   = $settings['widget'] ?? Settings::widget_defaults();
		$color    = sanitize_hex_color( (string) ( $widget['color'] ?? '' ) ) ?: '#202a8d';
		$bg       = sanitize_hex_color( (string) ( $widget['bg'] ?? '' ) ) ?: '#ffffff';
		$copy     = Settings::widget_copy( (array) $widget );

		// Never expose delivery credentials or exclusion rules to a site visitor.
		return new \WP_REST_Response(
			array(
				'ok'                           => true,
				'code'                         => 'ok',
				'enabled'                      => Settings::answer_desk_enabled(),
				'position'                     => sanitize_key( (string) ( $widget['position'] ?? 'bottom-right' ) ),
				'theme'                        => sanitize_key( (string) ( $widget['theme'] ?? 'light' ) ),
				'color'                        => $color,
				'bg'                           => $bg,
				'title'                        => sanitize_text_field( $copy['title'] ),
				'greeting'                     => sanitize_textarea_field( $copy['greeting'] ),
				'auto_open'                    => absint( $widget['auto_open'] ?? 0 ),
				'voice_enabled'                => ! empty( $widget['voice_enabled'] ),
				'voice_auto'                   => ! empty( $widget['voice_auto'] ),
				'voice_name'                   => sanitize_text_field( (string) ( $widget['voice_name'] ?? '' ) ),
				'handoff_enabled'              => Settings::answer_desk_handoff_available(),
				'handoff_text'                 => sanitize_text_field( (string) ( $widget['handoff_text'] ?? '' ) ),
				'conversation_logging_enabled' => ! empty( $widget['conversation_logging_enabled'] ),
				'conversation_notice'          => sanitize_text_field( $copy['conversation_notice'] ),
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
			// Refusals carry "message"; only a successful reply carries "answer". The widget
			// reads an "answer" as the assistant speaking, so this one used to be shown as one.
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'empty',
					'message' => __( 'Please enter a question.', 'ai-site-assistant-autopilot' ),
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
			// The widget's last few turns, so a follow-up keeps its subject. Nothing is stored here.
			$history = ConversationHistory::context( ConversationHistory::clean( $request->get_param( 'history' ) ), $question );
			$result  = ( new ChatEngine( new KnowledgeBase() ) )->ask( $question, $history );
			if ( 'usage_cap_reached' === (string) ( $result['status'] ?? '' ) ) {
				return new \WP_REST_Response(
					array(
						'ok'      => false,
						'code'    => 'usage_cap_reached',
						'message' => (string) ( $result['answer'] ?? '' ),
					),
					429
				);
			}
			if ( $this->conversation_logging_enabled() ) {
				$stored                    = ( new \AISAAutoPilot\Chat\VisitorConversationStore() )->append_turn(
					sanitize_text_field( (string) $request->get_param( 'conversation_id' ) ),
					$question,
					(string) ( $result['answer'] ?? '' ),
					is_array( $result['sources'] ?? null ) ? $result['sources'] : array()
				);
				$result['conversation_id'] = $stored['token'];
			}
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
		// Only the details the site owner switched on are read; the rest of the request is ignored.
		$contact = HandoffContact::collect(
			Settings::handoff_fields(),
			array(
				'name'    => $request->get_param( 'name' ),
				'email'   => $request->get_param( 'email' ),
				'phone'   => $request->get_param( 'phone' ),
				'website' => $request->get_param( 'website' ),
			)
		);
		if ( array() !== $contact['invalid'] ) {
			$invalid           = $contact['invalid'][0];
			$messages_by_field = array(
				'name'    => __( 'Please enter your name.', 'ai-site-assistant-autopilot' ),
				'email'   => __( 'Please provide a valid email address.', 'ai-site-assistant-autopilot' ),
				'phone'   => __( 'Please enter a valid mobile number.', 'ai-site-assistant-autopilot' ),
				'website' => __( 'Please enter a valid website address.', 'ai-site-assistant-autopilot' ),
			);
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'invalid_' . $invalid,
					'message' => $messages_by_field[ $invalid ],
					'fields'  => $contact['invalid'],
				),
				400
			);
		}
		if ( array() !== $contact['missing'] ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'missing_contact',
					'message' => __( 'Please fill in the required details.', 'ai-site-assistant-autopilot' ),
					'fields'  => $contact['missing'],
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

		$result = ( new HandoffService() )->handoff( $messages, $contact['values'] );
		// A visitor deliberately pressed "Talk to human", so keeping this request
		// in the protected inbox is appropriate. Ordinary chat messages remain
		// client-side only.
		$inbox_id = ( new \AISAAutoPilot\Support\HandoffInbox() )->record(
			$messages,
			$contact['values']['name'],
			$contact['values']['email'],
			! empty( $result['ok'] ) ? 'new' : 'failed',
			$contact['values']['phone'],
			$contact['values']['website']
		);
		return new \WP_REST_Response(
			array(
				'ok'        => (bool) $result['ok'],
				'code'      => ! empty( $result['ok'] ) ? 'sent' : 'handoff_failed',
				'message'   => ! empty( $result['ok'] )
					? __( 'Your request has been sent to the support team.', 'ai-site-assistant-autopilot' )
					: __( 'We could not send the request. Please try again later.', 'ai-site-assistant-autopilot' ),
				'reference' => $inbox_id,
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
		return Settings::answer_desk_enabled();
	}

	private function handoff_enabled(): bool {
		return Settings::answer_desk_handoff_available();
	}

	private function conversation_logging_enabled(): bool {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) && ! empty( $widget['conversation_logging_enabled'] );
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
			// A transcript is plain text: keep what the visitor typed ("3<4" is not markup) and drop only control
			// characters. Every output escapes it: the inbox (esc_html, textContent), the email (esc_html) and
			// Telegram (htmlspecialchars); SMS is plain text.
			$content = str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( (string) ( $message['content'] ?? '' ), true ) );
			$content = trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content ) );
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
