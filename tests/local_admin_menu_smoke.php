<?php
/**
 * Read-only local WordPress smoke test for the redesigned admin navigation.
 *
 * It validates the visible 10-item information architecture against the
 * canonical admin mockup. Legacy URLs remain registered but must not leak
 * back into the sidebar.
 *
 * Run: C:\xampp\php\php.exe tests\local_admin_menu_smoke.php
 */
declare(strict_types=1);

define( 'WP_ADMIN', true );
require 'C:/xampp/htdocs/wp/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;

wp_set_current_user( 1 );

$warnings = array();
set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ) use ( &$warnings ): bool {
		$warnings[] = sprintf( '%s in %s:%d', $message, $file, $line );
		return false;
	}
);

do_action( 'admin_menu' );
restore_error_handler();

global $submenu;
$visible = (array) ( $submenu[ AdminMenu::SLUG_HOME ] ?? array() );
$actual  = array_map(
	static function ( array $item ): array {
		return array(
			'title' => wp_strip_all_tags( (string) ( $item[0] ?? '' ) ),
			'slug'  => (string) ( $item[2] ?? '' ),
		);
	},
	$visible
);

$expected_slugs = array(
	AdminMenu::SLUG_HOME,
	AdminMenu::SLUG_SETUP,
	AdminMenu::SLUG_INTEGRATIONS,
	AdminMenu::SLUG_KB,
	AdminMenu::SLUG_CONVERSATIONS,
	AdminMenu::SLUG_WC_DASHBOARD,
	AdminMenu::SLUG_FORM_BUILDER,
	AdminMenu::SLUG_USAGE,
	AdminMenu::SLUG_CONNECTIONS,
	AdminMenu::SLUG_LOGS,
);

$legacy_slugs = array(
	AdminMenu::SLUG_SETTINGS,
	AdminMenu::SLUG_FORMS,
	AdminMenu::SLUG_HISTORY,
	AdminMenu::SLUG_CHAT,
	AdminMenu::SLUG_EFB,
	AdminMenu::SLUG_WC,
	AdminMenu::SLUG_WIDGET,
	AdminMenu::SLUG_HANDOFFS,
);
$visible_slugs = array_column( $actual, 'slug' );
$visible_titles = array_column( $actual, 'title' );

global $_registered_pages;
$legacy_routes_registered = array_reduce(
	$legacy_slugs,
	static function ( bool $carry, string $slug ) use ( $_registered_pages ): bool {
		return $carry && isset( $_registered_pages[ get_plugin_page_hookname( $slug, '' ) ] );
	},
	true
);

$checks = array(
	'admin-menu hook completed without PHP warnings' => array() === $warnings,
	'visible sidebar exactly matches the 10 mockup pages in order' => $expected_slugs === $visible_slugs,
	'visible sidebar labels are present in the active locale' => 10 === count( $visible_titles ) && 10 === count( array_filter( $visible_titles ) ),
	'legacy technical routes are hidden from the sidebar' => array() === array_intersect( $legacy_slugs, $visible_slugs ),
	'legacy bookmark routes are registered as direct hidden pages' => $legacy_routes_registered,
);

foreach ( $checks as $name => $passed ) {
	echo ( $passed ? 'PASS ' : 'FAIL ' ) . $name . PHP_EOL;
}

if ( array() !== $warnings ) {
	echo 'WARNINGS' . PHP_EOL;
	foreach ( $warnings as $warning ) {
		echo $warning . PHP_EOL;
	}
}

if ( $expected_slugs !== $visible_slugs ) {
	echo 'EXPECTED ' . wp_json_encode( $expected_slugs ) . PHP_EOL;
	echo 'ACTUAL ' . wp_json_encode( $actual ) . PHP_EOL;
}

exit( in_array( false, $checks, true ) ? 1 : 0 );
