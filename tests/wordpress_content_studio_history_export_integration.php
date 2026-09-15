<?php
/**
 * Local WordPress integration checks for the safe Content Studio CSV export.
 *
 * The endpoint itself exits after streaming, so this validates its reusable
 * URL, row selection, and CSV serialization with fixtures removed in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\ContentStudioHistoryExport;

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
	$prefix      = 'AISAAutoPilot Content Studio export ' . wp_generate_password( 8, false, false );
	$owned_id    = $create(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => '=HYPERLINK("https://example.test","' . $prefix . '")',
		)
	);
	$unmarked_id = $create(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $prefix . ' unmarked',
		)
	);
	if ( $owned_id > 0 ) {
		update_post_meta( $owned_id, '_aisaautopilot_generated', '1' );
	}

	$fixture_ids  = array_filter( array( $owned_id, $unmarked_id ) );
	$query_filter = static function ( WP_Query $query ) use ( $fixture_ids ): void {
		$meta_query = (array) $query->get( 'meta_query' );
		$owns_query = array( 'post', 'page' ) === $query->get( 'post_type' )
			&& '_aisaautopilot_generated' === ( $meta_query[0]['key'] ?? '' );
		if ( $owns_query ) {
			$query->set( 'post__in', $fixture_ids );
		}
	};
	add_action( 'pre_get_posts', $query_filter );

	$export      = new ContentStudioHistoryExport();
	$url         = ContentStudioHistoryExport::url();
	$url_query   = array();
	parse_str( (string) wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_QUERY ), $url_query );
	$records     = $export->records();
	$stream      = fopen( 'php://temp', 'w+' );
	$csv         = '';
	if ( false !== $stream ) {
		$export->write_csv( $stream );
		rewind( $stream );
		$csv = (string) stream_get_contents( $stream );
		fclose( $stream );
	}
	$check(
		'aisaautopilot_export_content_studio_history' === ( $url_query['action'] ?? '' )
		&& '' !== ( $url_query['_wpnonce'] ?? '' ),
		'Content Studio History exposes a dedicated nonce-protected admin export URL'
	);
	$check(
		1 === count( $records )
		&& $prefix !== ''
		&& false !== strpos( (string) ( $records[0]['title'] ?? '' ), $prefix )
		&& __( 'Template not recorded', 'ai-site-assistant-autopilot' ) === ( $records[0]['template'] ?? '' )
		&& __( 'Usage not recorded', 'ai-site-assistant-autopilot' ) === ( $records[0]['usage'] ?? '' )
		&& false === strpos( $csv, $prefix . ' unmarked' ),
		'Content Studio History export contains only durable marker-owned content rows'
	);
	$check(
		0 === strpos( $csv, "created_at,title,destination,state,template,usage,edit_url\n" )
		&& false !== strpos( $csv, "'=HYPERLINK" )
		&& false === strpos( $csv, 'post_content' )
		&& false === strpos( $csv, 'connector_id' ),
		'Content Studio History export uses an explicit safe CSV schema and neutralizes formulas'
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
