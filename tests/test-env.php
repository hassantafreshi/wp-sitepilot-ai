<?php
/**
 * Not a test. Where the local WordPress tests find WordPress and its address, so the same test runs on the owner's
 * XAMPP machine and on any other box. PHP twin of tests/test-env.cjs (same order, same variables).
 *
 *   WordPress  AISAAUTOPILOT_WP_ROOT, else the local test copy (AISAAUTOPILOT_LOCAL_SITE: the environment's, else
 *              ../aisa-wp-test/site next to this repository) when it exists,
 *              else the owner's C:\xampp\htdocs\wp.
 *   Address    AISAAUTOPILOT_BASE_URL, else http://127.0.0.1:8090 for the local copy, http://127.0.0.1/wp on XAMPP.
 *
 * These tests write to that site and restore what they change: run them one at a time.
 */

if ( ! defined( 'AISAAUTOPILOT_LOCAL_SITE' ) ) {
	// The environment's AISAAUTOPILOT_LOCAL_SITE, else ../aisa-wp-test/site next to this repository.
	$aisa_local_site = getenv( 'AISAAUTOPILOT_LOCAL_SITE' );
	define( 'AISAAUTOPILOT_LOCAL_SITE', is_string( $aisa_local_site ) && '' !== $aisa_local_site ? rtrim( $aisa_local_site, '/\\' ) : dirname( __DIR__, 2 ) . '/aisa-wp-test/site' );
	unset( $aisa_local_site );
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

	/**
	 * After wp-load.php: a test of a part that needs MySQL ends with SKIP on a site that runs on SQLite (the SQLite
	 * Database Integration drop-in): the knowledge index compares its VARBINARY terms byte for byte, which the drop-in
	 * stores as BLOB in a STRICT table where a text never equals a blob; the log and option locks use GET_LOCK().
	 */
	function aisa_test_require_mysql( string $why ): void {
		if ( ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ( defined( 'DATABASE_TYPE' ) && 'sqlite' === DATABASE_TYPE ) ) {
			echo "SKIP needs MySQL ({$why}); this site runs on SQLite. Run it on a MySQL site, e.g. the owner's XAMPP.\n";
			echo "0 checks, 0 failed\n";
			exit( 0 );
		}
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
