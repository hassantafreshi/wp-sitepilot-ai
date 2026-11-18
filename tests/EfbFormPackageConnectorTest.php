<?php
/**
 * A new Easy Form Builder form is created only through EFB's own form package API (contract 1.1), never by writing
 * EFB's tables, and the visitor confirmation e-mail follows EFB 4.3.2's default (owner rule).
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbSaveNotes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';

/** A $wpdb that answers EFB's schema check and fails the test if anything writes. */
final class EfbPackageTestDb {
	public string $prefix = 'wp_';
	/** @var string[] */
	public array $writes = array();

	public function prepare( string $query, ...$args ): string {
		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	}

	public function get_var( string $query ) {
		return str_contains( $query, 'SHOW TABLES' ) ? 'wp_emsfb_form' : null;
	}

	/** @return string[] */
	public function get_col( string $query, int $x = 0 ): array {
		return array( 'form_id', 'form_name', 'form_structer', 'form_email', 'form_type', 'form_created_by', 'form_access_by', 'form_create_date', 'status' );
	}

	public function insert( ...$args ): bool {
		$this->writes[] = 'insert';
		return true;
	}

	public function update( ...$args ): bool {
		$this->writes[] = 'update';
		return true;
	}
}

final class EfbFormPackageConnectorTest extends TestCase {

	private const PLAN = array(
		'title'                 => 'Contact',
		'confirm_to_respondent' => true,
		'fields'                => array(
			array(
				'key'      => 'name',
				'type'     => 'text',
				'label'    => 'Name',
				'required' => true,
				'options'  => array(),
			),
			array(
				'key'      => 'email',
				'type'     => 'email',
				'label'    => 'Email',
				'required' => true,
				'options'  => array(),
			),
			array(
				'key'      => 'work_email',
				'type'     => 'email',
				'label'    => 'Work email',
				'required' => false,
				'options'  => array(),
			),
		),
	);

	protected function setUp(): void {
		if ( ! defined( 'EMSFB_PLUGIN_VERSION' ) ) {
			define( 'EMSFB_PLUGIN_VERSION', '4.3.2' );
		}
		$GLOBALS['wpdb'] = new EfbPackageTestDb();
	}

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
		unset( $GLOBALS['wpdb'] );
	}

	public function test_1_without_efbs_form_package_api_nothing_is_written_and_the_admin_is_told_to_update(): void {
		$connector = new EasyFormBuilderConnector();
		self::assertTrue( $connector->is_available() );
		self::assertFalse( $connector->can_create() );

		$result = $connector->execute_action( self::PLAN );
		self::assertSame( 'failed', $result['status'] );
		self::assertSame( 'efb_api_missing', $result['code'] );
		self::assertStringContainsString( '4.3.2 or newer', $result['message'] );
		self::assertStringContainsString( 'never writes Easy Form Builder’s tables', $result['message'] );
		self::assertSame( array(), $GLOBALS['wpdb']->writes );
		self::assertNull( $connector->preview_check( self::PLAN ) );
		self::assertStringContainsString( '4.3.2', $connector->get_health()['notes'] );
	}

	public function test_2_the_package_is_efbs_envelope_and_import_runs_its_save_path(): void {
		require_once __DIR__ . '/fixtures/efb-form-package-fake.php';
		\Emsfb\Form_Package::$calls = array();
		\Emsfb\Form_Package::$next  = array();
		$connector                  = new EasyFormBuilderConnector();
		self::assertTrue( $connector->can_create() );

		$package = EasyFormBuilderConnector::package( self::PLAN );
		self::assertSame( 1, $package['schema'] );
		self::assertSame( 'Contact', $package['form']['form_name'] );
		self::assertSame( 'form', $package['form']['form_type'] );
		self::assertIsArray( $package['form']['form_structer'] );
		self::assertSame( 'step', $package['form']['form_structer'][1]['type'] );
		self::assertArrayNotHasKey( 'extras', $package, 'No add-on block and no secret travels with an AI form.' );

		$check = $connector->preview_check( self::PLAN );
		self::assertTrue( $check['ok'] );
		self::assertSame( 'validate', \Emsfb\Form_Package::$calls[0][0] );

		$result = $connector->execute_action( self::PLAN );
		self::assertSame( 'success', $result['status'] );
		self::assertSame( 42, $result['form_id'] );
		self::assertSame( '[EMS_Form_Builder id=42]', $result['shortcode'] );
		self::assertSame( 'tok0123456789abcdef', $result['import_token'] );
		self::assertSame( 'import', \Emsfb\Form_Package::$calls[1][0] );
		self::assertSame( 'Contact', \Emsfb\Form_Package::$calls[1][2]['name'] );
		self::assertSame( array(), $GLOBALS['wpdb']->writes, 'The connector itself never writes EFB tables.' );

		// Undo (package 1.1) goes through EFB's own delete with the import token.
		self::assertTrue( $connector->undo_create( 42, 'tok0123456789abcdef' ) );
		self::assertInstanceOf( \WP_Error::class, $connector->undo_create( 42, 'someone-elses-token' ) );
	}

	public function test_3_efbs_refusal_is_passed_on_in_its_own_words(): void {
		require_once __DIR__ . '/fixtures/efb-form-package-fake.php';
		\Emsfb\Form_Package::$next = array( 'import_error' => 'A login or registration form needs these fields: emaillogin' );
		$result                    = ( new EasyFormBuilderConnector() )->execute_action( self::PLAN );
		\Emsfb\Form_Package::$next = array();
		self::assertSame( 'failed', $result['status'] );
		self::assertSame( 'package_not_saved', $result['code'] );
		self::assertStringContainsString( 'needs these fields', $result['message'] );
	}

	public function test_4_first_email_field_confirms_to_the_visitor_and_later_ones_stay_off(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		$rows   = ( new EfbFormConverter() )->convert( self::PLAN );
		$emails = array_values( array_filter( $rows, static fn ( $r ) => 'email' === ( $r['type'] ?? '' ) ) );
		self::assertSame( 1, $emails[0]['noti'] );
		self::assertArrayNotHasKey( 'noti', $emails[1] );
		self::assertSame( $emails[0]['id_'], $rows[0]['email_to'] );
		self::assertTrue( $rows[0]['sendEmail'] );
	}

	public function test_5_the_admin_can_turn_the_confirmation_off(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		$plan                          = self::PLAN;
		$plan['confirm_to_respondent'] = false;
		$rows                          = ( new EfbFormConverter() )->convert( $plan );
		$emails                        = array_values( array_filter( $rows, static fn ( $r ) => 'email' === ( $r['type'] ?? '' ) ) );
		self::assertSame( 0, $emails[0]['noti'] );
		self::assertSame( '', $rows[0]['email_to'] );
		self::assertNotTrue( $rows[0]['sendEmail'] );
	}

	public function test_6_confirmation_is_on_unless_the_model_said_no_on_efb_4_3_2(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		$plan = array( 'confirm_to_respondent' => false );
		self::assertTrue( EfbFormConverter::confirmation_choice( $plan, array() ), 'Omitted means EFB’s default: on.' );
		self::assertTrue( EfbFormConverter::confirmation_choice( $plan, array( 'confirm_to_respondent' => true ) ) );
		self::assertFalse( EfbFormConverter::confirmation_choice( $plan, array( 'confirm_to_respondent' => false ) ) );
		self::assertFalse( EfbFormConverter::confirmation_choice( $plan, array( 'confirm_to_respondent' => 'no' ) ) );

		// An EFB without the rule (no capabilities.email_confirmation) keeps the model's switch.
		$caps = EfbRegistryFixture::site( 'free' );
		unset( $caps['email_confirmation'] );
		EfbRegistryFixture::apply( $caps );
		self::assertFalse( EfbFormConverter::confirmation_choice( $plan, array() ) );
	}

	public function test_7_import_notes_name_what_efb_left_out_and_the_payment_state(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnTSG' => false ) ) );
		$notes = EfbSaveNotes::messages(
			array(
				array(
					'code'   => 'addon_inactive',
					'reason' => 'addon_inactive',
					'addon'  => 'AdnTSG',
					'key'    => 'esign_mode',
				),
			),
			array(),
			array(
				'ready'  => false,
				'issues' => array( array( 'code' => 'pay_addon_inactive' ) ),
			)
		);
		self::assertStringContainsString( 'Typed Signature add-on', $notes[0] );
		self::assertStringContainsString( 'Visitors cannot pay', $notes[1] );
		self::assertStringContainsString( 'Add-ons', $notes[2] );
	}
}
