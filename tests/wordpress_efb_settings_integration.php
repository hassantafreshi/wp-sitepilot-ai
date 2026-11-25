<?php
/**
 * Local WordPress checks for the form settings the Easy Form Builder copilot changes by chat, on each of
 * EFB's plans. No request leaves the site: `pre_http_request` answers the AI call with a canned reply.
 *
 * Registry contract (EFB 4.3.2): the plan and whether the SMS, Conditional Logic and Address & Postal add-ons are on
 * come only from EFB's registry (`capabilities_efb()`), never from the browser's add-on list or the `emsfb_pro`
 * option. Each part therefore runs on the plan and add-ons EFB itself has (tests/efb_state_harness.php sets them
 * through EFB's own settings function in a process of their own).
 *
 * Proves: Pro applies private form, email subject, redirect (an address the admin typed, or the home
 * page), form type (sign-in form with its two fields) and SMS with its add-on; Free Plus applies email
 * subject and redirect but leaves the mobile layout block alone and names Pro; Free leaves email subject
 * alone and names Free Plus and Pro; SMS without its add-on is refused with an offer to install it (allowed
 * on Pro only); an address nobody typed is refused with a question; a reply never shows internal names;
 * a new form with an address block, a map, a link and an HTML block is saved the way EFB stores them.
 * The copilot settings, usage, the log and the forms this test created are restored or removed in finally; the
 * harness puts the site's plan and add-ons back.
 */

declare(strict_types=1);

require_once __DIR__ . '/efb_state_harness.php';

aisa_efb_parts(
	__FILE__,
	array(
		// Pro, the SMS add-on off.
		'pro'       => array(
			'plan'   => 1,
			'addons' => array( 'AdnSS' => 0 ),
		),
		// Pro, the SMS add-on on.
		'pro_sms'   => array(
			'plan'   => 1,
			'addons' => array( 'AdnSS' => 1 ),
		),
		// Free Plus, the SMS add-on off.
		'free_plus' => array(
			'plan'   => 3,
			'addons' => array( 'AdnSS' => 0 ),
		),
		// Free.
		'free'      => array(
			'plan'   => 2,
			'addons' => array( 'AdnSS' => 0 ),
		),
		// Pro with Conditional Logic and Address & Postal on (the new-form and date-rule checks).
		'pro_form'  => array(
			'plan'   => 1,
			'addons' => array(
				'AdnSMF' => 1,
				'AdnADR' => 1,
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
		$next    = array();
		$created = array();

	$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
		$checks[] = array( 'ok' => $ok, 'name' => $name );
	};
	$canned = static function ( $pre, $args, $url ) use ( &$next ) {
		if ( false === strpos( (string) $url, 'efb-settings-test.invalid' ) ) {
			return $pre;
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => wp_json_encode( array_shift( $next ) ) ) ) ), 'usage' => array( 'prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20 ) ) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
		);
	};
	$chat   = static function ( array $params ): array {
		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/chat' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return (array) rest_do_request( $request )->get_data();
	};
	$text   = static fn ( array $data ): string => implode( ' ', (array) ( $data['errors'] ?? array() ) ) . ' ' . (string) ( $data['reply'] ?? '' );
	$fields = array(
		array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		array( 'id' => 'email', 'label' => 'Email', 'type' => 'email' ),
	);
	$set    = static fn ( string $property, $value ): array => array( 'op' => 'set_form_property', 'property' => $property, 'value' => $value );
	$turn   = static fn ( array $ops, string $reply = 'Done.' ): array => array( 'reply' => $reply, 'action' => 'customize', 'operations' => $ops );
	$props  = static fn ( array $data ): array => array_column( (array) ( $data['operations'] ?? array() ), 'value', 'property' );


		add_filter( 'pre_http_request', $canned, 10, 3 );
		try {
			wp_set_current_user( 1 );
			\AISAAutoPilot\Core\Settings::update( array( 'provider' => 'deepseek', 'base_url' => 'https://efb-settings-test.invalid/v1', 'model' => 'efb-settings-test', 'key_management' => 'keyvault', 'efb_copilot_enabled' => true ) );
			\AISAAutoPilot\Core\Settings::set_api_key( 'efb-settings-test-key' ); // Inert; never printed or sent anywhere.
			delete_option( 'aisaautopilot_usage_totals' );
			$check( \AISAAutoPilot\Efb\EfbCapabilities::available(), 'Easy Form Builder publishes its registry (capabilities_efb)' );
			$open = array( 'has_form' => true, 'fields' => $fields, 'form_type' => 'form' );
			$sms    = array( $set( 'sms_notification', true ), $set( 'sms_admin_numbers', '+491701234567' ), array( 'op' => 'add_field', 'field' => array( 'type' => 'mobile', 'label' => 'Mobile' ) ), array( 'op' => 'set_field_property', 'target' => 'Mobile', 'property' => 'sms_to_respondent', 'value' => true ) );

			if ( 'pro' === $part ) {
			// 1. Pro: private form, email subject, redirect to a typed address and to the home page.
			$next[] = $turn( array( $set( 'require_login', true ), $set( 'email_subject', 'New enquiry' ), $set( 'thank_you_redirect', 'https://google.com' ) ) );
			$pro    = $chat( $open + array( 'message' => 'make it private, subject New enquiry, redirect : google.com' ) );
			$check( array( 'require_login' => true, 'email_subject' => 'New enquiry', 'thank_you_redirect' => 'https://google.com' ) === $props( $pro ) && empty( $pro['upgrade_required'] ), 'Pro applies private form, email subject and a redirect to the address the admin typed' );

			$next[] = $turn( array( $set( 'thank_you_redirect', home_url( '/' ) ) ) );
			$home   = $chat( $open + array( 'message' => 'redirect : home page' ) );
			$check( array( 'thank_you_redirect' => home_url( '/' ) ) === $props( $home ), '"Home page" becomes this site\'s own address' );

			$next[] = $turn( array( $set( 'thank_you_redirect', 'https://made-up.example/thanks' ) ), 'Done, visitors are redirected.' );
			$fake   = $chat( $open + array( 'message' => 'redirect visitors somewhere nice' ) );
			$check( array() === (array) ( $fake['operations'] ?? array() ) && false !== strpos( $text( $fake ), 'full address' ) && false === strpos( $text( $fake ), 'redirected' ), 'An address nobody typed is not applied; the admin is asked for it and the model\'s claim is dropped' );

			// 2. Form type: a sign-in form brings its two fields; a payment type is not a switch.
			$next[] = $turn( array( $set( 'form_type', 'login' ) ) );
			$login  = $chat( $open + array( 'message' => 'change form type to "login"' ) );
			$items  = (array) ( $login['operations'][0]['items'] ?? array() );
			$check( 'login' === ( $login['operations'][0]['value'] ?? '' ) && array( 'emaillogin', 'passwordlogin' ) === array_column( $items, 'id_' ), 'Form type "login" is applied with the two fields EFB\'s sign-in form reads' );

			$next[] = $turn( array( $set( 'form_type', 'payment' ) ) );
			$pay    = $chat( $open + array( 'message' => 'change form type to payment' ) );
			$check( array() === (array) ( $pay['operations'] ?? array() ) && false !== strpos( $text( $pay ), 'adding a payment' ), 'A payment form is not a type switch: the reply says how it is made' );

			// 3. SMS: refused without the add-on, with an install offer; applied with it.
			$next[] = $turn( $sms, 'SMS is on (smsEnableEl) with the SMS add-on (AdnSS).' );
			$off    = $chat( $open + array( 'message' => 'text +491701234567 and the person who fills it in', 'addons' => array( 'AdnSS' => 0 ) ) );
			$check( 'AdnSS' === ( $off['install'][0]['code'] ?? '' ) && true === ( $off['install'][0]['allowed'] ?? null ) && array( 'add_field' ) === array_column( (array) ( $off['operations'] ?? array() ), 'op' ) && false !== strpos( $text( $off ), 'SMS add-on' ), 'SMS without its add-on is not switched on; installing the add-on is offered' );
			$check( false === strpos( (string) ( $off['reply'] ?? '' ), 'smsEnableEl' ) && false === strpos( (string) ( $off['reply'] ?? '' ), 'AdnSS' ) && false === strpos( (string) ( $off['reply'] ?? '' ), 'SMS is on' ), 'The reply never shows a control id or an add-on code, and does not claim the part that was refused' );

			}

			if ( 'pro_sms' === $part ) {
			$next[] = $turn( $sms );
			$on     = $chat( $open + array( 'message' => 'text +491701234567 and the person who fills it in', 'addons' => array( 'AdnSS' => 1 ) ) );
			$check( array( 'set_form_property', 'set_form_property', 'add_field', 'set_field_property' ) === array_column( (array) ( $on['operations'] ?? array() ), 'op' ) && 'mobile' === ( $on['operations'][2]['items'][0]['type'] ?? '' ), 'With the add-on, SMS is switched on, the typed number is set and the respondent is texted through a mobile field' );

			}

			if ( 'free_plus' === $part ) {
			// 4. Free Plus: email subject and redirect are included; the mobile layout block is Pro only.
			$next[] = $turn( array( $set( 'email_subject', 'Hello' ), $set( 'thank_you_redirect', 'http://127.0.0.1/wp/' ) ) );
			$plus   = $chat( $open + array( 'message' => 'subject Hello and redirect to http://127.0.0.1/wp/' ) );
			$check( array( 'email_subject' => 'Hello', 'thank_you_redirect' => 'http://127.0.0.1/wp/' ) === $props( $plus ) && empty( $plus['upgrade_required'] ), 'Free Plus applies the email subject and the redirect without a plan message' );

			$next[] = $turn( array( $set( 'global_mobile_hide_labels', true ) ) );
			$mobile = $chat( $open + array( 'message' => 'hide all labels on mobile' ) );
			$check( ! empty( $mobile['upgrade_required'] ) && array() === (array) ( $mobile['operations'] ?? array() ) && false !== strpos( (string) $mobile['reply'], 'Easy Form Builder Pro' ) && false !== strpos( (string) $mobile['reply'], 'Free Plus' ) && false === strpos( (string) $mobile['reply'], 'does not unlock' ), 'Free Plus is told in one sentence that the mobile layout block is part of Pro' );

			$next[] = $turn( $sms );
			$nosms  = $chat( $open + array( 'message' => 'text +491701234567', 'addons' => array( 'AdnSS' => 0 ) ) );
			$check( 'AdnSS' === ( $nosms['install'][0]['code'] ?? '' ) && false === ( $nosms['install'][0]['allowed'] ?? null ) && 'pro' === ( $nosms['install'][0]['plan'] ?? '' ), 'On Free Plus the SMS add-on is shown as needing Pro instead of an install button' );

			}

			if ( 'free' === $part ) {
			// 5. Free: email subject is named with the plans that include it; the private-form switch still works.
			$next[] = $turn( array( $set( 'email_subject', 'Hello' ) ) );
			$free   = $chat( $open + array( 'message' => 'email subject Hello' ) );
			$check( ! empty( $free['upgrade_required'] ) && false !== strpos( (string) $free['reply'], 'Email subject' ) && false !== strpos( (string) $free['reply'], 'Free Plus and Pro' ) && false !== strpos( (string) $free['reply'], 'left it unchanged' ), 'Free is told which plans include the email subject, and that nothing was changed' );

			$next[] = $turn( array( $set( 'require_login', true ) ) );
			$priv   = $chat( $open + array( 'message' => 'make this form private' ) );
			$check( array( 'require_login' => true ) === $props( $priv ), 'A private form works on every plan' );

			}

			if ( 'pro_form' === $part ) {
			// 6. A new form with the address block, a map, a link and an HTML block is stored the way EFB stores them.
			$next[] = array(
				'reply'  => 'Here is the form.',
				'action' => 'create_form',
				'form'   => array(
					'title'  => 'Delivery',
					'fields' => array(
						array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
						array( 'type' => 'address', 'label' => 'Delivery address' ),
						array( 'type' => 'maps', 'label' => 'Door' ),
						array( 'type' => 'link', 'label' => 'Terms', 'url' => 'https://example.com/terms' ),
						array( 'type' => 'link', 'label' => 'Invented', 'url' => 'https://nobody-typed-this.example' ),
						array( 'type' => 'html', 'label' => 'Note', 'html' => '<p>Bring <strong>ID</strong></p><script>alert(1)</script>' ),
					),
				),
			);
			$new    = $chat( array( 'has_form' => false, 'message' => 'delivery form with address, map, a link to https://example.com/terms and a note' ) );
			$req    = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/create' );
			$req->set_param( 'action_id', (string) ( $new['action_id'] ?? '' ) );
			$done    = rest_do_request( $req )->get_data();
			$form_id = (int) ( $done['result']['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$created[] = $form_id;
			}
			$raw   = $form_id > 0 ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) ) : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$saved = json_decode( str_replace( '\\"', '"', $raw ), true );
			if ( ! is_array( $saved ) ) {
				$saved = json_decode( stripslashes( $raw ), true );
			}
			$saved = is_array( $saved ) ? $saved : array();
			$heads = array_values( array_filter( $saved, static fn ( $item ): bool => is_array( $item ) && ! in_array( $item['type'] ?? '', array( 'form', 'option', 'step' ), true ) ) );
			$types = array_column( $heads, 'type' );
			$check( array( 'text', 'conturyList', 'stateProvince', 'cityList', 'text', 'text', 'maps', 'link', 'html' ) === $types, 'The new form holds the address block as country, state, city and two text fields, then the map, the typed link and the HTML block (the invented link is left out): ' . implode( ',', $types ) );
			$by     = array_column( $heads, null, 'type' );
			$amount = array_column( $heads, 'amount' );
			// Saved through EFB's form package import, which stores positions the way EFB's builder saves them (digits as
			// strings, "10"); EFB reads every position as a number (efbOrderOf / efbNormalizeOrderEfb in new-efb.js).
			$numeric = array_map( static fn ( $a ): ?int => is_int( $a ) || ( is_string( $a ) && ctype_digit( $a ) ) ? (int) $a : null, $amount );
			$check( array() !== $numeric && ! in_array( null, $numeric, true ) && $numeric === range( $numeric[0], $numeric[0] + count( $numeric ) - 1 ), 'Fields are numbered one after another, so a state list stays tied to its country list: ' . implode( ',', $amount ) );
			$check( 'https://example.com/terms' === ( $by['link']['href'] ?? '' ) && false !== strpos( (string) ( $by['html']['value'] ?? '' ), '<strong>ID</strong>' ) && false === strpos( (string) ( $by['html']['value'] ?? '' ), 'alert' ), 'The link keeps the typed address; the HTML block keeps its formatting and loses the script with its code' );
			$check( 241 === count( array_filter( $saved, static fn ( $item ): bool => is_array( $item ) && 'option' === ( $item['type'] ?? '' ) && ( $item['parent'] ?? '' ) === ( $by['conturyList']['id_'] ?? '-' ) ) ), 'The country list arrives with its 241 countries' );

			// 7. Conditional logic on a date: before / after a calendar date, as EFB's logic engine compares them.
			$dated  = array(
				array( 'id' => 'when', 'label' => 'Event date', 'type' => 'date' ),
				array( 'id' => 'note', 'label' => 'Early-bird note', 'type' => 'text' ),
			);
			$rule   = static fn ( array $when ): array => array( 'op' => 'add_logic_rule', 'rule' => array( 'name' => 'Early bird', 'match' => 'all', 'when' => array( $when ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Early-bird note' ) ) ) );
			$next[] = $turn( array( $rule( array( 'field' => 'Event date', 'compare' => 'lt', 'value' => '2026-12-01' ) ) ) );
			$date   = $chat( array( 'has_form' => true, 'logic_addon' => true, 'fields' => $dated, 'message' => 'show the early-bird note if the event date is before 1 December 2026', 'addons' => array( 'AdnSMF' => 1 ) ) );
			$item   = (array) ( $date['operations'][0]['rule']['conditions']['items'][0] ?? array() );
			$check( 'date_before' === ( $item['compare'] ?? '' ) && '2026-12-01' === ( $item['value'] ?? '' ) && 'when' === ( $item['field_id'] ?? '' ), 'A rule on a date field becomes a date comparison with a full calendar date' );

			$next[] = $turn( array( $rule( array( 'field' => 'Event date', 'compare' => 'date_before', 'value' => '1 December' ) ) ) );
			$vague  = $chat( array( 'has_form' => true, 'logic_addon' => true, 'fields' => $dated, 'message' => 'show the note before 1 December', 'addons' => array( 'AdnSMF' => 1 ) ) );
			$check( array() === (array) ( $vague['operations'] ?? array() ) && false !== strpos( $text( $vague ), 'full date' ), 'A date rule without a full date is refused with what is needed' );
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
