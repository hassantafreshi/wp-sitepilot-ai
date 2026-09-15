<?php
/**
 * Local WordPress integration checks for the compact Human handoff endpoint.
 *
 * No provider request or test delivery is made. The test is intentionally
 * limited to capability checks, validation, encrypted credential storage and
 * the visitor-availability gate.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_secrets  = get_option( Settings::HANDOFF_SECRET_OPTION, $missing );
$original_user_id  = get_current_user_id();
$checks            = array();
$subscriber_id     = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$dispatch = static function ( int $user_id, array $params ): \WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new \WP_REST_Request( 'POST', '/aisaautopilot/v1/handoff/admin-settings' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	return rest_do_request( $request );
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array_merge(
				Settings::widget_defaults(),
				array(
					'enabled'                  => true,
					'title'                    => 'Keep this title',
					'voice_name'               => 'Keep this voice',
					'handoff_enabled'          => false,
					'handoff_email_enabled'    => false,
					'handoff_email_to'         => '',
					'handoff_telegram_enabled' => false,
					'handoff_telegram_chat'    => '',
					'handoff_twilio_enabled'   => false,
					'handoff_twilio_from'      => '',
					'handoff_twilio_to'        => '',
				)
			),
		)
	);
	$before = (array) Settings::get( 'widget', array() );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes['/aisaautopilot/v1/handoff/admin-settings'] ),
		'Human handoff stores channel settings through a separate protected route'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_handoff_' . wp_generate_password( 12, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'handoff-' . wp_generate_password( 12, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	$subscriber    = $dispatch(
		$subscriber_id,
		array(
			'channel'       => 'telegram',
			'enabled'       => true,
			'telegram_chat' => '-1001234567890',
		)
	);
	$check(
		$subscriber_id > 0 && in_array( $subscriber->get_status(), array( 401, 403 ), true ) && $before === (array) Settings::get( 'widget', array() ),
		'Human handoff channel updates require the manage_options capability'
	);

	$updated = $dispatch(
		1,
		array(
			'channel'        => 'telegram',
			'enabled'        => true,
			'telegram_chat'  => '-1001234567890',
			'telegram_token' => '123456789:abcdefghijklmnop',
		)
	);
	$after          = (array) Settings::get( 'widget', array() );
	$stored_secrets = get_option( Settings::HANDOFF_SECRET_OPTION, array() );
	$check(
		200 === $updated->get_status()
		&& true === ( $after['handoff_telegram_enabled'] ?? false )
		&& '-1001234567890' === ( $after['handoff_telegram_chat'] ?? '' )
		&& true === ( $after['handoff_enabled'] ?? false )
		&& 'Keep this title' === ( $after['title'] ?? '' )
		&& 'Keep this voice' === ( $after['voice_name'] ?? '' )
		&& '123456789:abcdefghijklmnop' === Settings::get_handoff_secret( 'handoff_telegram_token' )
		&& false === strpos( (string) ( $stored_secrets['handoff_telegram_token'] ?? '' ), '123456789:abcdefghijklmnop' )
		&& Settings::answer_desk_handoff_available(),
		'Channel update preserves unrelated settings, encrypts its credential and exposes handoff only when it is ready'
	);

	$before_invalid = (array) Settings::get( 'widget', array() );
	$invalid        = $dispatch(
		1,
		array(
			'channel'  => 'email',
			'enabled'  => true,
			'email_to' => 'not an address',
		)
	);
	$check(
		400 === $invalid->get_status() && $before_invalid === (array) Settings::get( 'widget', array() ),
		'Invalid email configuration is rejected without mutating a channel or the global handoff gate'
	);

	$cleared = $dispatch(
		1,
		array(
			'channel'              => 'telegram',
			'enabled'              => true,
			'telegram_chat'        => '-1001234567890',
			'clear_telegram_token' => true,
		)
	);
	$check(
		200 === $cleared->get_status()
		&& '' === Settings::get_handoff_secret( 'handoff_telegram_token' )
		&& ! Settings::answer_desk_handoff_available(),
		'An explicit credential removal keeps the channel honest and hides the visitor handoff action'
	);
} finally {
	if ( $subscriber_id > 0 ) {
		wp_delete_user( $subscriber_id );
	}
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	if ( $original_secrets === $missing ) {
		delete_option( Settings::HANDOFF_SECRET_OPTION );
	} else {
		update_option( Settings::HANDOFF_SECRET_OPTION, $original_secrets, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
