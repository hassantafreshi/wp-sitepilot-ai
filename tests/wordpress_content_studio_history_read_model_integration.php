<?php
/**
 * Local WordPress integration checks for Content Studio's durable history.
 *
 * Fixtures are removed in finally. No provider, Activity Log, or CSV endpoint
 * is called; the history source must be marked WordPress post/page output only.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Content\ContentStudioReadModel;

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
	$prefix   = 'AISAAutoPilot Content Studio history ' . wp_generate_password( 8, false, false );
	$older_id = $create(
		array(
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_title'    => $prefix . ' older post',
			'post_date'     => '2024-02-01 08:00:00',
			'post_date_gmt' => '2024-02-01 08:00:00',
		)
	);
	$newer_id = $create(
		array(
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => $prefix . ' newer page',
			'post_date'     => '2024-02-01 12:00:00',
			'post_date_gmt' => '2024-02-01 12:00:00',
		)
	);
	$fallback_draft_id = $create(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => $prefix . ' fresh local-date draft',
		)
	);
	$unmarked_id = $create(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => $prefix . ' unmarked post',
		)
	);
	$attachment_id = $create(
		array(
			'post_type'    => 'attachment',
			'post_status'  => 'inherit',
			'post_title'   => $prefix . ' marked non-content',
			'post_mime_type' => 'text/plain',
		)
	);
	foreach ( array( $older_id, $newer_id, $fallback_draft_id, $attachment_id ) as $owned_id ) {
		if ( $owned_id > 0 ) {
			update_post_meta( $owned_id, '_aisaautopilot_generated', '1' );
		}
	}

	$fixture_ids  = array_filter( array( $older_id, $newer_id, $fallback_draft_id, $unmarked_id, $attachment_id ) );
	$query_filter = static function ( WP_Query $query ) use ( $fixture_ids ): void {
		$meta_query = (array) $query->get( 'meta_query' );
		$owns_query = array( 'post', 'page' ) === $query->get( 'post_type' )
			&& '_aisaautopilot_generated' === ( $meta_query[0]['key'] ?? '' );
		if ( $owns_query ) {
			$query->set( 'post__in', $fixture_ids );
		}
	};
	add_action( 'pre_get_posts', $query_filter );

	$rows      = ( new ContentStudioReadModel() )->history( 20 );
	$row_by_id = array();
	foreach ( $rows as $row ) {
		$row_by_id[ (int) $row['id'] ] = $row;
	}
	$newer_row = $row_by_id[ $newer_id ] ?? array();
	$older_row = $row_by_id[ $older_id ] ?? array();
	$fallback_row = $row_by_id[ $fallback_draft_id ] ?? array();
	$row_order = array_flip( array_map( static fn( array $row ): int => (int) $row['id'], $rows ) );
	$check(
		$older_id > 0 && $newer_id > 0 && $fallback_draft_id > 0 && $unmarked_id > 0 && $attachment_id > 0
		&& isset( $row_by_id[ $older_id ], $row_by_id[ $newer_id ], $row_by_id[ $fallback_draft_id ] )
		&& ! isset( $row_by_id[ $unmarked_id ], $row_by_id[ $attachment_id ] ),
		'Content Studio History reads only marked post and page output'
	);
	$check(
		( $row_order[ $newer_id ] ?? PHP_INT_MAX ) < ( $row_order[ $older_id ] ?? PHP_INT_MAX )
		&& (int) ( $newer_row['created_at'] ?? 0 ) > (int) ( $older_row['created_at'] ?? 0 )
		&& '' !== ( $newer_row['created_at_iso'] ?? '' )
		&& '' !== ( $newer_row['when'] ?? '' )
		&& (int) ( $fallback_row['created_at'] ?? 0 ) > 0
		&& '—' !== ( $fallback_row['when'] ?? '' ),
		'Content Studio History orders saved output by creation time and timestamps fresh local-date drafts'
	);
	$check(
		__( 'Published by you', 'ai-site-assistant-autopilot' ) === ( $newer_row['state'] ?? '' )
		&& 'ok' === ( $newer_row['tone'] ?? '' )
		&& '' !== ( $newer_row['edit_url'] ?? '' )
		&& __( 'Template not recorded', 'ai-site-assistant-autopilot' ) === ( $newer_row['template'] ?? '' )
		&& __( 'Usage not recorded', 'ai-site-assistant-autopilot' ) === ( $newer_row['usage'] ?? '' ),
		'Content Studio History exposes destination/state and never invents template or usage attribution'
	);
} finally {
	if ( $query_filter instanceof Closure ) {
		remove_action( 'pre_get_posts', $query_filter );
	}
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
