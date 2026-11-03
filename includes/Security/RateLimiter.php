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
			// REMOTE_ADDR, or the visitor behind Cloudflare; never a client-written header. See ClientAddress.
			$identity = 'ip:' . ClientAddress::get();
		}

		return hash_hmac( 'sha256', $identity, wp_salt( 'auth' ) );
	}
}
