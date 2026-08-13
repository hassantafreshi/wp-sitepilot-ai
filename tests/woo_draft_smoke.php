<?php
/**
 * Isolated Phase 1 WooCommerce draft workflow check.
 *
 * The local XAMPP install keeps WooCommerce inactive, so this test supplies a
 * tiny WooCommerce CRUD double. It verifies SitePilot's contract without
 * modifying a store: only a simple draft is created and the proposal cannot
 * be replayed.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SitePilot\Connectors\WooCommerceProductSkill;
use SitePilot\Execution\ActionStore;
use SitePilot\Skills\ProductPlanValidator;

if ( ! class_exists( 'WooCommerce' ) ) {
	final class WooCommerce {}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	final class WC_Product_Simple {
		/** @var array<string,mixed> */
		public static array $last = array();

		public function set_name( string $value ): void { self::$last['name'] = $value; }
		public function set_status( string $value ): void { self::$last['status'] = $value; }
		public function set_description( string $value ): void { self::$last['description'] = $value; }
		public function set_short_description( string $value ): void { self::$last['short_description'] = $value; }
		public function set_regular_price( string $value ): void { self::$last['regular_price'] = $value; }
		public function set_sale_price( string $value ): void { self::$last['sale_price'] = $value; }
		public function set_stock_status( string $value ): void { self::$last['stock_status'] = $value; }
		public function set_manage_stock( bool $value ): void { self::$last['manage_stock'] = $value; }
		public function set_stock_quantity( int $value ): void { self::$last['stock_quantity'] = $value; }
		public function set_sku( string $value ): void { self::$last['sku'] = $value; }
		public function set_weight( string $value ): void { self::$last['weight'] = $value; }
		/** @param int[] $value */
		public function set_category_ids( array $value ): void { self::$last['categories'] = $value; }
		public function save(): int { return 9101; }
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = array() ): array { return array(); }
}

$passed = 0;
$failed = 0;
$check = static function ( bool $ok, string $name ) use ( &$passed, &$failed ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . PHP_EOL;
	$ok ? ++$passed : ++$failed;
};

$GLOBALS['sitepilot_test_transients'] = array();
$GLOBALS['sitepilot_test_added_options'] = array();
$GLOBALS['sitepilot_test_user_id'] = 7;

$plan = array(
	'name' => 'P1 Coffee', 'description' => 'Temporary draft description.', 'short_description' => 'Coffee.',
	'price' => '19.99', 'regular_price' => '19.99', 'sale_price' => '', 'categories' => array(), 'sku' => '', 'weight' => '500',
	'stock_status' => 'instock', 'stock_quantity' => 0, 'manage_stock' => false, 'status' => 'draft', 'type' => 'simple',
);
$store = new ActionStore();
$id = $store->create( $plan, 'woocommerce_product_draft' );
$result = ( new WooCommerceProductSkill( $store ) )->execute( $id );
$check( ! empty( $result['ok'] ) && 9101 === (int) ( $result['product_id'] ?? 0 ) && 'draft' === ( WC_Product_Simple::$last['status'] ?? '' ) && 'simple' === $plan['type'], 'approved proposal creates one simple WooCommerce draft' );
$replay = ( new WooCommerceProductSkill( $store ) )->execute( $id );
$check( empty( $replay['ok'] ) && 'action_expired' === ( $replay['code'] ?? '' ), 'WooCommerce product draft proposal cannot be replayed' );
$invalid = ( new ProductPlanValidator() )->validate( array_merge( $plan, array( 'status' => 'publish' ) ) );
$check( ! $invalid['ok'], 'Phase 1 product validator rejects publishing' );

echo PHP_EOL . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit( $failed > 0 ? 1 : 0 );
