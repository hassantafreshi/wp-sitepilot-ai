<?php
declare(strict_types=1);

namespace SitePilot\Voice;

use SitePilot\Core\Settings;
use SitePilot\Core\WpConnectors;
use SitePilot\Providers\ProviderException;
use SitePilot\Usage\VoiceUsageTracker;

/** Short-lived, OpenAI/Whisper-compatible audio transcription. */
final class VoiceTranscriber {
	public const MAX_BYTES = 5_000_000;
	public const MAX_DURATION_MS = 45_000;

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
		return null;
	}

	/**
	 * @param array<string,mixed> $file
	 * @return array{transcript:string,language:string}
	 */
	public function transcribe( array $file, int $duration_ms, string $language, string $scope ): array {
		$error = self::validate_file( $file, $duration_ms );
		if ( null !== $error ) {
			throw new ProviderException( $error, __( 'The audio recording is invalid, too large, or too long.', 'sitepilot-ai' ) );
		}
		$key = WpConnectors::get_effective_api_key();
		$base = rtrim( (string) Settings::get( 'base_url', '' ), '/' );
		if ( '' === $key || '' === $base ) {
			throw new ProviderException( 'provider_not_configured', __( 'Configure an AI provider before using voice input.', 'sitepilot-ai' ) );
		}

		$boundary = '----sitepilot' . wp_generate_password( 18, false, false );
		$filename = sanitize_file_name( (string) ( $file['name'] ?? 'voice.webm' ) );
		$contents = file_get_contents( (string) $file['tmp_name'] ); // Temporary PHP upload only; never persisted.
		if ( false === $contents ) {
			throw new ProviderException( 'audio_read_failed', __( 'The recording could not be read.', 'sitepilot-ai' ) );
		}
		$mime = sanitize_mime_type( (string) ( $file['type'] ?? 'audio/webm' ) );
		if ( '' === $mime || ! str_starts_with( $mime, 'audio/' ) ) {
			$mime = 'audio/webm';
		}
		$model = (string) Settings::get( 'voice_model', 'whisper-1' );
		// Do not pin Whisper to the site's locale: it must auto-detect a visitor
		// who speaks another language. The locale remains the UI/default fallback.
		$body = $this->multipart( $boundary, $model, $filename, $mime, $contents );
		unset( $contents );
		$response = wp_remote_post(
			$base . '/audio/transcriptions',
			array(
				'timeout' => 60,
				'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body' => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'stt_unreachable', __( 'Could not reach the speech-to-text provider.', 'sitepilot-ai' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = is_array( $data ) ? trim( (string) ( $data['text'] ?? '' ) ) : '';
		if ( $code < 200 || $code >= 300 || '' === $text ) {
			throw new ProviderException( 'stt_failed', __( 'The speech-to-text provider could not transcribe this recording. Check that your provider supports /audio/transcriptions.', 'sitepilot-ai' ) );
		}
		( new VoiceUsageTracker() )->record( $duration_ms / 1000, $scope, $model );
		return array( 'transcript' => mb_substr( $text, 0, 2000 ), 'language' => $language );
	}

	private function multipart( string $boundary, string $model, string $filename, string $mime, string $contents ): string {
		$eol = "\r\n";
		$body = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="model"' . $eol . $eol . $model . $eol;
		$body .= '--' . $boundary . $eol . 'Content-Disposition: form-data; name="response_format"' . $eol . $eol . 'json' . $eol;
		$body .= '--' . $boundary . $eol . 'Content-Disposition: form-data; name="file"; filename="' . addcslashes( $filename, "\\\"") . '"' . $eol;
		$body .= 'Content-Type: ' . $mime . $eol . $eol . $contents . $eol . '--' . $boundary . '--' . $eol;
		return $body;
	}
}
