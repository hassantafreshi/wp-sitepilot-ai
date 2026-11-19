<?php
/**
 * Creates and restores the isolated Activity Log browser-QA fixture.
 *
 * The fixture owns one generated draft plus the complete replacement log
 * option. Restore removes only the marker-owned draft and reinstates the exact
 * prior log value (including option absence).
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'prepare-empty', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_activity_log_browser_fixture.php prepare|prepare-empty|restore\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

const FIXTURE_OPTION = 'aisaautopilot_activity_log_browser_fixture';
const FIXTURE_META   = '_aisaautopilot_activity_log_browser_fixture';
const LOG_OPTION     = 'aisaautopilot_logs';

$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch the live log here.
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! is_array( $record['log'] ?? null ) || ! array_key_exists( 'exists', $record['log'] ) ) {
		throw new RuntimeException( 'Activity Log fixture snapshot is invalid; live data was left untouched.' );
	}
	$live = get_option( LOG_OPTION, array() );
	$unowned = array_values( array_filter( is_array( $live ) ? $live : array(), static function ( $entry ): bool {
		return ! is_array( $entry ) || 0 !== strpos( (string) ( $entry['message'] ?? '' ), 'AISAAutoPilot Activity Log browser ' );
	} ) );

	$post_id = absint( $record['post_id'] ?? 0 );
	if ( $post_id > 0 && '1' === (string) get_post_meta( $post_id, FIXTURE_META, true ) ) {
		wp_delete_post( $post_id, true );
	}

	$log_snapshot = (array) ( $record['log'] ?? array() );
	$saved = $log_snapshot['value'] ?? null;
	// Preserve events emitted by other requests during QA as well as the snapshot.
	$merged = is_array( $saved ) ? $saved : array();
	foreach ( $unowned as $entry ) {
		if ( ! in_array( $entry, $merged, true ) ) {
			$merged[] = $entry;
		}
	}
	if ( array() !== $unowned ) {
		update_option( LOG_OPTION, $merged, false );
	} elseif ( ! empty( $log_snapshot['exists'] ) ) {
		update_option( LOG_OPTION, $saved, false );
	} else {
		delete_option( LOG_OPTION );
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Activity Log browser fixture removed.\n";
	exit( 0 );
}

$restore();
$missing          = new stdClass();
$original_log     = get_option( LOG_OPTION, $missing );
$original_user_id = get_current_user_id();
$post_id          = 0;

try {
	// Persist the snapshot before creating anything that may itself emit logs.
	update_option(
		FIXTURE_OPTION,
		array(
			'post_id' => 0,
			'log'     => array(
				'exists' => $original_log !== $missing,
				'value'  => $original_log !== $missing ? $original_log : null,
			),
		),
		false
	);
	if ( 'prepare-empty' === $mode ) {
		update_option( LOG_OPTION, array(), false );
		echo "{}\n";
		exit( 0 );
	}
	wp_set_current_user( 1 );
	$suffix        = wp_generate_password( 10, false, false );
	$draft_message = 'AISAAutoPilot Activity Log browser draft ' . $suffix;
	$error_message = 'AISAAutoPilot Activity Log browser issue ' . $suffix;
	$draft_id      = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => $draft_message,
		),
		true
	);
	if ( is_wp_error( $draft_id ) || ! is_numeric( $draft_id ) ) {
		$message = is_wp_error( $draft_id ) ? $draft_id->get_error_message() : 'WordPress did not create the Activity Log browser draft.';
		throw new RuntimeException( $message );
	}
	$post_id = (int) $draft_id;
	if ( $post_id <= 0 ) {
		throw new RuntimeException( 'WordPress did not create the Activity Log browser draft.' );
	}
	update_post_meta( $post_id, '_aisaautopilot_generated', '1' );
	update_post_meta( $post_id, FIXTURE_META, '1' );

	update_option(
		FIXTURE_OPTION,
		array(
			'post_id' => $post_id,
			'log'     => array(
				'exists' => $original_log !== $missing,
				'value'  => $original_log !== $missing ? $original_log : null,
			),
		),
		false
	);

	$now = time();
	update_option(
		LOG_OPTION,
		array(
			array(
				'time'    => gmdate( 'c', $now - 300 ),
				'level'   => 'info',
				'event'   => 'handoff_email',
				'message' => 'AISAAutoPilot Activity Log browser handoff ' . $suffix,
				'context' => array(),
			),
			array(
				'time'    => gmdate( 'c', $now - 180 ),
				'level'   => 'info',
				'event'   => 'content.draft_created',
				'message' => $draft_message,
				'context' => array( 'post_id' => $post_id ),
			),
			array(
				'time'    => gmdate( 'c', $now - 90 ),
				'level'   => 'info',
				'event'   => 'woo.draft.created',
				'message' => 'AISAAutoPilot Activity Log browser missing product ' . $suffix,
				'context' => array( 'product_id' => 999999 ),
			),
			array(
				'time'    => gmdate( 'c', $now - 30 ),
				'level'   => 'error',
				'event'   => 'kb_index',
				'message' => $error_message,
				'context' => array(),
			),
		),
		false
	);

	echo wp_json_encode(
		array(
			'draft_message' => $draft_message,
			'error_message' => $error_message,
			'post_id'       => $post_id,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $post_id > 0 && '1' === (string) get_post_meta( $post_id, FIXTURE_META, true ) ) {
		wp_delete_post( $post_id, true );
	}
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
} finally {
	wp_set_current_user( $original_user_id );
}
