<?php
declare(strict_types=1);

namespace SitePilot\Usage;

/** Records STT duration separately; audio and transcripts are never stored. */
final class VoiceUsageTracker {
	private const OPTION  = 'sitepilot_ai_voice_usage_log';
	private const MAX_LOG = 500;

	public function record( float $seconds, string $scope, string $model ): void {
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'time'    => gmdate( 'c' ),
			'seconds' => round( max( 0.0, $seconds ), 2 ),
			'scope'   => sanitize_key( $scope ),
			'model'   => sanitize_text_field( $model ),
			'user_id' => get_current_user_id(),
		);
		if ( count( $log ) > self::MAX_LOG ) {
			$log = array_slice( $log, -self::MAX_LOG );
		}
		update_option( self::OPTION, $log, false );
	}
}
