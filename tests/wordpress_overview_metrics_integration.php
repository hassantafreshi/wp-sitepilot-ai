<?php
/**
 * Local WordPress integration check for Overview's non-mocked metric sources.
 *
 * The script creates only named test records and removes them in finally.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\HomePage;
use AISAAutoPilot\Chat\VisitorConversationStore;

wp_set_current_user( 1 );

$checks              = array();
$generated_draft_id  = 0;
$unrelated_draft_id  = 0;
$conversation_token  = '';
$store               = new VisitorConversationStore();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

try {
	$overview         = new HomePage();
	$draft_counter    = new ReflectionMethod( HomePage::class, 'count_generated_posts_by_status' );
	$question_counter = new ReflectionMethod( HomePage::class, 'answered_question_count' );
	$draft_counter->setAccessible( true );
	$question_counter->setAccessible( true );

	$drafts_before = (int) $draft_counter->invoke( $overview, 'draft' );
	$questions_before = (int) $question_counter->invoke( $overview );

	$generated_draft_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'AISAAutoPilot Overview generated-metric test',
			'post_content' => 'Temporary generated metric test record.',
		),
		true
	);
	if ( is_wp_error( $generated_draft_id ) ) {
		$generated_draft_id = 0;
	}
	if ( $generated_draft_id > 0 ) {
		update_post_meta( $generated_draft_id, '_aisaautopilot_generated', '1' );
	}

	$unrelated_draft_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => 'AISAAutoPilot Overview unrelated-metric test',
			'post_content' => 'Temporary unrelated metric test record.',
		),
		true
	);
	if ( is_wp_error( $unrelated_draft_id ) ) {
		$unrelated_draft_id = 0;
	}

	$drafts_after = (int) $draft_counter->invoke( $overview, 'draft' );
	$check(
		$generated_draft_id > 0 && $unrelated_draft_id > 0 && $drafts_after === $drafts_before + 1,
		'Overview counts only plugin-marked drafts, never unrelated WordPress drafts'
	);

	$conversation_token = wp_generate_uuid4();
	$store->append_turn( $conversation_token, 'Overview QA question one', 'Overview QA answer one' );
	$store->append_turn( $conversation_token, 'Overview QA question two', 'Overview QA answer two' );
	$questions_after = (int) $question_counter->invoke( $overview );
	$check(
		$questions_after === $questions_before + 2,
		'Overview counts recorded question turns rather than conversation sessions'
	);

	ob_start();
	$overview->render();
	$markup = (string) ob_get_clean();
	$check(
		4 === substr_count( $markup, 'data-sp-overview-stat=' )
		&& false !== strpos( $markup, 'data-sp-overview-stat="drafts-created"' ),
		'Overview keeps the mockup four-metric contract'
	);
	$check(
		6 === substr_count( $markup, 'data-sp-overview-running=' )
		&& false !== strpos( $markup, 'data-sp-overview-running="form-composer"' ),
		'Overview keeps all six mockup running-now integrations including Form Studio'
	);
} finally {
	if ( $generated_draft_id > 0 ) {
		wp_delete_post( $generated_draft_id, true );
	}
	if ( $unrelated_draft_id > 0 ) {
		wp_delete_post( $unrelated_draft_id, true );
	}
	if ( '' !== $conversation_token ) {
		global $wpdb;
		$wpdb->delete( $store->table(), array( 'session_token' => $conversation_token ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- removes only this script's named test conversation.
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
