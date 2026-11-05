<?php
/**
 * Local WordPress integration checks for the Content Studio Write surface.
 *
 * Fixtures are limited to this render through pre_get_posts and permanently
 * removed in finally. No provider request is made.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\CopilotPage;

$original_get     = $_GET;
$original_user_id = get_current_user_id();
$checks           = array();
$post_ids         = array();
$query_filter     = null;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$create = static function ( array $post ) use ( &$post_ids ): int {
	$id = wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) || ! is_numeric( $id ) ) {
		return 0;
	}

	$post_ids[] = (int) $id;
	return (int) $id;
};

try {
	wp_set_current_user( 1 );
	$prefix      = 'AISAAutoPilot Content Studio Write ' . wp_generate_password( 8, false, false );
	$post_id     = $create(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $prefix . ' post draft',
			'post_content' => 'Owned content fixture.',
		)
	);
	$page_id     = $create(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $prefix . ' published page',
			'post_content' => 'Owned content fixture.',
		)
	);
	$unmarked_id = $create(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $prefix . ' unmarked draft',
			'post_content' => 'This must not appear in the Write UI.',
		)
	);
	if ( $post_id > 0 ) {
		update_post_meta( $post_id, '_aisaautopilot_generated', '1' );
	}
	if ( $page_id > 0 ) {
		update_post_meta( $page_id, '_aisaautopilot_generated', '1' );
	}

	$fixture_ids = array_filter( array( $post_id, $page_id, $unmarked_id ) );
	$query_filter = static function ( WP_Query $query ) use ( $fixture_ids ): void {
		$meta_query = (array) $query->get( 'meta_query' );
		$owns_query = array( 'post', 'page' ) === $query->get( 'post_type' )
			&& '_aisaautopilot_generated' === ( $meta_query[0]['key'] ?? '' );
		if ( $owns_query ) {
			$query->set( 'post__in', $fixture_ids );
		}
	};
	add_action( 'pre_get_posts', $query_filter );

	$_GET = array( // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
		'page' => AdminMenu::SLUG_FORM_BUILDER,
		'tab'  => 'write',
	);
	ob_start();
	( new CopilotPage() )->render();
	$html = (string) ob_get_clean();

	$check(
		false !== strpos( $html, 'Nothing written yet' )
		&& false !== strpos( $html, 'What should it write?' )
		&& false !== strpos( $html, 'Write a draft' )
		&& false !== strpos( $html, 'maxlength="6000"' )
		&& false !== strpos( $html, 'rows="4"' )
		&& 1 === preg_match( '/role="radiogroup"[^>]*data-content-voice/', $html )
		&& 3 === substr_count( $html, 'role="radio" class="sp-content-voice__chip"' )
		&& 1 === substr_count( $html, 'aria-checked="true"' ),
		'Write follows the mockup empty-state, 78px composer, voice choice (one of three chips as a radio group, T32), and draft CTA structure'
	);
	$check(
		false !== strpos( $html, $prefix . ' post draft' )
		&& false !== strpos( $html, $prefix . ' published page' )
		&& false === strpos( $html, $prefix . ' unmarked draft' )
		&& 2 === substr_count( $html, 'data-content-recent-row' )
		&& false !== strpos( $html, 'data-content-empty hidden=' )
		&& false !== strpos( $html, 'data-content-recent aria-labelledby=' ),
		'Write lists only actual marked Content Studio output and keeps the mockup recent-drafts surface'
	);
	$check(
		false !== strpos( $html, 'data-content-preview hidden' )
		&& false !== strpos( $html, 'Approve &amp; create draft' )
		&& false !== strpos( $html, 'Draft only' )
		&& false === strpos( $html, 'autopublish' )
		&& false === strpos( $html, 'Publish immediately' ),
		'Write preserves preview plus explicit draft-only approval and exposes no automatic publish control'
	);
} finally {
	if ( $query_filter instanceof Closure ) {
		remove_action( 'pre_get_posts', $query_filter );
	}
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$_GET = $original_get; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- restore global after isolated local assertion.
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
