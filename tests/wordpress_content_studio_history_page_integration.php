<?php
/**
 * Local WordPress integration checks for the mockup-aligned Content Studio History page.
 *
 * Fixtures are removed in finally. The page is intentionally declarative and
 * contains no Activity Log table, filters, or provider interaction.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ContentStudioHistoryExport;
use AISAAutoPilot\Admin\HistoryPage;

$original_get     = $_GET;
$original_user_id = get_current_user_id();
$checks           = array();
$post_ids         = array();
$query_filter     = null;
$fixture_ids      = array();
$force_empty      = false;

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

$render = static function (): string {
	ob_start();
	( new HistoryPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$prefix      = 'AISAAutoPilot Content Studio History page ' . wp_generate_password( 8, false, false );
	$owned_id    = $create(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => $prefix . ' owned draft',
		)
	);
	$unmarked_id = $create(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $prefix . ' unmarked page',
		)
	);
	if ( $owned_id > 0 ) {
		update_post_meta( $owned_id, '_aisaautopilot_generated', '1' );
	}

	$fixture_ids  = array_filter( array( $owned_id, $unmarked_id ) );
	$query_filter = static function ( WP_Query $query ) use ( &$fixture_ids, &$force_empty ): void {
		$meta_query    = (array) $query->get( 'meta_query' );
		$content_types = (array) $query->get( 'post_type' );
		$owns_query    = in_array( 'post', $content_types, true )
			&& in_array( 'page', $content_types, true )
			&& '_aisaautopilot_generated' === ( $meta_query[0]['key'] ?? '' );
		if ( $owns_query ) {
			$query->set( 'post__in', $force_empty ? array( PHP_INT_MAX ) : $fixture_ids );
			$query->set( 'cache_results', false );
		}
	};
	add_action( 'pre_get_posts', $query_filter );
	$_GET = array( // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
		'page' => AdminMenu::SLUG_FORM_BUILDER,
		'tab'  => 'history',
	);
	$html = $render();
	$check(
		false !== strpos( $html, 'data-content-history' )
		&& 3 === substr_count( $html, 'data-sp-subtab=' )
		&& false !== strpos( $html, 'Recent saved output, usage when recorded, and its WordPress destination' )
		&& false !== strpos( $html, esc_url( ContentStudioHistoryExport::url() ) ),
		'Content Studio History renders the mockup card, three-tab shell, and real CSV action'
	);
	$check(
		false !== strpos( $html, $prefix . ' owned draft' )
		&& false === strpos( $html, $prefix . ' unmarked page' )
		&& 1 === substr_count( $html, 'data-content-history-row' )
		&& false !== strpos( $html, 'Template not recorded' )
		&& false !== strpos( $html, 'Usage not recorded' ),
		'Content Studio History shows only truthful saved-output rows and explicit unavailable attribution'
	);
	$check(
		false === strpos( $html, 'sp-history-search' )
		&& false === strpos( $html, 'sp-history-status' )
		&& false === strpos( $html, 'sp-tr--head' )
		&& false === strpos( $html, 'AISAAutoPilot Forms' )
		&& false === strpos( $html, '$0.004' ),
		'Content Studio History removes unrelated Activity Log controls and fake dollar costs'
	);

	$force_empty = true;
	$empty_html  = $render();
	$check(
		false !== strpos( $empty_html, 'data-content-history-empty' )
		&& false !== strpos( $empty_html, 'Nothing written yet' ),
		'Content Studio History renders its truthful empty state when no saved output exists'
	);
	$check(
		0 === substr_count( $empty_html, 'data-content-history-row' ),
		'Content Studio History renders no saved-output row in its empty state'
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
echo count( $checks ) . ' checks, ' . count( $failed ) . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
