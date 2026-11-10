<?php
/**
 * Local WordPress checks for conditional logic in the Easy Form Builder copilot and for the add-on
 * offers. No request leaves the site: `pre_http_request` answers the AI call with a canned reply.
 *
 * Proves: a new form can be planned with rules, which are resolved to the ids the form is saved with,
 * shown in plain words in the preview and saved with the form; with the Conditional Logic add-on off
 * the form is still built, no rule is applied, and installing the add-on is offered (allowed on Pro,
 * marked as needing a plan on Free); Free Plus keeps three rules of two conditions and says to buy Pro
 * or change the form; a rule about a field the form does not have is refused by name; a refused
 * payment offers its gateway's add-on. Settings, EFB's plan flag, usage, the log and the forms this
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
$next    = array();
$created = array();

$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$canned = static function ( $pre, $args, $url ) use ( &$next ) {
	if ( false === strpos( (string) $url, 'efb-logic-test.invalid' ) ) {
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
$text   = static fn ( array $data ): string => implode( ' ', (array) ( $data['errors'] ?? array() ) );
$form   = static function ( array $logic ): array {
	return array(
		'reply'  => 'Here is the form.',
		'action' => 'create_form',
		'form'   => array(
			'title'  => 'Event registration',
			'fields' => array(
				array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
				array( 'type' => 'radio', 'label' => 'How did you hear about us', 'options' => array( 'Friend', 'Search', 'Other' ) ),
				array( 'type' => 'text', 'label' => 'Please specify' ),
				array( 'type' => 'radio', 'label' => 'Attending', 'options' => array( 'Yes', 'No' ) ),
				array( 'type' => 'number', 'label' => 'Guests' ),
				array( 'type' => 'text', 'label' => 'Group code' ),
				array( 'type' => 'textarea', 'label' => 'Dietary needs' ),
			),
			'logic'  => $logic,
		),
	);
};
$other  = array( 'name' => 'Other source', 'match' => 'all', 'when' => array( array( 'field' => 'How did you hear about us', 'compare' => 'is', 'value' => 'Other' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Please specify' ) ) );
$attend = array( 'name' => 'Attending', 'match' => 'all', 'when' => array( array( 'field' => 'Attending', 'compare' => 'is', 'value' => 'Yes' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Guests' ), array( 'action' => 'show_field', 'target' => 'Dietary needs' ) ) );
$group  = array( 'name' => 'Groups', 'match' => 'all', 'when' => array( array( 'field' => 'Guests', 'compare' => 'gt', 'value' => '5' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Group code' ) ) );
$fourth = array( 'name' => 'Require code', 'match' => 'all', 'when' => array( array( 'field' => 'Guests', 'compare' => 'gte', 'value' => '10' ) ), 'then' => array( array( 'action' => 'set_required', 'target' => 'Group code' ) ) );
$three  = array( 'name' => 'Three', 'match' => 'any', 'when' => array( array( 'field' => 'Attending', 'compare' => 'is', 'value' => 'Yes' ), array( 'field' => 'Guests', 'compare' => 'gt', 'value' => '1' ), array( 'field' => 'Name', 'compare' => 'is_not_empty' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Dietary needs' ) ) );
$ghost  = array( 'name' => 'Ghost', 'match' => 'all', 'when' => array( array( 'field' => 'Country', 'compare' => 'is', 'value' => 'US' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Guests' ) ) );

add_filter( 'pre_http_request', $canned, 10, 3 );
try {
	wp_set_current_user( 1 );
	Settings::update( array( 'provider' => 'deepseek', 'base_url' => 'https://efb-logic-test.invalid/v1', 'model' => 'efb-logic-test', 'key_management' => 'keyvault', 'efb_copilot_enabled' => true ) );
	Settings::set_api_key( 'efb-logic-test-key' ); // Inert; never printed or sent anywhere.
	delete_option( 'aisaautopilot_usage_totals' );
	update_option( 'emsfb_pro', 1 );

	// 1. Pro with the add-on on: rules resolved, previewed and saved.
	$next[] = $form( array( $other, $attend, $group ) );
	$new    = $chat( array( 'has_form' => false, 'message' => 'event registration with logic', 'addons' => array( 'AdnSMF' => 1 ) ) );
	$lines  = (array) ( $new['preview']['logic'] ?? array() );
	$check(
		'preview' === ( $new['type'] ?? '' ) && 3 === count( $lines ) && false !== strpos( implode( ' ', $lines ), 'Please specify' ) && empty( $new['install'] ),
		'A new form with logic is previewed with each rule in plain words'
	);
	$req = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/create' );
	$req->set_param( 'action_id', (string) ( $new['action_id'] ?? '' ) );
	$done    = rest_do_request( $req )->get_data();
	$form_id = (int) ( $done['result']['form_id'] ?? 0 );
	if ( $form_id > 0 ) {
		$created[] = $form_id;
	}
	$raw   = $form_id > 0 ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) ) : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$items = json_decode( str_replace( '\\"', '"', $raw ), true );
	if ( ! is_array( $items ) ) {
		$items = json_decode( stripslashes( $raw ), true );
	}
	$settings = is_array( $items ) ? (array) ( $items[0] ?? array() ) : array();
	$ids      = array();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( isset( $item['id_'] ) ) {
			$ids[ (string) $item['id_'] ] = $item;
		}
	}
	$rules    = (array) ( $settings['logic_rules'] ?? array() );
	$resolved = array() !== $rules;
	foreach ( $rules as $rule ) {
		foreach ( (array) ( $rule['conditions']['items'] ?? array() ) as $condition ) {
			$resolved = $resolved && isset( $ids[ (string) ( $condition['field_id'] ?? '' ) ] );
		}
		foreach ( (array) ( $rule['actions'] ?? array() ) as $action ) {
			$resolved = $resolved && isset( $ids[ (string) ( $action['target'] ?? '' ) ] );
		}
	}
	$check( $form_id > 0 && true === ( $settings['logic'] ?? null ) && 3 === count( $rules ) && $resolved, 'The form is saved with its rules, every condition and target pointing at a real field of that form' );

	// 2. Add-on off: the form is built without rules and installing it is offered.
	$next[] = $form( array( $other ) );
	$off    = $chat( array( 'has_form' => false, 'message' => 'event registration with logic', 'addons' => array( 'AdnSMF' => 0 ) ) );
	$offer  = (array) ( $off['install'][0] ?? array() );
	$check(
		'preview' === ( $off['type'] ?? '' ) && array() === (array) ( $off['preview']['logic'] ?? array() ) && 'AdnSMF' === ( $offer['code'] ?? '' ) && true === ( $offer['allowed'] ?? null )
		&& false !== strpos( $text( $off ), 'install it for you if you allow it' ),
		'With the Conditional Logic add-on off, the form is built without rules and installing the add-on is offered'
	);
	$next[] = array( 'reply' => 'Added the rule.', 'action' => 'customize', 'operations' => array( array( 'op' => 'add_logic_rule', 'rule' => $other ) ) );
	$live   = $chat( array( 'has_form' => true, 'logic_addon' => false, 'message' => 'show specify for other', 'addons' => array( 'AdnSMF' => 0 ), 'fields' => array( array( 'id' => 'f1', 'label' => 'How did you hear about us', 'type' => 'radio', 'options' => array( array( 'id' => 'o1', 'value' => 'Other' ) ) ), array( 'id' => 'f2', 'label' => 'Please specify', 'type' => 'text' ) ) ) );
	$check( empty( $live['operations'] ) && 'AdnSMF' === ( $live['install'][0]['code'] ?? '' ), 'On an open form a rule without the add-on is not applied and the add-on is offered' );

	// 3. Free Plus: three rules of two conditions; the rest is explained with Pro as the way out.
	update_option( 'emsfb_pro', 3 );
	$next[] = $form( array( $other, $attend, $group, $fourth ) );
	$fp     = $chat( array( 'has_form' => false, 'message' => 'four rules', 'addons' => array( 'AdnSMF' => 1 ) ) );
	$check(
		3 === count( (array) ( $fp['preview']['logic'] ?? array() ) ) && false !== strpos( $text( $fp ), 'Free Plus allows 3' ) && false !== strpos( $text( $fp ), 'Buy Pro' )
		&& in_array( 'See EFB Pro', array_column( (array) ( $fp['links'] ?? array() ), 'label' ), true ),
		'Free Plus keeps three rules and says to buy Pro or change the form, with a link'
	);
	$next[] = $form( array( $three ) );
	$fc     = $chat( array( 'has_form' => false, 'message' => 'three conditions', 'addons' => array( 'AdnSMF' => 1 ) ) );
	$check( array() === (array) ( $fc['preview']['logic'] ?? array() ) && false !== strpos( $text( $fc ), '2 conditions in one rule' ), 'Free Plus refuses a rule with three conditions and says why' );

	// 4. Free: no rules; the add-on is offered as needing a plan.
	update_option( 'emsfb_pro', 2 );
	$next[] = $form( array( $other ) );
	$free   = $chat( array( 'has_form' => false, 'message' => 'logic on free', 'addons' => array( 'AdnSMF' => 0 ) ) );
	$check( 'AdnSMF' === ( $free['install'][0]['code'] ?? '' ) && false === ( $free['install'][0]['allowed'] ?? null ), 'On Free the Conditional Logic add-on is shown as needing Free Plus or Pro, not installable' );
	$next[] = $form( array( $other ) );
	$free2  = $chat( array( 'has_form' => false, 'message' => 'logic on free with add-on', 'addons' => array( 'AdnSMF' => 1 ) ) );
	$check( array() === (array) ( $free2['preview']['logic'] ?? array() ) && false !== strpos( $text( $free2 ), 'Free Plus or Pro' ), 'On Free with the add-on present no rule is planned and the plan is named' );

	// 4b. Free shows two pages at most: later pages fold into page two, with the way to more.
	$next[] = array( 'reply' => 'ok', 'action' => 'create_form', 'form' => array( 'title' => 'Long', 'fields' => array( array( 'type' => 'text', 'label' => 'A' ), array( 'type' => 'steps', 'label' => 'Two' ), array( 'type' => 'text', 'label' => 'B' ), array( 'type' => 'steps', 'label' => 'Three' ), array( 'type' => 'text', 'label' => 'C' ) ) ) );
	$pages  = $chat( array( 'has_form' => false, 'message' => 'three pages', 'addons' => array() ) );
	$check(
		2 === (int) ( $pages['preview']['steps'] ?? 0 ) && 3 === (int) ( $pages['preview']['field_count'] ?? 0 ) && false !== strpos( $text( $pages ), 'two at most' ) && ! empty( $pages['links'] ),
		'On Free a three-page request becomes two pages with every field kept, and says how to get more'
	);

	// 5. A rule about a field the form does not have is refused by name.
	update_option( 'emsfb_pro', 1 );
	$next[] = $form( array( $ghost ) );
	$gh     = $chat( array( 'has_form' => false, 'message' => 'ghost rule', 'addons' => array( 'AdnSMF' => 1 ) ) );
	$check( array() === (array) ( $gh['preview']['logic'] ?? array() ) && false !== strpos( $text( $gh ), '“Country”' ), 'A rule about a field the form does not have is refused, naming the field' );

	// 5b. A rule about a field added in the same message is resolved against that new field.
	$next[] = array(
		'reply'      => 'Added Country and the rule.',
		'action'     => 'customize',
		'operations' => array(
			array( 'op' => 'add_field', 'field' => array( 'type' => 'text', 'label' => 'Country' ) ),
			array( 'op' => 'add_logic_rule', 'rule' => array( 'name' => 'Biz country', 'match' => 'all', 'when' => array( array( 'field' => 'Customer type', 'compare' => 'is', 'value' => 'Business' ) ), 'then' => array( array( 'action' => 'show_field', 'target' => 'Country' ) ) ) ),
		),
	);
	$same   = $chat( array( 'has_form' => true, 'logic_addon' => true, 'message' => 'show a Country field for business customers', 'addons' => array( 'AdnSMF' => 1 ), 'fields' => array( array( 'id' => 'ct', 'label' => 'Customer type', 'type' => 'radio', 'options' => array( array( 'id' => 'ct_o1', 'value' => 'Individual' ), array( 'id' => 'ct_o2', 'value' => 'Business' ) ) ) ) ) );
	$names  = array_column( (array) ( $same['operations'] ?? array() ), 'op' );
	$added  = array_values( array_filter( (array) ( $same['operations'] ?? array() ), static fn ( $o ): bool => 'add_field' === ( $o['op'] ?? '' ) ) );
	$rule   = array_values( array_filter( (array) ( $same['operations'] ?? array() ), static fn ( $o ): bool => 'add_logic_rule' === ( $o['op'] ?? '' ) ) );
	$check(
		array( 'add_field', 'add_logic_rule' ) === $names && isset( $rule[0]['rule']['actions'][0]['target'], $added[0]['items'][0]['id_'] ) && $rule[0]['rule']['actions'][0]['target'] === $added[0]['items'][0]['id_'] && empty( $same['errors'] ),
		'A rule about a field added in the same message points at that new field, instead of being dropped while the reply claims it'
	);

	// 6. A payment whose add-on is off offers that gateway's add-on.
	$next[] = array( 'reply' => 'Added.', 'action' => 'customize', 'operations' => array( array( 'op' => 'add_payment', 'gateway' => 'paypal', 'items' => array( array( 'label' => 'Ticket', 'price' => 10 ) ) ) ) );
	$pay    = $chat( array( 'has_form' => true, 'message' => 'paypal ticket 10', 'addons' => array( 'AdnPAP' => 0 ), 'fields' => array( array( 'id' => 'f1', 'label' => 'Name', 'type' => 'text' ) ) ) );
	$check( 'AdnPAP' === ( $pay['install'][0]['code'] ?? '' ) && true === ( $pay['install'][0]['allowed'] ?? null ), 'A payment whose add-on is off offers to install that gateway' );
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
