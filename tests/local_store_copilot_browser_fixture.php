<?php
/**
 * Creates and removes the exact local fixture used by Store Copilot browser QA.
 *
 * It saves the complete previous plugin setting array, creates at most one
 * named WooCommerce draft marker, and restores/deletes only those owned
 * records. It never contacts an AI provider or reads/changes an order.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_store_copilot_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;

const FIXTURE_OPTION = 'aisaautopilot_store_copilot_browser_fixture';
const FIXTURE_META   = '_aisaautopilot_store_copilot_browser_fixture';

$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here:
	// before T15 a missing snapshot fell through to delete_option( Settings::OPTION ).
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'had_settings', $record ) ) {
		throw new RuntimeException( 'Store Copilot fixture snapshot is invalid; live settings were left untouched.' );
	}

	$product_id = absint( $record['product_id'] ?? 0 );
	if ( $product_id > 0 && '1' === get_post_meta( $product_id, FIXTURE_META, true ) ) {
		wp_delete_post( $product_id, true );
	}
	if ( ! empty( $record['had_settings'] ) ) {
		update_option( Settings::OPTION, $record['settings'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Store Copilot browser fixture removed.\n";
	exit( 0 );
}

$restore();

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$product_id        = 0;

try {
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

	if ( WooCommerceConnector::is_active() ) {
		$product_id = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'draft',
				'post_title'  => 'AISAAutoPilot Store browser QA draft',
			),
			true
		);
		if ( is_wp_error( $product_id ) ) {
			throw new RuntimeException( $product_id->get_error_message() );
		}
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			throw new RuntimeException( 'WooCommerce did not create the browser fixture product.' );
		}
		update_post_meta( $product_id, '_aisaautopilot_generated', '1' );
		update_post_meta( $product_id, FIXTURE_META, '1' );
	}

	update_option(
		FIXTURE_OPTION,
		array(
			'had_settings' => $original_settings !== $missing,
			'settings'     => $original_settings !== $missing ? $original_settings : array(),
			'product_id'   => $product_id,
		),
		false
	);

	echo wp_json_encode(
		array(
			'product_id' => $product_id,
			'woo_active' => WooCommerceConnector::is_active(),
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $product_id > 0 && '1' === get_post_meta( $product_id, FIXTURE_META, true ) ) {
		wp_delete_post( $product_id, true );
	}
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	delete_option( FIXTURE_OPTION );
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
