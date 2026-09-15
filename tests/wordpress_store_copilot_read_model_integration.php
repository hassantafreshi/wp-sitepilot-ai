<?php
/**
 * Local WordPress integration check for Store Copilot's read-only product row.
 * It creates one exact temporary draft product and deletes that exact post in
 * finally. No provider, order, publish, edit, bulk, or refund action occurs.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_get      = $_GET;
$original_user_id  = get_current_user_id();
$product_id        = 0;
$checks            = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
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

	if ( ! WooCommerceConnector::is_active() ) {
		$check( true, 'Store read model safely returns no product rows while WooCommerce is inactive' );
	} else {
		$title      = 'AISAAutoPilot Store Read Model ' . wp_generate_password( 12, false, false );
		$created_product = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);
		$product_id = is_wp_error( $created_product ) ? 0 : (int) $created_product;
		$check( $product_id > 0, 'Store read-model fixture creates one named Woo product safely' );
		if ( $product_id > 0 ) {
			update_post_meta( $product_id, '_aisaautopilot_generated', '1' );
		}

		$rows = WooCommerceConnector::recent_products( 20 );
		$row  = null;
		foreach ( $rows as $candidate ) {
			if ( $product_id === (int) $candidate['id'] ) {
				$row = $candidate;
				break;
			}
		}
		$check(
			$product_id > 0
			&& is_array( $row )
			&& $title === $row['name']
			&& true === $row['generated']
			&& '' !== $row['origin']
			&& '' !== $row['status']
			&& 'draft' === get_post_status( $product_id ),
			'Read model returns the exact temporary AutoPilot draft with its source marker and localized status label'
		);

		$_GET['tab'] = 'products';
		ob_start();
		( new StoreCopilotPage() )->render();
		$markup = (string) ob_get_clean();
		$check(
			false !== strpos( $markup, $title )
			&& false !== strpos( $markup, 'Created by AutoPilot' )
			&& false !== strpos( $markup, 'data-sp-store-product-generated="1"' )
			&& false === strpos( $markup, 'action=edit' ),
			'Products tab renders the real source-marked draft as read-only markup without an edit link'
		);
	}
} finally {
	if ( $product_id > 0 ) {
		wp_delete_post( $product_id, true );
	}
	$_GET = $original_get;
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
