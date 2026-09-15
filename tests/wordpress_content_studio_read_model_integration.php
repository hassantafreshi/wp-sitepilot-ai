<?php
/**
 * Local WordPress integration checks for the Content Studio Write read model.
 * The fixtures are deleted in finally and no provider request is made.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Content\ContentStudioReadModel;

$original_user_id = get_current_user_id();
$checks           = array();
$post_ids         = array();

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
	$prefix     = 'AISAAutoPilot Content Studio read model ' . wp_generate_password( 8, false, false );
	$draft_id   = $create(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $prefix . ' draft',
			'post_content' => 'Owned draft fixture.',
		)
	);
	$page_id    = $create(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $prefix . ' published page',
			'post_content' => 'Owned published fixture.',
		)
	);
	$unmarked_id = $create(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $prefix . ' unmarked draft',
			'post_content' => 'This must not appear in the Content Studio list.',
		)
	);
	if ( $draft_id > 0 ) {
		update_post_meta( $draft_id, '_aisaautopilot_generated', '1' );
	}
	if ( $page_id > 0 ) {
		update_post_meta( $page_id, '_aisaautopilot_generated', '1' );
	}

	$rows       = ( new ContentStudioReadModel() )->recent_drafts( 20 );
	$row_by_id  = array();
	foreach ( $rows as $row ) {
		$row_by_id[ (int) $row['id'] ] = $row;
	}
	$draft_row  = $row_by_id[ $draft_id ] ?? array();
	$page_row   = $row_by_id[ $page_id ] ?? array();
	$check(
		$draft_id > 0 && $page_id > 0 && $unmarked_id > 0
		&& isset( $row_by_id[ $draft_id ], $row_by_id[ $page_id ] )
		&& ! isset( $row_by_id[ $unmarked_id ] ),
		'Content Studio reads only marked post and page output, never an unmarked draft'
	);
	$check(
		__( 'Draft saved', 'ai-site-assistant-autopilot' ) === ( $draft_row['state'] ?? '' )
		&& 'warn' === ( $draft_row['tone'] ?? '' )
		&& __( 'Published by you', 'ai-site-assistant-autopilot' ) === ( $page_row['state'] ?? '' )
		&& 'ok' === ( $page_row['tone'] ?? '' )
		&& '' !== ( $draft_row['kind'] ?? '' )
		&& '' !== ( $page_row['edit_url'] ?? '' ),
		'Content Studio exposes truthful kind, status, tone, and WordPress destination metadata'
	);
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
