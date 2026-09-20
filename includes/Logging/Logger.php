<?php
declare(strict_types=1);

namespace AISAAutoPilot\Logging;

use AISAAutoPilot\Security\Redactor;

/**
 * Lightweight, redacted, append-style local log kept as a bounded ring buffer
 * in an option. Enough for MVP support/debugging without a DB table.
 */
final class Logger {

	private const OPTION = 'aisaautopilot_logs';
	private const MAX    = 200;

	/** Seconds a writer waits for a concurrent append before writing anyway. */
	private const LOCK_WAIT = 3;

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

		// Concurrent requests used to overwrite each other's entries: each read the
		// buffer, appended and wrote the whole array back. The read-modify-write now
		// runs under a database lock and reads the row itself, because this request's
		// cached copy goes stale as soon as another request appends.
		$lock = $this->acquire_lock();
		try {
			$logs   = $this->read_stored();
			$logs[] = $entry;
			if ( count( $logs ) > self::MAX ) {
				$logs = array_slice( $logs, -self::MAX );
			}

			update_option( self::OPTION, $logs, false );
		} finally {
			$this->release_lock( $lock );
		}
	}

	/** @return array<int|string, mixed> */
	private function read_stored(): array {
		global $wpdb;

		if ( $wpdb instanceof \wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the cached copy is what goes stale between concurrent writers.
			$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION ) );
			$logs = null === $raw ? array() : maybe_unserialize( $raw );
		} else {
			$logs = get_option( self::OPTION, array() );
		}

		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Named lock scoped to this database and table prefix, since MySQL lock names
	 * are server-wide. Returns null when the lock was not taken: a timeout, or a
	 * database without named locks. The entry is still written in that case.
	 */
	private function acquire_lock(): ?string {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return null;
		}

		$name     = 'aisaautopilot_logs_' . md5( ( defined( 'DB_NAME' ) ? (string) DB_NAME : '' ) . '|' . $wpdb->options );
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::LOCK_WAIT ) );
		$wpdb->suppress_errors( $suppress );

		return '1' === (string) $acquired ? $name : null;
	}

	private function release_lock( ?string $name ): void {
		global $wpdb;

		if ( null === $name || ! $wpdb instanceof \wpdb ) {
			return;
		}

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		$wpdb->suppress_errors( $suppress );
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
