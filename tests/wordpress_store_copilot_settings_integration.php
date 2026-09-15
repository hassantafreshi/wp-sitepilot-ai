<?php
/**
 * Local WordPress integration checks for Store Copilot's live settings.
 *
 * The suite never calls an AI provider and never creates a WooCommerce object.
 * It verifies the protected preference route and the disabled smart-search
 * gate only.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\WooCommerceController;
use AISAAutoPilot\Connectors\WooCommerceProductSkill;
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
	$request = new \WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'woocommerce' => array(
				'enabled'       => true,
				'auto_draft'    => true,
				'enable_search' => false,
				'enable_edit'   => false,
			),
		)
	);
	$before = (array) Settings::get( 'woocommerce', array() );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes['/aisaautopilot/v1/woocommerce/admin-settings'] )
		&& isset( $routes['/aisaautopilot/v1/woocommerce/smart-search'] ),
		'Store settings and the explicitly gated protected search route are registered'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_store_' . wp_generate_password( 12, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'store-' . wp_generate_password( 12, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	$subscriber    = $dispatch(
		$subscriber_id,
		array(
			'enabled'       => false,
			'enable_search' => true,
		)
	);
	$check(
		$subscriber_id > 0
		&& in_array( $subscriber->get_status(), array( 401, 403 ), true )
		&& $before === (array) Settings::get( 'woocommerce', array() ),
		'Store settings require manage_options and leave settings untouched otherwise'
	);

	$updated = $dispatch(
		1,
		array(
			'enabled'       => false,
			'enable_search' => true,
		)
	);
	$after = (array) Settings::get( 'woocommerce', array() );
	$check(
		200 === $updated->get_status()
		&& false === ( $after['enabled'] ?? true )
		&& true === ( $after['enable_search'] ?? false )
		&& true === ( $after['auto_draft'] ?? false )
		&& false === ( $after['enable_edit'] ?? true ),
		'Store updates persist only the two live preferences while preserving Phase 1 draft/edit invariants'
	);

	$disabled_search = ( new WooCommerceController() )->smart_search( new \WP_REST_Request( 'POST' ) );
	$check(
		403 === $disabled_search->get_status() && 'feature_disabled' === ( $disabled_search->get_data()['code'] ?? '' ),
		'Disabled Store search rejects before a provider or product query can run'
	);
	$blocked_plan = ( new WooCommerceProductSkill() )->plan( 'A product that must not reach a provider.' );
	$check(
		empty( $blocked_plan['ok'] ) && 'connector_not_available' === ( $blocked_plan['code'] ?? '' ),
		'Paused Store connector blocks proposal creation server-side before a provider call'
	);
	wp_set_current_user( 1 );
	$disabled_read = rest_do_request( new \WP_REST_Request( 'GET', '/aisaautopilot/v1/woocommerce/categories' ) );
	$disabled_read_status = $disabled_read instanceof \WP_REST_Response ? $disabled_read->get_status() : 0;
	$check(
		in_array( $disabled_read_status, array( 401, 403 ), true ),
		'Paused Store connector blocks protected Store read routes as well as write routes'
	);

	$before_incomplete = (array) Settings::get( 'woocommerce', array() );
	$incomplete        = $dispatch(
		1,
		array(
			'enabled' => true,
		)
	);
	$check(
		400 === $incomplete->get_status() && $before_incomplete === (array) Settings::get( 'woocommerce', array() ),
		'Incomplete Store preference updates are rejected without changing the saved contract'
	);
} finally {
	if ( $subscriber_id > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
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
