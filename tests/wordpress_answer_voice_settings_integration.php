<?php
/**
 * Local WordPress integration checks for Answer Desk's actual voice controls.
 *
 * No audio is recorded, no provider is contacted and no browser speech API is
 * invoked. The endpoint is intentionally limited to settings the public
 * widget already consumes.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
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
	$request = new \WP_REST_Request( 'POST', '/aisaautopilot/v1/voice/admin-settings' );
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
					'enabled'       => true,
					'color'         => '#ff455f',
					'title'         => 'Keep this title',
					'voice_enabled' => false,
					'voice_auto'    => false,
					'voice_name'    => 'Keep this browser voice',
				)
			),
		)
	);
	$before = (array) Settings::get( 'widget', array() );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes['/aisaautopilot/v1/voice/admin-settings'] ),
		'Answer Desk voice settings are served through their own protected route'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_voice_' . wp_generate_password( 12, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'voice-' . wp_generate_password( 12, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	$subscriber    = $dispatch(
		$subscriber_id,
		array(
			'voice_enabled' => true,
			'voice_auto'    => true,
		)
	);
	$check(
		$subscriber_id > 0 && in_array( $subscriber->get_status(), array( 401, 403 ), true ) && $before === (array) Settings::get( 'widget', array() ),
		'Voice controls require the manage_options capability and leave settings untouched otherwise'
	);

	$updated = $dispatch(
		1,
		array(
			'voice_enabled' => true,
			'voice_auto'    => true,
		)
	);
	$after = (array) Settings::get( 'widget', array() );
	$check(
		200 === $updated->get_status()
		&& true === ( $after['voice_enabled'] ?? false )
		&& true === ( $after['voice_auto'] ?? false )
		&& '#ff455f' === ( $after['color'] ?? '' )
		&& 'Keep this title' === ( $after['title'] ?? '' )
		&& 'Keep this browser voice' === ( $after['voice_name'] ?? '' ),
		'Voice updates persist only the two live widget controls and preserve unrelated settings'
	);

	$auto_off = $dispatch(
		1,
		array(
			'voice_enabled' => true,
			'voice_auto'    => false,
		)
	);
	$check(
		200 === $auto_off->get_status()
		&& true === ( Settings::get( 'widget', array() )['voice_enabled'] ?? false )
		&& false === ( Settings::get( 'widget', array() )['voice_auto'] ?? true ),
		'Voice reply playback can be paused without disabling visitor voice input'
	);

	$before_incomplete = (array) Settings::get( 'widget', array() );
	$incomplete        = $dispatch(
		1,
		array(
			'voice_enabled' => false,
		)
	);
	$check(
		400 === $incomplete->get_status() && $before_incomplete === (array) Settings::get( 'widget', array() ),
		'Incomplete voice updates are rejected without changing the public widget contract'
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
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
