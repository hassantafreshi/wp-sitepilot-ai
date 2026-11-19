<?php
/**
 * Local WordPress checks for Store Copilot > Descriptions: reviewed AI descriptions for
 * products that already exist. Needs WooCommerce active. The AI service is an inert
 * endpoint answered by pre_http_request; no real request is made.
 *
 * Proves it is off until the store manager turns it on; the list favours missing and
 * short descriptions; a draft is sanitized and kept as a proposal; only the administrator
 * who asked can apply it, once; an approval is refused when the description changed
 * after the draft; only the description changes (never price, stock or title); Undo
 * restores the previous text; and the routes are for store managers only.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\ProductDescriptions;
use AISAAutoPilot\Core\Settings;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
if ( ! function_exists( 'wc_get_product' ) ) {
	echo "SKIP WooCommerce is not active\n";
	exit( 0 );
}

$original_settings = get_option( Settings::OPTION );
$original_key      = get_option( Settings::KEY_OPTION, null );
$original_logs     = get_option( 'aisaautopilot_logs', null );
$created           = array();
$users             = array();
$calls             = 0;
$rest              = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, '/aisaautopilot/v1/woocommerce/descriptions' . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};
$provider = static function ( $preempt, array $args, string $url ) use ( &$calls ) {
	if ( false === strpos( $url, 'descriptions-test.invalid' ) ) {
		return $preempt;
	}
	++$calls;
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'choices' => array( array( 'message' => array( 'content' => "```html\n<h2>Built to last</h2><p>Hand-stitched leather <script>alert(1)</script>that ages well.</p><ul><li>Full grain</li></ul>\n```" ) ) ),
				'usage'   => array( 'prompt_tokens' => 120, 'completion_tokens' => 60, 'total_tokens' => 180 ),
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'provider'    => 'deepseek',
			'base_url'    => 'https://descriptions-test.invalid/v1',
			'model'       => 'test-model',
			'woocommerce' => array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => false ),
		)
	);
	Settings::set_api_key( 'descriptions-test-key' );
	add_filter( 'pre_http_request', $provider, 10, 3 );

	$thin = new WC_Product_Simple();
	$thin->set_name( 'QA Leather Wallet' );
	$thin->set_regular_price( '49.00' );
	$thin->set_manage_stock( true );
	$thin->set_stock_quantity( 7 );
	$thin->set_description( 'A wallet.' );
	$thin->set_status( 'publish' );
	$created[] = $thin_id = (int) $thin->save();
	$rich = new WC_Product_Simple();
	$rich->set_name( 'QA Rich Product' );
	$rich->set_description( str_repeat( 'Plenty of words about this product already. ', 10 ) );
	$rich->set_status( 'publish' );
	$created[] = $rich_id = (int) $rich->save();

	// 1. Off by default.
	$off = $rest( 'GET', '' );
	$check( 403 === $off->get_status() && 'descriptions_disabled' === ( $off->get_data()['code'] ?? '' ) && false === ProductDescriptions::enabled(), 'Off until the store manager turns Product descriptions on' );

	// 2. Turning it on through the Store settings route; a save without the key keeps it.
	$save = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	$save->set_param( 'enabled', true );
	$save->set_param( 'enable_search', false );
	$save->set_param( 'enable_descriptions', true );
	rest_do_request( $save );
	$keep = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	$keep->set_param( 'enabled', true );
	$keep->set_param( 'enable_search', false );
	rest_do_request( $keep );
	$check( ProductDescriptions::enabled(), 'The Store settings route turns it on, and a save without the new switch keeps it' );

	// 3. The list: missing or short descriptions first; everything when the filter is off.
	$short_ids = array_column( (array) ( $rest( 'GET', '' )->get_data()['products'] ?? array() ), 'id' );
	$all_ids   = array_column( (array) ( $rest( 'GET', '', array( 'only_short' => '0' ) )->get_data()['products'] ?? array() ), 'id' );
	$check( in_array( $thin_id, $short_ids, true ) && ! in_array( $rich_id, $short_ids, true ) && in_array( $rich_id, $all_ids, true ), 'The list shows products with a missing or short description, and all of them when asked' );

	// 4. A draft: one request, sanitized, kept as a proposal; nothing changes yet.
	$draft = $rest( 'POST', '/draft', array( 'product_id' => $thin_id, 'tone' => 'friendly' ) )->get_data();
	$check(
		! empty( $draft['ok'] ) && 1 === $calls && false === strpos( (string) $draft['draft'], '<script' ) && false === strpos( (string) $draft['draft'], '```' )
		&& false !== strpos( (string) $draft['draft'], '<h2>Built to last</h2>' ) && 'A wallet.' === $draft['current']
		&& 'A wallet.' === wc_get_product( $thin_id )->get_description(),
		'A draft takes one AI request, is cleaned of scripts and code fences, and changes nothing yet'
	);

	// 5. Only the administrator who asked can apply it.
	$users[] = $other = (int) wp_insert_user( array( 'user_login' => 'qa_desc_other_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	wp_set_current_user( $other );
	$foreign = $rest( 'POST', '/apply', array( 'action_id' => $draft['action_id'] ) );
	wp_set_current_user( 1 );
	$check( 400 === $foreign->get_status() && 'draft_forbidden' === ( $foreign->get_data()['code'] ?? '' ), 'Another administrator cannot apply someone else\'s draft' );

	// 6. Apply: only the description changes; the old text is kept; a second apply is refused.
	$applied = $rest( 'POST', '/apply', array( 'action_id' => $draft['action_id'] ) );
	$after   = wc_get_product( $thin_id );
	$again   = $rest( 'POST', '/apply', array( 'action_id' => $draft['action_id'] ) );
	$check(
		200 === $applied->get_status() && false !== strpos( $after->get_description(), 'Built to last' )
		&& '49.00' === $after->get_regular_price() && 7 === (int) $after->get_stock_quantity() && 'QA Leather Wallet' === $after->get_name() && 'publish' === $after->get_status()
		&& 'A wallet.' === ( get_post_meta( $thin_id, ProductDescriptions::PREVIOUS_META, true )['html'] ?? '' )
		&& 400 === $again->get_status(),
		'Apply replaces the description only (price, stock, title and status stay), keeps the old text, and works once'
	);

	// 7. Undo restores it; a second Undo has nothing to do.
	$undo  = $rest( 'POST', '/undo', array( 'product_id' => $thin_id ) );
	$undo2 = $rest( 'POST', '/undo', array( 'product_id' => $thin_id ) );
	$check( 200 === $undo->get_status() && 'A wallet.' === wc_get_product( $thin_id )->get_description() && 400 === $undo2->get_status(), 'Undo puts the previous description back, once' );

	// 8. A description changed after the draft is not overwritten; an edited draft is applied as edited.
	$draft2 = $rest( 'POST', '/draft', array( 'product_id' => $thin_id ) )->get_data();
	$manual = wc_get_product( $thin_id );
	$manual->set_description( 'Edited by hand meanwhile.' );
	$manual->save();
	$stale  = $rest( 'POST', '/apply', array( 'action_id' => $draft2['action_id'] ) );
	$draft3 = $rest( 'POST', '/draft', array( 'product_id' => $thin_id ) )->get_data();
	$edited = $rest( 'POST', '/apply', array( 'action_id' => $draft3['action_id'], 'description' => '<p>My own words <iframe src="x"></iframe></p>' ) );
	$check(
		409 === $stale->get_status() && 'changed_since_draft' === ( $stale->get_data()['code'] ?? '' )
		&& 200 === $edited->get_status() && '<p>My own words </p>' === wc_get_product( $thin_id )->get_description(),
		'A description changed after the draft is never overwritten; an edited draft is applied as edited, cleaned'
	);

	// 9. Store managers only.
	$users[] = $subscriber = (int) wp_insert_user( array( 'user_login' => 'qa_desc_sub_' . wp_generate_password( 5, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'subscriber' ) );
	wp_set_current_user( $subscriber );
	$denied = array( $rest( 'GET', '' )->get_status(), $rest( 'POST', '/draft', array( 'product_id' => $thin_id ) )->get_status(), $rest( 'POST', '/undo', array( 'product_id' => $thin_id ) )->get_status() );
	wp_set_current_user( 1 );
	$check( array( 403, 403, 403 ) === $denied || array() === array_diff( $denied, array( 401, 403 ) ), 'Every Descriptions route refuses a non-manager' );
} finally {
	remove_filter( 'pre_http_request', $provider, 10 );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	update_option( Settings::OPTION, $original_settings );
	if ( null === $original_key ) {
		delete_option( Settings::KEY_OPTION );
	} else {
		update_option( Settings::KEY_OPTION, $original_key, false );
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
