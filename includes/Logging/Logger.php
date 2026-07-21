<?php
declare(strict_types=1);

namespace SitePilot\Logging;

use SitePilot\Security\Redactor;

/**
 * Lightweight, redacted, append-style local log kept as a bounded ring buffer
 * in an option. Enough for MVP support/debugging without a DB table.
 */
final class Logger {

	private const OPTION = 'sitepilot_ai_logs';
	private const MAX    = 200;

	/**
	 * @param array<string, mixed> $context
	 */
	public function log( string $level, string $event, string $message, array $context = array() ): void {
		$entry = array(
			'time'    => gmdate( 'c' ),
			'level'   => $level,
			'event'   => $event,
			'message' => Redactor::text( $message ),
			'user_id' => get_current_user_id(),
			'context' => Redactor::array( $context ),
		);

		$logs = get_option( self::OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = $entry;
		if ( count( $logs ) > self::MAX ) {
			$logs = array_slice( $logs, -self::MAX );
		}

		update_option( self::OPTION, $logs, false );
	}

	public function info( string $event, string $message, array $context = array() ): void {
		$this->log( 'info', $event, $message, $context );
	}

	public function error( string $event, string $message, array $context = array() ): void {
		$this->log( 'error', $event, $message, $context );
	}

	/**
	 * @return array<int, array<string, mixed>> Newest first.
	 */
	public function recent( int $limit = 100 ): array {
		$logs = get_option( self::OPTION, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}

		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}
}
