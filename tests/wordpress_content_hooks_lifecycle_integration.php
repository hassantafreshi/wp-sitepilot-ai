<?php
/**
 * Local WordPress integration check for Site Knowledge lifecycle cleanup.
 *
 * The parent process temporarily turns automatic indexing off, then starts a
 * clean WordPress process. That makes the child prove the Plugin registered
 * ContentHooks at boot while auto-indexing was disabled. The test seeds its
 * KB rows directly, so no provider or network call is ever made.
 */
declare(strict_types=1);

$is_child = isset( $argv[1] ) && '--verify-auto-index-off' === $argv[1];
$wp_root  = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Content\ContentHooks;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;

/**
 * Restore the raw settings option exactly as it existed before the parent test.
 *
 * @param mixed  $original Original option value.
 * @param object $missing  Sentinel used when the option did not exist.
 */
$restore_settings = static function ( $original, object $missing ): void {
	if ( $original === $missing ) {
		delete_option( Settings::OPTION );
		return;
	}

	update_option( Settings::OPTION, $original, false );
};

if ( ! $is_child ) {
	$missing  = new stdClass();
	$original = get_option( Settings::OPTION, $missing );
	$output   = '';
	$exit     = 1;

	try {
		Settings::update( array( 'kb_auto_index' => false ) );

		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --verify-auto-index-off';
		$process = proc_open(
			$command,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			dirname( __DIR__ )
		);
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Could not start the isolated WordPress lifecycle check.' );
		}

		$output .= stream_get_contents( $pipes[1] );
		$error   = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );
		if ( '' !== $error ) {
			$output .= $error;
		}
	} catch ( Throwable $error ) {
		$output .= 'FAIL ' . $error->getMessage() . PHP_EOL;
	} finally {
		$restore_settings( $original, $missing );
	}

	echo $output;
	exit( $exit );
}

wp_set_current_user( 1 );

$checks            = array();
$unpublish_post_id = 0;
$trash_post_id     = 0;
$knowledge_base    = new KnowledgeBase();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$has_content_hook = static function ( string $hook, string $method ): bool {
	global $wp_filter;

	if ( ! isset( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof WP_Hook ) {
		return false;
	}

	foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $registered ) {
			$callback = $registered['function'] ?? null;
			if (
				is_array( $callback )
				&& isset( $callback[0], $callback[1] )
				&& $callback[0] instanceof ContentHooks
				&& $method === $callback[1]
			) {
				return true;
			}
		}
	}

	return false;
};

$create_published_post = static function ( string $title ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => 'Temporary Site Knowledge lifecycle test content.',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	return wp_update_post(
		array(
			'ID'          => (int) $post_id,
			'post_status' => 'publish',
		),
		true
	);
};

$seed_entry = static function ( KnowledgeBase $kb, int $post_id, string $title ): bool {
	return $kb->upsert(
		array(
			'post_id'      => $post_id,
			'post_type'    => 'post',
			'title'        => $title,
			'summary'      => 'Direct local test fixture; no AI provider is used.',
			'keywords'     => 'test, lifecycle',
			'category'     => 'Testing',
			'content_type' => 'article',
			'content_hash' => hash( 'sha256', (string) $post_id ),
			'token_count'  => 1,
		)
	);
};

try {
	$knowledge_base->create_table();

	$check(
		! Settings::get( 'kb_auto_index', true ),
		'The isolated WordPress process booted with automatic Site Knowledge indexing off'
	);
	$check(
		$has_content_hook( 'transition_post_status', 'on_status_change' )
		&& $has_content_hook( 'wp_trash_post', 'on_trash' )
		&& $has_content_hook( 'deleted_post', 'on_delete' ),
		'Plugin registers ContentHooks lifecycle cleanup hooks even when auto-indexing is off'
	);

	$unpublish_post_id = $create_published_post( 'AISAAutoPilot lifecycle unpublish fixture' );
	if ( is_wp_error( $unpublish_post_id ) ) {
		throw new RuntimeException( $unpublish_post_id->get_error_message() );
	}
	$unpublish_post_id = (int) $unpublish_post_id;
	wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $unpublish_post_id ) );
	$check(
		false === wp_next_scheduled( 'aisaautopilot_index_post', array( $unpublish_post_id ) ),
		'Publishing while auto-indexing is off schedules no deferred provider work'
	);
	$check(
		$seed_entry( $knowledge_base, $unpublish_post_id, 'AISAAutoPilot lifecycle unpublish fixture' )
		&& null !== $knowledge_base->get_by_post_id( $unpublish_post_id ),
		'The unpublish fixture is seeded directly in the Knowledge Base without an external provider'
	);
	$unpublish_result = wp_update_post(
		array(
			'ID'          => $unpublish_post_id,
			'post_status' => 'draft',
		),
		true
	);
	$check(
		! is_wp_error( $unpublish_result ) && null === $knowledge_base->get_by_post_id( $unpublish_post_id ),
		'An auto-index-disabled published item is removed from Site Knowledge when unpublished'
	);

	$trash_post_id = $create_published_post( 'AISAAutoPilot lifecycle trash fixture' );
	if ( is_wp_error( $trash_post_id ) ) {
		throw new RuntimeException( $trash_post_id->get_error_message() );
	}
	$trash_post_id = (int) $trash_post_id;
	$check(
		$seed_entry( $knowledge_base, $trash_post_id, 'AISAAutoPilot lifecycle trash fixture' )
		&& null !== $knowledge_base->get_by_post_id( $trash_post_id ),
		'The trash fixture is seeded directly in the Knowledge Base without an external provider'
	);
	$trash_result = wp_trash_post( $trash_post_id );
	$check(
		false !== $trash_result && null === $knowledge_base->get_by_post_id( $trash_post_id ),
		'An auto-index-disabled published item is removed from Site Knowledge when trashed'
	);
} finally {
	foreach ( array( $unpublish_post_id, $trash_post_id ) as $post_id ) {
		if ( $post_id <= 0 ) {
			continue;
		}
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );
		$knowledge_base->delete_by_post_id( $post_id );
		wp_delete_post( $post_id, true );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
