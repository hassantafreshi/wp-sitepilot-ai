<?php
/**
 * Local WordPress integration checks for the Answer Desk menu badge. The badge
 * must show the real number of handoff threads still marked "new", with
 * screen-reader text, and must not add menu items. One marked thread is
 * inserted and exactly that row is deleted afterwards; no delivery runs.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Support\HandoffInbox;

$checks           = array();
$original_user_id = get_current_user_id();
$inbox            = new HandoffInbox();
$marker           = 'aisaautopilot-menu-badge-qa';
$inserted         = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

// Registers the menu from scratch and returns the visible Answer Desk label.
$answer_desk_label = static function (): string {
	global $menu, $submenu;
	$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- isolated menu registration for this check.
	$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- isolated menu registration for this check.
	( new AdminMenu() )->register();
	foreach ( (array) ( $submenu[ AdminMenu::SLUG_HOME ] ?? array() ) as $item ) {
		if ( AdminMenu::SLUG_CONVERSATIONS === ( $item[2] ?? '' ) ) {
			return (string) $item[0];
		}
	}
	return '';
};

try {
	wp_set_current_user( 1 );
	$before       = $inbox->count_new();
	$label_before = $answer_desk_label();
	$check(
		0 === $before
			? ( 'Answer Desk' === trim( wp_strip_all_tags( $label_before ) ) && false === strpos( $label_before, 'awaiting-mod' ) )
			: false !== strpos( $label_before, 'count-' . $before . '"' ),
		'Menu label matches the real waiting count before the fixture (no bubble at zero)'
	);
	// D-23 (2026-09-17): eight visible main pages; Usage & Cost, Health and Activity Log are Overview tabs.
	$check( 8 === count( (array) ( $GLOBALS['submenu'][ AdminMenu::SLUG_HOME ] ?? array() ) ), 'Sidebar still has exactly 8 visible items' );

	if ( ! $inbox->is_ready() ) {
		$check( 0 === $before, 'Without the inbox table the waiting count is 0 and nothing is queried' );
	} else {
		$inserted = $inbox->record( array( array( 'role' => 'user', 'content' => 'Menu badge QA' ) ), $marker, 'menu-badge@example.test', 'new' );
		$after    = $before + 1;
		$check( $inserted > 0 && $after === $inbox->count_new(), 'count_new() counts a thread that is still new' );

		$label = $answer_desk_label();
		$check(
			false !== strpos( $label, 'class="awaiting-mod count-' . $after . '"' )
				&& false !== strpos( $label, '<span class="pending-count" aria-hidden="true">' . number_format_i18n( $after ) . '</span>' )
				&& false !== strpos( $label, '<span class="screen-reader-text">' ),
			'Answer Desk shows the WordPress count bubble with screen-reader text'
		);
		$check( 8 === count( (array) ( $GLOBALS['submenu'][ AdminMenu::SLUG_HOME ] ?? array() ) ), 'The bubble does not add a menu item' );

		$inbox->set_status( $inserted, 'in_progress' );
		$check( $before === $inbox->count_new(), 'A thread a person picked up no longer counts as waiting' );
	}
} finally {
	if ( $inserted > 0 ) {
		global $wpdb;
		$wpdb->delete( $inbox->table(), array( 'id' => $inserted, 'visitor_name' => $marker ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- removes only the row this check inserted.
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
