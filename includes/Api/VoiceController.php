<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Security\RateLimiter;
use SitePilot\Voice\VoiceService;

/**
 * REST controller for STT (speech-to-text) and TTS (text-to-speech).
 *
 * POST /voice/transcribe — Audio bytes → transcribed text.
 * POST /voice/speak     — Text → synthesized audio (or null → use browser).
 */
final class VoiceController {

	/**
	 * Transcribe audio to text via Groq Whisper API.
	 */
	public function transcribe( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! RateLimiter::allow( 'voice_transcribe', 10, 600 ) ) {
			return $this->respond( array(
				'message' => __( 'Too many requests — please wait a moment and try again.', 'sitepilot-ai' ),
			), false, 429, 'rate_limited' );
		}

		$audio  = (string) $request->get_param( 'audio' );
		$format = (string) $request->get_param( 'format' );
		if ( '' === $format ) {
			$format = 'webm';
		}

		if ( '' === $audio ) {
			return $this->respond( array(
				'message' => __( 'No audio data received.', 'sitepilot-ai' ),
			), false, 400, 'no_audio' );
		}

		try {
			$voice = new VoiceService();
			$text  = $voice->transcribe( $audio, $format );

			return $this->respond( array( 'text' => $text ), true );
		} catch ( \RuntimeException $e ) {
			return $this->respond( array(
				'message' => $e->getMessage(),
			), false, 400, 'stt_error' );
		}
	}

	/**
	 * Synthesize text → speech audio (MP3). Returns null if no TTS provider
	 * is configured — the frontend should fall back to browser SpeechSynthesis.
	 */
	public function synthesize( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! RateLimiter::allow( 'voice_synthesize', 10, 600 ) ) {
			return $this->respond( array(
				'message' => __( 'Too many requests — please wait a moment and try again.', 'sitepilot-ai' ),
			), false, 429, 'rate_limited' );
		}

		$text  = (string) $request->get_param( 'text' );
		$voice = (string) $request->get_param( 'voice' );
		if ( '' === $voice ) {
			$voice = 'alloy';
		}

		if ( '' === trim( $text ) ) {
			return $this->respond( array(
				'message' => __( 'No text to synthesize.', 'sitepilot-ai' ),
			), false, 400, 'no_text' );
		}

		try {
			$svc   = new VoiceService();
			$audio = $svc->synthesize( $text, $voice );

			if ( null === $audio ) {
				// No TTS provider; tell frontend to use browser SpeechSynthesis.
				return $this->respond( array(
					'message' => __( 'Using browser TTS.', 'sitepilot-ai' ),
					'use_browser' => true,
				), true );
			}

			return new \WP_REST_Response( array(
				'ok'      => true,
				'audio'   => base64_encode( $audio ),
				'format'  => 'mp3',
			), 200, array( 'Content-Type' => 'application/json' ) );
		} catch ( \RuntimeException $e ) {
			return $this->respond( array(
				'message' => $e->getMessage(),
			), false, 400, 'tts_error' );
		}
	}

	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): \WP_REST_Response {
		return new \WP_REST_Response( array_merge( array( 'ok' => $ok, 'code' => $code ), $data ), $status );
	}
}
