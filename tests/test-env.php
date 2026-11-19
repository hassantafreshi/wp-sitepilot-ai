<?php
/**
 * Not a test. Where the local WordPress tests find WordPress and its address, so the same test runs on the owner's
 * XAMPP machine and on any other box. PHP twin of tests/test-env.cjs (same order, same variables).
 *
 *   WordPress  AISAAUTOPILOT_WP_ROOT, else the local test copy (AISAAUTOPILOT_LOCAL_SITE below) when it exists,
 *              else the owner's C:\xampp\htdocs\wp.
 *   Address    AISAAUTOPILOT_BASE_URL, else http://127.0.0.1:8090 for the local copy, http://127.0.0.1/wp on XAMPP.
 *
 * These tests write to that site and restore what they change: run them one at a time.
 */

if ( ! defined( 'AISAAUTOPILOT_LOCAL_SITE' ) ) {
	define( 'AISAAUTOPILOT_LOCAL_SITE', '/tmp/claude-0/-home-user/6470513e-3182-5834-893e-38f1388be77b/scratchpad/wpsite-sp/site' );
}

if ( ! function_exists( 'aisa_test_wp_root' ) ) {
	/** The WordPress folder (the one that holds wp-load.php). */
	function aisa_test_wp_root(): string {
		$env = getenv( 'AISAAUTOPILOT_WP_ROOT' );
		if ( is_string( $env ) && '' !== $env ) {
			return rtrim( $env, '/\\' );
		}
		if ( is_file( AISAAUTOPILOT_LOCAL_SITE . '/wp-load.php' ) ) {
			return AISAAUTOPILOT_LOCAL_SITE;
		}
		return 'C:\\xampp\\htdocs\\wp';
	}

	/** The site's address without a trailing slash. */
	function aisa_test_base_url(): string {
		$env = getenv( 'AISAAUTOPILOT_BASE_URL' );
		if ( is_string( $env ) && '' !== $env ) {
			return rtrim( $env, '/' );
		}
		return AISAAUTOPILOT_LOCAL_SITE === aisa_test_wp_root() ? 'http://127.0.0.1:8090' : 'http://127.0.0.1/wp';
	}
}
