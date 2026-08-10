<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Core\SiteLanguage;
use SitePilot\Providers\ProviderException;
use SitePilot\Voice\VoiceService;
use SitePilot\Voice\VoiceTranscriber;

/**
 * Voice REST endpoints for the public widget and the authenticated EFB editor.
 */
final class VoiceController {

	/**
	 * Transcribe a base64-encoded public-widget recording.
	 */
	public function transcribe( \WP_REST_Request $request ): \WP_REST_Response {
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
			$text = ( new VoiceService() )->transcribe( $audio, $format );
			return $this->respond( array( 'text' => $text ), true );
		} catch ( \RuntimeException $e ) {
			return $this->respond( array(
				'message' => $e->getMessage(),
			), false, 400, 'stt_error' );
		}
	}

	/**
	 * Transcribe a multipart recording from the protected form-builder screen.
	 */
	public function transcribe_efb( \WP_REST_Request $request ): \WP_REST_Response {
		$file     = $request->get_file_params()['audio'] ?? array();
		$duration = absint( $request->get_param( 'duration_ms' ) );
		$language = SiteLanguage::primary( (string) $request->get_param( 'language' ) ?: SiteLanguage::site() );

		try {
			$result = ( new VoiceTranscriber() )->transcribe(
				is_array( $file ) ? $file : array(),
				$duration,
				$language,
				'admin_voice_transcription'
			);
			return new \WP_REST_Response( array( 'ok' => true ) + $result );
		} catch ( ProviderException $e ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'code'    => $e->error_code(),
				'message' => $e->user_message(),
			), 400 );
		}
	}

	/**
	 * Synthesize text to speech, or direct the widget to browser speech when
	 * no TTS provider is configured.
	 */
	public function speak( \WP_REST_Request $request ): \WP_REST_Response {
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
			$audio = ( new VoiceService() )->synthesize( $text, $voice );
			if ( null === $audio ) {
				return $this->respond( array(
					'message'     => __( 'Using browser TTS.', 'sitepilot-ai' ),
					'use_browser' => true,
				), true );
			}

			return new \WP_REST_Response( array(
				'ok'     => true,
				'audio'  => base64_encode( $audio ),
				'format' => 'mp3',
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
