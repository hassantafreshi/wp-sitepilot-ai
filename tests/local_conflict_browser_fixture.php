<?php
/**
 * Fixture for tests/conflict_browser_matrix.cjs (T31). `prepare` writes two stand-in plugins
 * under the WordPress.org folder names `social-chat-buttons` and `ai-engine` (header only, no
 * code), marks them active through a temporary must-use plugin (the real active_plugins
 * option is not changed) and turns the Answer Desk chat button on; `restore` removes all of
 * it. Refuses to run if either folder already exists, so a real plugin is never touched.
 * Usage: php local_conflict_browser_fixture.php prepare|restore
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

$state_option = 'aisaautopilot_local_conflict_fixture';
$mu_file      = WPMU_PLUGIN_DIR . '/aisaautopilot-qa-conflict.php';
$stand_ins    = array(
	'social-chat-buttons/social-chat-buttons.php' => 'Social Chat Buttons',
	'ai-engine/ai-engine.php'                     => 'AI Engine',
);
$mode = (string) ( $argv[1] ?? '' );

if ( 'prepare' === $mode ) {
	$state = get_option( $state_option );
	if ( ! is_array( $state ) ) {
		foreach ( array_keys( $stand_ins ) as $file ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . dirname( $file ) ) ) {
				fwrite( STDERR, 'Refusing: ' . dirname( $file ) . " already exists in the plugins folder.\n" );
				exit( 1 );
			}
		}
		$state = array( 'settings' => get_option( Settings::OPTION ) );
		foreach ( $stand_ins as $file => $name ) {
			wp_mkdir_p( WP_PLUGIN_DIR . '/' . dirname( $file ) );
			file_put_contents( WP_PLUGIN_DIR . '/' . $file, "<?php\n/**\n * Plugin Name: {$name}\n * Description: QA stand-in written by tests/local_conflict_browser_fixture.php. No code.\n * Version: 0.0.0\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local test fixture.
		}
		update_option( $state_option, $state, false );
	}
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		wp_mkdir_p( WPMU_PLUGIN_DIR );
	}
	$list = var_export( array_keys( $stand_ins ), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- generates fixture code.
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local test fixture.
		$mu_file,
		"<?php\n// Temporary QA fixture (tests/local_conflict_browser_fixture.php): reports two stand-in plugins as active.\nadd_filter( 'option_active_plugins', static function ( \$value ) {\n\tif ( ! get_option( 'aisaautopilot_local_conflict_fixture' ) ) { return \$value; }\n\treturn array_values( array_unique( array_merge( (array) \$value, {$list} ) ) );\n} );\n"
	);
	wp_clean_plugins_cache( false );
	Settings::update( array( 'public_assistant_enabled' => true, 'widget' => array( 'enabled' => true ) ) );
	echo wp_json_encode( array( 'plugins' => array_keys( $stand_ins ) ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$state = get_option( $state_option );
	if ( is_array( $state ) ) {
		update_option( Settings::OPTION, $state['settings'] );
		foreach ( array_keys( $stand_ins ) as $file ) {
			wp_delete_file( WP_PLUGIN_DIR . '/' . $file );
			@rmdir( WP_PLUGIN_DIR . '/' . dirname( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- local test fixture.
		}
	}
	wp_delete_file( $mu_file );
	delete_option( $state_option );
	wp_clean_plugins_cache( false );
	echo "restored\n";
	exit( 0 );
}

fwrite( STDERR, "Usage: php local_conflict_browser_fixture.php prepare|restore\n" );
exit( 1 );
