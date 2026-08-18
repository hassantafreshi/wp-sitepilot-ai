<?php
/**
 * Creates one owned administrator account for local browser release checks.
 * The account can be removed with the `delete` command after testing.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'create';
if ( ! in_array( $mode, array( 'create', 'delete' ), true ) ) {
	fwrite( STDERR, "Usage: php local_browser_user.php create|delete\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

const OPTION = 'aisaautopilot_local_browser_user';

if ( 'delete' === $mode ) {
	$record = get_option( OPTION, array() );
	$user_id = is_array( $record ) ? absint( $record['user_id'] ?? 0 ) : 0;
	if ( $user_id > 0 && get_userdata( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	delete_option( OPTION );
	echo "Local browser QA account removed.\n";
	exit( 0 );
}

$existing = get_option( OPTION, array() );
if ( is_array( $existing ) && ! empty( $existing['user_id'] ) && get_userdata( (int) $existing['user_id'] ) ) {
	echo wp_json_encode( $existing ) . PHP_EOL;
	exit( 0 );
}

$username = 'aisaautopilot_release_qa_' . strtolower( wp_generate_password( 8, false, false ) );
$password = wp_generate_password( 28, true, true );
$email    = $username . '@example.test';
$user_id  = wp_create_user( $username, $password, $email );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, $user_id->get_error_message() . PHP_EOL );
	exit( 1 );
}

$user = new WP_User( $user_id );
$user->set_role( 'administrator' );
$record = array( 'user_id' => (int) $user_id, 'username' => $username, 'password' => $password );
update_option( OPTION, $record, false );
echo wp_json_encode( $record ) . PHP_EOL;
