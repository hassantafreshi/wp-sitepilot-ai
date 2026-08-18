<?php
/**
 * Local XAMPP helper to activate, deactivate, or inspect WooCommerce while
 * executing AISAAutoPilot Phase 1 integration tests. It is not shipped in a
 * release archive.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'status';
if ( ! in_array( $mode, array( 'activate', 'deactivate', 'status' ), true ) ) {
	fwrite( STDERR, "Usage: php local_woocommerce_state.php activate|deactivate|status\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
$aisaautopilot_woo_plugin = 'woocommerce/woocommerce.php';

if ( ! is_readable( $wp_root . '/wp-content/plugins/' . $aisaautopilot_woo_plugin ) ) {
	fwrite( STDERR, "WooCommerce is not installed in this local WordPress environment.\n" );
	exit( 1 );
}

require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_clean_plugins_cache( true );

if ( 'activate' === $mode ) {
	$plugins = get_plugins();
	if ( ! isset( $plugins[ $aisaautopilot_woo_plugin ] ) ) {
		$header = get_plugin_data( $wp_root . '/wp-content/plugins/' . $aisaautopilot_woo_plugin, false, false );
		fwrite( STDERR, 'WordPress could not index the WooCommerce plugin header after cache refresh. Parsed name: ' . (string) ( $header['Name'] ?? '' ) . '; path: ' . $wp_root . '/wp-content/plugins/' . $aisaautopilot_woo_plugin . PHP_EOL );
		exit( 1 );
	}
	$result = activate_plugin( $aisaautopilot_woo_plugin, '', false, true );
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, $result->get_error_message() . PHP_EOL );
		exit( 1 );
	}
}

if ( 'deactivate' === $mode && is_plugin_active( $aisaautopilot_woo_plugin ) ) {
	deactivate_plugins( $aisaautopilot_woo_plugin, true );
}

echo 'WooCommerce: ' . ( is_plugin_active( $aisaautopilot_woo_plugin ) ? 'active' : 'inactive' ) . PHP_EOL;
