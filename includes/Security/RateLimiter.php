<?php
declare(strict_types=1);

namespace SitePilot\Security;

/**
 * Simple IP-scoped request throttle for public, unauthenticated REST endpoints
 * that trigger paid or quota-limited work (LLM calls, Twilio SMS, Telegram
 * messages, Whisper transcription). Backed by WordPress transients, so it
 * needs no extra DB table and works on any host.
 *
 * This is a best-effort fixed-window counter, not a security boundary by
 * itself — it exists to keep a single anonymous caller from running up API
 * bills or exhausting quota, not to stop a distributed attacker. Pair it with
 * sane per-window limits for the endpoint it protects.
 */
final class RateLimiter {

	/**
	 * @param string $action         Unique key for the endpoint being throttled, e.g. "widget_ask".
	 * @param int    $max_requests   Max requests allowed within the window.
	 * @param int    $window_seconds Window length in seconds.
	 * @return bool True if the request is allowed, false if the caller is currently throttled.
	 */
	public static function allow( string $action, int $max_requests, int $window_seconds ): bool {
		$key   = self::transient_key( $action );
		$count = (int) get_transient( $key );

		if ( $count >= $max_requests ) {
			return false;
		}

		set_transient( $key, $count + 1, $window_seconds );

		return true;
	}

	private static function transient_key( string $action ): string {
		return 'sitepilot_rl_' . $action . '_' . self::client_hash();
	}

	private static function client_hash(): string {
		return substr( hash( 'sha256', self::client_ip() ), 0, 20 );
	}

	/**
	 * Best-effort client IP. Not authoritative behind proxies without proper
	 * trusted-header configuration — acceptable here since this is a throttle,
	 * not an access-control decision.
	 */
	private static function client_ip(): string {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $candidates as $server_key ) {
			if ( empty( $_SERVER[ $server_key ] ) ) {
				continue;
			}
			$raw = (string) wp_unslash( $_SERVER[ $server_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$ip  = trim( explode( ',', $raw )[0] );
			if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
