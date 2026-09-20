<?php
declare(strict_types=1);

namespace AISAAutoPilot\Voice;

/*
 * Exception messages here are never echoed: they travel back through the REST
 * layer as JSON, or are rendered by a caller with esc_html(). Escaping them at
 * the throw site would put HTML entities into the JSON payload, so the sniff is
 * disabled for this file rather than silenced line by line.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Usage\VoiceUsageTracker;

/** Short-lived, OpenAI/Whisper-compatible audio transcription. */
final class VoiceTranscriber {
	public const MAX_BYTES       = 5_000_000;
	public const MAX_DURATION_MS = 45_000;

	/**
	 * The containers a browser MediaRecorder produces, keyed by the bytes that
	 * open them: Chrome, Edge and Firefox record WebM (older Firefox, Ogg) and
	 * Safari records MP4; WAV covers recorders that emit PCM. Anything else is
	 * refused before it reaches a provider, whatever Content-Type the client sent.
	 *
	 * @var array<string,array{int,string}> mime => [offset, signature]
	 */
	private const CONTAINERS = array(
		'audio/webm' => array( 0, "\x1A\x45\xDF\xA3" ),
		'audio/ogg'  => array( 0, 'OggS' ),
		'audio/mp4'  => array( 4, 'ftyp' ),
		'audio/wav'  => array( 0, 'RIFF' ), // "WAVE" must follow at offset 8.
	);

	/** @param array<string,mixed> $file */
	public static function validate_file( array $file, int $duration_ms ): ?string {
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_readable( (string) $file['tmp_name'] ) ) {
			return 'invalid_audio';
		}
		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return 'audio_too_large';
		}
		if ( $duration_ms <= 0 || $duration_ms > self::MAX_DURATION_MS ) {
			return 'audio_too_long';
		}
		if ( null === self::detect_mime( (string) $file['tmp_name'] ) ) {
			return 'invalid_audio';
		}
		return null;
	}

	/** The container the first bytes of the upload declare, or null when it is none of the four. */
	public static function detect_mime( string $path ): ?string {
		$head = is_readable( $path ) ? file_get_contents( $path, false, null, 0, 12 ) : false; // Local PHP upload; only its header is read.
		if ( ! is_string( $head ) || strlen( $head ) < 12 ) {
			return null;
		}
		foreach ( self::CONTAINERS as $mime => $signature ) {
			if ( substr( $head, $signature[0], strlen( $signature[1] ) ) !== $signature[1] ) {
				continue;
			}
			if ( 'audio/wav' === $mime && 'WAVE' !== substr( $head, 8, 4 ) ) {
				return null;
			}
			return $mime;
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $file
	 * @return array{transcript:string,language:string}
	 */
	public function transcribe( array $file, int $duration_ms, string $language, string $scope ): array {
		$error = self::validate_file( $file, $duration_ms );
		if ( null !== $error ) {
			throw new ProviderException( $error, __( 'The audio recording is invalid, too large, or too long.', 'ai-site-assistant-autopilot' ) );
		}
		$key  = WpConnectors::get_effective_api_key();
		$base = rtrim( (string) Settings::get( 'base_url', '' ), '/' );
		if ( '' === $key || '' === $base || ! $this->is_safe_endpoint( $base . '/audio/transcriptions' ) ) {
			throw new ProviderException( 'provider_not_configured', __( 'Configure an AI provider before using voice input.', 'ai-site-assistant-autopilot' ) );
		}

		$boundary = '----aisaautopilot' . wp_generate_password( 18, false, false );
		// The name and type the provider sees come from the bytes validate_file()
		// accepted, never from the client's filename or Content-Type.
		$mime     = (string) self::detect_mime( (string) $file['tmp_name'] );
		$filename = 'voice.' . substr( $mime, strlen( 'audio/' ) );
		$contents = file_get_contents( (string) $file['tmp_name'] ); // Temporary PHP upload only; never persisted.
		if ( false === $contents ) {
			throw new ProviderException( 'audio_read_failed', __( 'The recording could not be read.', 'ai-site-assistant-autopilot' ) );
		}
		$model = (string) Settings::get( 'voice_model', 'whisper-1' );
		// Do not pin Whisper to the site's locale: it must auto-detect a visitor
		// who speaks another language. The locale remains the UI/default fallback.
		$body = $this->multipart( $boundary, $model, $filename, $mime, $contents );
		unset( $contents );
		$response = wp_remote_post(
			$base . '/audio/transcriptions',
			array(
				'timeout'            => 60,
				'headers'            => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'               => $body,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'stt_unreachable', __( 'Could not reach the speech-to-text provider.', 'ai-site-assistant-autopilot' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = is_array( $data ) ? trim( (string) ( $data['text'] ?? '' ) ) : '';
		if ( $code < 200 || $code >= 300 || '' === $text ) {
			throw new ProviderException( 'stt_failed', __( 'The speech-to-text provider could not transcribe this recording. Check that your provider supports /audio/transcriptions.', 'ai-site-assistant-autopilot' ) );
		}
		( new VoiceUsageTracker() )->record( $duration_ms / 1000, $scope, $model );
		return array(
			'transcript' => mb_substr( $text, 0, 2000 ),
			'language'   => $language,
		);
	}

	private function multipart( string $boundary, string $model, string $filename, string $mime, string $contents ): string {
		$eol   = "\r\n";
		$body  = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="model"' . $eol . $eol . $model . $eol;
		$body .= '--' . $boundary . $eol . 'Content-Disposition: form-data; name="response_format"' . $eol . $eol . 'json' . $eol;
		$body .= '--' . $boundary . $eol . 'Content-Disposition: form-data; name="file"; filename="' . addcslashes( $filename, '\\"' ) . '"' . $eol;
		$body .= 'Content-Type: ' . $mime . $eol . $eol . $contents . $eol . '--' . $boundary . '--' . $eol;
		return $body;
	}

	private function is_safe_endpoint( string $endpoint ): bool {
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		if ( 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return true;
		}

		return (bool) apply_filters( 'aisaautopilot_allow_insecure_provider_url', false, $endpoint );
	}
}
