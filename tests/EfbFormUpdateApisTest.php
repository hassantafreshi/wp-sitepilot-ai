<?php
/**
 * The Easy Form Builder 4.3.2 APIs the copilot switched to (EFB's docs/autopilot-integration.md "AI builder core
 * APIs"), and the older paths it keeps when EFB is older:
 *
 *  - the version from EFB's registry; form.update, drafts, the save warnings and name_policy found by capability;
 *  - a new form keeps its approved name (name_policy keep) and waits as a draft; an older EFB imports as before;
 *  - a saved form is read and changed through Form_Package::read() / update() (base_hash), refusals passed on;
 *  - the copilot's operations become EFB's patches (EfbFormUpdate), option ids kept, editor-only work listed;
 *  - payment gateways come from EFB's catalogue (payment_gateways): no slug → add-on map of the copilot's own.
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbFormUpdate;
use AISAAutoPilot\Efb\EfbPayments;
use AISAAutoPilot\Efb\EfbSaveNotes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';
require_once __DIR__ . '/EfbFormPackageConnectorTest.php';

final class EfbFormUpdateApisTest extends TestCase {

	private const PLAN = array(
		'title'  => 'Contact',
		'fields' => array(
			array(
				'key'      => 'name',
				'type'     => 'text',
				'label'    => 'Name',
				'required' => true,
				'options'  => array(),
			),
		),
	);

	/** A saved form as EFB stores it: row 0, a step, a text field, a radio with two options, an e-mail field. */
	private static function rows(): array {
		return array(
			array(
				'type'              => 'form',
				'formName'          => 'Contact',
				'captcha'           => '1',
				'logic_rules'       => array( array( 'id' => 'r1' ) ),
				'thank_you_message' => array(
					'icon'     => 'bi-hand-thumbs-up',
					'thankYou' => 'Thanks',
				),
			),
			array(
				'id_'  => '1',
				'type' => 'step',
				'name' => 'Start',
				'step' => 1,
			),
			array(
				'id_'      => 'nm',
				'type'     => 'text',
				'name'     => 'Name',
				'required' => '1',
				'step'     => 1,
			),
			array(
				'id_'  => 'sz',
				'type' => 'radio',
				'name' => 'Size',
				'step' => 1,
			),
			array(
				'id_'       => 'o1',
				'type'      => 'option',
				'parent'    => 'sz',
				'value'     => 'Small',
				'opt_value' => 's',
			),
			array(
				'id_'    => 'o2',
				'type'   => 'option',
				'parent' => 'sz',
				'value'  => 'Large',
			),
			array(
				'id_'  => 'em',
				'type' => 'email',
				'name' => 'Email',
				'step' => 1,
			),
		);
	}

	protected function setUp(): void {
		if ( ! defined( 'EMSFB_PLUGIN_VERSION' ) ) {
			define( 'EMSFB_PLUGIN_VERSION', '4.3.2' );
		}
		$GLOBALS['wpdb'] = new EfbPackageTestDb();
		require_once __DIR__ . '/fixtures/efb-form-package-fake.php';
		\Emsfb\Form_Package::$calls = array();
		\Emsfb\Form_Package::$next  = array();
		\Emsfb\Form_Package::$api   = array();
		\Emsfb\Form_Package::$forms = array(
			7 => array(
				'name'   => 'Contact',
				'rows'   => self::rows(),
				'status' => 'draft',
			),
		);
	}

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
		\Emsfb\Form_Package::$api = array();
		unset( $GLOBALS['wpdb'] );
	}

	/** EFB 4.3.2: the registry blocks and the form package 1.2. */
	private static function efb_432(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::ai_core( EfbRegistryFixture::site( 'free_plus' ) ) );
		\Emsfb\Form_Package::$api = array(
			'version'     => 1.2,
			'functions'   => array( 'export', 'validate', 'import', 'delete', 'import_entries', 'read', 'update', 'publish', 'set_status' ),
			'import_args' => array(
				'name_policy' => array( 'suffix', 'keep', 'fail' ),
				'status'      => array( 'published', 'draft' ),
			),
		);
	}

	public function test_1_the_version_and_the_new_apis_are_found_in_efbs_registry_and_off_on_an_older_efb(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		self::assertSame( '4.3.1', EfbCapabilities::efb_version(), 'The registry an older EFB reports.' );
		self::assertFalse( EfbCapabilities::can_update_saved_forms() );
		self::assertFalse( EfbCapabilities::drafts_available() );
		self::assertFalse( EfbCapabilities::keeps_imported_name() );
		self::assertFalse( EfbCapabilities::save_events() );
		self::assertNotContains( 'form.update', ( new EasyFormBuilderConnector() )->get_capabilities() );

		self::efb_432();
		self::assertSame( '4.3.2', EfbCapabilities::efb_version() );
		self::assertTrue( EfbCapabilities::efb_at_least( '4.3.2' ) );
		self::assertTrue( EfbCapabilities::can_update_saved_forms() );
		self::assertTrue( EfbCapabilities::drafts_available() );
		self::assertTrue( EfbCapabilities::keeps_imported_name() );
		self::assertTrue( EfbCapabilities::save_events() );
		self::assertStringContainsString( 'EFB_VERSION=4.3.2', EfbCapabilities::prompt_facts() );
		$caps = ( new EasyFormBuilderConnector() )->get_capabilities();
		self::assertContains( 'form.update', $caps );
		self::assertContains( 'form.publish', $caps );

		// The registry says 4.3.2 but the form package is still 1.1 (a half-updated site): the new paths stay off.
		\Emsfb\Form_Package::$api = array();
		self::assertFalse( EfbCapabilities::can_update_saved_forms() );
		self::assertFalse( EfbCapabilities::drafts_available() );
	}

	public function test_2_a_new_form_keeps_its_approved_name_and_waits_as_a_draft_on_efb_4_3_2(): void {
		self::efb_432();
		\Emsfb\Form_Package::$next = array( 'import' => array( 'status' => 'draft', 'warnings' => array( array( 'code' => 'verify_unavailable', 'm' => 'x' ) ) ) );
		$result                    = ( new EasyFormBuilderConnector() )->execute_action( self::PLAN, array( 'draft' => true ) );
		$args                      = \Emsfb\Form_Package::$calls[0][2];
		self::assertSame( 'keep', $args['name_policy'], 'No "… (imported)" for a form the admin named.' );
		self::assertSame( 'draft', $args['status'] );
		self::assertTrue( $result['is_draft'] );
		self::assertTrue( $result['can_publish'] );
		self::assertSame( 'verify_unavailable', $result['warnings'][0]['code'] );
		self::assertNotSame( array(), EfbSaveNotes::messages( array(), $result['warnings'] ) );

		// Asked for a published form: no status argument.
		\Emsfb\Form_Package::$calls = array();
		\Emsfb\Form_Package::$next  = array();
		$result                     = ( new EasyFormBuilderConnector() )->execute_action( self::PLAN );
		self::assertArrayNotHasKey( 'status', \Emsfb\Form_Package::$calls[0][2] );
		self::assertFalse( $result['is_draft'] );
	}

	public function test_3_an_older_efb_imports_as_before(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$result = ( new EasyFormBuilderConnector() )->execute_action( self::PLAN, array( 'draft' => true ) );
		$args   = \Emsfb\Form_Package::$calls[0][2];
		self::assertSame( 'success', $result['status'] );
		self::assertArrayNotHasKey( 'name_policy', $args );
		self::assertArrayNotHasKey( 'status', $args, 'An EFB without drafts publishes at once, as before.' );
		self::assertFalse( $result['is_draft'] );
		self::assertFalse( $result['can_publish'] );
	}

	public function test_4_a_saved_form_is_read_and_changed_through_efbs_form_update(): void {
		self::efb_432();
		$connector = new EasyFormBuilderConnector();
		self::assertTrue( $connector->can_update() );
		$read = $connector->read_form( 7 );
		self::assertSame( 'Contact', $read['form_name'] );
		self::assertSame( 'draft', $read['status'] );
		self::assertSame( 64, strlen( $read['base_hash'] ) );
		self::assertInstanceOf( \WP_Error::class, $connector->read_form( 99 ) );

		$patches = array(
			array(
				'op'  => 'field',
				'id'  => 'nm',
				'set' => array( 'name' => 'Full name' ),
			),
		);
		$done    = $connector->update_form( 7, $patches, $read['base_hash'], 'Rename' );
		self::assertSame( 7, $done['form_id'] );
		$call = array_values( array_filter( \Emsfb\Form_Package::$calls, static fn ( $c ) => 'update' === $c[0] ) );
		self::assertSame( array( 'update', 7, $patches, array( 'base_hash' => $read['base_hash'], 'note' => 'Rename' ) ), $call[0] );

		$stale = $connector->update_form( 7, $patches, 'an-old-hash' );
		self::assertSame( 'stale_hash', $stale->get_error_code(), 'A form saved in between is never overwritten.' );
		\Emsfb\Form_Package::$next = array( 'update_error' => array( 'addon_required', 'needs', array( 'addon' => 'AdnCLC' ) ) );
		$addon                     = $connector->update_form( 7, $patches, $read['base_hash'] );
		self::assertSame( 'addon_required', $addon->get_error_code() );
		self::assertSame( 'AdnCLC', $addon->get_error_data()['addon'] );

		self::assertTrue( $connector->publish_form( 7 ) );
		self::assertSame( 'published', \Emsfb\Form_Package::$forms[7]['status'] );
	}

	public function test_5_an_older_efb_cannot_change_a_saved_form_from_here_and_says_so(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$connector = new EasyFormBuilderConnector();
		self::assertFalse( $connector->can_update() );
		$error = $connector->read_form( 7 );
		self::assertSame( 'efb_update_unavailable', $error->get_error_code() );
		self::assertStringContainsString( '4.3.1', $error->get_error_message() );
		self::assertStringContainsString( 'editor', $error->get_error_message() );
		self::assertSame( 'efb_publish_unavailable', $connector->publish_form( 7 )->get_error_code() );
		self::assertSame( array(), array_filter( \Emsfb\Form_Package::$calls, static fn ( $c ) => 'update' === $c[0] ) );
	}

	public function test_6_a_saved_form_is_read_like_an_open_one(): void {
		$view = EfbFormUpdate::view( self::rows() );
		self::assertSame( array( 'nm', 'sz', 'em' ), array_column( $view['fields'], 'id' ) );
		self::assertTrue( $view['fields'][0]['required'] );
		self::assertSame( array( 'Small', 'Large' ), array_column( $view['fields'][1]['options'], 'value' ) );
		self::assertSame(
			array(
				array(
					'id'    => '1',
					'label' => 'Start',
				),
			),
			$view['steps']
		);
		self::assertTrue( $view['captcha'] );
		self::assertCount( 1, $view['logic_rules'] );
	}

	public function test_7_the_copilots_operations_become_efbs_patches(): void {
		$ops  = array(
			array(
				'op'    => 'set_form_name',
				'value' => 'Contact us',
			),
			array(
				'op'     => 'set_field',
				'target' => 'Name',
				'prop'   => 'label',
				'value'  => 'Full name',
			),
			array(
				'op'     => 'set_field',
				'target' => 'em',
				'prop'   => 'required',
				'value'  => true,
			),
			array(
				'op'     => 'set_field',
				'target' => 'em',
				'prop'   => 'width',
				'value'  => 'half',
			),
			array(
				'op'    => 'set_confirmation',
				'value' => 'We will call you.',
			),
			array(
				'op'    => 'add_field',
				'items' => array(
					array(
						'id_'  => 'ph',
						'type' => 'tel',
						'name' => 'Phone',
					),
				),
				'after' => 'Email',
			),
			array(
				'op'     => 'add_option',
				'target' => 'Size',
				'value'  => 'Medium',
			),
			array(
				'op'     => 'rename_option',
				'target' => 'Size',
				'from'   => 'large',
				'value'  => 'Big',
			),
			array(
				'op'     => 'move_field',
				'target' => 'Size',
				'after'  => 'Email',
			),
		);
		$conv = EfbFormUpdate::patches( $ops, self::rows() );
		$p    = $conv['patches'];
		self::assertSame( array(), $conv['skipped'] );
		self::assertSame(
			array(
				'op'    => 'name',
				'value' => 'Contact us',
			),
			$p[0]
		);
		self::assertSame( array( 'formName' => 'Contact us' ), $p[1]['set'] );
		self::assertSame(
			array(
				'op'  => 'field',
				'id'  => 'nm',
				'set' => array( 'name' => 'Full name' ),
			),
			$p[2],
			'A field named by its label becomes its id.'
		);
		self::assertSame( array( 'required' => '1' ), $p[3]['set'] );
		self::assertSame( array( 'size' => '50' ), $p[4]['set'] );
		self::assertSame( 'We will call you.', $p[5]['set']['thank_you_message']['thankYou'] );
		self::assertSame( 'bi-hand-thumbs-up', $p[5]['set']['thank_you_message']['icon'], 'The other confirmation texts stay.' );
		self::assertSame( 'insert', $p[6]['op'] );
		self::assertSame( 'em', $p[6]['after'] );
		self::assertSame( 'remove', $p[7]['op'] );
		self::assertSame( array( 'sz' ), $p[7]['ids'] );
		self::assertSame( 'insert', $p[8]['op'] );
		self::assertSame( array( 'sz', 'o1', 'o2' ), array_column( $p[8]['rows'], 'id_' ), 'A moved field goes with its options, ids kept.' );
		self::assertSame( 'options', $p[9]['op'] );
		self::assertSame( array( 'o1', 'o2', null ), array_map( static fn ( $o ) => $o['id_'] ?? null, $p[9]['options'] ), 'Existing options keep their ids (references stay).' );
		self::assertSame( array( 'Small', 'Big', 'Medium' ), array_column( $p[9]['options'], 'value' ) );
		self::assertSame( 's', $p[9]['options'][0]['opt_value'] );
		self::assertCount( 9, $conv['changes'] );
	}

	public function test_8_what_needs_the_editor_is_listed_never_guessed(): void {
		$conv = EfbFormUpdate::patches(
			array(
				array(
					'op'      => 'add_payment',
					'payment' => array(),
				),
				array(
					'op'   => 'add_logic_rule',
					'rule' => array(),
				),
				array(
					'op'     => 'set_field',
					'target' => 'Nobody',
					'prop'   => 'label',
					'value'  => 'x',
				),
				array(
					'op'     => 'remove_option',
					'target' => 'Size',
					'value'  => 'Huge',
				),
				array(
					'op'     => 'remove_field',
					'target' => 'em',
				),
				array(
					'op'     => 'set_field',
					'target' => 'em',
					'prop'   => 'label',
					'value'  => 'Removed already',
				),
			),
			self::rows()
		);
		self::assertSame( array( 'editor_only', 'editor_only', 'field_not_found', 'option_not_found', 'field_not_found' ), array_column( $conv['skipped'], 'reason' ) );
		self::assertSame(
			array(
				array(
					'op'  => 'remove',
					'ids' => array( 'em' ),
				),
			),
			$conv['patches']
		);
	}

	public function test_9_payment_gateways_come_from_efbs_catalogue_without_a_map_of_the_copilots_own(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		self::assertFalse( defined( EfbPayments::class . '::PAY00' ), 'The copilot keeps no slug → add-on map.' );
		self::assertSame( 'AdnXND', EfbPayments::pay00()['xendit'] );
		self::assertArrayNotHasKey( 'stripecheckout', EfbPayments::pay00(), 'The PAY-OUT fields of the older gateways are reported under their legacy names.' );
		self::assertSame( 'Tap Payments', EfbPayments::label( 'tappay' ) );
		self::assertSame( 'AdnTAP', EfbPayments::addon_of( 'tappay' ) );
		$status = EfbPayments::status( array(), null );
		self::assertSame( 'free_plus', $status['paymob']['package'] );
		self::assertSame( 'addon', $status['paymob']['state'] );
		self::assertTrue( $status['paymob']['known'] );

		// An older EFB lists only the gateways that are registered: an off one is unknown (EFB needs an update), never offered.
		$caps                     = EfbRegistryFixture::site( 'free_plus' );
		$caps['payment_gateways'] = array();
		EfbRegistryFixture::apply( $caps );
		self::assertSame( array(), EfbPayments::pay00() );
		$off = EfbPayments::validate_payment(
			array(
				'gateway' => 'xendit',
				'items'   => array(
					array(
						'label' => 'Ticket',
						'price' => 5,
					),
				),
			),
			EfbPayments::status( array(), null ),
			true
		);
		self::assertSame( 'payment_unverified:xendit', $off['reason'] );
	}

	/** QA5-017: a proposal names a form setting in plain words, never its key ("require_login"). */
	public function test_a_proposal_names_form_settings_in_plain_words(): void {
		$conv    = EfbFormUpdate::patches(
			array(
				array(
					'op'       => 'set_form_property',
					'property' => 'require_login',
					'value'    => true,
				),
				array(
					'op'       => 'set_form_property',
					'property' => 'submit_button_text',
					'value'    => 'Send',
				),
			),
			self::rows()
		);
		$changes = implode( ' | ', $conv['changes'] );
		self::assertStringContainsString( 'Make the form private: only logged-in users can see and send it', $changes );
		self::assertStringContainsString( 'Change the form setting “Submit button text”', $changes );
		self::assertStringNotContainsString( 'require_login', $changes );
		self::assertStringNotContainsString( 'submit_button_text', $changes );
	}
}
