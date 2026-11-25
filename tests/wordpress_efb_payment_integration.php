<?php
/**
 * Local WordPress checks for online payment in the Easy Form Builder copilot. No request leaves the
 * site: `pre_http_request` answers the AI call with a canned reply and keeps the prompt that would
 * have been sent.
 *
 * Registry contract (EFB 4.3.2, PAY-OUT): whether a gateway can be used, on which plan, and whether its keys are saved
 * come only from EFB's registry (`capabilities_efb()`: `features['field:<gateway>']`, `payment_gateways`,
 * `payment_keys`), never from the browser's add-on list or the `emsfb_settings` option. Each part therefore runs on
 * the plan and add-ons EFB itself has (tests/efb_state_harness.php sets them through EFB's own settings function in a
 * process of their own), and the Stripe keys are saved and removed through the Stripe add-on's own key store
 * (`Stripe_Keys_Efb::save()`, which writes through EFB's settings function); placeholders only.
 *
 * Proves: a payment request becomes real fields (priced choices carrying their prices, a total and the gateway field)
 * and the form-level settings EFB writes itself; the model is told which gateways are on and whether keys are saved,
 * never a key; a gateway whose add-on is off, a plan below the add-on's, an unsupported currency, a missing price and a
 * second gateway are each refused with a plain reason and the right link and the form is left alone; a new payment
 * form is previewed with every price shown and is saved as a payment form; and the old "do it yourself" help card no
 * longer follows a payment request. The copilot settings, usage, the log, the keys and the forms this test created are
 * restored or removed in finally; the harness puts the site's plan and add-ons back.
 */

declare(strict_types=1);

require_once __DIR__ . '/efb_state_harness.php';

aisa_efb_parts(
	__FILE__,
	array(
		// Pro, Stripe add-on on, PayPal off.
		'pro'      => array(
			'plan'   => 1,
			'addons' => array(
				'AdnSPF' => 1,
				'AdnPAP' => 0,
			),
		),
		// The Stripe add-on is off.
		'off'      => array(
			'plan'   => 1,
			'addons' => array(
				'AdnSPF' => 0,
				'AdnPAP' => 0,
			),
		),
		// Free: below the Stripe add-on's plan (Free Plus in EFB's registry).
		'free'     => array(
			'plan'   => 2,
			'addons' => array(
				'AdnSPF' => 1,
				'AdnPAP' => 0,
			),
		),
		// Stripe and PayPal both on.
		'two'      => array(
			'plan'   => 1,
			'addons' => array(
				'AdnSPF' => 1,
				'AdnPAP' => 1,
			),
		),
	),
	static function ( string $part ): array {
		global $wpdb;
		$missing   = new stdClass();
		$options   = array( \AISAAutoPilot\Core\Settings::OPTION, \AISAAutoPilot\Core\Settings::KEY_OPTION, 'aisaautopilot_logs', 'aisaautopilot_usage_totals', 'aisaautopilot_usage_period', 'aisaautopilot_monthly_budget' );
		$snapshots = array();
		foreach ( $options as $option ) {
			$snapshots[ $option ] = get_option( $option, $missing );
		}
		$checks  = array();
		$prompts = array();
		$next    = array();
		$created = array();
		$keys    = null; // The Stripe add-on's stored keys before this part, when it changed them.

		$check  = static function ( bool $ok, string $name, $detail = null ) use ( &$checks ): void {
			$checks[] = array(
				'ok'   => $ok,
				'name' => $name . ( $ok || null === $detail ? '' : ' :: ' . substr( (string) wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 700 ) ),
			);
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
			return (array) rest_do_request( $request )->get_data();
		};
		$errors    = static fn ( array $data ): string => implode( ' ', (array) ( $data['errors'] ?? array() ) );
		$urls      = static fn ( array $data ): string => implode( ' ', array_column( (array) ( $data['links'] ?? array() ), 'url' ) );
		$open_form = array(
			'has_form' => true,
			'fields'   => array(
				array( 'id' => 'name1', 'label' => 'Name', 'type' => 'text' ),
				array( 'id' => 'mail1', 'label' => 'Email', 'type' => 'email' ),
			),
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
		// The browser's add-on list is still sent the way the editor sends it; the registry, not this list, decides.
		$browser = array( 'AdnSPF' => 1, 'AdnPAP' => 1 );

		add_filter( 'pre_http_request', $canned, 10, 3 );
		try {
			wp_set_current_user( 1 );
			\AISAAutoPilot\Core\Settings::update( array( 'provider' => 'deepseek', 'base_url' => 'https://efb-payment-test.invalid/v1', 'model' => 'efb-payment-test', 'key_management' => 'keyvault', 'efb_copilot_enabled' => true ) );
			\AISAAutoPilot\Core\Settings::set_api_key( 'efb-payment-test-key' ); // Inert; never printed or sent anywhere.
			delete_option( 'aisaautopilot_usage_totals' );
			$registry = \AISAAutoPilot\Efb\EfbCapabilities::available();
			$check( $registry, 'Easy Form Builder publishes its registry (capabilities_efb)' );
			if ( ! $registry ) {
				return $checks;
			}
			$stripe = \AISAAutoPilot\Efb\EfbPayments::status( array() )['stripe'] ?? array();

			if ( 'pro' === $part ) {
				$check( ! empty( $stripe['addon_active'] ) && ! empty( $stripe['plan_ok'] ), 'The registry reports the Stripe add-on on and allowed on Pro', $stripe );
				// Start without Stripe keys: the add-on's own key store, emptied through its own save (restored in finally).
				$keys = class_exists( '\\Emsfb\\Stripe_Keys_Efb' ) ? array( 'stored' => \Emsfb\Stripe_Keys_Efb::stored() ) : null;
				if ( null !== $keys ) {
					\Emsfb\Stripe_Keys_Efb::save( array( 'pkey' => '' ), array(), array( 'skey' => 1 ) );
					\AISAAutoPilot\Efb\EfbCapabilities::reset();
				}

				// 1. A Stripe request with prices becomes fields and form settings; keys are not saved, so it says so.
				$next[] = $reply_for( $stripe_op );
				$data   = $chat( array_merge( $open_form, array( 'message' => 'add stripe payment gateway, basic 20 dollars, pro 49.50', 'addons' => $browser ) ) );
				$op     = $data['operations'][0] ?? array();
				$items  = $op['items'] ?? array();
				$types  = array_column( $items, 'type' );
				$prices = array_values( array_map( static fn ( array $i ): string => (string) $i['price'], array_filter( $items, static fn ( array $i ): bool => 'option' === ( $i['type'] ?? '' ) ) ) );
				$check(
					'ops' === ( $data['type'] ?? '' ) && 'add_payment' === ( $op['op'] ?? '' ) && array( 'payRadio', 'option', 'option', 'ttlprc', 'stripe' ) === $types
					&& array( '20', '49.5' ) === $prices
					&& array( 'type' => 'payment', 'getway' => 'stripe', 'currency' => 'usd', 'paymentmethod' => 'charge' ) === ( $op['form_settings'] ?? null ),
					'A Stripe request with prices becomes a priced choice, a total and the Stripe field, with the form settings EFB writes itself',
					$data
				);
				$check(
					false !== strpos( $errors( $data ), 'account keys' ) && 1 === count( (array) ( $data['links'] ?? array() ) ) && false !== strpos( $urls( $data ), 'page=Emsfb_stripe_payment' ),
					'Without saved keys (EFB\'s payment_keys) it says so and links to the Stripe settings page',
					$data
				);
				$check(
					false !== strpos( $prompts[0] ?? '', 'Stripe (stripe): add-on active, plan ok, keys missing' ) && false !== strpos( $prompts[0] ?? '', 'PayPal (paypal): add-on not active' )
					&& false !== strpos( $prompts[0] ?? '', 'ONLINE PAYMENT' ),
					'The model is told which gateways are on (EFB\'s registry, not the browser\'s list) and whether keys exist, and how to ask for payment',
					substr( (string) strstr( (string) ( $prompts[0] ?? '' ), 'Stripe (stripe)' ), 0, 400 )
				);

				// 2. With keys saved (placeholders, through the Stripe add-on's own key store) there is no key warning, and no key reaches the model.
				$saved = null !== $keys ? \Emsfb\Stripe_Keys_Efb::save( array( 'pkey' => 'pk_test_placeholder_0001' ), array( 'skey' => 'sk_test_placeholder_0001' ) ) : array( 'ok' => false );
				\AISAAutoPilot\Efb\EfbCapabilities::reset();
				$next[] = $reply_for( $stripe_op );
				$data   = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => $browser ) ) );
				$check(
					! empty( $saved['ok'] ) && 'ops' === ( $data['type'] ?? '' ) && false === strpos( $errors( $data ), 'account keys' ) && empty( $data['links'] )
					&& false !== strpos( $prompts[1] ?? '', 'keys saved' ) && false === strpos( $prompts[1] ?? '', 'placeholder_0001' ),
					'With keys saved there is no key warning, and the model never receives a key',
					array( $saved, $data )
				);

				// 3. Refusals: each leaves the form alone and says why.
				$no_price = $stripe_op;
				unset( $no_price['items'] );
				$next[] = $reply_for( $no_price );
				$free   = $chat( array_merge( $open_form, array( 'message' => 'add stripe payment', 'addons' => $browser ) ) );
				$check( empty( $free['operations'] ) && false !== strpos( $errors( $free ), 'price' ), 'No price is never guessed: it asks for the price', $free );
				$euro             = $stripe_op;
				$euro['currency'] = 'xyz';
				$next[]           = $reply_for( $euro );
				$cur              = $chat( array_merge( $open_form, array( 'message' => 'add stripe in xyz', 'addons' => $browser ) ) );
				$check( empty( $cur['operations'] ) && false !== strpos( $errors( $cur ), 'XYZ' ), 'A currency the gateway cannot take is refused by name', $cur );

				// 4. Payment field types never come through the generic add_field path.
				$next[] = array( 'reply' => 'Added.', 'action' => 'customize', 'operations' => array( array( 'op' => 'add_field', 'field' => array( 'type' => 'stripe', 'label' => 'Card' ) ) ) );
				$direct = $chat( array_merge( $open_form, array( 'message' => 'add a stripe card field', 'addons' => $browser ) ) );
				$check( empty( $direct['operations'] ), 'A gateway field cannot be added as a plain field, only as a validated payment', $direct );

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
				$new = $chat( array( 'has_form' => false, 'message' => 'wedding photography booking with a 50 dollar deposit by stripe', 'addons' => $browser ) );
				$pf  = $new['preview']['fields'] ?? array();
				$pt  = array_column( $pf, 'type' );
				$pay = array_values( array_filter( $pf, static fn ( $f ): bool => 'payRadio' === ( $f['type'] ?? '' ) ) )[0] ?? array();
				$check(
					'preview' === ( $new['type'] ?? '' ) && 'Stripe' === ( $new['preview']['payment']['gateway'] ?? '' ) && 'USD' === ( $new['preview']['payment']['currency'] ?? '' )
					&& in_array( 'stripe', $pt, true ) && array( 'Booking deposit — 50 USD' ) === ( $pay['options'] ?? array() ),
					'A new payment form is previewed with every price in plain sight',
					$new
				);
				$check(
					false !== strpos( $errors( $new ), 'shown in the preview' ) && false === strpos( $errors( $new ), 'in the form now' ),
					'A new form says the prices are in the preview to check before creating, not that they are already in the form',
					$new['errors'] ?? null
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
					'Approving it saves a payment form with the gateway, currency, price, total and Stripe field',
					array( $done, substr( $flat, 0, 300 ) )
				);

				// 7. A payment request that reaches the help route gets the payment gateways article (commit 1be0193, "gateways
				//    only through their add-on contract": efb-knowledge.json), not an unrelated help card.
				$next[] = array( 'reply' => 'See the steps.', 'action' => 'answer_help', 'help_topic' => 'stripe payment gateway' );
				$help   = $chat( array( 'has_form' => true, 'message' => 'add stripe payment gateway to the form', 'addons' => $browser ) );
				$check(
					'help' === ( $help['type'] ?? '' ) && 'Online payment gateways' === ( $help['title'] ?? '' ) && false !== strpos( (string) ( $help['answer'] ?? '' ), 'Every payment gateway is an Easy Form Builder add-on' ),
					'A payment question is answered with the payment gateways article (every gateway is an add-on), not an unrelated help article',
					$help
				);
			}

			if ( 'off' === $part ) {
				// 3. A gateway whose add-on is off in EFB is refused, even when the browser's list says it is on.
				$check( empty( $stripe['addon_active'] ), 'The registry reports the Stripe add-on off', $stripe );
				$next[] = $reply_for( $stripe_op );
				$off    = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => $browser ) ) );
				$check(
					empty( $off['operations'] ) && false !== strpos( $errors( $off ), 'install it for you' ) && false !== strpos( $urls( $off ), 'page=Emsfb_addon' )
					&& in_array( 'AdnSPF', array_column( (array) ( $off['install'] ?? array() ), 'code' ), true ),
					'A gateway whose add-on is off is refused with the add-on page and its install offer as the way forward',
					$off
				);

				// 6. The same request in a new form with the add-on off: the form is still built, the payment is explained.
				$next[]  = array( 'reply' => 'A booking form.', 'action' => 'create_form', 'form' => array( 'title' => 'Booking', 'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ), 'payment' => array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Deposit', 'price' => 50 ) ) ) ) );
				$off_new = $chat( array( 'has_form' => false, 'message' => 'booking with stripe deposit', 'addons' => $browser ) );
				$check(
					'preview' === ( $off_new['type'] ?? '' ) && empty( $off_new['preview']['payment'] ) && false !== strpos( $errors( $off_new ), 'install it for you' ) && ! empty( $off_new['links'] ),
					'A new form whose payment add-on is off is still built, without a payment that could not work, and says what to turn on',
					$off_new
				);
			}

			if ( 'free' === $part ) {
				// On Free the Stripe add-on is below its plan in EFB's registry: payment is refused with the plan and its link.
				$check( empty( $stripe['plan_ok'] ) && 'free_plus' === ( $stripe['package'] ?? '' ), 'The registry reports Stripe as needing Free Plus on Free', $stripe );
				$next[] = $reply_for( $stripe_op );
				$lic    = $chat( array_merge( $open_form, array( 'message' => 'add stripe', 'addons' => $browser ) ) );
				$check(
					empty( $lic['operations'] ) && false !== strpos( $errors( $lic ) . ' ' . (string) ( $lic['reply'] ?? '' ), 'Free Plus' ) && ! empty( $lic['links'] ),
					'On Free it says the Stripe add-on needs Free Plus (or Pro) and links to it',
					$lic
				);
			}

			if ( 'two' === $part ) {
				$next[] = $reply_for( $stripe_op );
				$two    = $chat( array( 'has_form' => true, 'message' => 'add stripe too', 'addons' => $browser, 'fields' => array( array( 'id' => 'pp1', 'label' => 'PayPal', 'type' => 'paypal' ) ) ) );
				$check( empty( $two['operations'] ) && false !== strpos( $errors( $two ), 'only one gateway' ), 'A form that already takes payment through PayPal is not given a second gateway', $two );
			}
		} catch ( Throwable $error ) {
			$check( false, 'Unexpected ' . get_class( $error ) . ': ' . $error->getMessage() );
		} finally {
			remove_filter( 'pre_http_request', $canned, 10 );
			foreach ( $created as $form_id ) {
				// EFB's undo of the import that created it (Form_Package::delete with the copilot's undo token).
				$token = get_transient( 'aisaautopilot_efb_undo_1_' . $form_id );
				$token = is_array( $token ) ? (string) ( $token['token'] ?? '' ) : $token; // {token, base_hash} since QA5-001
				if ( is_string( $token ) && '' !== $token && class_exists( '\\Emsfb\\Form_Package' ) && true === \Emsfb\Form_Package::delete( $form_id, $token ) ) {
					delete_transient( 'aisaautopilot_efb_undo_1_' . $form_id );
				} else {
					$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $form_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}
			if ( null !== $keys ) {
				// The add-on's keys as they were: its own store, written back through EFB's settings function (none: emptied
				// through the add-on's own save).
				if ( is_array( $keys['stored'] ) ) {
					$write = new ReflectionMethod( '\\Emsfb\\Stripe_Keys_Efb', 'write' );
					$write->setAccessible( true );
					$write->invoke( null, $keys['stored'] );
				} else {
					\Emsfb\Stripe_Keys_Efb::save( array( 'pkey' => '' ), array(), array( 'skey' => 1 ) );
				}
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

		return $checks;
	}
);
