<?php
/**
 * Local WordPress contracts for unanswered questions, "Write it" and saved answers.
 *
 * Covers reasons, counts, the 7-day window, entries stored before reasons were kept, a write that
 * another request made stale, the Content Studio link (source_gap in the stored proposal and the
 * draft link after approval), and saved answers: the REST routes, matching in ChatEngine without an
 * AI request, editing, duplicates, limits and permissions. Every outbound HTTP request is refused and
 * counted. The gap, saved-answer, log and settings options are restored in finally, and the draft
 * created here is deleted.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ContentController;
use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\SavedAnswers;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Execution\ContentDraftExecutor;
use AISAAutoPilot\Skills\ContentPlanValidator;

$missing       = new stdClass();
$option_names  = array( 'aisaautopilot_kb_gaps', 'aisaautopilot_kb_saved_answers', 'aisaautopilot_logs', 'aisaautopilot_settings' );
$snapshots     = array();
foreach ( $option_names as $option_name ) {
	$snapshots[ $option_name ] = get_option( $option_name, $missing );
}
$original_user = get_current_user_id();
$checks        = array();
$http_calls    = 0;
$created_posts = array();
$subscriber_id = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$block_http = static function () use ( &$http_calls ) {
	++$http_calls;
	return new WP_Error( 'aisaautopilot_test_blocked', 'Outbound HTTP is blocked in this test.' );
};

$rest = static function ( string $method, string $route, array $params = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/aisaautopilot/v1/' . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

try {
	add_filter( 'pre_http_request', $block_http, PHP_INT_MAX );
	wp_set_current_user( 1 );
	delete_option( 'aisaautopilot_kb_gaps' );
	delete_option( 'aisaautopilot_kb_saved_answers' );
	$gaps = new KbGaps();

	// 1. Reasons, counts and older entries.
	$legacy_question = 'Do you still sell the old catalogue?';
	update_option(
		'aisaautopilot_kb_gaps',
		array(
			KbGaps::key( $legacy_question ) => array(
				'question' => $legacy_question,
				'language' => '',
				'count'    => 3,
				'first'    => '2026-09-01 08:00:00',
				'last'     => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ),
				'status'   => 'open',
			),
		),
		false
	);
	$questions = array(
		'no_context'      => 'Do you ship to Canada?',
		'model_not_found' => 'How long is the return window?',
		'empty_answer'    => 'Is there a student discount?',
		'no_citation'     => 'Do you offer gift wrapping?',
	);
	foreach ( $questions as $reason => $question ) {
		$gaps->record( $question, '', $reason );
	}
	$gaps->record( 'do you ship to   CANADA?', '', 'no_context' );
	$list   = $gaps->open_list( 50 );
	$by_key = array();
	foreach ( $list as $item ) {
		$by_key[ $item['hash'] ] = $item;
	}
	$canada = $by_key[ KbGaps::key( $questions['no_context'] ) ] ?? array();
	$legacy = $by_key[ KbGaps::key( $legacy_question ) ] ?? array();
	$check(
		5 === count( $list ) && 2 === ( $canada['count'] ?? 0 ) && 'no_context' === ( $canada['reason'] ?? '' )
		&& 'Asked 2 times' === ( $canada['asked_label'] ?? '' ) && '' !== ( $canada['reason_label'] ?? '' )
		&& 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T/', (string) ( $canada['last_seen'] ?? '' ) ) && array_key_exists( 'draft', $canada ) && null === $canada['draft'],
		'A repeated question bumps one entry that keeps its reason, count, readable labels and an ISO last-seen time'
	);
	$all_reasons = array_map(
		static function ( string $question ) use ( $by_key ): string {
			return (string) ( $by_key[ KbGaps::key( $question ) ]['reason'] ?? '' );
		},
		$questions
	);
	$check( array_keys( $questions ) === array_values( $all_reasons ), 'Each of the four reasons is stored for its question' );
	$check(
		3 === ( $legacy['count'] ?? 0 ) && '' === ( $legacy['reason'] ?? 'x' ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T/', (string) ( $legacy['last_seen'] ?? '' ) )
		&& 'Recorded before reasons were kept.' === ( $legacy['reason_label'] ?? '' ),
		'An entry stored before reasons were kept is read with defaults instead of breaking'
	);

	// 2. The 7-day window, in the class and the REST route.
	$recent_count = count( $gaps->open_list( 50, '7d' ) );
	$windowed     = $rest( 'GET', 'kb/gaps', array( 'window' => '7d' ) );
	$window_data  = (array) $windowed->get_data();
	$first_item   = (array) ( $window_data['items'][0] ?? array() );
	$check(
		4 === $recent_count && 5 === $gaps->open_count() && 4 === $gaps->open_count( '7d' )
		&& 200 === $windowed->get_status() && 4 === ( $window_data['total'] ?? 0 ) && '7d' === ( $window_data['window'] ?? '' )
		&& false === strpos( wp_json_encode( $window_data['items'] ?? array() ), 'old catalogue' ),
		'Last 7 days leaves out a question last asked ten days ago, in the list, the count and GET kb/gaps'
	);
	$write_url = (string) ( $first_item['write_url'] ?? '' );
	$check(
		false !== strpos( $write_url, 'page=aisaautopilot-form-builder' ) && false !== strpos( $write_url, 'tab=write' )
		&& false !== strpos( $write_url, 'source_gap=' . ( $first_item['hash'] ?? 'x' ) ) && false !== strpos( urldecode( $write_url ), (string) ( $first_item['question'] ?? 'x' ) ),
		'Write it opens Content Studio Write with the question in an editable brief and the gap reference'
	);

	// 3. A write made stale by another request does not lose that request's question.
	$gaps->open_list();
	global $wpdb;
	$stored                                        = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'aisaautopilot_kb_gaps' ) ) );
	$stored[ KbGaps::key( 'Other request question' ) ] = array(
		'question' => 'Other request question',
		'count'    => 1,
		'status'   => 'open',
	);
	$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $stored ) ), array( 'option_name' => 'aisaautopilot_kb_gaps' ) ); // phpcs:ignore WordPress.DB -- simulates a concurrent writer behind this request's cache.
	$gaps->record( 'This request question', '', 'no_context' );
	wp_cache_delete( 'aisaautopilot_kb_gaps', 'options' );
	$after_race = $gaps->all();
	$check(
		isset( $after_race[ KbGaps::key( 'Other request question' ) ], $after_race[ KbGaps::key( 'This request question' ) ] ),
		'Recording a question keeps one that another request stored after this request cached the list'
	);

	// 4. Content Studio: source_gap is checked before any AI request, kept in the proposal and linked after approval.
	$canada_hash = KbGaps::key( $questions['no_context'] );
	$plan_call   = static function ( string $source_gap ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/content/plan' );
		$request->set_param( 'prompt', 'Write a help article about shipping to Canada.' );
		$request->set_param( 'source_gap', $source_gap );
		return ( new ContentController() )->plan( $request );
	};
	$http_before = $http_calls;
	$refused     = $plan_call( str_repeat( 'a', 32 ) );
	$malformed   = $plan_call( '../../etc' );
	$check(
		400 === $refused->get_status() && 'invalid_source_gap' === ( $refused->get_data()['code'] ?? '' )
		&& 400 === $malformed->get_status() && 'invalid_source_gap' === ( $malformed->get_data()['code'] ?? '' )
		&& $http_before === $http_calls,
		'content/plan refuses a source_gap that is not an open question before contacting an AI service'
	);

	$validated = ( new ContentPlanValidator() )->validate(
		array(
			'content' => array(
				'post_type'        => 'post',
				'title'            => 'AutoPilot QA gap draft ' . wp_generate_password( 6, false, false ),
				'body'             => '<p>We ship to Canada within ten working days.</p>',
				'meta_description' => 'Shipping to Canada.',
			),
		)
	);
	$store     = new ActionStore();
	$action_id = $store->create( $validated->plan, 'content_draft', array( 'source_gap' => $canada_hash ) );
	$bogus_id  = $store->create( $validated->plan, 'content_draft', array( 'source_gap' => 'not-a-hash' ) );
	$record    = (array) $store->get( $action_id );
	$check(
		$canada_hash === ( $record['execution_context']['source_gap'] ?? '' ) && '' === ( ( (array) $store->get( $bogus_id ) )['execution_context']['source_gap'] ?? 'x' ),
		'The proposal stores a valid gap reference and drops a malformed one'
	);
	$executed = ( new ContentDraftExecutor( $store ) )->approve_and_execute( $action_id );
	$post_id  = (int) ( $executed['post_id'] ?? 0 );
	if ( $post_id > 0 ) {
		$created_posts[] = $post_id;
	}
	$linked = null;
	foreach ( $gaps->open_list( 50 ) as $item ) {
		if ( $canada_hash === $item['hash'] ) {
			$linked = $item;
		}
	}
	$check(
		'success' === ( $executed['status'] ?? '' ) && $post_id > 0 && 'draft' === get_post_status( $post_id )
		&& $post_id === ( $linked['draft_post_id'] ?? 0 ) && $post_id === ( $linked['draft']['id'] ?? 0 )
		&& false !== strpos( (string) ( $linked['draft']['url'] ?? '' ), 'post=' . $post_id )
		&& $canada_hash === get_post_meta( $post_id, '_aisaautopilot_source_gap', true ),
		'Approving the draft links it to the question, which stays open and now offers Open draft'
	);
	wp_set_current_user( 0 );
	$guest_view = array_values( array_filter( $gaps->open_list( 50 ), static fn( array $item ): bool => $canada_hash === $item['hash'] ) );
	$check(
		$post_id > 0 && 1 === count( $guest_view ) && $post_id === $guest_view[0]['draft_post_id'] && null === $guest_view[0]['draft'],
		'Open draft is only offered to someone who can edit the draft'
	);
	wp_set_current_user( 1 );
	// The unapproved proposal made only to check the stored context is removed; ActionStore keeps proposals in transients.
	delete_transient( 'aisaautopilot_action_' . $bogus_id );

	// 5. Saved answers through REST, matched in ChatEngine without an AI request.
	$gift_hash = KbGaps::key( $questions['no_citation'] );
	$saved     = $rest(
		'POST',
		'kb/answers',
		array(
			'gap'    => $gift_hash,
			'answer' => "Yes — gift wrapping costs 3 < 4 euros & includes a card.\nChoose it at checkout.",
		)
	);
	$saved_id  = (string) ( $saved->get_data()['item']['id'] ?? '' );
	$check(
		200 === $saved->get_status() && 'created' === ( $saved->get_data()['code'] ?? '' ) && true === ( $saved->get_data()['gap_dismissed'] ?? null )
		&& null === $gaps->get( $gift_hash )
		&& "Yes — gift wrapping costs 3 < 4 euros & includes a card.\nChoose it at checkout." === ( ( new SavedAnswers() )->all()[ $saved_id ]['answer'] ?? '' ),
		'Answering a question saves the text as typed, takes the question from the gap and dismisses it'
	);
	$http_before = $http_calls;
	$engine      = new ChatEngine( new KnowledgeBase() );
	$reply       = $engine->ask( 'do you OFFER gift-wrapping' );
	$check(
		'answered' === ( $reply['status'] ?? '' ) && true === ( $reply['saved_answer'] ?? null )
		&& false !== strpos( (string) ( $reply['answer'] ?? '' ), 'gift wrapping costs' ) && array() === ( $reply['sources'] ?? null )
		&& $http_before === $http_calls && 1 === ( ( new SavedAnswers() )->all()[ $saved_id ]['hits'] ?? 0 ),
		'The same question in other case and punctuation gets the saved answer with no AI request, and the use is counted'
	);
	$followup = $engine->ask( 'do you offer gift wrapping', "User: hello\nAssistant: hi" );
	$check(
		empty( $followup['saved_answer'] ) && null === ( new SavedAnswers() )->find( 'Can you wrap my gift?' ),
		'A follow-up question and a differently worded question are not answered from the saved text'
	);

	$persian = $rest(
		'POST',
		'kb/answers',
		array(
			'question' => 'هزینهٔ ارسال به کرج چقدر است؟',
			'answer'   => 'ارسال به کرج رایگان است.',
		)
	);
	$reply_fa = $engine->ask( 'هزينهٔ ارسال به كرج چقدر است' );
	$check(
		200 === $persian->get_status() && true === ( $reply_fa['saved_answer'] ?? null ) && 'ارسال به کرج رایگان است.' === ( $reply_fa['answer'] ?? '' ),
		'A Persian question typed with Arabic letter forms and no question mark matches its saved answer'
	);

	$edited = $rest(
		'POST',
		'kb/answers',
		array(
			'id'       => $saved_id,
			'question' => 'Can you gift wrap my order?',
			'answer'   => 'Yes, at checkout.',
		)
	);
	$edited_id = (string) ( $edited->get_data()['item']['id'] ?? '' );
	$all       = ( new SavedAnswers() )->all();
	$check(
		200 === $edited->get_status() && 'updated' === ( $edited->get_data()['code'] ?? '' ) && $edited_id !== $saved_id
		&& ! isset( $all[ $saved_id ] ) && 1 === ( $all[ $edited_id ]['hits'] ?? 0 ),
		'Rewording a saved question moves the answer to the new wording and keeps its use count'
	);
	$duplicate = $rest(
		'POST',
		'kb/answers',
		array(
			'id'       => $edited_id,
			'question' => 'هزینه ارسال به کرج چقدر است',
			'answer'   => 'Duplicate',
		)
	);
	$refusals  = array(
		$rest( 'POST', 'kb/answers', array( 'question' => '?!', 'answer' => 'x' ) ),
		$rest( 'POST', 'kb/answers', array( 'question' => 'Opening hours?', 'answer' => '   ' ) ),
		$rest( 'POST', 'kb/answers', array( 'question' => 'Opening hours?', 'answer' => str_repeat( 'a', SavedAnswers::ANSWER_MAX + 1 ) ) ),
		$rest( 'POST', 'kb/answers', array( 'gap' => str_repeat( 'b', 32 ), 'answer' => 'x' ) ),
	);
	$check(
		400 === $duplicate->get_status() && 'duplicate_question' === ( $duplicate->get_data()['code'] ?? '' )
		&& array( 'invalid_question', 'invalid_answer', 'invalid_answer', 'gap_not_found' ) === array_map(
			static function ( WP_REST_Response $response ): string {
				return (string) ( $response->get_data()['code'] ?? '' );
			},
			$refusals
		),
		'A duplicate question, an empty question or answer, a too-long answer and a missing gap are refused'
	);

	$listed  = $rest( 'GET', 'kb/answers' );
	$deleted = $rest( 'POST', 'kb/answers/delete', array( 'id' => $edited_id ) );
	$again   = $rest( 'POST', 'kb/answers/delete', array( 'id' => $edited_id ) );
	$check(
		200 === $listed->get_status() && 2 === count( (array) ( $listed->get_data()['items'] ?? array() ) )
		&& 200 === $deleted->get_status() && 404 === $again->get_status() && null === ( new SavedAnswers() )->find( 'Can you gift wrap my order?' ),
		'Saved answers are listed and one can be deleted once'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_answers_' . wp_generate_password( 10, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'answers-' . wp_generate_password( 10, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	wp_set_current_user( $subscriber_id );
	$denied = array(
		$rest( 'GET', 'kb/answers' )->get_status(),
		$rest( 'POST', 'kb/answers', array( 'question' => 'Q?', 'answer' => 'A' ) )->get_status(),
		$rest( 'POST', 'kb/answers/delete', array( 'id' => 'x' ) )->get_status(),
		$rest( 'GET', 'kb/gaps' )->get_status(),
	);
	wp_set_current_user( 1 );
	$check( $subscriber_id > 0 && array() === array_diff( $denied, array( 401, 403 ) ), 'Every saved-answer and gap route requires manage_options' );

	// 6. The Site Knowledge overview carries the window filter and the saved answers card.
	$_GET['page'] = 'aisaautopilot-kb';
	ob_start();
	( new \AISAAutoPilot\Admin\KnowledgeBasePage() )->render();
	$page = (string) ob_get_clean();
	unset( $_GET['page'] );
	$check(
		false !== strpos( $page, 'data-sp-kb-answers' ) && false !== strpos( $page, 'data-sp-kb-gaps-window="7d"' )
		&& false !== strpos( $page, 'data-sp-kb-answer-add' ) && false !== strpos( $page, '"answerIt"' ),
		'Site Knowledge shows the time filter, the saved answers card and the strings its script needs'
	);
} catch ( Throwable $error ) {
	$check( false, 'Unanswered question contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	remove_filter( 'pre_http_request', $block_http, PHP_INT_MAX );
	foreach ( $created_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $subscriber_id > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $subscriber_id );
	}
	foreach ( $snapshots as $option_name => $value ) {
		if ( $value === $missing ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $value, false );
		}
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
