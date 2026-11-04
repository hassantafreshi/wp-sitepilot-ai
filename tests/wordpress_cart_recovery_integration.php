<?php
/**
 * Local WordPress checks for abandoned-cart reminders (Connectors\CartRecovery). Needs
 * WooCommerce active. Email is captured by pre_wp_mail; nothing leaves the site. The
 * reminder link and the checkout notice are covered in a real browser by
 * tests/cart_recovery_browser_matrix.cjs.
 *
 * Proves it is off until the store manager turns it on; a cart is kept only with a valid
 * email, a non-empty cart, not for staff and not after the shopper declined; one row per
 * session, refreshed when the cart changes; carts appear for the manager only after an
 * hour; the one reminder is sent only by the manager, once, with a link; an order deletes
 * the carts of that email and counts a recovery; retention, export, erase and the policy
 * text cover it; and the routes are for store managers only.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Connectors\CartRecovery;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\PrivacyTools;
use AISAAutoPilot\Support\Retention;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
if ( ! function_exists( 'wc_load_cart' ) ) {
	echo "SKIP WooCommerce is not active\n";
	exit( 0 );
}

global $wpdb;
$original_settings  = get_option( Settings::OPTION );
$original_recovered = get_option( CartRecovery::RECOVERED_OPTION, null );
$original_logs      = get_option( 'aisaautopilot_logs', null );
$recovery           = new CartRecovery();
CartRecovery::ensure_schema();
$table    = $recovery->table();
$original = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
$created = array();
$users   = array();
$mails   = array();
$catch   = static function ( $pre, $atts ) use ( &$mails ) {
	$mails[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $catch, 10, 2 );
delete_transient( 'aisaautopilot_rate_public_cart_' . hash_hmac( 'sha256', 'ip:unknown', wp_salt( 'auth' ) ) );

$new_shopper = static function (): void {
	wp_set_current_user( 0 );
	WC()->session = new WC_Session_Handler();
	WC()->session->init();
	WC()->cart = new WC_Cart();
};
$set = static function ( bool $on ): void {
	wp_set_current_user( 1 );
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	$request->set_param( 'enabled', true );
	$request->set_param( 'enable_search', false );
	$request->set_param( 'cart_recovery', $on );
	rest_do_request( $request );
};
$rows = static function () use ( $wpdb, $table ): array {
	return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
};
$age = static function ( int $id, int $seconds ) use ( $wpdb, $table ): void {
	$wpdb->update( $table, array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), array( 'id' => $id ) );
};
$route = static function ( int $id, string $action, int $user ) {
	wp_set_current_user( $user );
	return rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/carts/' . $id . '/' . $action ) );
};

try {
	wc_load_cart();
	foreach ( array( 'QA Recovery Mug', 'QA Recovery Cap' ) as $name ) {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '12' );
		$product->set_status( 'publish' );
		$created[] = (int) $product->save();
	}
	list( $mug, $cap ) = $created;

	// 1. Off by default: nothing is kept.
	$set( false );
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$off = $recovery->capture( 'shopper.one@example.test' );
	$check( ! CartRecovery::enabled() && 'off' === $off['code'] && array() === $rows(), 'Off by default: no cart is kept' );

	// 2. On through the Store settings route.
	$set( true );
	$check( CartRecovery::enabled(), 'The Store settings route turns Abandoned cart reminders on' );

	// 3. A valid email and a non-empty cart are needed; then one row per session.
	$new_shopper();
	$empty = $recovery->capture( 'shopper.one@example.test' );
	WC()->cart->add_to_cart( $mug, 2 );
	$bad   = $recovery->capture( 'not-an-email' );
	$first = $recovery->capture( 'shopper.one@example.test' );
	$again = $recovery->capture( 'shopper.one@example.test' );
	$kept  = $rows();
	$items = json_decode( (string) ( $kept[0]['contents'] ?? '[]' ), true );
	$check(
		'empty' === $empty['code'] && 'invalid_email' === $bad['code'] && $first['ok'] && $again['ok'] && 1 === count( $kept )
		&& 'shopper.one@example.test' === $kept[0]['email'] && 2 === (int) $items[0]['quantity'] && 'QA Recovery Mug' === $items[0]['name']
		&& 1 === preg_match( '/^[a-f0-9]{32}$/', (string) $kept[0]['token'] ),
		'A cart is kept only with a valid email and something in it, once per session, with its products and a random link token'
	);

	// 4. Changing the cart refreshes the kept copy; emptying it deletes the copy.
	WC()->cart->add_to_cart( $cap );
	$refreshed = json_decode( (string) ( $rows()[0]['contents'] ?? '[]' ), true );
	$check( 2 === count( (array) $refreshed ) && 1 === count( $rows() ), 'Adding a product updates the same kept cart' );
	WC()->cart->empty_cart();
	$check( array() === $rows(), 'Emptying the cart deletes the kept copy' );

	// 5. Declining deletes the cart and keeps nothing more in that session.
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$recovery->capture( 'shopper.two@example.test' );
	$had      = count( $rows() );
	$declined = $recovery->decline();
	$after    = $recovery->capture( 'shopper.two@example.test' );
	$check( 1 === $had && $declined['ok'] && 'declined' === $after['code'] && array() === $rows(), 'Do not keep my cart deletes it and nothing more is kept in that session' );

	// 6. Store staff are never kept.
	wp_set_current_user( 1 );
	WC()->session = new WC_Session_Handler();
	WC()->session->init();
	WC()->cart = new WC_Cart();
	WC()->cart->add_to_cart( $mug );
	$staff = $recovery->capture( 'admin@example.test' );
	$check( 'staff' === $staff['code'] && array() === $rows(), 'A store manager testing checkout is not kept' );

	// 7. The manager sees a cart only after an hour.
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	WC()->cart->add_to_cart( $cap );
	$recovery->capture( 'shopper.three@example.test' );
	$id    = (int) $rows()[0]['id'];
	$fresh = $recovery->abandoned();
	$age( $id, 2 * HOUR_IN_SECONDS );
	$left = $recovery->abandoned();
	$check( array() === $fresh && 1 === count( $left ) && 'shopper.three@example.test' === $left[0]['email'], 'A cart appears for the store manager once it has been left for an hour' );

	// 8. The Orders card lists it; the routes are for store managers only.
	wp_set_current_user( 1 );
	$_GET = array( 'page' => 'aisaautopilot-wc-dashboard', 'tab' => 'orders' );
	ob_start();
	( new StoreCopilotPage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();
	$check(
		false !== strpos( $html, 'data-sp-cart="' . $id . '"' ) && false !== strpos( $html, 'shopper.three@example.test' ) && false !== strpos( $html, 'data-sp-cart-remind="' . $id . '"' ),
		'Store Copilot > Orders lists the abandoned cart with Send reminder and Delete'
	);
	$users[] = $subscriber = (int) wp_insert_user( array( 'user_login' => 'qa_cart_sub_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'subscriber' ) );
	$denied  = array( $route( $id, 'remind', $subscriber )->get_status(), $route( $id, 'delete', $subscriber )->get_status() );
	$check( array() === array_diff( $denied, array( 401, 403 ) ) && array() === $mails && 1 === count( $rows() ), 'A non-manager can neither send a reminder nor delete a cart' );

	// 9. The one reminder: sent only by the manager, once, with the products and a link that refills the cart.
	$sent   = $route( $id, 'remind', 1 );
	$second = $route( $id, 'remind', 1 );
	$mail   = $mails[0] ?? array();
	$token  = (string) $rows()[0]['token'];
	$check(
		200 === $sent->get_status() && 409 === $second->get_status() && 1 === count( $mails )
		&& 'shopper.three@example.test' === ( $mail['to'] ?? '' ) && false !== strpos( (string) $mail['subject'], get_bloginfo( 'name' ) )
		&& false !== strpos( (string) $mail['message'], 'QA Recovery Cap' ) && false !== strpos( (string) $mail['message'], CartRecovery::RESTORE_ARG . '=' . $token )
		&& 'reminded' === $rows()[0]['status'],
		'Send reminder emails the shopper once, listing the products with a link that refills the cart; a second try is refused'
	);

	// 10. An order for that email deletes its carts and counts a recovery.
	$before_recovered = $recovery->recovered();
	$new_shopper();
	$order = wc_create_order();
	$order->set_billing_email( 'shopper.three@example.test' );
	$order->save();
	$created_orders = array( $order->get_id() );
	$recovery->on_order_placed( $order );
	$check( array() === $rows() && $before_recovered + 1 === $recovery->recovered(), 'An order for that email deletes its kept carts and counts one order after a reminder' );

	// 11. Retention: the Abandoned carts period (30 days by default) deletes older carts.
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$recovery->capture( 'shopper.four@example.test' );
	$new_shopper();
	WC()->cart->add_to_cart( $cap );
	$recovery->capture( 'shopper.five@example.test' );
	$ids = array_map( 'intval', array_column( $rows(), 'id' ) );
	$age( $ids[0], 40 * DAY_IN_SECONDS );
	$run = ( new Retention() )->run();
	$check( 30 === Retention::DEFAULTS['carts_days'] && 1 === $run['carts'] && array( $ids[1] ) === array_map( 'intval', array_column( $rows(), 'id' ) ), 'The daily cleanup deletes carts older than the Abandoned carts period and keeps newer ones' );

	// 12. Privacy: export and erase by email; the policy text names it while it is on.
	$export = ( new PrivacyTools() )->export( 'shopper.five@example.test' );
	$groups = array_column( $export['data'], 'group_id' );
	$erase  = ( new PrivacyTools() )->erase( 'shopper.five@example.test' );
	$policy = PrivacyTools::policy_text();
	$check(
		in_array( 'aisaautopilot-carts', $groups, true ) && $erase['items_removed'] && array() === $rows()
		&& false !== strpos( $policy, 'one reminder' ),
		'The personal data exporter and eraser find carts by email, and the privacy text describes them while reminders are on'
	);

	// 13. Delete route; switching off stops keeping.
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$recovery->capture( 'shopper.six@example.test' );
	$del = $route( (int) $rows()[0]['id'], 'delete', 1 );
	$set( false );
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$recovery->capture( 'shopper.seven@example.test' );
	$check( 200 === $del->get_status() && array() === $rows() && false === strpos( PrivacyTools::policy_text(), 'one reminder' ), 'Delete removes a cart; switched off, nothing is kept and the privacy text drops it' );

	// 14. Only what is needed is stored.
	$columns = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	$check( array( 'id', 'token', 'email', 'user_id', 'contents', 'total', 'currency', 'status', 'reminded_at', 'created_at', 'updated_at' ) === $columns, 'The table holds the email, the cart, its total and the reminder state: no name, address, phone or IP address' );
} finally {
	remove_filter( 'pre_wp_mail', $catch, 10 );
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $created_orders ?? array() as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			$o->delete( true );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	foreach ( (array) $original as $row ) {
		$wpdb->insert( $table, $row );
	}
	update_option( Settings::OPTION, $original_settings );
	foreach ( array( CartRecovery::RECOVERED_OPTION => $original_recovered, 'aisaautopilot_logs' => $original_logs ) as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value, false );
		}
	}
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
