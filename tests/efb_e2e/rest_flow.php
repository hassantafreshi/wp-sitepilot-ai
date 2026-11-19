<?php
/**
 * End-to-end checks of the Easy Form Builder copilot on a real WordPress with Easy Form Builder (dev4) and its add-ons:
 * chat -> plan -> preview -> approve -> the EFB form exists with the right JSON, for every owner rule of the EFB
 * integration (SPLIT add-ons and plans, e-mail confirmation default, forward jump, gateways through their add-on
 * contract, portal, plans, form package 1.1 undo, no direct table write). The AI provider is the inert mock
 * (aisa-ai-mock-mu.php); nothing leaves the site.
 *
 *   wp eval-file tests/efb_e2e/rest_flow.php <scenario>   (throwaway local test site only)
 *
 * EFB loads its add-ons once per request, so the add-on switches and the plan of each scenario are set by the driver
 * (tests/efb_e2e/run_rest.sh, through EFB's own settings functions) in separate processes before the scenario runs.
 * The forms a scenario creates are removed again through EFB's Form_Package::delete().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AISAAutoPilot\Efb\EfbCapabilities;

wp_set_current_user( 1 );
$aisa_scenario = (string) ( $args[0] ?? '' );
$aisa_checks   = array();
$aisa_created = array();
$aisa_check   = static function ( bool $ok, string $name, $detail = null ) use ( &$aisa_checks ): void {
	$aisa_checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok || null === $detail ? '' : ' :: ' . substr( wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ), 0, 900 ) ) . "\n";
};
$aisa_queue = static function ( array $reply ): void {
	$q   = (array) get_option( 'aisa_e2e_ai_queue', array() );
	$q[] = $reply;
	update_option( 'aisa_e2e_ai_queue', $q, false );
};
$aisa_rest = static function ( string $route, array $params ): array {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/' . $route );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	$response = rest_do_request( $request );

	return array_merge( array( '_status' => $response->get_status() ), (array) $response->get_data() );
};
$aisa_rows = static function ( int $form_id ): array {
	global $wpdb;
	$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) );
	$rows = json_decode( str_replace( '\\"', '"', $raw ), true );

	return is_array( $rows ) ? $rows : array();
};
$aisa_of_type = static function ( array $rows, string $type ): array {
	return array_values( array_filter( $rows, static fn ( $r ) => is_array( $r ) && ( $r['type'] ?? '' ) === $type ) );
};
$aisa_create = static function ( array $form, string $message = 'Build it' ) use ( $aisa_queue, $aisa_rest ): array {
	$aisa_queue(
		array(
			'reply'  => 'Here is the form.',
			'action' => 'create_form',
			'form'   => $form,
		)
	);

	return $aisa_rest(
		'efb/chat',
		array(
			'message'  => $message,
			'has_form' => false,
			'addons'   => array(),
		)
	);
};
$aisa_approve = static function ( array $preview ) use ( $aisa_rest, &$aisa_created ): array {
	$done = $aisa_rest( 'efb/create', array( 'action_id' => (string) ( $preview['action_id'] ?? '' ) ) );
	if ( ! empty( $done['result']['form_id'] ) ) {
		$aisa_created[] = (int) $done['result']['form_id'];
	}

	return $done;
};
$aisa_contact = array(
	array(
		'type'     => 'text',
		'label'    => 'Full name',
		'required' => true,
	),
	array(
		'type'     => 'email',
		'label'    => 'Email',
		'required' => true,
	),
);

try {
	if ( 'split' === $aisa_scenario ) {
		// 1. SPLIT (driver: Free Plus, AdnCLC off, AdnADR off): a calculation without Numbers & Calculations becomes a Number field; the preview names add-on and plan.
		$p    = $aisa_create(
			array(
				'title'  => 'Quote request',
				'fields' => array_merge(
					$aisa_contact,
					array(
						array(
							'type'  => 'calc',
							'label' => 'Estimated total',
						),
						array(
							'type'  => 'location',
							'label' => 'Site location',
						),
					)
				),
			)
		);
		$need = array_column( (array) ( $p['addon_required'] ?? array() ), null, 'type' );
		$aisa_check( 'preview' === ( $p['type'] ?? '' ), 'SPLIT: the plan becomes a preview', $p );
		$aisa_check( 'AdnCLC' === ( $need['calc']['addon'] ?? '' ) && 'number' === ( $need['calc']['alternative'] ?? '' ) && 'free_plus' === ( $need['calc']['package'] ?? '' ), 'SPLIT: calc needs AdnCLC (Free Plus) and becomes a Number field', $need );
		$aisa_check( 'AdnADR' === ( $need['maps']['addon'] ?? '' ) && 'text' === ( $need['maps']['alternative'] ?? '' ), 'SPLIT: the map needs Address & Postal and becomes a Text field', $need );
		$aisa_check( 'Numbers & Calculations' === ( $need['calc']['addon_name'] ?? '' ) && 'Address & Postal' === ( $need['maps']['addon_name'] ?? '' ) && 'free' === ( $need['maps']['package'] ?? '' ), 'SPLIT: the preview names each add-on and its plan (Address & Postal: every plan)', $need );
		$aisa_check( false !== strpos( implode( ' ', array_column( (array) ( $p['links'] ?? array() ), 'url' ) ), 'page=Emsfb_addon' ), 'SPLIT: the preview links to EFB\'s Add-ons page', $p['links'] ?? null );
		$aisa_check( in_array( 'AdnCLC', array_column( (array) ( $p['install'] ?? array() ), 'code' ), true ), 'SPLIT: the install of Numbers & Calculations is offered', $p['install'] ?? null );
		$aisa_check( ! in_array( 'calc', array_column( (array) ( $p['preview']['fields'] ?? array() ), 'type' ), true ), 'SPLIT: the preview never promises the calc field', $p['preview']['fields'] ?? null );
		$done = $aisa_approve( $p );
		$fid  = (int) ( $done['result']['form_id'] ?? 0 );
		$rows = $aisa_rows( $fid );
		$aisa_check( $fid > 0 && array() === $aisa_of_type( $rows, 'calc' ) && 1 === count( $aisa_of_type( $rows, 'number' ) ) && array() === $aisa_of_type( $rows, 'maps' ), 'SPLIT: approved form exists in EFB with the Number field and no calc / map', $done );

		// 2. E-mail confirmation default (owner rule, EFB 4.3.2) on that same form.
		$email = $aisa_of_type( $rows, 'email' )[0] ?? array();
		$aisa_check( in_array( $email['noti'] ?? null, array( 1, '1', true ), true ) && ( $rows[0]['email_to'] ?? '' ) === ( $email['id_'] ?? 'x' ) && in_array( $rows[0]['sendEmail'] ?? null, array( true, 1, '1' ), true ), 'Confirmation: the first Email field is the recipient (noti 1, email_to, sendEmail)', array( $rows[0]['email_to'] ?? null, $rows[0]['sendEmail'] ?? null, $email ) );
		$aisa_check( ! empty( $done['result']['can_undo'] ), 'Form package 1.1: the creation can be undone', $done );

		// 3. Two Email fields: the second stays off. Asked off: no recipient.
		$p    = $aisa_create(
			array(
				'title'  => 'Two emails',
				'fields' => array_merge(
					$aisa_contact,
					array(
						array(
							'type'  => 'email',
							'label' => 'Work email',
						),
					)
				),
			)
		);
		$rows = $aisa_rows( (int) ( $aisa_approve( $p )['result']['form_id'] ?? 0 ) );
		$em   = $aisa_of_type( $rows, 'email' );
		$aisa_check( 2 === count( $em ) && ( $rows[0]['email_to'] ?? '' ) === ( $em[0]['id_'] ?? 'x' ) && ! in_array( $em[1]['noti'] ?? null, array( 1, '1', true ), true ), 'Confirmation: a second Email field stays off', $em );
		$p    = $aisa_create(
			array(
				'title'                 => 'No copy',
				'confirm_to_respondent' => false,
				'fields'                => $aisa_contact,
			),
			'a contact form, but do not email the visitor a copy'
		);
		$aisa_check( empty( $p['preview']['notifications']['respondent'] ), 'Confirmation off: the preview says no visitor copy', $p['preview']['notifications'] ?? null );
		$rows = $aisa_rows( (int) ( $aisa_approve( $p )['result']['form_id'] ?? 0 ) );
		$em   = $aisa_of_type( $rows, 'email' );
		$aisa_check( '' === ( $rows[0]['email_to'] ?? 'x' ) && in_array( $em[0]['noti'] ?? null, array( 0, '0' ), true ), 'Confirmation off: noti 0 and no email_to in the stored form', array( $rows[0]['email_to'] ?? null, $em ) );
		$prompts = (array) get_option( 'aisa_e2e_ai_prompts', array() );
		$last    = end( $prompts );
		$aisa_check( false !== strpos( (string) ( $last['user'] ?? '' ), 'VISITOR_CONFIRMATION_EMAIL_DEFAULT=on' ) && false !== strpos( (string) ( $last['system'] ?? '' ), 'ON by default' ), 'Confirmation: the model is told EFB\'s default and the registry facts', null );
	}

	if ( 'address' === $aisa_scenario ) {
		// F17 + F16: a national address scheme is built by EFB's own templates (needs_states left to the page), and a new
		// form gets EFB's accessibility defaults (efb_form_defaults_efb).
		$p     = $aisa_create(
			array(
				'title'  => 'Delivery address',
				'fields' => array(
					array(
						'type'     => 'text',
						'label'    => 'Full name',
						'required' => true,
					),
					array(
						'type'     => 'address',
						'label'    => 'Address',
						'required' => true,
						'settings' => array( 'scheme' => 'ir' ),
					),
				),
			)
		);
		$notes = implode( ' ', (array) ( $p['preview']['efb_notes'] ?? array() ) );
		$aisa_check( false !== strpos( $notes, 'province list of the address is filled by Easy Form Builder' ), 'F17: the preview says EFB\'s page fills the province list (needs_states)', $p['preview']['efb_notes'] ?? $p );
		$rows  = $aisa_rows( (int) ( $aisa_approve( $p )['result']['form_id'] ?? 0 ) );
		$parts = array_values( array_filter( $rows, static fn ( $r ) => is_array( $r ) && 'address' === ( $r['cmp_kind'] ?? '' ) ) );
		$aisa_check( count( $parts ) >= 5 && 'ir' === ( $parts[0]['addr_scheme'] ?? '' ) && 'stateProvince' === ( $parts[0]['type'] ?? '' ), 'F17: the stored address is EFB\'s Iranian scheme (grouped parts)', array_map( static fn ( $r ) => array( $r['type'] ?? '', $r['addr_part'] ?? '' ), $parts ) );
		$aisa_check( 'blur' === ( $rows[0]['val_timing'] ?? '' ) && '1' === (string) ( $rows[0]['err_summary'] ?? '' ), 'F16: a new form gets EFB\'s inline-validation defaults', array( $rows[0]['val_timing'] ?? null, $rows[0]['err_summary'] ?? null ) );
	}

	if ( 'tsg' === $aisa_scenario ) {
		// 4. Add-on present: typed signature kept; without it the drawn signature stays.
		$p    = $aisa_create(
			array(
				'title'  => 'Agreement',
				'fields' => array(
					array(
						'type'     => 'esign',
						'label'    => 'Sign by typing your name',
						'settings' => array( 'esign_mode' => 'type' ),
					),
				),
			)
		);
		$need = (array) ( $p['addon_required'] ?? array() );
		$aisa_check( 'AdnTSG' === ( $need[0]['addon'] ?? '' ) && 'esign' === ( $need[0]['alternative'] ?? '' ), 'SPLIT: typed signature without its add-on keeps the drawn signature', $need );
	}

	if ( 'free' === $aisa_scenario ) {
		// 5. Plan gate on Free: mobile becomes Phone, the third page folds, the upgrade dialog is EFB's.
			$p     = $aisa_create(
			array(
				'title'  => 'Long survey',
				'fields' => array(
					array(
						'type'  => 'mobile',
						'label' => 'Mobile',
					),
					array(
						'type'  => 'steps',
						'label' => 'Page 2',
					),
					array(
						'type'  => 'text',
						'label' => 'City',
					),
					array(
						'type'  => 'steps',
						'label' => 'Page 3',
					),
					array(
						'type'  => 'text',
						'label' => 'Comment',
					),
				),
			)
		);
		$need  = array_column( (array) ( $p['addon_required'] ?? array() ), null, 'type' );
		$links = implode( ' ', array_column( (array) ( $p['links'] ?? array() ), 'url' ) );
		$aisa_check( 'plan' === ( $need['mobile']['reason'] ?? '' ) && 'tel' === ( $need['mobile']['alternative'] ?? '' ), 'Plan: mobile on Free becomes a Phone field', $need );
		$aisa_check( false !== strpos( $links, 'efb_upgrade=field%3Amobile' ) && false !== strpos( $links, 'efb_origin=sitepilot' ), 'Plan: the link opens EFB\'s own upgrade dialog for the feature', $links );
		$aisa_check( 2 === (int) ( $p['preview']['steps'] ?? 0 ), 'Plan: Free keeps two pages (limit:steps)', $p['preview']['steps'] ?? null );
		}

	if ( 'logic' === $aisa_scenario ) {
		// 6. Conditional logic forward jump (owner rule): the preview says which steps and fields become optional.
		$p     = $aisa_create(
			array(
				'title'  => 'Registration',
				'fields' => array(
					array(
						'type'     => 'radio',
						'label'    => 'Customer type',
						'required' => true,
						'options'  => array( 'Business', 'Individual' ),
					),
					array(
						'type'  => 'steps',
						'label' => 'Company',
					),
					array(
						'type'     => 'text',
						'label'    => 'Company name',
						'required' => true,
					),
					array(
						'type'  => 'steps',
						'label' => 'Contact',
					),
					array(
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
				),
				'logic'  => array(
					array(
						'name' => 'Skip company',
						'when' => array(
							array(
								'field'   => 'Customer type',
								'compare' => 'is',
								'value'   => 'Individual',
							),
						),
						'then' => array(
							array(
								'action' => 'jump_to_step',
								'target' => '3',
							),
						),
					),
				),
			)
		);
		$logic = implode( ' ', (array) ( $p['preview']['logic'] ?? array() ) );
		$aisa_check( false !== strpos( $logic, 'It skips step “Company”' ) && false !== strpos( $logic, '“Company name” become optional, are not checked and are not charged' ), 'Logic: the forward jump names the skipped step and its now-optional field', $logic );
		$rows = $aisa_rows( (int) ( $aisa_approve( $p )['result']['form_id'] ?? 0 ) );
		$rule = $rows[0]['logic_rules'][0] ?? array();
		$aisa_check( 'jump_to_step' === ( $rule['actions'][0]['type'] ?? '' ), 'Logic: the jump rule is stored in the EFB form', $rule );
	}

	if ( 'payments' === $aisa_scenario ) {
		// 7. Payments: a gateway whose add-on is off is refused with the install offer; a PAY00 gateway on is built from
		//    EFB's own field template and the form is a payment form that is not ready until keys exist.
		$aisa_queue(
			array(
				'reply'  => 'Here is the form.',
				'action' => 'create_form',
				'form'   => array(
					'title'   => 'Workshop',
					'fields'  => $aisa_contact,
					'payment' => array(
						'gateway'  => 'stripe',
						'currency' => 'usd',
						'items'    => array(
							array(
								'label' => 'Seat',
								'price' => 40,
							),
						),
					),
				),
			)
		);
		$p = $aisa_rest(
			'efb/chat',
			array(
				'message'  => 'workshop with stripe 40 usd',
				'has_form' => false,
			)
		);
		$aisa_check( false !== strpos( implode( ' ', (array) ( $p['errors'] ?? array() ) ), 'Stripe needs its Easy Form Builder add-on' ) && in_array( 'AdnSPF', array_column( (array) ( $p['install'] ?? array() ), 'code' ), true ) && empty( $p['preview']['payment'] ), 'Payments: Stripe without its add-on is not added, its add-on is offered', $p );
		$offer = array_column( (array) ( $p['install'] ?? array() ), null, 'code' )['AdnSPF'] ?? array();
		$aisa_check( 'free_plus' === ( $offer['plan'] ?? '' ), 'Payments: the Stripe add-on installs on Free Plus (from EFB\'s registry)', $offer );

			$aisa_queue(
			array(
				'reply'  => 'Here is the form.',
				'action' => 'create_form',
				'form'   => array(
					'title'   => 'Ticket',
					'fields'  => $aisa_contact,
					'payment' => array(
						'gateway'  => 'mollie',
						'currency' => 'EUR',
						'items'    => array(
							array(
								'label' => 'Ticket',
								'price' => 25,
							),
						),
					),
				),
			)
		);
		$p    = $aisa_rest(
			'efb/chat',
			array(
				'message'  => 'ticket form with mollie 25 eur',
				'has_form' => false,
			)
		);
		$aisa_check( 'Mollie' === ( $p['preview']['payment']['gateway'] ?? '' ) && 'EUR' === ( $p['preview']['payment']['currency'] ?? '' ), 'Payments: Mollie (PAY00) is planned with its add-on\'s currency', $p['preview']['payment'] ?? $p );
		$done  = $aisa_approve( $p );
		$rows  = $aisa_rows( (int) ( $done['result']['form_id'] ?? 0 ) );
		$notes = implode( ' ', (array) ( $done['result']['notes'] ?? array() ) );
		$aisa_check( 'payment' === ( $rows[0]['type'] ?? '' ) && 'mollie' === ( $rows[0]['getway'] ?? '' ) && 'EUR' === ( $rows[0]['currency'] ?? '' ) && 1 === count( $aisa_of_type( $rows, 'mollie' ) ), 'Payments: the stored form is a Mollie payment form with EFB\'s gateway field', array_slice( $rows, 0, 1 ) );
		$aisa_check( false === ( $done['result']['payment']['ready'] ?? null ) && false !== strpos( $notes, 'Visitors cannot pay' ), 'Payments: EFB\'s readiness says not ready (no keys) and the admin is told', $done['result'] ?? $done );
	}

	if ( 'portal' === $aisa_scenario ) {
		// 8. Portal and plans in how-to answers.
		$aisa_queue(
			array(
				'reply'      => 'Here is how.',
				'action'     => 'answer_help',
				'help_topic' => 'customer portal my submissions',
			)
		);
		$h = $aisa_rest(
			'efb/chat',
			array(
				'message'  => 'can my customers see their submissions?',
				'has_form' => false,
			)
		);
		$aisa_check( 'help' === ( $h['type'] ?? '' ) && false !== strpos( (string) ( $h['answer'] ?? '' ), 'the customer portal is not ready' ), 'Portal: portal_core is read (not ready until the free EFB Customer Portal plugin is active)', $h );
		$aisa_check( false !== strpos( (string) ( $h['answer'] ?? '' ), 'free EFB Customer Portal plugin' ) && false === strpos( (string) ( $h['answer'] ?? '' ), 'Pro add-on' ) && false === strpos( (string) ( $h['answer'] ?? '' ), 'add-on on Pro' ), 'Portal: the answer names the free plugin (portal contract 1.1), never a Pro add-on', $h );
	}

	if ( 'editor' === $aisa_scenario ) {
		// 9. Open form: a calculation asked for in the editor becomes a Number field with the reason and the offer.
		$aisa_queue(
			array(
				'reply'      => 'Added.',
				'action'     => 'customize',
				'operations' => array(
					array(
						'op'    => 'add_field',
						'field' => array(
							'type'  => 'calc',
							'label' => 'Total',
						),
					),
				),
			)
		);
		$c     = $aisa_rest(
			'efb/chat',
			array(
				'message'  => 'add a total calculation',
				'has_form' => true,
				'fields'   => array(
					array(
						'id'    => 'name1',
						'label' => 'Name',
						'type'  => 'text',
						'step'  => '1',
					),
				),
				'steps'    => array(
					array(
						'id'    => '1',
						'label' => 'Step 1',
					),
				),
			)
		);
		$types = array_column( (array) ( $c['operations'][0]['items'] ?? array() ), 'type' );
		$aisa_check( 'ops' === ( $c['type'] ?? '' ) && array( 'number' ) === $types && 'AdnCLC' === ( $c['addon_required'][0]['addon'] ?? '' ), 'Editor: calc without its add-on goes in as a Number field with the reason', $c );
	}

	if ( 'write' === $aisa_scenario ) {
		// 10. Never a direct write: the old save endpoint answers 410.
		$aisa_approve( $aisa_create( array( 'title' => 'Undo me', 'fields' => $aisa_contact ) ) );
		$s = $aisa_rest(
			'efb/save',
			array(
				'form_id'   => $aisa_created[0] ?? 1,
				'structure' => array( array( 'type' => 'form' ) ),
			)
		);
		$aisa_check( 410 === $s['_status'] && 'use_efb_save' === ( $s['code'] ?? '' ), 'No direct write: /efb/save refuses and points to EFB\'s Save', $s );

		// 11. Undo through EFB's Form_Package::delete().
		$first = array_shift( $aisa_created );
		$u     = $aisa_rest( 'efb/undo', array( 'form_id' => $first ) );
		$aisa_check( ! empty( $u['ok'] ) && array() === $aisa_rows( (int) $first ), 'Form package 1.1: Undo deletes the new form through EFB', $u );
		$u2 = $aisa_rest( 'efb/undo', array( 'form_id' => $first ) );
		$aisa_check( 404 === $u2['_status'], 'Form package 1.1: a second undo is refused', $u2 );
	}

	if ( 'saved' === $aisa_scenario ) {
		// EFB 4.3.2 (driver: Free Plus, AdnCLC off): the version from EFB's registry, a new form as a draft with the name
		// the admin approved, Publish, a SAVED form changed through EFB's form.update after approval (base_hash, stale,
		// add-on refusal, nothing written by a refusal), and the owner's own Save warnings explained.
		$aisa_check( version_compare( EfbCapabilities::efb_version(), '4.3.2', '>=' ) && EfbCapabilities::can_update_saved_forms() && EfbCapabilities::drafts_available() && EfbCapabilities::keeps_imported_name() && EfbCapabilities::save_events(), 'EFB 4.3.2: the version and form.update / drafts / name_policy / save warnings found in EFB\'s registry', EfbCapabilities::efb_version() );
		$title = 'AI saved ' . wp_generate_password( 5, false );
		$a     = $aisa_approve( $aisa_create( array( 'title' => $title, 'fields' => $aisa_contact ) ) );
		$b     = $aisa_approve( $aisa_create( array( 'title' => $title, 'fields' => $aisa_contact ) ) );
		$fid   = (int) ( $a['result']['form_id'] ?? 0 );
		$fid2  = (int) ( $b['result']['form_id'] ?? 0 );
		$read  = \Emsfb\Form_Package::read( $fid );
		$read2 = \Emsfb\Form_Package::read( $fid2 );
		$aisa_check( ! empty( $a['result']['is_draft'] ) && 'draft' === ( $read['status'] ?? '' ), 'Draft: the new form waits as a draft in EFB (visitors do not see it)', array( $a, $read['status'] ?? null ) );
		$aisa_check( $title === ( $read['form_name'] ?? '' ) && $title === ( $read2['form_name'] ?? '' ), 'name_policy keep: the same approved name twice, no "(imported)"', array( $read['form_name'] ?? null, $read2['form_name'] ?? null ) );
		$page = do_shortcode( '[EMS_Form_Builder id=' . $fid . ']' );
		wp_set_current_user( 0 );
		$guest = do_shortcode( '[EMS_Form_Builder id=' . $fid . ']' );
		wp_set_current_user( 1 );
		$aisa_check( '' === trim( $guest ) && false !== strpos( $page, 'efb-draft-note' ), 'Draft: a visitor gets nothing from the shortcode, the admin sees the draft notice', array( strlen( $guest ), strlen( $page ) ) );
		$forms = $aisa_rest( 'efb/forms', array() );
		$mine  = array_values( array_filter( (array) ( $forms['forms'] ?? array() ), static fn ( $f ) => (int) $f['id'] === $fid ) );
		$aisa_check( ! empty( $forms['ok'] ) && 1 === count( $mine ) && ! empty( $mine[0]['draft'] ) && $title === $mine[0]['name'], 'Saved forms: EFB\'s own forms list, the draft marked', $mine );
		$pub = $aisa_rest( 'efb/publish', array( 'form_id' => $fid ) );
		$aisa_check( ! empty( $pub['ok'] ) && 'published' === ( \Emsfb\Form_Package::read( $fid )['status'] ?? '' ), 'Publish: the admin\'s click publishes it through EFB', $pub );

		// A change of the saved form: proposed (nothing written), approved (EFB's save path), its notes.
		$before = $aisa_rows( $fid );
		$aisa_queue(
			array(
				'reply'      => 'Done.',
				'action'     => 'customize',
				'operations' => array(
					array(
						'op'     => 'set_field',
						'target' => 'Email',
						'prop'   => 'required',
						'value'  => false,
					),
					array(
						'op'    => 'add_field',
						'field' => array(
							'type'  => 'tel',
							'label' => 'Phone',
						),
						'after' => 'Email',
					),
					array(
						'op'    => 'set_form_name',
						'value' => $title . ' v2',
					),
				),
			)
		);
		$c = $aisa_rest(
			'efb/chat',
			array(
				'message'  => 'make email optional and add a phone after it',
				'has_form' => false,
				'form_id'  => $fid,
				'addons'   => array(),
			)
		);
		$aisa_check( 'update_preview' === ( $c['type'] ?? '' ) && ! empty( $c['action_id'] ) && 3 <= count( (array) ( $c['changes'] ?? array() ) ) && $fid === (int) ( $c['form']['id'] ?? 0 ), 'form.update: a saved form\'s change becomes a proposal with the changes in plain words', $c );
		$aisa_check( $before === $aisa_rows( $fid ), 'form.update: nothing is written before the approval', null );
		$u     = $aisa_rest( 'efb/update', array( 'action_id' => (string) ( $c['action_id'] ?? '' ) ) );
		$rows  = $aisa_rows( $fid );
		$email = $aisa_of_type( $rows, 'email' )[0] ?? array();
		$tel   = $aisa_of_type( $rows, 'tel' )[0] ?? array();
		$order = array_values( array_map( static fn ( $r ) => (string) ( $r['type'] ?? '' ), array_filter( array_slice( $rows, 1 ), static fn ( $r ) => 'option' !== ( $r['type'] ?? '' ) ) ) );
		$aisa_check( ! empty( $u['ok'] ) && in_array( $email['required'] ?? null, array( 0, '0', false ), true ) && 'Phone' === ( $tel['name'] ?? '' ) && array_search( 'tel', $order, true ) === array_search( 'email', $order, true ) + 1, 'form.update: approved → saved through EFB (Email optional, Phone right after it)', array( $u, $order ) );
		$aisa_check( $title . ' v2' === ( \Emsfb\Form_Package::read( $fid )['form_name'] ?? '' ), 'form.update: the form renamed', null );
		$revs = class_exists( '\\Emsfb\\Revisions' ) ? \Emsfb\Revisions::list_for_form( $fid ) : array();
		$aisa_check( 'autopilot' === ( $revs[0]['source'] ?? '' ), 'form.update: EFB keeps a version "autopilot"', $revs[0] ?? null );
		$again = $aisa_rest( 'efb/update', array( 'action_id' => (string) ( $c['action_id'] ?? '' ) ) );
		$aisa_check( 422 === $again['_status'], 'form.update: the approval is one-time', $again );

		// The form saved in between (the editor, another admin): the proposal made before is refused, nothing written.
		$aisa_queue(
			array(
				'reply'      => 'Done.',
				'action'     => 'customize',
				'operations' => array(
					array(
						'op'     => 'set_field',
						'target' => 'Phone',
						'prop'   => 'help',
						'value'  => 'With the country code',
					),
				),
			)
		);
		$c2  = $aisa_rest(
			'efb/chat',
			array(
				'message' => 'add help to phone',
				'form_id' => $fid,
				'addons'  => array(),
			)
		);
		$cur = \Emsfb\Form_Package::read( $fid );
		\Emsfb\Form_Package::update(
			$fid,
			array(
				array(
					'op'    => 'name',
					'value' => $title . ' v3',
				),
			),
			array( 'base_hash' => (string) $cur['base_hash'] )
		);
		$mid = $aisa_rows( $fid );
		$s   = $aisa_rest( 'efb/update', array( 'action_id' => (string) ( $c2['action_id'] ?? '' ) ) );
		$aisa_check( 409 === $s['_status'] && 'stale_hash' === ( $s['code'] ?? '' ) && false !== strpos( (string) ( $s['message'] ?? '' ), 'after I read it' ) && $mid === $aisa_rows( $fid ), 'form.update: a form saved in between is never overwritten (stale_hash, in plain words)', $s );

		// An add-on's field the site does not have: EFB refuses it (addon_required), the copilot says which add-on.
		$aisa_queue(
			array(
				'reply'      => 'Done.',
				'action'     => 'customize',
				'operations' => array(
					array(
						'op'    => 'add_field',
						'field' => array(
							'type'  => 'text',
							'label' => 'Code',
						),
					),
				),
			)
		);
		$c3    = $aisa_rest(
			'efb/chat',
			array(
				'message' => 'add a code field',
				'form_id' => $fid,
				'addons'  => array(),
			)
		);
		$store = new \AISAAutoPilot\Execution\ActionStore();
		$rec   = $store->get( (string) ( $c3['action_id'] ?? '' ) );
		// The proposal is changed to carry a calc field (Numbers & Calculations is off on this site) to see EFB's refusal.
		$rec['plan']['patches'] = array(
			array(
				'op'   => 'insert',
				'rows' => array(
					array(
						'id_'     => 'aicalc001',
						'type'    => 'calc',
						'name'    => 'Total',
						'formula' => '1+1',
					),
				),
			),
		);
		set_transient( 'aisaautopilot_action_' . (string) ( $c3['action_id'] ?? '' ), $rec, 900 );
		$snap = $aisa_rows( $fid );
		$r    = $aisa_rest( 'efb/update', array( 'action_id' => (string) ( $c3['action_id'] ?? '' ) ) );
		$aisa_check( 'addon_required' === ( $r['code'] ?? '' ) && false !== strpos( (string) ( $r['message'] ?? '' ), 'Numbers & Calculations' ) && $snap === $aisa_rows( $fid ), 'form.update: EFB refuses an add-on\'s field (addon_required), the add-on named, nothing written', $r );

		// The owner's own Save in EFB's editor: its warnings (efb:form-saved) in plain words.
		$n = $aisa_rest(
			'efb/save-notes',
			array(
				'warnings' => array(
					array(
						'code'  => 'addon_keys_kept',
						'addon' => 'AdnTSG',
						'm'     => 'Settings of Typed Signature are kept until the add-on is installed.',
					),
					array(
						'code'  => 'pay_x_info',
						'level' => 'info',
						'm'     => 'an info line',
					),
				),
			)
		);
		$aisa_check( ! empty( $n['ok'] ) && 1 === count( (array) ( $n['notes'] ?? array() ) ) && false !== strpos( (string) $n['notes'][0], 'Typed Signature' ), 'Save warnings: the owner\'s Save explained (an info line left out)', $n );
	}

} finally {
	foreach ( $aisa_created as $id ) {
		$token = get_transient( 'aisaautopilot_efb_undo_1_' . $id );
		if ( is_string( $token ) && class_exists( '\\Emsfb\\Form_Package' ) ) {
			\Emsfb\Form_Package::delete( $id, $token );
			delete_transient( 'aisaautopilot_efb_undo_1_' . $id );
		}
	}
}

$aisa_failed = count( array_filter( $aisa_checks, static fn ( $c ) => ! $c['ok'] ) );
echo sprintf( "\n%d passed, %d failed\n", count( $aisa_checks ) - $aisa_failed, $aisa_failed );
