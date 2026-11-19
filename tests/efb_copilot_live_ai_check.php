<?php
/**
 * Live check of the Easy Form Builder copilot against the real, configured AI provider.
 *
 * It sends fifteen realistic requests (English and Persian) through the plugin's own
 * /efb/chat route as an administrator and checks the structure of each answer: new
 * forms, edits to an open form, conditional logic, review, explaining logic, help, and
 * the boundaries (payments, notification recipients). Nothing is saved to Easy Form
 * Builder: chat answers are proposals, and a proposed form only becomes real after
 * approval, which this script never gives.
 *
 * It spends real tokens, so it runs only with AISAAUTOPILOT_LIVE_AI=1. With
 * AISAAUTOPILOT_LIVE_AI_OUT=<file> the full answers are written there for review.
 * The activity log is restored on exit; the usage record is kept, because it is real.
 *
 * Run: AISAAUTOPILOT_LIVE_AI=1 php tests/efb_copilot_live_ai_check.php
 */

declare(strict_types=1);

if ( '1' !== getenv( 'AISAAUTOPILOT_LIVE_AI' ) ) {
	echo "SKIP set AISAAUTOPILOT_LIVE_AI=1 to call the real provider (it costs tokens).\n";
	exit( 0 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

wp_set_current_user( 1 );
if ( ! ( new ProviderManager() )->is_configured() ) {
	echo "SKIP no AI provider is configured.\n";
	exit( 0 );
}

$missing       = new stdClass();
$original_logs = get_option( 'aisaautopilot_logs', $missing );
$checks        = array();
$answers       = array();
$before        = ( new UsageTracker() )->get_totals();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

/** The open form the edits run against, shaped like the browser's liveFields(). */
$contact = array(
	array( 'id' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'options' => array() ),
	array( 'id' => 'work_email', 'label' => 'Work email', 'type' => 'email', 'required' => true, 'options' => array() ),
	array( 'id' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false, 'options' => array() ),
);
$with_choice = array_merge(
	$contact,
	array(
		array(
			'id'       => 'customer_type',
			'label'    => 'Customer type',
			'type'     => 'select',
			'required' => true,
			'options'  => array(
				array( 'id' => 'customer_type_o1', 'value' => 'Personal' ),
				array( 'id' => 'customer_type_o2', 'value' => 'Business' ),
			),
		),
		array( 'id' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false, 'options' => array() ),
	)
);
$rule = array(
	'id'              => 'rule_live1',
	'name'            => 'AI rule',
	'scope'           => 'field',
	'enabled'         => true,
	'priority'        => 10,
	'stop_processing' => false,
	'conditions'      => array(
		'type'     => 'group',
		'operator' => 'AND',
		'items'    => array(
			array( 'type' => 'condition', 'source' => 'field', 'field_id' => 'customer_type', 'compare' => 'is', 'value' => 'customer_type_o2' ),
		),
	),
	'actions'         => array( array( 'type' => 'show_field', 'target' => 'company' ) ),
);

$chat = static function ( string $message, array $context = array() ) use ( &$answers ): array {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/chat' );
	$params  = array_merge(
		array(
			'message'     => $message,
			'trace_id'    => 'live' . substr( md5( $message ), 0, 8 ),
			'has_form'    => false,
			'fields'      => array(),
			'steps'       => array(),
			'history'     => array(),
			'logic_addon' => false,
			'logic_rules' => array(),
			'captcha'     => false,
			'spam_shield' => false,
		),
		$context
	);
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$started  = microtime( true );
	$response = rest_do_request( $request );
	$data     = (array) $response->get_data();
	$answers[] = array(
		'message' => $message,
		'status'  => $response->get_status(),
		'seconds' => round( microtime( true ) - $started, 1 ),
		'answer'  => $data,
	);

	return $data;
};
$open = static fn( array $fields, array $extra = array() ): array => array_merge(
	array(
		'has_form'    => true,
		'fields'      => $fields,
		'steps'       => array( array( 'id' => '1', 'label' => 'Contact' ) ),
		'logic_addon' => true,
	),
	$extra
);
$ops_named = static fn( array $data, string $op ): array => array_values( array_filter( (array) ( $data['operations'] ?? array() ), static fn( $o ): bool => is_array( $o ) && $op === ( $o['op'] ?? '' ) ) );
$preview_types = static fn( array $data ): array => array_map( static fn( $f ): string => (string) ( $f['type'] ?? '' ), (array) ( $data['preview']['fields'] ?? array() ) );
$preview_labels = static fn( array $data ): array => array_map( static fn( $f ): string => (string) ( $f['label'] ?? '' ), (array) ( $data['preview']['fields'] ?? array() ) );
$persian = static fn( string $text ): bool => 1 === preg_match( '/[\x{0600}-\x{06FF}]/u', $text );

try {
	// New forms.
	$d = $chat( 'Build a contact form with name, email, mobile number and message. Email me each submission and send the sender a confirmation.' );
	$t = $preview_types( $d );
	$check( 'preview' === ( $d['type'] ?? '' ) && in_array( 'email', $t, true ) && ( in_array( 'mobile', $t, true ) || in_array( 'tel', $t, true ) ), 'Contact form: a preview with email and phone fields' );
	$check( in_array( 'trmCheckbox', $t, true ), 'Contact form: a consent checkbox is proposed for personal data' );
	$check( ! empty( $d['preview']['notifications']['admin'] ) && ! empty( $d['preview']['notifications']['respondent'] ), 'Contact form: admin and respondent notifications are switched on' );
	$check( (string) get_option( 'admin_email' ) === (string) ( $d['preview']['notifications']['admin'] ?? '' ), 'Contact form: the admin address comes from the site, not the AI' );

	$d = $chat( 'یک فرم ثبت‌نام کارگاه آموزشی بساز با نام، ایمیل، شماره موبایل، انتخاب کارگاه (طراحی، برنامه‌نویسی، بازاریابی) و توضیحات.' );
	$labels = $preview_labels( $d );
	$check( 'preview' === ( $d['type'] ?? '' ) && array() !== $labels && count( array_filter( $labels, $persian ) ) >= count( $labels ) - 1, 'Persian request: the form is written in Persian' );
	$choice = array_values( array_filter( (array) ( $d['preview']['fields'] ?? array() ), static fn( $f ): bool => in_array( (string) ( $f['type'] ?? '' ), array( 'select', 'radio', 'checkbox', 'multiselect' ), true ) ) );
	$check( isset( $choice[0] ) && 3 === count( (array) ( $choice[0]['options'] ?? array() ) ), 'Persian request: the workshop choice carries the three named options' );

	$d = $chat( 'A complete job application form: personal details, contact details, education, work experience, skills, availability, salary expectations, CV upload and two references.' );
	$t = $preview_types( $d );
	$check( 'preview' === ( $d['type'] ?? '' ) && count( $t ) > 10 && in_array( 'steps', $t, true ), 'Long form: more than ten questions are split into steps' );

	$d = $chat( 'form' );
	$check( 'preview' !== ( $d['type'] ?? '' ) && '' !== trim( (string) ( $d['reply'] ?? '' ) ), 'One-word request: asks what the form is for instead of inventing one' );

	$d = $chat( 'Create a donation form that takes a $50 payment with Stripe.' );
	$payment = array( 'stripe', 'paypal', 'persiaPay', 'zarinPal', 'payCheckbox', 'payRadio', 'paySelect', 'payMultiselect', 'prcfld', 'ttlprc' );
	$check( array() === array_intersect( $payment, $preview_types( $d ) ), 'Payment request: no payment or price field is ever proposed' );

	$d = $chat( "Create a short form with exactly these two questions, word for word: 'What is your favourite colour?' as a text field, and 'How did you hear about us?' as a dropdown with Friend, Google and Instagram." );
	$labels = $preview_labels( $d );
	$check( in_array( 'What is your favourite colour?', $labels, true ) && in_array( 'How did you hear about us?', $labels, true ), 'Verbatim request: the admin\'s own wording is kept exactly' );

	// Edits to an open form.
	$d = $chat( 'Add a Customer type dropdown with the options Personal and Business, and a text field called Company.', $open( $contact ) );
	$added = $ops_named( $d, 'add_field' );
	$types = array_map( static fn( $o ): string => (string) ( $o['field']['type'] ?? '' ), $added );
	$check( 'ops' === ( $d['type'] ?? '' ) && 2 === count( $added ) && in_array( 'select', $types, true ) && array() === array_filter( $added, static fn( $o ): bool => empty( $o['items'] ) ), 'Edit: two fields are added with EFB templates built by the server' );

	$d = $chat( 'Show Company only when Customer type is Business.', $open( $with_choice ) );
	$rules = $ops_named( $d, 'add_logic_rule' );
	$built = $rules[0]['rule'] ?? array();
	$check(
		1 === count( $rules ) && 'customer_type' === ( $built['conditions']['items'][0]['field_id'] ?? '' ) && 'customer_type_o2' === ( $built['conditions']['items'][0]['value'] ?? '' )
			&& 'show_field' === ( $built['actions'][0]['type'] ?? '' ) && 'company' === ( $built['actions'][0]['target'] ?? '' ),
		'Logic: one rule that shows Company for the Business option id'
	);

	$d = $chat( 'Rename the Business option of Customer type to Business account.', $open( $with_choice ) );
	$renamed = $ops_named( $d, 'rename_option' );
	$check( 1 === count( $renamed ) && 'Business' === ( $renamed[0]['from'] ?? '' ) && 'Business account' === ( $renamed[0]['value'] ?? '' ), 'Rename: the option is renamed, not removed and re-added' );

	$d = $chat( 'Review this form.', $open( $contact ) );
	$codes = array_map( static fn( $i ): string => (string) ( $i['code'] ?? '' ), (array) ( $d['items'] ?? array() ) );
	$check( 'list' === ( $d['type'] ?? '' ) && in_array( 'personal_data_without_consent', $codes, true ), 'Review: the missing consent is found' );

	$d = $chat( 'What does the conditional logic on this form do?', $open( $with_choice, array( 'logic_rules' => array( $rule ) ) ) );
	$lines = implode( ' ', array_map( static fn( $i ): string => (string) ( $i['message'] ?? '' ), (array) ( $d['items'] ?? array() ) ) );
	$check( 'list' === ( $d['type'] ?? '' ) && false !== strpos( $lines, 'Company' ) && false !== strpos( $lines, 'Business' ), 'Explain: the rule is described in plain words' );

	$d = $chat( 'Send all notifications from this form to test@example.com.', $open( $contact ) );
	$check( false === strpos( (string) wp_json_encode( $d['operations'] ?? array() ), 'test@example.com' ), 'Recipient: the AI never writes a notification address' );

	$d = $chat( 'How do I turn on email notifications?', $open( $contact ) );
	$check( 'help' === ( $d['type'] ?? '' ) && '' !== trim( (string) ( $d['answer'] ?? $d['reply'] ?? '' ) ), 'Help: a how-to answer instead of an edit' );

	$d = $chat( 'Make Work email optional and half width.', $open( $contact ) );
	$set = $ops_named( $d, 'set_field' );
	$props = array_map( static fn( $o ): string => (string) ( $o['prop'] ?? '' ) . '=' . wp_json_encode( $o['value'] ?? null ), $set );
	$check( in_array( 'required=false', $props, true ) && in_array( 'width="half"', $props, true ), 'Edit: Work email becomes optional and half width' );

	$d = $chat( 'فیلد Message را اختیاری کن و برایش یک متن راهنمای کوتاه فارسی بنویس.', $open( $contact ) );
	$set   = $ops_named( $d, 'set_field' );
	$help  = array_values( array_filter( $set, static fn( $o ): bool => 'help' === ( $o['prop'] ?? '' ) ) );
	$check( isset( $help[0] ) && $persian( (string) $help[0]['value'] ) && in_array( 'required', array_map( static fn( $o ): string => (string) ( $o['prop'] ?? '' ), $set ), true ), 'Persian edit: optional, with Persian help text' );
} catch ( Throwable $error ) {
	$check( false, 'Live copilot check completes without a fatal error: ' . $error->getMessage() );
} finally {
	if ( $original_logs === $missing ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}

$after = ( new UsageTracker() )->get_totals();
$out   = (string) getenv( 'AISAAUTOPILOT_LIVE_AI_OUT' );
if ( '' !== $out ) {
	file_put_contents( $out, (string) wp_json_encode( $answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local QA output.
}

foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
printf(
	"MODEL %s, %d requests, %d prompt and %d completion tokens\n",
	(string) Settings::get( 'model' ),
	(int) ( $after['request_count'] ?? 0 ) - (int) ( $before['request_count'] ?? 0 ),
	(int) ( $after['prompt_tokens'] ?? 0 ) - (int) ( $before['prompt_tokens'] ?? 0 ),
	(int) ( $after['completion_tokens'] ?? 0 ) - (int) ( $before['completion_tokens'] ?? 0 )
);
$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
echo count( $checks ) . ' checks, ' . count( $failed ) . " failed\n";
exit( array() === $failed ? 0 : 1 );
