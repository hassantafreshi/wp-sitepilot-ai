<?php
/**
 * Fixture for tests/product_descriptions_browser_matrix.cjs. `prepare` points the AI
 * service at an inert host answered by a temporary must-use plugin, creates two
 * products, and turns Product descriptions on; `status` prints the thin product's
 * description; `restore` removes all of it. Needs WooCommerce active.
 * Usage: php local_product_descriptions_browser_fixture.php prepare|status|restore
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

$state_option = 'aisaautopilot_local_desc_fixture';
$mu_file      = WPMU_PLUGIN_DIR . '/aisaautopilot-qa-descriptions-fake.php';
$mode         = (string) ( $argv[1] ?? '' );

if ( 'prepare' === $mode ) {
	$state = get_option( $state_option );
	if ( ! is_array( $state ) ) {
		$state = array(
			'settings' => get_option( Settings::OPTION ),
			'key'      => get_option( Settings::KEY_OPTION, null ),
		);
	}
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		wp_mkdir_p( WPMU_PLUGIN_DIR );
	}
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local test fixture.
		$mu_file,
		"<?php\n// Temporary QA fixture (tests/local_product_descriptions_browser_fixture.php): answers the inert AI host.\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) {\n\tif ( false === strpos( (string) \$url, 'descriptions-browser.invalid' ) || ! get_option( 'aisaautopilot_local_desc_fixture' ) ) { return \$pre; }\n\treturn array( 'headers' => array(), 'cookies' => array(), 'filename' => null, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => '<h2>Built to last</h2><p>Hand-stitched leather <script>alert(1)</script>that ages well.</p><ul><li>Full grain</li></ul>' ) ) ), 'usage' => array( 'prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150 ) ) ) );\n}, 10, 3 );\n"
	);
	Settings::update(
		array(
			'provider'    => 'deepseek',
			'base_url'    => 'https://descriptions-browser.invalid/v1',
			'model'       => 'test-model',
			'woocommerce' => array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => true ),
		)
	);
	Settings::set_api_key( 'descriptions-browser-key' );
	$thin = new WC_Product_Simple();
	$thin->set_name( 'QA Browser Wallet' );
	$thin->set_regular_price( '49.00' );
	$thin->set_description( 'A wallet.' );
	$thin->set_status( 'publish' );
	$state['products'] = array( (int) $thin->save() );
	update_option( $state_option, $state, false );
	echo wp_json_encode( array( 'product' => $state['products'][0] ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'status' === $mode ) {
	$state   = (array) get_option( $state_option, array() );
	$product = wc_get_product( (int) ( $state['products'][0] ?? 0 ) );
	echo wp_json_encode( array( 'description' => $product ? $product->get_description() : null ) ) . PHP_EOL;
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$state = get_option( $state_option );
	if ( is_array( $state ) ) {
		foreach ( (array) ( $state['products'] ?? array() ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		update_option( Settings::OPTION, $state['settings'] );
		if ( null === $state['key'] ) {
			delete_option( Settings::KEY_OPTION );
		} else {
			update_option( Settings::KEY_OPTION, $state['key'], false );
		}
		delete_option( $state_option );
	}
	if ( file_exists( $mu_file ) ) {
		wp_delete_file( $mu_file );
	}
	echo 'Product descriptions browser fixture removed.' . PHP_EOL;
	exit( 0 );
}

fwrite( STDERR, "Usage: prepare|status|restore\n" );
exit( 2 );
