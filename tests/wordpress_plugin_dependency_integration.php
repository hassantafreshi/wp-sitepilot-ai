<?php
/**
 * Local WordPress checks for the "needs another plugin" state (Admin\PluginDependency):
 * Store Copilot without WooCommerce and the Easy Form Builder copilot without Easy Form
 * Builder, the Integrations, Setup and Form Studio buttons, and the activation handler.
 *
 * WooCommerce and Easy Form Builder are already loaded in a process where they are active,
 * so this script deactivates both, runs its checks in a fresh PHP process (`child`) where
 * they are not loaded, and puts both plugins back the way they were in `finally`. The
 * "not installed" case is simulated with the plugin header cache, so nothing is deleted.
 *
 * Proves: installed-but-inactive offers Activate, not installed offers Install with a valid
 * WordPress installer nonce, a user without the plugin capabilities gets a note instead of
 * a button; Store Copilot shows only the needed-plugin state (no tabs, no proposal form);
 * the legacy Store URL, Easy Form Builder page, Integrations cards, Setup cards and Form
 * Studio all point at the right step; the handler refuses a bad nonce, an unknown plugin and
 * a user who may not activate plugins, activates on a valid request, returns only to an
 * AutoPilot screen, and the confirmation cannot be faked while the plugin is inactive.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
$child   = 'child' === ( $argv[1] ?? '' );

const AISA_QA_WOO = 'woocommerce/woocommerce.php';
const AISA_QA_EFB = 'easy-form-builder/emsfb.php';

if ( ! $child ) {
	require $wp_root . '/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	wp_clean_plugins_cache( true );
	$installed = get_plugins();
	if ( ! isset( $installed[ AISA_QA_WOO ], $installed[ AISA_QA_EFB ] ) ) {
		echo "SKIP WooCommerce and Easy Form Builder must both be installed in the local site\n";
		exit( 0 );
	}
	$was_active = array_values( array_filter( array( AISA_QA_WOO, AISA_QA_EFB ), 'is_plugin_active' ) );
	$code       = 1;
	try {
		deactivate_plugins( array( AISA_QA_WOO, AISA_QA_EFB ), true );
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- local test runner.
		echo implode( "\n", $output ) . "\n";
	} finally {
		// The child changed active_plugins behind this process's option cache: re-read it
		// from the database and put back exactly the two plugins this script touched.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'active_plugins', 'options' );
		$current = array_diff( (array) get_option( 'active_plugins', array() ), array( AISA_QA_WOO, AISA_QA_EFB ) );
		$current = array_values( array_unique( array_merge( $current, $was_active ) ) );
		sort( $current );
		update_option( 'active_plugins', $current );
	}
	exit( $code );
}

require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Admin\EasyFormBuilderPage;
use AISAAutoPilot\Admin\IntegrationsPage;
use AISAAutoPilot\Admin\PluginDependency;
use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Admin\WooCommerceSettings;

final class AisaQaRedirect extends Exception {}
final class AisaQaDie extends Exception {}

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$render = static function ( callable $callback, array $get ): string {
	$_GET     = $get;
	$_REQUEST = $get;
	ob_start();
	try {
		$callback();
	} finally {
		$html     = (string) ob_get_clean();
		$_GET     = array();
		$_REQUEST = array();
	}
	return $html;
};
$query = static function ( string $url ): array {
	parse_str( (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_QUERY ), $args );
	return $args;
};
// Runs the activation handler; returns [ 'redirect' => url ] or [ 'die' => status ].
$handle = static function ( array $get ): array {
	$_GET     = $get;
	$_REQUEST = $get;
	try {
		( new PluginDependency() )->handle_activate();
		return array( 'none' => true );
	} catch ( AisaQaRedirect $redirect ) {
		return array( 'redirect' => $redirect->getMessage() );
	} catch ( AisaQaDie $die ) {
		return array( 'die' => (int) $die->getMessage() );
	} finally {
		$_GET     = array();
		$_REQUEST = array();
	}
};
add_filter(
	'wp_redirect',
	static function ( $location ) {
		throw new AisaQaRedirect( (string) $location );
	}
);
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $message, $title = '', $args = array() ) {
			throw new AisaQaDie( (string) ( is_array( $args ) ? ( $args['response'] ?? 500 ) : 500 ) );
		};
	}
);
// Removes plugin capabilities from the current user while set.
$no_plugin_caps = static function ( array $allcaps ): array {
	foreach ( array( 'install_plugins', 'activate_plugins', 'update_plugins' ) as $cap ) {
		$allcaps[ $cap ] = false;
	}
	return $allcaps;
};

wp_set_current_user( 1 );
$admin_page = AdminMenu::SLUG_WC_DASHBOARD;

try {
	// 1. Installed but not active: Activate, through the handler, back to the given screen.
	$woo  = PluginDependency::status( PluginDependency::WOOCOMMERCE, $admin_page );
	$args = is_array( $woo['action'] ) ? $query( $woo['action']['url'] ) : array();
	$check(
		'inactive' === $woo['state'] && AISA_QA_WOO === $woo['file'] && 'activate' === ( $woo['action']['kind'] ?? '' )
		&& 'Activate WooCommerce' === ( $woo['action']['label'] ?? '' ) && PluginDependency::ACTION === ( $args['action'] ?? '' )
		&& 'woocommerce' === ( $args['dependency'] ?? '' ) && $admin_page === ( $args['return'] ?? '' )
		&& false !== wp_verify_nonce( (string) ( $args['_wpnonce'] ?? '' ), PluginDependency::ACTION . '_woocommerce' ),
		'Installed but inactive WooCommerce offers Activate WooCommerce, a nonce-protected link that returns to Store Copilot'
	);

	// 2. Not installed (header cache without it): Install, through WordPress's installer.
	$all = get_plugins();
	unset( $all[ AISA_QA_WOO ] );
	wp_cache_set( 'plugins', array( '' => $all ), 'plugins' );
	$missing = PluginDependency::status( PluginDependency::WOOCOMMERCE, $admin_page );
	$args    = is_array( $missing['action'] ) ? $query( $missing['action']['url'] ) : array();
	$html    = $render( array( new StoreCopilotPage(), 'render' ), array( 'page' => $admin_page ) );
	wp_cache_delete( 'plugins', 'plugins' );
	$check(
		'missing' === $missing['state'] && 'install' === ( $missing['action']['kind'] ?? '' ) && 'Install WooCommerce' === ( $missing['action']['label'] ?? '' )
		&& false !== strpos( (string) $missing['action']['url'], 'update.php?action=install-plugin' ) && 'woocommerce' === ( $args['plugin'] ?? '' )
		&& false !== wp_verify_nonce( (string) ( $args['_wpnonce'] ?? '' ), 'install-plugin_woocommerce' )
		&& false !== strpos( $html, 'Install WooCommerce to use Store Copilot' ) && false !== strpos( $html, 'data-sp-dependency-state="missing"' ),
		'Missing WooCommerce offers Install WooCommerce through the WordPress installer with a valid nonce, and the page says so'
	);

	// 3. Without plugin capabilities: no button, a note saying who can do it.
	add_filter( 'user_has_cap', $no_plugin_caps );
	$limited = PluginDependency::status( PluginDependency::WOOCOMMERCE, $admin_page );
	$html    = $render( array( new StoreCopilotPage(), 'render' ), array( 'page' => $admin_page ) );
	remove_filter( 'user_has_cap', $no_plugin_caps );
	$check(
		null === $limited['action'] && false !== strpos( $limited['note'], 'activate WooCommerce' )
		&& false === strpos( $html, 'data-sp-dependency-action=' ) && false !== strpos( $html, 'data-sp-dependency-note' ),
		'A user who may not manage plugins sees who can activate WooCommerce instead of a button'
	);

	// 4. Store Copilot shows only the needed-plugin state, whatever tab was asked for.
	$html = $render( array( new StoreCopilotPage(), 'render' ), array( 'page' => $admin_page, 'tab' => 'orders' ) );
	$check(
		false !== strpos( $html, 'data-sp-dependency="woocommerce"' ) && false !== strpos( $html, 'Activate WooCommerce to use Store Copilot' )
		&& 3 === substr_count( $html, '<li>' ) && false === strpos( $html, 'data-sp-subtab' ) && false === strpos( $html, 'id="sp-woo-plan"' )
		&& false === strpos( $html, 'data-sp-store-orders' ) && false !== strpos( $html, 'data-sp-store-active="0"' )
		&& false !== strpos( $html, 'https://wordpress.org/plugins/woocommerce/' ),
		'Store Copilot without WooCommerce shows the activate step and what it offers, with no tabs, proposal form or order panel'
	);

	// 5. The legacy Store URL comes back to itself.
	$html = $render( array( new WooCommerceSettings(), 'render' ), array( 'page' => AdminMenu::SLUG_WC ) );
	$check( false !== strpos( $html, 'return=' . AdminMenu::SLUG_WC ), 'The legacy WooCommerce URL shows the same state and returns to itself after activating' );

	// 6. Easy Form Builder page: activate step on the status region; the allowed list stays.
	$html = $render( array( new EasyFormBuilderPage(), 'render' ), array( 'page' => AdminMenu::SLUG_EFB ) );
	$check(
		1 === preg_match( '/<section class="sp-dep"[^>]*data-sp-dependency="easy-form-builder"[^>]*data-sp-dependency-state="inactive"[^>]*data-sp-efb-status/', $html )
		&& false !== strpos( $html, 'Activate Easy Form Builder to use its AI copilot' ) && false !== strpos( $html, 'return=' . AdminMenu::SLUG_EFB )
		&& false !== strpos( $html, 'data-sp-efb-op' ),
		'The Easy Form Builder page offers Activate Easy Form Builder and keeps the list of what the copilot may change'
	);

	// 7. Integrations cards: Activate (not Install), back to Integrations, and the reason.
	$html = $render( array( new IntegrationsPage(), 'render' ), array( 'page' => AdminMenu::SLUG_INTEGRATIONS ) );
	$check(
		false !== strpos( $html, 'Activate WooCommerce' ) && false !== strpos( $html, 'Activate Easy Form Builder' )
		&& false === strpos( $html, 'Install WooCommerce' ) && false === strpos( $html, 'Install Easy Form Builder' )
		&& 2 === substr_count( $html, 'return=' . AdminMenu::SLUG_INTEGRATIONS ) && false !== strpos( $html, 'WooCommerce is installed on this site but is not active.' ),
		'Integrations cards offer Activate for installed plugins, come back to Integrations and say why'
	);

	// 8. Setup cards and Form Studio.
	$setup  = $render( array( new SettingsPage(), 'render' ), array( 'page' => AdminMenu::SLUG_SETUP ) );
	$studio = $render( array( new CopilotPage(), 'render_forms' ), array( 'page' => AdminMenu::SLUG_FORM_STUDIO ) );
	$check(
		2 === substr_count( $setup, 'data-sp-dependency-action="activate"' ) && false !== strpos( $setup, 'return=' . AdminMenu::SLUG_SETUP )
		&& false !== strpos( $studio, 'data-sp-dependency-action="activate"' ) && false !== strpos( $studio, 'return=' . AdminMenu::SLUG_FORM_STUDIO ),
		'Setup offers Activate on both cards and Form Studio offers Activate Easy Form Builder'
	);

	// 9. A confirmation cannot be faked while the plugin is still inactive; a failure is explained.
	$fake   = $render( array( PluginDependency::class, 'render_result_notice' ), array( PluginDependency::RESULT_ARG => 'woocommerce', PluginDependency::RESULT_STATUS_ARG => 'activated' ) );
	$failed = $render( array( PluginDependency::class, 'render_result_notice' ), array( PluginDependency::RESULT_ARG => 'woocommerce', PluginDependency::RESULT_STATUS_ARG => 'failed' ) );
	$check(
		'' === trim( $fake ) && false !== strpos( $failed, 'data-sp-dependency-result="failed"' ) && false !== strpos( $failed, 'role="alert"' ),
		'"Activated" is not shown while WooCommerce is inactive; a failed activation is announced with a link to Plugins'
	);

	// 10. Handler refusals: bad nonce, unknown plugin, a user who may not activate.
	$bad_nonce = $handle( array( 'dependency' => 'woocommerce', '_wpnonce' => 'nope' ) );
	$unknown   = $handle( array( 'dependency' => 'hello-dolly', '_wpnonce' => wp_create_nonce( PluginDependency::ACTION . '_hello-dolly' ) ) );
	add_filter( 'user_has_cap', $no_plugin_caps );
	$no_cap = $handle( array( 'dependency' => 'woocommerce', '_wpnonce' => wp_create_nonce( PluginDependency::ACTION . '_woocommerce' ) ) );
	remove_filter( 'user_has_cap', $no_plugin_caps );
	$check(
		isset( $bad_nonce['die'] ) && 400 === ( $unknown['die'] ?? 0 ) && 403 === ( $no_cap['die'] ?? 0 ) && ! is_plugin_active( AISA_QA_WOO ),
		'The handler refuses a bad nonce, an unknown plugin (400) and a user who may not activate plugins (403); nothing is activated'
	);

	// 11. A valid request activates WooCommerce and returns to the screen, never elsewhere.
	$done = $handle( array( 'dependency' => 'woocommerce', 'return' => 'plugins', '_wpnonce' => wp_create_nonce( PluginDependency::ACTION . '_woocommerce' ) ) );
	$back = $query( (string) ( $done['redirect'] ?? '' ) );
	$check(
		is_plugin_active( AISA_QA_WOO ) && AdminMenu::SLUG_INTEGRATIONS === ( $back['page'] ?? '' )
		&& 'woocommerce' === ( $back[ PluginDependency::RESULT_ARG ] ?? '' ) && 'activated' === ( $back[ PluginDependency::RESULT_STATUS_ARG ] ?? '' )
		&& 0 === strpos( (string) ( $done['redirect'] ?? '' ), admin_url( 'admin.php' ) ),
		'A valid request activates WooCommerce and returns to an AutoPilot screen (an unknown return page falls back to Integrations)'
	);
} catch ( Throwable $error ) {
	$check( false, 'Unexpected ' . get_class( $error ) . ': ' . $error->getMessage() );
} finally {
	wp_cache_delete( 'plugins', 'plugins' );
	wp_set_current_user( 0 );
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
