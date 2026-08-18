<?php
/**
 * Dependency-free smoke tests for P1-0/P1-1.
 *
 * Run with: php tests/phase1_smoke.php
 * This complements PHPUnit when a machine does not have a supported PHPUnit.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AISAAutoPilot\Connectors\ConnectorInterface;
use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Execution\ActionExecutor;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Forms\NativeFormSubmission;
use AISAAutoPilot\Skills\FormPlanValidator;

$passed = 0;
$failed = 0;

$check = static function ( bool $condition, string $name ) use ( &$passed, &$failed ): void {
	if ( $condition ) {
		++$passed;
		echo "PASS {$name}\n";
		return;
	}
	++$failed;
	echo "FAIL {$name}\n";
};

// P1-1 data contract: basic types only and canonical phone -> tel.
$validator = new FormPlanValidator();
$plan_result = $validator->validate( array(
	'title' => 'Contact',
	'fields' => array(
		array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
		array( 'type' => 'phone', 'label' => 'Phone' ),
		array( 'type' => 'select', 'label' => 'Topic', 'options' => array( 'Sales', 'Support' ) ),
	),
) );
$check( $plan_result->ok && 'tel' === $plan_result->plan['fields'][1]['type'], 'form plan normalizes phone to tel' );
$file_result = $validator->validate( array( 'title' => 'Upload', 'fields' => array( array( 'type' => 'file', 'label' => 'Attachment' ) ) ) );
$check( ! $file_result->ok, 'independent form rejects file upload' );

// P1-1 delivery contract: validate submitted values in memory; reject tampering.
$fields = array(
	array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ),
	array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ),
	array( 'key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true, 'options' => array( 'Sales', 'Support' ) ),
	array( 'key' => 'consent', 'type' => 'checkbox', 'label' => 'Consent', 'required' => true ),
);
$submission = new NativeFormSubmission();
$valid_submission = $submission->validate( $fields, array( 'sp_field_name' => '<b>Ada</b>', 'sp_field_email' => 'ada@example.test', 'sp_field_topic' => 'Support', 'sp_field_consent' => '1' ) );
$check( $valid_submission['ok'] && 'Ada' === $valid_submission['values']['name'], 'submission sanitizes values for email delivery' );
$tampered_submission = $submission->validate( $fields, array( 'sp_field_name' => 'Ada', 'sp_field_email' => 'ada@example.test', 'sp_field_topic' => 'Admin access', 'sp_field_consent' => '1' ) );
$check( ! $tampered_submission['ok'] && in_array( 'invalid_topic', $tampered_submission['errors'], true ), 'submission rejects values outside approved options' );

// P1-0 release contract: another administrator cannot consume the proposal.
$GLOBALS['aisaautopilot_test_transients'] = array();
$GLOBALS['aisaautopilot_test_added_options'] = array();
$GLOBALS['aisaautopilot_test_user_id'] = 7;
$store = new ActionStore();
$check( 900 === ActionStore::ttl(), 'action proposal expiry is exposed for the review UI' );
$id = $store->create( array( 'title' => 'Contact' ), 'pinned' );
$check( 'forbidden' === $store->claim( $id, 8 )['status'] && null !== $store->get( $id ), 'wrong user cannot consume proposal' );

// P1-0 release contract: execution uses the connector pinned in the proposal,
// never today's preferred/fallback connector.
$registry = new ConnectorRegistry();
$pinned = new class implements ConnectorInterface {
	public int $runs = 0;
	public function get_id(): string { return 'pinned'; }
	public function get_name(): string { return 'Pinned connector'; }
	public function is_available(): bool { return true; }
	public function get_capabilities(): array { return array( 'form.create' ); }
	public function validate_action( array $plan ): array { return array(); }
	public function execute_action( array $plan, array $context = array() ): array { ++$this->runs; return array( 'status' => 'success', 'form_id' => 42, 'connector' => $this->get_name() ); }
	public function get_health(): array { return array( 'available' => true, 'version' => 'test', 'notes' => '' ); }
};
$registry->register( $pinned );
$action_id = $store->create( array( 'title' => 'Contact', 'fields' => array() ), 'pinned' );
$execution = ( new ActionExecutor( $registry, $store ) )->approve_and_execute( $action_id );
$check( 'success' === $execution['status'] && 1 === $pinned->runs, 'approved action executes its pinned connector once' );

$native = new NativeFormConnector();
$check( array() !== $native->validate_action( array( 'title' => 'Contact', 'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ) ) ), 'native connector requires stored notification email' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
