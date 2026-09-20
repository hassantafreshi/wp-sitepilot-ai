<?php
/**
 * Creates and restores the unanswered questions and saved answers browser-QA fixture.
 *
 * Prepare snapshots the gap and saved-answer options (including "the option did not exist"), replaces
 * them with three marker-named questions (one last asked ten days ago, one linked to a Content Studio
 * draft) and one saved answer, and creates that draft. Restore deletes the draft only when its title
 * still carries the marker, and puts both options back exactly. No AI service is contacted.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_kb_answers_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\SavedAnswers;

const FIXTURE_OPTION  = 'aisaautopilot_kb_answers_browser_fixture';
const FIXTURE_MARKER  = 'AutoPilot QA · ';
const FIXTURE_OPTIONS = array( 'aisaautopilot_kb_gaps', 'aisaautopilot_kb_saved_answers' );

$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch real questions or answers here.
	if ( ! is_array( $record ) ) {
		return;
	}
	foreach ( (array) ( $record['posts'] ?? array() ) as $post_id ) {
		$post = get_post( (int) $post_id );
		if ( $post instanceof WP_Post && 0 === strpos( $post->post_title, FIXTURE_MARKER ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( FIXTURE_OPTIONS as $option ) {
		$snapshot = (array) ( $record['options'][ $option ] ?? array() );
		if ( ! empty( $snapshot['exists'] ) ) {
			update_option( $option, $snapshot['value'], false );
		} else {
			delete_option( $option );
		}
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Unanswered questions browser fixture removed.\n";
	exit( 0 );
}

$restore();

try {
	$options = array();
	foreach ( FIXTURE_OPTIONS as $option ) {
		$value              = get_option( $option, null );
		$options[ $option ] = null === $value ? array( 'exists' => false ) : array(
			'exists' => true,
			'value'  => $value,
		);
	}
	// Persist the snapshot before anything changes, so an interrupted prepare can still be restored.
	update_option(
		FIXTURE_OPTION,
		array(
			'options' => $options,
			'posts'   => array(),
		),
		false
	);

	$draft_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => FIXTURE_MARKER . 'Return window answer draft',
			'post_content' => '<p>Temporary draft for the unanswered questions browser check.</p>',
		),
		true
	);
	if ( is_wp_error( $draft_id ) ) {
		throw new RuntimeException( $draft_id->get_error_message() );
	}
	$draft_id = (int) $draft_id;
	update_post_meta( $draft_id, '_aisaautopilot_generated', '1' );
	update_option(
		FIXTURE_OPTION,
		array(
			'options' => $options,
			'posts'   => array( $draft_id ),
		),
		false
	);

	$now       = time();
	$questions = array(
		'ship'    => array( FIXTURE_MARKER . 'Do you ship to Canada?', 9, 'no_context', $now - HOUR_IN_SECONDS, 0 ),
		'returns' => array( FIXTURE_MARKER . 'How long is the return window?', 7, 'model_not_found', $now - 2 * DAY_IN_SECONDS, $draft_id ),
		'old'     => array( FIXTURE_MARKER . 'Do you still sell the old catalogue?', 2, 'empty_answer', $now - 10 * DAY_IN_SECONDS, 0 ),
	);
	$gaps      = array();
	$hashes    = array();
	foreach ( $questions as $id => $question ) {
		$hash          = KbGaps::key( $question[0] );
		$hashes[ $id ] = $hash;
		$gaps[ $hash ] = array(
			'question'      => $question[0],
			'language'      => '',
			'count'         => $question[1],
			'first_seen'    => gmdate( 'c', $question[3] - DAY_IN_SECONDS ),
			'last_seen'     => gmdate( 'c', $question[3] ),
			'status'        => 'open',
			'reason'        => $question[2],
			'draft_post_id' => $question[4],
		);
	}
	update_option( 'aisaautopilot_kb_gaps', $gaps, false );

	$answer_question = FIXTURE_MARKER . 'What are your opening hours?';
	$answer_id       = SavedAnswers::key( $answer_question );
	update_option(
		'aisaautopilot_kb_saved_answers',
		array(
			$answer_id => array(
				'question'  => $answer_question,
				'answer'    => 'We are open Monday to Friday, 9:00 to 17:00.',
				'created'   => gmdate( 'c', $now - DAY_IN_SECONDS ),
				'updated'   => gmdate( 'c', $now - DAY_IN_SECONDS ),
				'hits'      => 2,
				'last_used' => gmdate( 'c', $now - HOUR_IN_SECONDS ),
			),
		),
		false
	);

	echo wp_json_encode(
		array(
			'ship'     => $hashes['ship'],
			'returns'  => $hashes['returns'],
			'old'      => $hashes['old'],
			'answer'   => $answer_id,
			'draft_id' => $draft_id,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
