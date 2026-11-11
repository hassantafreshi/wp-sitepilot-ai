<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

/**
 * Gives every plugin script and stylesheet a version that changes when the file does.
 *
 * WordPress appends `?ver=<plugin version>` to an asset address, and a CDN in front of the site may keep
 * that address for days. A hotfix or a staging build that keeps the version number would then be served
 * the old file. The version here is the plugin version plus a short stamp of the file itself, so a changed
 * file always has a new address and an unchanged one keeps its cached copy.
 */
final class AssetVersion {

	public static function register(): void {
		add_filter( 'script_loader_src', array( self::class, 'filter' ), 20 );
		add_filter( 'style_loader_src', array( self::class, 'filter' ), 20 );
	}

	/**
	 * @param mixed $src The address WordPress is about to print.
	 * @return mixed
	 */
	public static function filter( $src ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}

		return self::versioned( $src, AISAAUTOPILOT_URL, AISAAUTOPILOT_DIR, AISAAUTOPILOT_VERSION );
	}

	/**
	 * Pure part, so it can be tested without WordPress.
	 *
	 * @param string $src         Asset address, with or without a query string.
	 * @param string $plugin_url  The plugin's base address (with a trailing slash).
	 * @param string $plugin_dir  The plugin's folder (with a trailing slash).
	 * @param string $version     The plugin version.
	 */
	public static function versioned( string $src, string $plugin_url, string $plugin_dir, string $version ): string {
		$strip = static fn ( string $url ): string => (string) preg_replace( '#^https?:#i', '', $url );
		$base  = $strip( $plugin_url );
		$bare  = $strip( $src );
		if ( '' === $base || ! str_starts_with( $bare, $base ) ) {
			return $src;
		}

		$relative = (string) strtok( substr( $bare, strlen( $base ) ), '?#' );
		if ( '' === $relative || str_contains( $relative, '..' ) ) {
			return $src;
		}
		$file = $plugin_dir . $relative;
		if ( ! is_file( $file ) ) {
			return $src;
		}

		$stamp = substr( md5( (string) filesize( $file ) . ':' . (string) filemtime( $file ) ), 0, 8 );
		$value = rawurlencode( $version . '-' . $stamp );
		if ( preg_match( '/([?&])ver=[^&#]*/', $src ) ) {
			return (string) preg_replace( '/([?&])ver=[^&#]*/', '${1}ver=' . $value, $src, 1 );
		}

		return $src . ( str_contains( $src, '?' ) ? '&' : '?' ) . 'ver=' . $value;
	}
}
