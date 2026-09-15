<?php
/**
 * Local WordPress integration checks for the compact Widget settings endpoint.
 *
 * This uses the registered REST route so its capability gate is tested
 * alongside the non-destructive Settings::update() contract. Browser QA covers
 * the WordPress REST nonce over a real cookie-authenticated HTTP request. No
 * provider, handoff delivery, or public request is made here.
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

	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/admin-settings' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	return rest_do_request( $request );
};

try {
	wp_set_current_user( 1 );
	$fixture_widget = array_merge(
		Settings::widget_defaults(),
		array(
			'enabled'                      => true,
			'color'                        => '#202a8d',
			'title'                        => 'Keep this title',
			'conversation_logging_enabled' => true,
			'conversation_notice'          => 'Keep this notice',
			'voice_name'                   => 'Keep this voice',
			'handoff_text'                 => 'Keep this handoff text',
			'suggestions'                  => array( 'Keep this suggestion' ),
		)
	);
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => $fixture_widget,
		)
	);
	$before = (array) Settings::get( 'widget', array() );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes['/aisaautopilot/v1/widget/admin-settings'] ),
		'Compact Widget update route is registered separately from public widget settings'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_widget_' . wp_generate_password( 12, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'widget-' . wp_generate_password( 12, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	$subscriber    = $dispatch(
		$subscriber_id,
		array(
			'enabled' => false,
			'color'   => '#ff455f',
		)
	);
	$check(
		$subscriber_id > 0 && in_array( $subscriber->get_status(), array( 401, 403 ), true ) && $before === (array) Settings::get( 'widget', array() ),
		'Widget partial update requires the manage_options capability'
	);

	wp_set_current_user( 1 );
	$updated     = $dispatch(
		1,
		array(
			'enabled' => false,
			'color'   => '#ff455f',
		)
	);
	$after = (array) Settings::get( 'widget', array() );
	$before_preserved = $before;
	$after_preserved  = $after;
	unset( $before_preserved['enabled'], $before_preserved['color'], $after_preserved['enabled'], $after_preserved['color'] );
	$check(
		200 === $updated->get_status()
		&& false === ( $after['enabled'] ?? true )
		&& '#ff455f' === ( $after['color'] ?? '' )
		&& $before_preserved === $after_preserved,
		'Widget partial update changes only visibility and accent colour while preserving unrelated settings'
	);

	$invalid = $dispatch(
		1,
		array(
			'enabled' => true,
			'color'   => 'not-a-colour',
		)
	);
	$check(
		400 === $invalid->get_status() && $after === (array) Settings::get( 'widget', array() ),
		'Widget partial update rejects an invalid colour without applying a fallback or mutation'
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
