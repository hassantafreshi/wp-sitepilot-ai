<?php
/**
 * Creates and removes the isolated Content Studio History browser fixture.
 *
 * It creates one marker-owned draft and one ordinary page, then removes only
 * posts carrying this fixture marker. No provider, Activity Log, or content
 * body is involved.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_content_studio_history_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

const FIXTURE_OPTION = 'aisaautopilot_content_studio_history_browser_fixture';
const FIXTURE_META   = '_aisaautopilot_content_studio_history_browser_fixture';

$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, array() );
	if ( ! is_array( $record ) ) {
		delete_option( FIXTURE_OPTION );
		return;
	}

	foreach ( array( 'owned_id', 'unmarked_id' ) as $key ) {
		$post_id = absint( $record[ $key ] ?? 0 );
		if ( $post_id > 0 && '1' === get_post_meta( $post_id, FIXTURE_META, true ) ) {
			wp_delete_post( $post_id, true );
		}
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Content Studio History browser fixture removed.\n";
	exit( 0 );
}

$restore();
$original_user_id = get_current_user_id();
$owned_id         = 0;
$unmarked_id      = 0;

try {
	wp_set_current_user( 1 );
	$suffix         = wp_generate_password( 10, false, false );
	$owned_title    = 'AISAAutoPilot Content Studio browser history ' . $suffix;
	$unmarked_title = 'AISAAutoPilot Content Studio browser unmarked ' . $suffix;
	$owned_id       = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => $owned_title,
		),
		true
	);
	if ( is_wp_error( $owned_id ) ) {
		throw new RuntimeException( $owned_id->get_error_message() );
	}
	$owned_id = (int) $owned_id;
	if ( $owned_id <= 0 ) {
		throw new RuntimeException( 'WordPress did not create the owned Content Studio History fixture.' );
	}
	update_post_meta( $owned_id, '_aisaautopilot_generated', '1' );
	update_post_meta( $owned_id, FIXTURE_META, '1' );

	$unmarked_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => $unmarked_title,
		),
		true
	);
	if ( is_wp_error( $unmarked_id ) ) {
		throw new RuntimeException( $unmarked_id->get_error_message() );
	}
	$unmarked_id = (int) $unmarked_id;
	if ( $unmarked_id <= 0 ) {
		throw new RuntimeException( 'WordPress did not create the unmarked Content Studio History fixture.' );
	}
	update_post_meta( $unmarked_id, FIXTURE_META, '1' );

	update_option(
		FIXTURE_OPTION,
		array(
			'owned_id'    => $owned_id,
			'unmarked_id' => $unmarked_id,
		),
		false
	);

	echo wp_json_encode(
		array(
			'title'          => $owned_title,
			'unmarked_title' => $unmarked_title,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $owned_id > 0 && '1' === get_post_meta( $owned_id, FIXTURE_META, true ) ) {
		wp_delete_post( $owned_id, true );
	}
	if ( $unmarked_id > 0 && '1' === get_post_meta( $unmarked_id, FIXTURE_META, true ) ) {
		wp_delete_post( $unmarked_id, true );
	}
	delete_option( FIXTURE_OPTION );
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
} finally {
	wp_set_current_user( $original_user_id );
}
