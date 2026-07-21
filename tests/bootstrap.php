<?php
/**
 * Unit-test bootstrap. Loads the plugin's pure-logic classes and shims the very
 * few WordPress functions they touch, so these tests run without a WordPress
 * install. (Classes that need real WordPress are covered by integration tests.)
 *
 * @package SitePilot\AI
 */

declare(strict_types=1);

define( 'SITEPILOT_AI_VERSION', 'test' );

// Minimal WordPress function shims used by the classes under test.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

// PSR-4 autoloader for the plugin source.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'SitePilot\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/../includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
