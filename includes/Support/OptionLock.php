<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

/**
 * Read-modify-write of one array option under a named database lock.
 *
 * Visitor requests can write the same option at the same time (an unanswered question, a saved answer
 * being used). Without a lock each request reads the array, changes it and writes the whole array back,
 * so one change overwrites the other. The same approach protects the activity log (see Logger).
 */
final class OptionLock {

	private const WAIT = 3;

	/**
	 * @param callable(array<string,mixed>):array<string,mixed> $mutate Receives the stored array and returns the new one.
	 * @return array<string,mixed> The array that was written.
	 */
	public static function mutate( string $option, callable $mutate ): array {
		$lock = self::acquire( $option );
		try {
			$value = $mutate( self::read( $option ) );
			// The request's cached copy may be stale; drop it so update_option compares against the row just read.
			wp_cache_delete( $option, 'options' );
			update_option( $option, $value, false );
			return $value;
		} finally {
			self::release( $lock );
		}
	}

	/**
	 * The stored array read straight from the database, bypassing a cached copy that another request made stale.
	 *
	 * @return array<string,mixed>
	 */
	public static function read( string $option ): array {
		global $wpdb;
		if ( $wpdb instanceof \wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the cached copy is what goes stale between concurrent writers.
			$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) );
			$value = null === $raw ? array() : maybe_unserialize( $raw );
		} else {
			$value = get_option( $option, array() );
		}
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Named lock scoped to this database and table prefix, since MySQL lock names are server-wide. Returns null
	 * when the lock was not taken (a timeout, or a database without named locks); the write still happens then.
	 */
	private static function acquire( string $option ): ?string {
		global $wpdb;
		if ( ! $wpdb instanceof \wpdb ) {
			return null;
		}
		$name     = 'aisaautopilot_opt_' . md5( ( defined( 'DB_NAME' ) ? (string) DB_NAME : '' ) . '|' . $wpdb->options . '|' . $option );
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::WAIT ) );
		$wpdb->suppress_errors( $suppress );
		return '1' === (string) $acquired ? $name : null;
	}

	private static function release( ?string $name ): void {
		global $wpdb;
		if ( null === $name || ! $wpdb instanceof \wpdb ) {
			return;
		}
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		$wpdb->suppress_errors( $suppress );
	}
}
