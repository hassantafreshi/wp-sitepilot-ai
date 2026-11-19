<?php
/**
 * Fixture for tests/dependency_browser_matrix.cjs. `prepare` remembers whether WooCommerce
 * and Easy Form Builder are active and deactivates both (nothing is deleted); `restore`
 * puts back exactly what was active before. Both plugins must be installed.
 * Usage: php local_dependency_browser_fixture.php prepare|restore
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$state_option = 'aisaautopilot_local_dependency_fixture';
$plugins      = array( 'woocommerce/woocommerce.php', 'easy-form-builder/emsfb.php' );
$mode         = (string) ( $argv[1] ?? '' );

if ( 'prepare' === $mode ) {
	wp_clean_plugins_cache( true );
	$installed = get_plugins();
	foreach ( $plugins as $file ) {
		if ( ! isset( $installed[ $file ] ) ) {
			fwrite( STDERR, "Refusing: {$file} is not installed.\n" );
			exit( 1 );
		}
	}
	if ( ! is_array( get_option( $state_option ) ) ) {
		update_option( $state_option, array( 'active' => array_values( array_filter( $plugins, 'is_plugin_active' ) ) ), false );
	}
	deactivate_plugins( $plugins, true );
	echo "Dependency fixture ready: WooCommerce and Easy Form Builder are inactive.\n";
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$state = get_option( $state_option );
	if ( is_array( $state ) ) {
		deactivate_plugins( $plugins, true );
		foreach ( (array) $state['active'] as $file ) {
			activate_plugin( (string) $file, '', false, true );
		}
		delete_option( $state_option );
	}
	echo "Dependency fixture restored.\n";
	exit( 0 );
}

fwrite( STDERR, "Usage: php local_dependency_browser_fixture.php prepare|restore\n" );
exit( 64 );
