<?php
/**
 * Fixture for tests/cart_funnel_browser_matrix.cjs. `prepare` creates a product, turns
 * Count carts off (the test turns it on in the UI) and empties the counts; `status`
 * prints the counts for the last 7 days; `restore` puts back the settings, the counts
 * and removes the product. Needs WooCommerce active.
 * Usage: php local_cart_funnel_browser_fixture.php prepare|status|restore
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\CartFunnel;
use AISAAutoPilot\Core\Settings;

global $wpdb;
$state_option = 'aisaautopilot_local_funnel_fixture';
$mode         = (string) ( $argv[1] ?? '' );
CartFunnel::ensure_schema();
$table = ( new CartFunnel() )->table();

if ( 'prepare' === $mode ) {
	$state = get_option( $state_option );
	if ( ! is_array( $state ) ) {
		$state = array(
			'settings' => get_option( Settings::OPTION ),
			'rows'     => $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
		);
		$product = new WC_Product_Simple();
		$product->set_name( 'QA Funnel Mug' );
		$product->set_regular_price( '12' );
		$product->set_status( 'publish' );
		$state['product'] = (int) $product->save();
	}
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	Settings::update( array( 'woocommerce' => array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => false, 'cart_funnel' => false ) ) );
	update_option( $state_option, $state, false );
	echo wp_json_encode( array( 'product' => $state['product'], 'checkout' => wc_get_checkout_url() ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'status' === $mode ) {
	$t = ( new CartFunnel() )->totals( 7 );
	echo wp_json_encode( array( 'cart' => $t['cart'], 'checkout' => $t['checkout'], 'on' => CartFunnel::enabled() ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$state = get_option( $state_option );
	if ( is_array( $state ) ) {
		wp_delete_post( (int) $state['product'], true );
		update_option( Settings::OPTION, $state['settings'] );
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
		foreach ( (array) $state['rows'] as $row ) {
			$wpdb->insert( $table, $row );
		}
		delete_option( $state_option );
	}
	echo "restored\n";
	exit( 0 );
}

echo "Usage: prepare|status|restore\n";
exit( 1 );
