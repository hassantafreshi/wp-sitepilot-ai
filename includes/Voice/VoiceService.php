<?php
declare(strict_types=1);

namespace SitePilot\Voice;

use SitePilot\Core\Settings;

/**
 * Voice Service — STT (Speech-to-Text) + TTS (Text-to-Speech).
 *
 * STT: via Groq Whisper API (or any OpenAI-compatible audio transcription).
 * TTS: via OpenAI TTS API when configured; falls back to browser native.
 *
 * Usage:
 *   $voice = new VoiceService();
 *   $text  = $voice->transcribe($audio_base64, 'webm');   // STT
 *   $mp3   = $voice->synthesize('Hello world', 'alloy');  // TTS (null = use browser)
 */
final class VoiceService {

	private const GROQ_WHISPER = 'https://api.groq.com/openai/v1/audio/transcriptions';
	private const OPENAI_TTS    = 'https://api.openai.com/v1/audio/speech';
	private const MAX_AUDIO_SIZE = 25 * 1024 * 1024; // 25 MB

	/**
	 * Transcribe audio bytes to text via Groq Whisper (OpenAI-compatible).
	 *
	 * @param string $audio_base64 Base64-encoded audio data.
	 * @param string $format       MIME / file extension (webm, mp3, ogg, wav).
	 * @return string Transcribed text.
	 * @throws \RuntimeException On failure.
	 */
	public function transcribe( string $audio_base64, string $format = 'webm' ): string {
		$api_key = $this->resolve_stt_key();
		if ( '' === $api_key ) {
			throw new \RuntimeException( __( 'AI provider is not configured for speech recognition.', 'sitepilot-ai' ) );
		}

		$audio_binary = base64_decode( $audio_base64, true );
		if ( false === $audio_binary || strlen( $audio_binary ) > self::MAX_AUDIO_SIZE ) {
			throw new \RuntimeException( __( 'Invalid or oversized audio data.', 'sitepilot-ai' ) );
		}

		$boundary = '----SP' . uniqid();
		$filename = 'audio.' . sanitize_file_name( $format );

		$body  = "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
		$body .= "Content-Type: audio/{$format}\r\n\r\n";
		$body .= $audio_binary . "\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
		$body .= "whisper-large-v3-turbo\r\n";
		$body .= "--{$boundary}--\r\n";

		$response = wp_remote_post( self::GROQ_WHISPER, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			),
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( __( 'Speech recognition service unreachable.', 'sitepilot-ai' ) );
		}

		$code  = (int) wp_remote_retrieve_response_code( $response );
		$raw   = (string) wp_remote_retrieve_body( $response );
		$data  = json_decode( $raw, true );

		if ( 401 === $code ) {
			throw new \RuntimeException( __( 'STT API key rejected. Check your provider key in Settings.', 'sitepilot-ai' ) );
		}
		if ( $code < 200 || $code >= 300 || ! isset( $data['text'] ) ) {
			throw new \RuntimeException( sprintf(
				/* translators: %d: HTTP response code. */
				__( 'Speech recognition failed (HTTP %d).', 'sitepilot-ai' ),
				$code
			) );
		}

		return sanitize_text_field( $data['text'] );
	}

	/**
	 * Synthesize text to speech audio bytes (MP3).
	 * Returns null if no TTS-capable provider is configured — caller should
	 * fall back to browser-native SpeechSynthesis.
	 *
	 * @param string $text  Text to speak (max 4096 chars).
	 * @param string $voice Voice name: alloy, echo, fable, onyx, nova, shimmer.
	 * @return string|null Raw MP3 bytes, or null if TTS is unavailable.
	 */
	public function synthesize( string $text, string $voice = 'alloy' ): ?string {
		$api_key = Settings::get_api_key();
		if ( '' === $api_key ) {
			return null; // No configured key → use browser SpeechSynthesis.
		}

		$text = mb_substr( $text, 0, 4000 );

		$response = wp_remote_post( self::OPENAI_TTS, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model' => 'tts-1',
				'input' => $text,
				'voice' => $voice,
			) ),
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null; // Silent fallback to browser TTS.
		}

		$audio = wp_remote_retrieve_body( $response );

		return is_string( $audio ) && strlen( $audio ) > 100 ? $audio : null;
	}

	/**
	 * Resolve the API key suitable for STT.
	 * Uses the provider key from settings (same as LLM calls).
	 */
	private function resolve_stt_key(): string {
		return Settings::get_api_key();
	}
}
