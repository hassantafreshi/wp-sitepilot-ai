<?php
declare(strict_types=1);

namespace AISAAutoPilot\Security;

/**
 * Small, privacy-preserving fixed-window limiter for unauthenticated and
 * privileged REST actions. The stored transient contains only a keyed hash of
 * the caller identity; raw IP addresses are never persisted by this class.
 */
final class RateLimiter {

	private const PREFIX = 'aisaautopilot_rate_';

	public static function allow( string $scope, int $limit, int $window_seconds ): bool {

		if ( $limit < 1 || $window_seconds < 1 ) {
			return false;
		}

		$scope = sanitize_key( $scope );
		if ( '' === $scope ) {
			return false;
		}

		$key   = self::PREFIX . $scope . '_' . self::identity_hash( $scope );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window_seconds );

		return true;
	}

	private static function identity_hash( string $scope ): string {

		$user_id = get_current_user_id();
		if ( $user_id > 0 && str_starts_with( $scope, 'admin_' ) ) {
			$identity = 'user:' . $user_id;
		} else {
			/*
			 * Do not trust X-Forwarded-For, X-Real-IP, or CDN-specific headers.
			 * Those headers are client-controlled unless a site has explicitly
			 * configured and validated trusted proxies. REMOTE_ADDR is the safe
			 * default for a WordPress plugin.
			 */
			$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reduced to hex, colon and dot on the next line, then HMAC'd.
			$identity = 'ip:' . substr( preg_replace( '/[^0-9a-fA-F:.]/', '', $ip ) ?? '', 0, 64 );
		}

		return hash_hmac( 'sha256', $identity, wp_salt( 'auth' ) );
	}
}
