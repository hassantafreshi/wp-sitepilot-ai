<?php
/**
 * Local WordPress checks for Form Studio layout and editing. No request leaves the site:
 * `pre_http_request` answers the AI call with a canned plan and keeps the request body, so what
 * the model would have been sent can be read.
 *
 * Proves: a plan keeps each field's key, width, hint and placeholder through the preview; a change
 * sends the model the whole current plan (not a summary) and names the selected field only when it
 * is a field of that plan; an expired plan or another administrator's plan cannot be changed and
 * costs no AI request; the edit endpoint changes a plan with no AI request, validates it, refuses
 * unknown fields and operations, and leaves the earlier plan valid for Undo; and the published form
 * lays fields out in columns, shows hints, and renders number, date and web-address fields.
 * Settings, the API key, the usage totals, the activity log and the forms are restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionStore;

$missing   = new stdClass();
$options   = array( Settings::OPTION, Settings::KEY_OPTION, 'aisaautopilot_logs', 'aisaautopilot_usage_totals', 'aisaautopilot_usage_period', 'aisaautopilot_monthly_budget' );
$snapshots = array();
foreach ( $options as $option ) {
	$snapshots[ $option ] = get_option( $option, $missing );
}
$checks  = array();
$bodies  = array();
$next    = array();
$created = array();
$other   = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
// Answers the AI call with whatever plan the test queued, and records what would have been sent.
$canned = static function ( $pre, $args, $url ) use ( &$bodies, &$next ) {
	if ( false === strpos( (string) $url, 'form-layout-test.invalid' ) ) {
		return $pre;
	}
	$bodies[] = json_decode( (string) ( $args['body'] ?? '' ), true );
	$plan     = array_shift( $next );
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => wp_json_encode( $plan ) ) ) ),
				'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20 ),
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};
$rest = static function ( string $route, array $params ): WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/' . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};
$prompt_of = static function ( $body ): string {
	return is_array( $body ) ? (string) ( $body['messages'][1]['content'] ?? '' ) : '';
};

add_filter( 'pre_http_request', $canned, 10, 3 );
try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'form_composer_enabled' => true,
			'provider'              => 'deepseek',
			'base_url'              => 'https://form-layout-test.invalid/v1',
			'model'                 => 'form-layout-test',
			'key_management'        => 'keyvault',
		)
	);
	Settings::set_api_key( 'form-layout-test-key' ); // Inert; never printed or sent anywhere.
	delete_option( 'aisaautopilot_usage_totals' );

	// 1. A plan keeps key, width, hint and placeholder through to the preview.
	$next[] = array(
		'form' => array(
			'title'  => 'Yoga class',
			'fields' => array(
				array( 'type' => 'text', 'label' => 'First name', 'required' => true, 'width' => 'half', 'placeholder' => 'Ada' ),
				array( 'type' => 'text', 'label' => 'Last name', 'required' => true, 'width' => 'half' ),
				array( 'type' => 'email', 'label' => 'Email', 'width' => 'two_thirds', 'help' => 'We reply within a day' ),
				array( 'type' => 'tel', 'label' => 'Phone', 'width' => 'third' ),
				array( 'type' => 'number', 'label' => 'Guests', 'width' => 'quarter', 'settings' => array( 'min' => '1', 'max' => '10' ) ),
				array( 'type' => 'date', 'label' => 'Day', 'width' => 'quarter', 'settings' => array( 'min' => 'today' ) ),
				array( 'type' => 'url', 'label' => 'Website' ),
				array( 'type' => 'textarea', 'label' => 'Message' ),
			),
		),
	);
	$first   = $rest( 'forms/plan', array( 'prompt' => 'Yoga class. First and last name side by side.' ) );
	$data    = $first->get_data();
	$fields  = $data['preview']['fields'] ?? array();
	$by_key  = array_column( $fields, null, 'key' );
	$check(
		200 === $first->get_status() && 8 === count( $fields )
		&& 'half' === ( $by_key['first_name']['width'] ?? '' ) && 'half' === ( $by_key['last_name']['width'] ?? '' )
		&& 'two_thirds' === ( $by_key['email']['width'] ?? '' ) && 'We reply within a day' === ( $by_key['email']['help'] ?? '' )
		&& 'Ada' === ( $by_key['first_name']['placeholder'] ?? '' ) && 'full' === ( $by_key['message']['width'] ?? '' )
		&& '10' === ( $by_key['guests']['settings']['max'] ?? '' ),
		'A plan keeps each field\'s key, width, hint, placeholder and settings in the preview'
	);
	$check(
		false !== strpos( $prompt_of( $bodies[0] ?? null ), 'Yoga class' ) && false === strpos( $prompt_of( $bodies[0] ?? null ), 'CURRENT_FORM' ),
		'A new form sends only the request, with no current form'
	);
	$plan_id = (string) ( $data['action_id'] ?? '' );

	// 2. A change sends the whole current plan, and the selected field by key.
	$next[]  = array(
		'form' => array(
			'title'  => 'Yoga class',
			'fields' => array(
				array( 'key' => 'first_name', 'type' => 'text', 'label' => 'First name', 'required' => false, 'width' => 'half', 'placeholder' => 'Ada' ),
				array( 'key' => 'last_name', 'type' => 'text', 'label' => 'Last name', 'required' => true, 'width' => 'half' ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'width' => 'two_thirds', 'help' => 'We reply within a day' ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'width' => 'third' ),
				array( 'key' => 'guests', 'type' => 'number', 'label' => 'Guests', 'width' => 'quarter', 'settings' => array( 'min' => '1', 'max' => '10' ) ),
				array( 'key' => 'day', 'type' => 'date', 'label' => 'Day', 'width' => 'quarter', 'settings' => array( 'min' => 'today' ) ),
				array( 'key' => 'website', 'type' => 'url', 'label' => 'Website' ),
				array( 'key' => 'message', 'type' => 'textarea', 'label' => 'Message' ),
			),
		),
	);
	$revised = $rest( 'forms/plan', array( 'prompt' => 'Make this optional', 'revise_of' => $plan_id, 'selected' => 'first_name' ) );
	$sent    = $prompt_of( $bodies[1] ?? null );
	$sys     = (string) ( $bodies[1]['messages'][0]['content'] ?? '' );
	$check(
		200 === $revised->get_status() && false !== strpos( $sent, 'CURRENT_FORM:' ) && false !== strpos( $sent, '"width":"two_thirds"' )
		&& false !== strpos( $sent, 'We reply within a day' ) && false !== strpos( $sent, '"placeholder":"Ada"' ) && false !== strpos( $sent, '"max":"10"' )
		&& false !== strpos( $sent, 'SELECTED_FIELD: first_name' ) && false !== strpos( $sys, 'CHANGING AN EXISTING FORM' ),
		'A change sends the model the whole current plan with every detail, and names the selected field'
	);
	$rev_fields = array_column( $revised->get_data()['preview']['fields'] ?? array(), null, 'key' );
	$check(
		false === ( $rev_fields['first_name']['required'] ?? true ) && 'two_thirds' === ( $rev_fields['email']['width'] ?? '' ) && 8 === count( $rev_fields ),
		'The changed plan keeps the field keys and what was not asked to change'
	);

	// 3. A selected key that is not a field of the plan is dropped; an expired or foreign plan costs no AI request.
	$next[] = array( 'form' => array( 'title' => 'Yoga class', 'fields' => array( array( 'key' => 'email', 'type' => 'email', 'label' => 'Email' ) ) ) );
	$rest( 'forms/plan', array( 'prompt' => 'Keep only email', 'revise_of' => $plan_id, 'selected' => 'not_a_field' ) );
	$check( false === strpos( $prompt_of( $bodies[2] ?? null ), 'SELECTED_FIELD' ), 'A selected key that is not a field of the plan is not passed to the model' );
	$count  = count( $bodies );
	$gone   = $rest( 'forms/plan', array( 'prompt' => 'Change it', 'revise_of' => 'no-such-plan' ) );
	$store  = new ActionStore();
	$theirs = $store->create( array( 'title' => 'x', 'fields' => array( array( 'key' => 'a', 'type' => 'text', 'label' => 'A' ) ) ), 'native_form' );
	$other  = (int) wp_insert_user( array( 'user_login' => 'layout-test-' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	wp_set_current_user( $other );
	$foreign = $rest( 'forms/plan', array( 'prompt' => 'Change it', 'revise_of' => $plan_id ) );
	$foreign_edit = $rest( 'forms/edit', array( 'action_id' => $plan_id, 'ops' => array( array( 'op' => 'set_width', 'key' => 'email', 'value' => 'half' ) ) ) );
	wp_set_current_user( 1 );
	$check(
		410 === $gone->get_status() && 'plan_expired' === ( $gone->get_data()['code'] ?? '' ) && 410 === $foreign->get_status() && 410 === $foreign_edit->get_status() && count( $bodies ) === $count,
		'An expired plan, or another administrator\'s plan, cannot be changed and costs no AI request'
	);

	// 4. The edit endpoint changes a plan without an AI request and leaves the earlier plan valid for Undo.
	$rev_id = (string) ( $revised->get_data()['action_id'] ?? '' );
	$edit   = $rest(
		'forms/edit',
		array(
			'action_id' => $rev_id,
			'ops'       => array(
				array( 'op' => 'set_width', 'key' => 'website', 'value' => 'half' ),
				array( 'op' => 'set_width', 'key' => 'message', 'value' => 'half' ),
				array( 'op' => 'set_text', 'key' => 'website', 'prop' => 'help', 'value' => 'Your site or profile' ),
				array( 'op' => 'move', 'key' => 'website', 'direction' => 'up' ),
			),
		)
	);
	$edited = array_column( $edit->get_data()['preview']['fields'] ?? array(), null, 'key' );
	$check(
		200 === $edit->get_status() && count( $bodies ) === $count && ( $edit->get_data()['action_id'] ?? '' ) !== $rev_id
		&& 'half' === ( $edited['website']['width'] ?? '' ) && 'Your site or profile' === ( $edited['website']['help'] ?? '' )
		&& 'two_thirds' === ( $edited['email']['width'] ?? '' ) && 'We reply within a day' === ( $edited['email']['help'] ?? '' )
		&& array_search( 'website', array_keys( $edited ), true ) < array_search( 'message', array_keys( $edited ), true ),
		'A click-sized edit changes the plan with no AI request and keeps everything else'
	);
	$check( null !== $store->get( $rev_id ) && null !== $store->get( (string) $edit->get_data()['action_id'] ), 'The plan before an edit stays valid, so Undo can bring it back' );
	$bad_field = $rest( 'forms/edit', array( 'action_id' => $rev_id, 'ops' => array( array( 'op' => 'set_width', 'key' => 'ghost', 'value' => 'half' ) ) ) );
	$bad_op    = $rest( 'forms/edit', array( 'action_id' => $rev_id, 'ops' => array( array( 'op' => 'set_text', 'key' => 'email', 'prop' => 'type', 'value' => 'payment' ) ) ) );
	$too_many  = $rest( 'forms/edit', array( 'action_id' => $rev_id, 'ops' => array_fill( 0, 21, array( 'op' => 'set_required', 'key' => 'email', 'value' => true ) ) ) );
	$none      = $rest( 'forms/edit', array( 'action_id' => $rev_id, 'ops' => array() ) );
	$check(
		422 === $bad_field->get_status() && 422 === $bad_op->get_status() && 400 === $too_many->get_status() && 400 === $none->get_status(),
		'An unknown field, an operation outside the list, too many operations or none are refused'
	);

	// 5. Approve the edited plan; the published form lays fields out in columns.
	$approve = $rest( 'forms/execute', array( 'action_id' => (string) $edit->get_data()['action_id'] ) );
	$form_id = (int) ( $approve->get_data()['result']['form_id'] ?? 0 );
	if ( $form_id > 0 ) {
		$created[] = $form_id;
		wp_update_post( array( 'ID' => $form_id, 'post_status' => 'publish' ) );
	}
	$html = do_shortcode( '[aisaautopilot_form id="' . $form_id . '"]' );
	$check(
		$form_id > 0 && false !== strpos( $html, 'class="aisaautopilot-form__fields"' )
		&& substr_count( $html, 'aisaautopilot-form__row--half' ) >= 3 && false !== strpos( $html, 'aisaautopilot-form__row--two_thirds' )
		&& false !== strpos( $html, 'aisaautopilot-form__row--quarter' ) && false !== strpos( $html, 'aisaautopilot-form__row--third' ),
		'The published form puts fields in columns by their widths'
	);
	$check(
		1 === preg_match( '/id="sp-help-email"[^>]*>We reply within a day/', $html ) && false !== strpos( $html, 'aria-describedby="sp-help-email"' )
		&& 1 === preg_match( '/type="number"[^>]*min="1"[^>]*max="10"[^>]*step="any"/', $html )
		&& 1 === preg_match( '/type="date"[^>]*min="' . preg_quote( wp_date( 'Y-m-d' ), '/' ) . '"/', $html )
		&& false !== strpos( $html, 'type="url"' ),
		'The published form shows hints, links them to the field, and renders number, date and web-address fields with their limits'
	);
	$css = (string) file_get_contents( AISAAUTOPILOT_DIR . 'assets/frontend/form.css' );
	$check(
		false !== strpos( $css, 'repeat(12' ) && false !== strpos( $css, '@container' ) && false !== strpos( $css, '@media (max-width: 400px)' ),
		'The form stylesheet is a twelve-column grid that stacks on a narrow form'
	);
} catch ( Throwable $error ) {
	$check( false, 'Unexpected ' . get_class( $error ) . ': ' . $error->getMessage() );
} finally {
	remove_filter( 'pre_http_request', $canned, 10 );
	foreach ( $created as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $other > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $other );
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
