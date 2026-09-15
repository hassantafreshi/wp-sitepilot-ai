<?php
/**
 * Read-only local WordPress smoke test for AISAAutoPilot admin asset routing.
 *
 * Each known slug must receive the shared shell and UI stylesheet, while only
 * its declared page script(s) are queued. This catches a missing enqueue before
 * browser/visual QA turns it into a blank or non-functional screen.
 *
 * Run: C:\xampp\php\php.exe tests\local_admin_assets_smoke.php
 */
declare(strict_types=1);

define( 'WP_ADMIN', true );
require 'C:/xampp/htdocs/wp/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;

wp_set_current_user( 1 );

$routes = array(
	AdminMenu::SLUG_SETUP        => array( 'setup-wizard.js' ),
	AdminMenu::SLUG_HOME         => array(),
	// Bare Content Studio opens the canonical Write tab; the retained Form
	// Composer deep link is checked by the Content Studio navigation integration.
	AdminMenu::SLUG_FORM_BUILDER => array( 'content-studio.js' ),
	AdminMenu::SLUG_FORMS        => array(),
	AdminMenu::SLUG_HISTORY      => array(),
	AdminMenu::SLUG_EFB          => array(),
	AdminMenu::SLUG_CONNECTIONS  => array( 'connections.js' ),
	AdminMenu::SLUG_CHAT         => array( 'chat.js', 'voice.js' ),
	AdminMenu::SLUG_KB           => array( 'knowledge-base.js' ),
	AdminMenu::SLUG_USAGE        => array( 'usage.js' ),
	AdminMenu::SLUG_WIDGET       => array( 'widget-settings.js' ),
	AdminMenu::SLUG_WC           => array( 'woocommerce.js' ),
	AdminMenu::SLUG_WC_DASHBOARD => array( 'woocommerce.js' ),
	AdminMenu::SLUG_SETTINGS     => array( 'settings.js' ),
	AdminMenu::SLUG_LOGS         => array(),
	AdminMenu::SLUG_INTEGRATIONS => array( 'integrations.js' ),
	AdminMenu::SLUG_HANDOFFS     => array(),
	AdminMenu::SLUG_CONVERSATIONS => array( 'answer-widget.js', 'answer-handoff.js', 'answer-voice.js' ),
);

$menu     = new AdminMenu();
$failures = array();

foreach ( $routes as $slug => $files ) {
	$_GET = array( 'page' => $slug );
	wp_styles()->queue  = array();
	wp_scripts()->queue = array();
	$menu->enqueue_assets( 'test_page_aisaautopilot' );

	$styles  = wp_styles();
	$scripts = wp_scripts();
	$expected_script_handles = array( 'aisaautopilot-shell' );
	foreach ( $files as $file ) {
		$expected_script_handles[] = 'aisaautopilot-' . pathinfo( $file, PATHINFO_FILENAME );
	}

	$has_common_assets = in_array( 'aisaautopilot-plus-jakarta-sans', $styles->queue, true )
		&& in_array( 'aisaautopilot-ui', $styles->queue, true )
		&& in_array( 'aisaautopilot-shell', $scripts->queue, true );
	$has_expected_scripts = array() === array_diff( $expected_script_handles, $scripts->queue );
	$has_local_files = array_reduce(
		$files,
		static function ( bool $carry, string $file ): bool {
			return $carry && is_readable( dirname( __DIR__ ) . '/assets/admin/' . $file );
		},
		true
	);
	$has_shell_config = false !== strpos( (string) ( $scripts->get_data( 'aisaautopilot-shell', 'data' ) ?? '' ), 'AISAAutoPilotShellConfig' );

	$passed = $has_common_assets && $has_expected_scripts && $has_local_files && $has_shell_config;
	echo ( $passed ? 'PASS ' : 'FAIL ' ) . $slug . ' asset contract' . PHP_EOL;
	if ( ! $passed ) {
		$failures[] = $slug;
		echo '  common=' . ( $has_common_assets ? 'yes' : 'no' ) . ' expected_scripts=' . ( $has_expected_scripts ? 'yes' : 'no' ) . ' files=' . ( $has_local_files ? 'yes' : 'no' ) . ' shell_config=' . ( $has_shell_config ? 'yes' : 'no' ) . PHP_EOL;
	}
}

$font_src = (string) ( wp_styles()->registered['aisaautopilot-plus-jakarta-sans']->src ?? '' );
$font_ok  = false !== strpos( $font_src, 'Plus+Jakarta+Sans' ) && false !== strpos( $font_src, 'JetBrains+Mono' );
echo ( $font_ok ? 'PASS ' : 'FAIL ' ) . 'mockup font request includes sans and mono families' . PHP_EOL;
if ( ! $font_ok ) {
	$failures[] = 'font';
}

$_GET = array();
exit( array() === $failures ? 0 : 1 );
