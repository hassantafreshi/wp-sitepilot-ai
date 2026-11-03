<?php
/**
 * Local WordPress checks for the dormant write methods of WooCommerceOrderSkill.
 * Needs WooCommerce active. No email leaves the site (pre_wp_mail).
 *
 * The status, refund and note methods have no REST route (Phase 2, T48), but they
 * must be sound when that work starts: a status is accepted with or without the
 * "wc-" prefix (ltrim( 'wc-' ) used to turn "completed" into "ompleted"), a refund ID
 * is never treated as an order, and no write route is registered today.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\WooCommerceOrderSkill;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
if ( ! function_exists( 'wc_create_order' ) ) {
	echo "SKIP WooCommerce is not active\n";
	exit( 0 );
}

$original_logs = get_option( 'aisaautopilot_logs', null );
$no_mail       = static function () {
	return true;
};
add_filter( 'pre_wp_mail', $no_mail );
$created = array();

try {
	wp_set_current_user( 1 );
	$skill = new WooCommerceOrderSkill();
	$order = wc_create_order();
	$order->set_billing_email( 'order.skill@example.test' );
	$order->set_total( 20 );
	$order->save();
	$created[] = $order_id = $order->get_id();

	// 1. The prefix is removed exactly; letters of the status name are kept.
	$check(
		'completed' === WooCommerceOrderSkill::status_slug( 'wc-completed' ) && 'completed' === WooCommerceOrderSkill::status_slug( 'completed' )
		&& 'cancelled' === WooCommerceOrderSkill::status_slug( 'cancelled' ) && 'on-hold' === WooCommerceOrderSkill::status_slug( 'wc-on-hold' ),
		'A status name loses only the exact "wc-" prefix (completed, cancelled and on-hold stay whole)'
	);

	// 2. Statuses whose names start with w, c or "-" now work, with or without the prefix.
	$completed = $skill->update_status( $order_id, 'completed' );
	$state1    = wc_get_order( $order_id )->get_status();
	$cancelled = $skill->update_status( $order_id, 'wc-cancelled' );
	$state2    = wc_get_order( $order_id )->get_status();
	$check(
		! empty( $completed['ok'] ) && 'completed' === $state1 && 'completed' === $completed['new_status']
		&& ! empty( $cancelled['ok'] ) && 'cancelled' === $state2,
		'update_status() sets "completed" and "wc-cancelled" (both were refused before the fix)'
	);

	// 3. Unknown statuses are refused and change nothing.
	$bogus = $skill->update_status( $order_id, 'wc-' );
	$other = $skill->update_status( $order_id, 'shipped-to-mars' );
	$check(
		empty( $bogus['ok'] ) && empty( $other['ok'] ) && 'cancelled' === wc_get_order( $order_id )->get_status(),
		'An empty or unknown status is refused and the order keeps its status'
	);

	// 4. A refund ID is not an order: every method says "not found" instead of failing inside WooCommerce.
	$skill->update_status( $order_id, 'processing' );
	$refund = wc_create_refund(
		array(
			'order_id' => $order_id,
			'amount'   => 5,
			'reason'   => 'QA',
		)
	);
	$refund_id = is_wp_error( $refund ) ? 0 : $refund->get_id();
	$not_found = __( 'Order not found.', 'ai-site-assistant-autopilot' );
	$results   = array(
		$skill->get_order( $refund_id ),
		$skill->update_status( $refund_id, 'completed' ),
		$skill->create_refund( $refund_id, 1.0 ),
		$skill->add_note( $refund_id, 'QA note' ),
	);
	$all_not_found = $refund_id > 0;
	foreach ( $results as $r ) {
		$all_not_found = $all_not_found && empty( $r['ok'] ) && $not_found === ( $r['error'] ?? '' );
	}
	$check( $all_not_found, 'A refund ID gets "Order not found." from get_order, update_status, create_refund and add_note' );

	// 5. The real order still works, and its refund total is respected.
	$detail   = $skill->get_order( $order_id );
	$too_much  = $skill->create_refund( $order_id, 50.0 );
	$check(
		! empty( $detail['ok'] ) && $order_id === (int) $detail['order']['id'] && empty( $too_much['ok'] ) && 15.0 === (float) ( $too_much['max_refund'] ?? 0 ),
		'The order itself still loads, and a refund above what is left (15 of 20) is refused'
	);

	// 6. The contract: no order write route is registered.
	$routes = array_keys( rest_get_server()->get_routes() );
	$writes = array_filter(
		$routes,
		static function ( string $route ): bool {
			return 0 === strpos( $route, '/aisaautopilot/v1/woocommerce/orders' ) && '/aisaautopilot/v1/woocommerce/orders/recent' !== $route;
		}
	);
	$check( in_array( '/aisaautopilot/v1/woocommerce/orders/recent', $routes, true ) && array() === array_values( $writes ), 'Only the read-only recent-orders route is registered; status, refund and note have none' );
} finally {
	remove_filter( 'pre_wp_mail', $no_mail );
	foreach ( $created as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			foreach ( $o->get_refunds() as $r ) {
				$r->delete( true );
			}
			$o->delete( true );
		}
	}
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
