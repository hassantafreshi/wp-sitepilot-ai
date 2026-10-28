<?php
/**
 * Local WordPress integration checks for semantic (embeddings) search at scale.
 *
 * 2,100 temporary entries get embeddings; the best match is the oldest one, which the
 * earlier "newest 2,000" read could never see. The embedding endpoint is an inert URL
 * intercepted by pre_http_request. Proves the search reads every vector page by page and
 * returns the best ones in order, that readiness is a cheap existence check, that the
 * entry list never ships vectors, and that the table's schema is remembered. Every
 * temporary row and option is removed in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\KnowledgeBaseController;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\VectorSearch;
use AISAAutoPilot\Core\Settings;

global $wpdb;

$missing   = new stdClass();
$checks    = array();
$options   = array( Settings::OPTION, Settings::KEY_OPTION, Settings::EMBED_KEY_OPTION, 'aisaautopilot_monthly_budget', 'aisaautopilot_usage_totals', 'aisaautopilot_usage_period', 'aisaautopilot_usage_log', 'aisaautopilot_usage_daily', 'aisaautopilot_usage_period_contexts', 'aisaautopilot_logs', 'aisaautopilot_kb_version' );
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}
$original_user_id = get_current_user_id();
$kb               = new KnowledgeBase();
$table            = $kb->table();
$base_post_id     = 970000000;
$rows             = 2100;
$model            = 'vector-scale-contract-model';
$http_filter      = null;
$embed_calls      = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $value, false );
};

$cleanup = static function () use ( $wpdb, $table, $base_post_id, $rows ): void {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE post_id BETWEEN %d AND %d", $base_post_id, $base_post_id + $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

try {
	wp_set_current_user( 1 );
	$kb->create_table();
	$check( '2' === get_option( KnowledgeBase::SCHEMA_OPTION, '' ), 'Creating the table records its schema, so requests skip the column checks' );

	Settings::update(
		array(
			'provider'           => 'deepseek',
			'base_url'           => 'https://vector-scale-test.invalid/v1',
			'embedding_base_url' => 'https://vector-scale-test.invalid/v1',
			'model'              => 'vector-scale-chat-model',
			'embedding_model'    => $model,
			'key_management'     => 'keyvault',
			'embeddings_enabled' => true,
		)
	);
	Settings::set_api_key( 'vector-scale-test-key' );
	update_option( 'aisaautopilot_monthly_budget', 0, false );

	$cleanup();
	$vector_search = new VectorSearch( $kb );
	$check( ! $kb->has_embeddings( $model ) && ! $vector_search->is_ready(), 'Without vectors for the model, semantic search is not ready' );

	// Oldest first: row 0 points along the question, the others away from it, increasingly.
	$now = gmdate( 'Y-m-d H:i:s' );
	for ( $start = 0; $start < $rows; $start += 300 ) {
		$values = array();
		for ( $i = $start; $i < min( $rows, $start + 300 ); $i++ ) {
			$vector   = 0 === $i ? array( 1.0, 0.0, 0.0 ) : array( 0.1, 1.0, $i / $rows );
			$values[] = $wpdb->prepare(
				"(%d, 'post', %s, 'Scale fixture summary', '', '', 'article', %s, %s, %s, 'indexed', 1, %s, %s)",
				$base_post_id + $i,
				'Scale fixture ' . $i,
				wp_json_encode( $vector ),
				$model,
				md5( (string) $i ),
				$now,
				gmdate( 'Y-m-d H:i:s', time() + $i )
			);
		}
		$wpdb->query( "INSERT INTO {$table} (post_id, post_type, title, summary, keywords, category, content_type, embedding, embedding_model, content_hash, status, token_count, indexed_at, updated_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	$http_filter = static function ( $preempt, array $args, string $url ) use ( &$embed_calls ) {
		if ( false === strpos( $url, 'vector-scale-test.invalid' ) ) {
			return $preempt;
		}
		++$embed_calls;
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'data'  => array( array( 'embedding' => array( 1.0, 0.0, 0.0 ) ) ),
					'usage' => array(
						'prompt_tokens' => 1,
						'total_tokens'  => 1,
					),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	};
	add_filter( 'pre_http_request', $http_filter, 10, 3 );

	$before  = memory_get_usage();
	$results = $vector_search->search( 'which fixture points along the question?', 5 );
	$used    = memory_get_peak_usage() - $before;
	$scores  = array_column( $results, 'score' );
	$sorted  = $scores;
	rsort( $sorted );
	$check(
		$kb->has_embeddings( $model ) && $vector_search->is_ready() && 1 === $embed_calls,
		'Readiness is a single existence check, and the question is embedded once'
	);
	$check(
		5 === count( $results )
		&& $base_post_id === (int) ( $results[0]['post_id'] ?? 0 )
		&& abs( 1.0 - (float) ( $results[0]['score'] ?? 0 ) ) < 0.0001
		&& $scores === $sorted
		&& ! array_key_exists( 'vector', $results[0] ),
		'Among 2,100 vectors the oldest, best match is found (the old newest-2,000 read missed it), five results best first, no vectors returned'
	);
	$check(
		$used < 8 * 1024 * 1024,
		'Reading 2,100 vectors page by page stays under 8 MB of extra memory (' . round( $used / 1048576, 1 ) . ' MB)'
	);

	$list_request = new WP_REST_Request( 'GET', '/aisaautopilot/v1/kb/list' );
	$list_request->set_param( 'search', 'Scale fixture' );
	$list_request->set_param( 'per_page', 20 );
	$listed  = (array) ( new KnowledgeBaseController() )->list_entries( $list_request )->get_data();
	$entries = (array) ( $listed['entries'] ?? $listed['items'] ?? array() );
	$first   = (array) ( $entries[0] ?? array() );
	$check(
		array() !== $first && isset( $first['post_id'] ) && ! array_key_exists( 'embedding', $first ),
		'The entry list sends each entry without its vector'
	);
} catch ( Throwable $error ) {
	$check( false, 'Vector search scale contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	if ( null !== $http_filter ) {
		remove_filter( 'pre_http_request', $http_filter, 10 );
	}
	$cleanup();
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
