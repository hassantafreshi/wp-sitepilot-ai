<?php
/**
 * Fixture for tests/cart_recovery_browser_matrix.cjs. `prepare` turns WooCommerce's Coming soon mode off, creates a product, turns
 * reminders off (the test turns them on in the UI), empties the kept carts, clears the
 * shopper rate limit and installs a temporary must-use plugin that records wp_mail
 * instead of sending; `status` prints the kept carts and recorded emails; `age` makes
 * every kept cart two hours old; `restore` removes all of it. Needs WooCommerce active.
 * Usage: php local_cart_recovery_browser_fixture.php prepare|status|age|restore
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\CartRecovery;
use AISAAutoPilot\Core\Settings;

global $wpdb;
$state_option = 'aisaautopilot_local_cart_fixture';
$mail_option  = 'aisaautopilot_local_cart_mails';
$mu_file      = WPMU_PLUGIN_DIR . '/aisaautopilot-qa-cart-mail.php';
$mode         = (string) ( $argv[1] ?? '' );
CartRecovery::ensure_schema();
$table = ( new CartRecovery() )->table();

if ( 'prepare' === $mode ) {
	$state = get_option( $state_option );
	if ( ! is_array( $state ) ) {
		$state   = array(
			'settings'  => get_option( Settings::OPTION ),
			'rows'      => $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
			'recovered' => get_option( CartRecovery::RECOVERED_OPTION, null ),
			'coming_soon' => get_option( 'woocommerce_coming_soon', null ),
		);
		$product = new WC_Product_Simple();
		$product->set_name( 'QA Recovery Teapot' );
		$product->set_regular_price( '24' );
		$product->set_status( 'publish' );
		$state['product'] = (int) $product->save();
	}
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		wp_mkdir_p( WPMU_PLUGIN_DIR );
	}
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local test fixture.
		$mu_file,
		"<?php\n// Temporary QA fixture (tests/local_cart_recovery_browser_fixture.php): records wp_mail instead of sending.\nadd_filter( 'pre_wp_mail', static function ( \$pre, \$atts ) {\n\tif ( ! get_option( 'aisaautopilot_local_cart_fixture' ) ) { return \$pre; }\n\t\$mails = (array) get_option( 'aisaautopilot_local_cart_mails', array() );\n\t\$mails[] = array( 'to' => \$atts['to'], 'subject' => \$atts['subject'], 'message' => \$atts['message'] );\n\tupdate_option( 'aisaautopilot_local_cart_mails', \$mails, false );\n\treturn true;\n}, 10, 2 );\n"
	);
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient%aisaautopilot\\_rate\\_public\\_cart%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
	delete_option( $mail_option );
	Settings::update( array( 'woocommerce' => array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => false, 'cart_funnel' => false, 'cart_recovery' => false ) ) );
	// Shoppers must reach the real checkout, not WooCommerce's Coming soon page.
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( $state_option, $state, false );
	echo wp_json_encode( array( 'product' => $state['product'], 'checkout' => wc_get_checkout_url() ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'status' === $mode ) {
	echo wp_json_encode(
		array(
			'on'    => CartRecovery::enabled(),
			'carts' => $wpdb->get_results( "SELECT id, email, status, contents FROM {$table} ORDER BY id", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
			'mails' => get_option( $mail_option, array() ),
		)
	) . PHP_EOL;
	exit( 0 );
}

if ( 'age' === $mode ) {
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET updated_at = %s", gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	echo "aged\n";
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
		if ( null === $state['recovered'] ) {
			delete_option( CartRecovery::RECOVERED_OPTION );
		} else {
			update_option( CartRecovery::RECOVERED_OPTION, $state['recovered'], false );
		}
		if ( null === ( $state['coming_soon'] ?? null ) ) {
			delete_option( 'woocommerce_coming_soon' );
		} else {
			update_option( 'woocommerce_coming_soon', $state['coming_soon'] );
		}
		delete_option( $state_option );
	}
	delete_option( $mail_option );
	if ( file_exists( $mu_file ) ) {
		unlink( $mu_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- local test fixture.
	}
	echo "restored\n";
	exit( 0 );
}

echo "Usage: prepare|status|age|restore\n";
exit( 1 );
