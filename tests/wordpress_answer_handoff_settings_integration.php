<?php
/**
 * Local WordPress integration checks for the compact Human handoff endpoint.
 *
 * No provider request or test delivery is made. The test is intentionally
 * limited to capability checks, validation, encrypted credential storage, the
 * visitor-availability gate and the details the handoff card asks visitors for.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';
// The cleanup calls wp_delete_user(), which a plain wp-load.php does not load.
require_once ABSPATH . 'wp-admin/includes/user.php';

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

	// Details the handoff card asks for.
	$fields_request = static function ( int $user_id, $fields ): \WP_REST_Response {
		wp_set_current_user( $user_id );
		$request = new \WP_REST_Request( 'POST', '/aisaautopilot/v1/handoff/visitor-fields' );
		$request->set_param( 'fields', $fields );
		return rest_do_request( $request );
	};
	$check(
		isset( $routes['/aisaautopilot/v1/handoff/visitor-fields'] )
		&& array(
			'name'    => 'off',
			'email'   => 'off',
			'phone'   => 'off',
			'website' => 'off',
		) === Settings::handoff_fields(),
		'Visitor details have their own protected route and every detail starts switched off'
	);
	$wanted          = array(
		'name'    => 'required',
		'email'   => 'optional',
		'phone'   => 'optional',
		'website' => 'off',
	);
	$fields_before   = (array) Settings::get( 'widget', array() );
	$subscriber_save = $fields_request( $subscriber_id, $wanted );
	$check(
		$subscriber_id > 0 && in_array( $subscriber_save->get_status(), array( 401, 403 ), true ) && $fields_before === (array) Settings::get( 'widget', array() ),
		'Saving visitor details requires the manage_options capability'
	);
	$saved_fields = $fields_request( 1, $wanted );
	$check(
		200 === $saved_fields->get_status()
		&& $wanted === ( $saved_fields->get_data()['fields'] ?? null )
		&& $wanted === Settings::handoff_fields()
		&& 'Keep this title' === ( (array) Settings::get( 'widget', array() ) )['title'],
		'An administrator saves off, optional or required per detail without touching other widget settings'
	);
	$refusals = array(
		array_merge( $wanted, array( 'name' => 'maybe' ) ),
		array( 'name' => 'off' ),
		array_merge( $wanted, array( 'fax' => 'optional' ) ),
		'required',
	);
	$all_refused = true;
	foreach ( $refusals as $refusal ) {
		$response    = $fields_request( 1, $refusal );
		$all_refused = $all_refused && 400 === $response->get_status() && 'handoff_fields_invalid' === ( $response->get_data()['code'] ?? '' );
	}
	$check( $all_refused && $wanted === Settings::handoff_fields(), 'An unknown mode, a partial set, an unknown detail or a non-object is refused and nothing changes' );

	wp_set_current_user( 1 );
	ob_start();
	( new \AISAAutoPilot\Admin\AnswerDeskHandoffPage() )->render();
	$page_html = (string) ob_get_clean();
	$check(
		false !== strpos( $page_html, 'data-sp-handoff-details-form' )
		&& 4 === substr_count( $page_html, 'role="radiogroup"' )
		&& 12 === substr_count( $page_html, 'class="sp-answer-handoff-seg__option"' )
		&& 1 === preg_match( '/name="name" value="required" checked=\'checked\'/', $page_html )
		&& 1 === preg_match( '/name="website" value="off" checked=\'checked\'/', $page_html ),
		'The Human handoff tab lists the four details with Off, Optional and Required and marks the saved choice'
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
