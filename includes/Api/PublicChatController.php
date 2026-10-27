<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Chat\ConversationHistory;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Voice\VoiceTranscriber;

/** Public, read-only assistant endpoints. They retain neither audio nor chats. */
final class PublicChatController {
	private const TEXT_LIMIT  = 20;
	private const VOICE_LIMIT = 8;

	public function ask( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_enabled() ) {
			return $this->error( 'assistant_disabled', __( 'The site assistant is not enabled.', 'ai-site-assistant-autopilot' ), 403 );
		}
		if ( ! $this->allow( 'text', self::TEXT_LIMIT ) ) {
			return $this->error( 'rate_limited', __( 'Too many messages. Please try again later.', 'ai-site-assistant-autopilot' ), 429 );
		}
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question || mb_strlen( $question ) > 1500 ) {
			return $this->error( 'invalid_question', __( 'Please enter a question of up to 1,500 characters.', 'ai-site-assistant-autopilot' ), 400 );
		}
		$context = ConversationHistory::context( ConversationHistory::clean( $request->get_param( 'history' ) ), $question );
		try {
			$result = ( new ChatEngine() )->ask( $question, $context );
			if ( 'usage_cap_reached' === (string) ( $result['status'] ?? '' ) ) {
				return $this->error( 'usage_cap_reached', (string) ( $result['answer'] ?? '' ), 429 );
			}
			return new \WP_REST_Response( array( 'ok' => true ) + $result );
		} catch ( \Throwable $e ) {
			return $this->error( 'assistant_unavailable', __( 'The assistant is temporarily unavailable. Please try again later.', 'ai-site-assistant-autopilot' ), 503 );
		}
	}

	public function transcribe( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_enabled() ) {
			return $this->error( 'assistant_disabled', __( 'The site assistant is not enabled.', 'ai-site-assistant-autopilot' ), 403 );
		}
		// The mic is a widget setting, so this route follows it. The gate runs before the rate
		// limiter: a site with voice switched off should spend neither the visitor's voice
		// quota nor a provider call.
		if ( ! $this->is_voice_enabled() ) {
			return $this->error( 'voice_disabled', __( 'Voice input is turned off for this site.', 'ai-site-assistant-autopilot' ), 403 );
		}
		if ( ! $this->allow( 'voice', self::VOICE_LIMIT ) ) {
			return $this->error( 'rate_limited', __( 'Too many voice requests. Please try again later.', 'ai-site-assistant-autopilot' ), 429 );
		}
		$file     = $request->get_file_params()['audio'] ?? array();
		$duration = absint( $request->get_param( 'duration_ms' ) );
		$language = SiteLanguage::primary( (string) $request->get_param( 'language' ) ?: SiteLanguage::site() );
		try {
			$result = ( new VoiceTranscriber() )->transcribe( is_array( $file ) ? $file : array(), $duration, $language, 'public_voice_transcription' );
			return new \WP_REST_Response( array( 'ok' => true ) + $result );
		} catch ( ProviderException $e ) {
			return $this->error( $e->error_code(), $e->user_message(), 400 );
		} catch ( \Throwable $e ) {
			return $this->error( 'voice_unavailable', __( 'Voice input is temporarily unavailable. Please try again later.', 'ai-site-assistant-autopilot' ), 503 );
		}
	}

	private function allow( string $kind, int $limit ): bool {
		return RateLimiter::allow( 'public_' . $kind, $limit, HOUR_IN_SECONDS );
	}

	private function is_enabled(): bool {
		return Settings::answer_desk_enabled();
	}

	/** The same flag that decides whether the widget renders a mic at all. */
	private function is_voice_enabled(): bool {
		$widget = (array) ( Settings::all()['widget'] ?? array() );
		return ! empty( $widget['voice_enabled'] );
	}
	private function error( string $code, string $message, int $status ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}
}
