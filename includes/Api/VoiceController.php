<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Voice\VoiceTranscriber;

/**
 * Authenticated voice endpoints for WordPress administration screens.
 */
final class VoiceController {
	/**
	 * Transcribe a multipart recording from the protected form-builder screen.
	 */
	public function transcribe_efb( \WP_REST_Request $request ): \WP_REST_Response {

		return $this->transcribe_admin_upload( $request, 'admin_voice_transcription' );
	}

	/** Transcribe a protected recording for the independent AISAAutoPilot form builder. */
	public function transcribe_form_builder( \WP_REST_Request $request ): \WP_REST_Response {

		return $this->transcribe_admin_upload( $request, 'form_builder_voice_transcription' );
	}

	/** Transcribe a protected voice command from the administrator chat. */
	public function transcribe_chat( \WP_REST_Request $request ): \WP_REST_Response {

		return $this->transcribe_admin_upload( $request, 'chat_voice_transcription' );
	}

	private function transcribe_admin_upload( \WP_REST_Request $request, string $scope ): \WP_REST_Response {
		if ( ! RateLimiter::allow( 'admin_voice', 30, 10 * MINUTE_IN_SECONDS ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'rate_limited',
					'message' => __( 'Too many voice requests. Please try again shortly.', 'ai-site-assistant-autopilot' ),
				),
				429
			);
		}

		$file     = $request->get_file_params()['audio'] ?? array();
		$duration = absint( $request->get_param( 'duration_ms' ) );
		$language = SiteLanguage::primary( (string) $request->get_param( 'language' ) ?: SiteLanguage::site() );

		try {
			$result = ( new VoiceTranscriber() )->transcribe(
				is_array( $file ) ? $file : array(),
				$duration,
				$language,
				$scope
			);
			return new \WP_REST_Response( array( 'ok' => true ) + $result );
		} catch ( ProviderException $e ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => $e->error_code(),
					'message' => $e->user_message(),
				),
				400
			);
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'voice_unavailable',
					'message' => __( 'Voice input is temporarily unavailable. Please try again later.', 'ai-site-assistant-autopilot' ),
				),
				503
			);
		}
	}
}
