<?php
declare(strict_types=1);

namespace AISAAutoPilot\Security;

/**
 * The visitor's address for rate limits.
 *
 * REMOTE_ADDR is the only address a plugin can trust by default: X-Forwarded-For and its
 * kind are written by the client. Behind Cloudflare, though, REMOTE_ADDR is a Cloudflare
 * edge server shared by many visitors, so they would all count against one limit and the
 * assistant would stop answering everyone. Cloudflare's CF-Connecting-IP header is used
 * only when the request really comes from one of Cloudflare's published address ranges,
 * where a visitor cannot forge it. Sites behind another proxy can name the address
 * themselves with the `aisaautopilot_client_ip` filter.
 */
final class ClientAddress {

	/** Cloudflare's published ranges (https://www.cloudflare.com/ips/, checked 2026-11-03). */
	private const CLOUDFLARE = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/** A valid IP address, or 'unknown'. */
	public static function get(): string {
		$remote = self::valid( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an IP address by valid().
		$ip     = $remote;
		if ( '' !== $remote && self::in_ranges( $remote, self::CLOUDFLARE ) ) {
			$visitor = self::valid( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an IP address by valid().
			if ( '' !== $visitor ) {
				$ip = $visitor;
			}
		}

		/**
		 * Filters the visitor address used for rate limits, for a site behind a proxy other
		 * than Cloudflare. Return the visitor's address only when the proxy itself sets it.
		 *
		 * @param string $ip     The address the plugin will use.
		 * @param string $remote REMOTE_ADDR as received.
		 */
		$filtered = self::valid( (string) apply_filters( 'aisaautopilot_client_ip', $ip, $remote ) );

		return '' !== $filtered ? $filtered : ( '' !== $ip ? $ip : 'unknown' );
	}

	/**
	 * @param string[] $ranges CIDR blocks.
	 */
	public static function in_ranges( string $ip, array $ranges ): bool {
		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return false;
		}
		foreach ( $ranges as $range ) {
			list( $subnet, $bits ) = array_pad( explode( '/', $range, 2 ), 2, '' );
			$net                   = inet_pton( $subnet );
			if ( false === $net || strlen( $net ) !== strlen( $packed ) ) {
				continue;
			}
			$bits  = '' === $bits ? strlen( $net ) * 8 : (int) $bits;
			$bytes = intdiv( $bits, 8 );
			if ( 0 !== strncmp( $packed, $net, $bytes ) ) {
				continue;
			}
			$rest = $bits % 8;
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	private static function valid( string $ip ): string {
		$ip = trim( $ip );

		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
