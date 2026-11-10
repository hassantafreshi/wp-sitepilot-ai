<?php
/**
 * Local WordPress checks for online payment in the Easy Form Builder copilot. No request leaves the
 * site: `pre_http_request` answers the AI call with a canned reply and keeps the prompt that would
 * have been sent.
 *
 * Proves: a payment request becomes real fields (priced choices carrying their prices, a total and the
 * gateway field) and the form-level settings EFB writes itself; the model is told which add-ons are on
 * and whether keys are saved, never a key; a gateway whose add-on is off, a Free licence, an unsupported
 * currency, a missing price, and a second gateway are each refused with a plain reason and the right
 * link and the form is left alone; a new payment form is previewed with every price shown and is saved
 * as a payment form; and the old "do it yourself" help card no longer follows a payment request.
 * Settings, the EFB licence flag and settings option, the usage totals, the log and the forms this
 * test created are restored or removed in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

global $wpdb;
$missing   = new stdClass();
$options   = array( Settings::OPTION, Settings::KEY_OPTION, 'aisaautopilot_logs', 'aisaautopilot_usage_totals', 'aisaautopilot_usage_period', 'aisaautopilot_monthly_budget', 'emsfb_pro', 'emsfb_settings' );
$snapshots = array();
foreach ( $options as $option ) {
	$snapshots[ $option ] = get_option( $option, $missing );
}
$checks  = array();
$prompts = array();
$next    = array();
$created = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$canned = static function ( $pre, $args, $url ) use ( &$prompts, &$next ) {
	if ( false === strpos( (string) $url, 'efb-payment-test.invalid' ) ) {
		return $pre;
	}
	$body      = json_decode( (string) ( $args['body'] ?? '' ), true );
	$prompts[] = (string) ( $body['messages'][0]['content'] ?? '' ) . "\n" . (string) ( $body['messages'][1]['content'] ?? '' );
	$reply     = array_shift( $next );
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => wp_json_encode( $reply ) ) ) ),
				'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20 ),
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};
$chat = static function ( array $params ): array {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/chat' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	return (array) $response->get_data();
};
$open_form = array(
	'has_form' => true,
	'fields'   => array( array( 'id' => 'name1', 'label' => 'Name', 'type' => 'text' ), array( 'id' => 'mail1', 'label' => 'Email', 'type' => 'email' ) ),
);
$stripe_op = array(
	'op'          => 'add_payment',
	'gateway'     => 'stripe',
	'currency'    => 'usd',
	'choice_type' => 'radio',
	'label'       => 'Package',
	'items'       => array( array( 'label' => 'Basic', 'price' => 20 ), array( 'label' => 'Pro', 'price' => '49.50' ) ),
);
$reply_for = static fn ( array $op ): array => array( 'reply' => 'Added the payment.', 'action' => 'customize', 'operations' => array( $op ) );
// Placeholders only: the test needs a "key present" shape, not a key.
$keys_saved = wp_json_encode( array( 'stripePKey' => 'pk_test_placeholder_0001', 'stripeSKey' => 'sk_test_placeholder_0001' ) );

add_filter( 'pre_http_request', $canned, 10, 3 );
try {
	wp_set_current_user( 1 );
	Settings::update( array( 'provider' => 'deepseek', 'base_url' => 'https://efb-payment-test.invalid/v1', 'model' => 'efb-payment-test', 'key_management' => 'keyvault', 'efb_copilot_enabled' => true ) );
	Settings::set_api_key( 'efb-payment-test-key' ); // Inert; never printed or sent anywhere.
	delete_option( 'aisaautopilot_usage_totals' );
	update_option( 'emsfb_pro', 1 );
	update_option( 'emsfb_settings', '{}' );

	// 1. A Stripe request with prices becomes fields and form settings; keys are not saved, so it says so.
	$next[] = $reply_for( $stripe_op );
	$data   = $chat( array_merge( $open_form, array( 'message' => 'add stripe payment gateway, basic 20 dollars, pro 49.50', 'addons' => array( 'AdnSPF' => 1, 'AdnPAP' => 0 ) ) ) );
	$op     = $data['operations'][0] ?? array();
	$items  = $op['items'] ?? array();
	$types  = array_column( $items, 'type' );
	$prices = array_values( array_map( static fn ( array $i ): string => (string) $i['price'], array_filter( $items, static fn ( array $i ): bool => 'option' === ( $i['type'] ?? '' ) ) ) );
	$check(
		'ops' === ( $data['type'] ?? '' ) && 'add_payment' === ( $op['op'] ?? '' ) && array( 'payRadio', 'option', 'option', 'ttlprc', 'stripe' ) === $types
		&& array( '20', '49.5' ) === $prices
		&& array( 'type' => 'payment', 'getway' => 'stripe', 'currency' => 'usd', 'paymentmethod' => 'charge' ) === ( $op['form_settings'] ?? null ),
		'A Stripe request with prices becomes a priced choice, a total and the Stripe field, with the form settings EFB writes itself'
	);
	$stripe_links = array_column( $data['links'] ?? array(), 'url' );
	$check(
		false !== strpos( implode( ' ', (array) ( $data['errors'] ?? array() ) ), 'account keys' ) && 1 === count( $stripe_links ) && false !== strpos( (string) $stripe_links[0], 'page=Emsfb_stripe_payment' ),
		'Without saved keys it says so and links to the Stripe settings page'
	);
	$check(
		false !== strpos( $prompts[0] ?? '', 'Stripe (stripe): add-on active, keys missing' ) && false !== strpos( $prompts[0] ?? '', 'PayPal (paypal): add-on not active' )
		&& false !== strpos( $prompts[0] ?? '', 'ONLINE PAYMENT' ),
		'The model is told which gateways are on and whether keys exist, and how to ask for payment'
	);

	// 2. With keys saved there is no key warning, and no key reaches the model.
	update_option( 'emsfb_settings', $keys_saved );
	$next[] = $reply_for( $stripe_op );
	$data   = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => array( 'AdnSPF' => 1 ) ) ) );
	$check(
		'ops' === ( $data['type'] ?? '' ) && false === strpos( implode( ' ', (array) ( $data['errors'] ?? array() ) ), 'account keys' ) && empty( $data['links'] )
		&& false !== strpos( $prompts[1] ?? '', 'keys saved' ) && false === strpos( $prompts[1] ?? '', 'placeholder_0001' ),
		'With keys saved there is no key warning, and the model never receives a key'
	);

	// 3. Refusals: each leaves the form alone and says why, with the right link.
	$next[] = $reply_for( $stripe_op );
	$off    = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => array( 'AdnSPF' => 0 ) ) ) );
	$check(
		empty( $off['operations'] ) && false !== strpos( implode( ' ', (array) ( $off['errors'] ?? array() ) ), 'install it for you' ) && false !== strpos( implode( ' ', array_column( $off['links'] ?? array(), 'url' ) ), 'page=Emsfb_addon' ),
		'A gateway whose add-on is off is refused with the add-on page as the way forward'
	);
	$no_price = $stripe_op;
	unset( $no_price['items'] );
	$next[]   = $reply_for( $no_price );
	$free     = $chat( array_merge( $open_form, array( 'message' => 'add stripe payment', 'addons' => array( 'AdnSPF' => 1 ) ) ) );
	$check( empty( $free['operations'] ) && false !== strpos( implode( ' ', (array) ( $free['errors'] ?? array() ) ), 'price' ), 'No price is never guessed: it asks for the price' );
	$euro      = $stripe_op;
	$euro['currency'] = 'xyz';
	$next[]    = $reply_for( $euro );
	$cur       = $chat( array_merge( $open_form, array( 'message' => 'add stripe in xyz', 'addons' => array( 'AdnSPF' => 1 ) ) ) );
	$check( empty( $cur['operations'] ) && false !== strpos( implode( ' ', (array) ( $cur['errors'] ?? array() ) ), 'XYZ' ), 'A currency the gateway cannot take is refused by name' );
	update_option( 'emsfb_pro', 2 );
	$next[] = $reply_for( $stripe_op );
	$lic    = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => array( 'AdnSPF' => 1 ) ) ) );
	$check( empty( $lic['operations'] ) && false !== strpos( implode( ' ', (array) ( $lic['errors'] ?? array() ) ), 'Pro' ) && ! empty( $lic['links'] ), 'On a Free licence it says payment is part of Pro and links to it' );
	update_option( 'emsfb_pro', 1 );
	$next[]   = $reply_for( $stripe_op );
	$two      = $chat( array( 'has_form' => true, 'message' => 'add stripe too', 'addons' => array( 'AdnSPF' => 1, 'AdnPAP' => 1 ), 'fields' => array( array( 'id' => 'pp1', 'label' => 'PayPal', 'type' => 'paypal' ) ) ) );
	$check( empty( $two['operations'] ) && false !== strpos( implode( ' ', (array) ( $two['errors'] ?? array() ) ), 'only one gateway' ), 'A form that already takes payment through PayPal is not given a second gateway' );

	// 4. Payment field types never come through the generic add_field path.
	$next[] = array( 'reply' => 'Added.', 'action' => 'customize', 'operations' => array( array( 'op' => 'add_field', 'field' => array( 'type' => 'stripe', 'label' => 'Card' ) ) ) );
	$direct = $chat( array_merge( $open_form, array( 'message' => 'add a stripe card field', 'addons' => array( 'AdnSPF' => 1 ) ) ) );
	$check( empty( $direct['operations'] ), 'A gateway field cannot be added as a plain field, only as a validated payment' );

	// 5. A new payment form: previewed with every price, then saved as a payment form.
	$next[] = array(
		'reply'  => 'Here is a booking form with a deposit.',
		'action' => 'create_form',
		'form'   => array(
			'title'   => 'Photography booking',
			'fields'  => array( array( 'type' => 'text', 'label' => 'Name', 'required' => true ), array( 'type' => 'email', 'label' => 'Email', 'required' => true ) ),
			'payment' => array( 'gateway' => 'stripe', 'currency' => 'usd', 'choice_type' => 'radio', 'label' => 'Deposit', 'items' => array( array( 'label' => 'Booking deposit', 'price' => 50 ) ) ),
		),
	);
	$new = $chat( array( 'has_form' => false, 'message' => 'wedding photography booking with a 50 dollar deposit by stripe', 'addons' => array( 'AdnSPF' => 1 ) ) );
	$pf  = $new['preview']['fields'] ?? array();
	$check(
		'preview' === ( $new['type'] ?? '' ) && 'Stripe' === ( $new['preview']['payment']['gateway'] ?? '' ) && 'USD' === ( $new['preview']['payment']['currency'] ?? '' )
		&& 4 === count( $pf ) && 'payRadio' === ( $pf[2]['type'] ?? '' ) && array( 'Booking deposit — 50 USD' ) === ( $pf[2]['options'] ?? array() ) && 'stripe' === ( $pf[3]['type'] ?? '' ),
		'A new payment form is previewed with every price in plain sight'
	);
	$check(
		false !== strpos( implode( ' ', (array) ( $new['errors'] ?? array() ) ), 'shown in the preview' ) && false === strpos( implode( ' ', (array) ( $new['errors'] ?? array() ) ), 'in the form now' ),
		'A new form says the prices are in the preview to check before creating, not that they are already in the form'
	);
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/create' );
	$request->set_param( 'action_id', (string) ( $new['action_id'] ?? '' ) );
	$done    = rest_do_request( $request )->get_data();
	$form_id = (int) ( $done['result']['form_id'] ?? ( $done['form_id'] ?? 0 ) );
	if ( $form_id > 0 ) {
		$created[] = $form_id;
	}
	$table = $wpdb->prefix . 'emsfb_form';
	$row   = $form_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT form_type, form_structer FROM {$table} WHERE form_id = %d", $form_id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$flat  = is_array( $row ) ? str_replace( '\\', '', (string) $row['form_structer'] ) : '';
	$check(
		$form_id > 0 && is_array( $row ) && 'payment' === $row['form_type'] && false !== strpos( $flat, '"getway":"stripe"' ) && false !== strpos( $flat, '"currency":"usd"' )
		&& false !== strpos( $flat, '"type":"payment"' ) && false !== strpos( $flat, '"price":"50"' ) && false !== strpos( $flat, '"type":"stripe"' ) && false !== strpos( $flat, '"type":"ttlprc"' ),
		'Approving it saves a payment form with the gateway, currency, price, total and Stripe field'
	);

	// 6. The same request in a new form with the add-on off: the form is still built, the payment is explained.
	$next[] = array( 'reply' => 'A booking form.', 'action' => 'create_form', 'form' => array( 'title' => 'Booking', 'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ), 'payment' => array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Deposit', 'price' => 50 ) ) ) ) );
	$off_new = $chat( array( 'has_form' => false, 'message' => 'booking with stripe deposit', 'addons' => array() ) );
	$check(
		'preview' === ( $off_new['type'] ?? '' ) && empty( $off_new['preview']['payment'] ) && false !== strpos( implode( ' ', (array) ( $off_new['errors'] ?? array() ) ), 'install it for you' ) && ! empty( $off_new['links'] ),
		'A new form whose payment add-on is off is still built, without a payment that could not work, and says what to turn on'
	);

	// 7. The old help card is gone for a payment request that reaches the help route.
	$next[] = array( 'reply' => 'See the steps.', 'action' => 'answer_help', 'help_topic' => 'stripe payment gateway' );
	$help   = $chat( array( 'has_form' => true, 'message' => 'add stripe payment gateway to the form', 'addons' => array( 'AdnSPF' => 1 ) ) );
	$check( 'help' === ( $help['type'] ?? '' ) && empty( $help['title'] ), 'A payment question is not answered with an unrelated help article' );
} catch ( Throwable $error ) {
	$check( false, 'Unexpected ' . get_class( $error ) . ': ' . $error->getMessage() );
} finally {
	remove_filter( 'pre_http_request', $canned, 10 );
	foreach ( $created as $form_id ) {
		$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $form_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	foreach ( $snapshots as $option => $value ) {
		if ( $value === $missing ) {
			delete_option( $option );
		} else {
			update_option( $option, $value, false );
		}
	}
	wp_set_current_user( 0 );
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
