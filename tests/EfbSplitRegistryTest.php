<?php
/**
 * Easy Form Builder's SPLIT and plan registry drive what the copilot may put in a form.
 *
 * The registry is the one Easy Form Builder itself returns (tests/fixtures, see EfbRegistryFixture); the helper derives
 * other sites from it with EFB's own availability rule. Nothing here keeps a plan level or an add-on code by hand.
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbFeatureGate;
use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Efb\EfbLicense;
use AISAAutoPilot\Efb\EfbSaveNotes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';

final class EfbSplitRegistryTest extends TestCase {

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
	}

	public function test_registry_answers_plan_add_ons_and_moved_features(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );

		self::assertTrue( EfbCapabilities::available() );
		self::assertSame( 'free_plus', EfbCapabilities::plan() );
		self::assertSame( 'AdnCLC', EfbCapabilities::moved()['field:calc'] );
		self::assertSame( 'AdnTSG', EfbCapabilities::moved()['setting:esign_mode'] );
		self::assertSame( 'AdnADR', EfbCapabilities::moved()['field:maps'] );
		self::assertSame( 'AdnGEO', EfbCapabilities::moved()['setting:smart_geo'] );
		self::assertSame( 'AdnBEF', EfbCapabilities::moved()['setting:advdate_availability'] );
		$calc = EfbCapabilities::addon( 'AdnCLC' );
		self::assertSame( 'Numbers & Calculations', $calc['name'] );
		self::assertSame( 'free_plus', $calc['package'] );
		self::assertSame( 'free', EfbCapabilities::addon( 'AdnADR' )['package'] );
		self::assertSame( 'pro', EfbCapabilities::addon( 'AdnBEF' )['package'] );
		// Steps: two on Free, no limit above it.
		self::assertSame( 2, EfbCapabilities::limit( 'steps', 'free' ) );
		self::assertNull( EfbCapabilities::limit( 'steps', 'free_plus' ) );
		// An unknown key never refuses anything.
		$unknown = EfbCapabilities::state( 'field:not_in_efb' );
		self::assertFalse( $unknown['known'] );
		self::assertTrue( $unknown['available'] );
	}

	public function test_plan_comes_from_efb_and_a_pending_pro_counts_as_free(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'pro_pending' ) );
		$status = EfbLicense::status();
		self::assertSame( 'pro_pending', $status['plan'] );
		self::assertSame( 'free', $status['tier'] );
		self::assertFalse( $status['has_pro_features'] );
		self::assertSame( 'registry', $status['source'] );

		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'pro' ) );
		self::assertTrue( EfbLicense::status()['is_pro'] );

		// fa_IR sites buy Pro once; elsewhere it is a yearly subscription.
		self::assertTrue( EfbCapabilities::one_time_purchase( 'fa_IR' ) );
		self::assertFalse( EfbCapabilities::one_time_purchase( 'en_US' ) );
		self::assertFalse( EfbCapabilities::one_time_purchase( 'ar' ) );
	}

	public function test_without_a_registry_the_older_checks_answer(): void {
		EfbRegistryFixture::apply( null );
		self::assertFalse( EfbCapabilities::available() );
		self::assertSame( '', EfbCapabilities::plan() );
		self::assertSame( 'option', EfbLicense::status()['source'] );
		$fields = array( array( 'type' => 'calc', 'label' => 'Total' ) );
		self::assertSame( $fields, EfbFeatureGate::new_form_fields( $fields )['fields'] );
	}

	public function test_field_plan_levels_are_efbs_not_the_bundled_catalog(): void {
		// The bundled catalog calls heading and consent "pro"; EFB's registry says every plan.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		self::assertFalse( EfbFormModel::requires_pro_field( 'heading' ) );
		self::assertFalse( EfbFormModel::requires_pro_to_create( 'trmCheckbox' ) );
		self::assertTrue( EfbFormModel::requires_pro_to_create( 'mobile' ) );

		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		self::assertFalse( EfbFormModel::requires_pro_to_create( 'mobile' ) );
		// Custom colours are Free Plus in EFB (the catalog said Pro); the mobile layout is Pro.
		self::assertSame( '', EfbFormModel::plan_of( array( 'requires_pro' => true ), 'checked_color' ) );
		self::assertSame( 'pro', EfbFormModel::plan_of( array( 'requires_pro' => true ), 'mobile_width' ) );
	}

	public function test_a_calculation_without_its_add_on_becomes_a_number_field_with_the_reason(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnCLC' => false ) ) );
		$gate = EfbFeatureGate::new_form_fields(
			array(
				array( 'type' => 'text', 'label' => 'Name' ),
				array( 'type' => 'calculation', 'label' => 'Total' ),
			)
		);

		self::assertSame( 'text', $gate['fields'][0]['type'] );
		self::assertSame( 'number', $gate['fields'][1]['type'] );
		self::assertSame( 'Total', $gate['fields'][1]['label'] );
		self::assertCount( 1, $gate['blocked'] );
		$blocked = $gate['blocked'][0];
		self::assertSame( 'addon', $blocked['reason'] );
		self::assertSame( 'AdnCLC', $blocked['addon'] );
		self::assertSame( 'free_plus', $blocked['package'] );
		self::assertSame( 'number', $blocked['alternative'] );

		$message = EfbFeatureGate::messages( $gate['blocked'] )[0];
		self::assertStringContainsString( 'Numbers & Calculations', $message );
		self::assertStringContainsString( 'Free Plus', $message );
		self::assertStringContainsString( 'Number field instead', $message );

		$offer = EfbFeatureGate::offers( $gate['blocked'] )[0];
		self::assertSame( 'AdnCLC', $offer['code'] );
		self::assertTrue( $offer['allowed'] );
		self::assertStringContainsString( 'efb_upgrade=addon%3AAdnCLC', $offer['upgrade_url'] );
		self::assertStringContainsString( 'efb_origin=sitepilot', $offer['upgrade_url'] );
	}

	public function test_a_calculation_with_its_add_on_is_handed_to_the_efb_editor(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$gate = EfbFeatureGate::new_form_fields( array( array( 'type' => 'calc', 'label' => 'Total' ) ) );
		self::assertSame( 'builder_only', $gate['blocked'][0]['reason'] );
		self::assertSame( 'number', $gate['fields'][0]['type'] );
		self::assertStringContainsString( 'Easy Form Builder editor', EfbFeatureGate::messages( $gate['blocked'] )[0] );
		self::assertSame( array(), EfbFeatureGate::offers( $gate['blocked'] ) );
	}

	public function test_a_map_without_address_and_postal_says_it_installs_on_every_plan(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnADR' => false ) ) );
		$gate = EfbFeatureGate::new_form_fields( array( array( 'type' => 'location', 'label' => 'Where are you?' ) ) );
		self::assertSame( 'text', $gate['fields'][0]['type'] );
		self::assertSame( 'AdnADR', $gate['blocked'][0]['addon'] );
		self::assertStringContainsString( 'every plan', EfbFeatureGate::messages( $gate['blocked'] )[0] );
	}

	public function test_typed_signature_without_its_add_on_keeps_the_drawn_signature(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnTSG' => false ) ) );
		$gate = EfbFeatureGate::new_form_fields(
			array(
				array(
					'type'     => 'esign',
					'label'    => 'Sign here',
					'settings' => array( 'esign_mode' => 'type' ),
				),
			)
		);
		self::assertSame( 'esign', $gate['fields'][0]['type'] );
		self::assertArrayNotHasKey( 'settings', $gate['fields'][0] );
		self::assertSame( 'setting:esign_mode', $gate['blocked'][0]['feature'] );
		self::assertSame( 'AdnTSG', $gate['blocked'][0]['addon'] );
		self::assertStringContainsString( 'Typed Signature', EfbFeatureGate::messages( $gate['blocked'] )[0] );

		// With the add-on the typed signature stays as asked.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$ok = EfbFeatureGate::new_form_fields(
			array(
				array(
					'type'     => 'esign',
					'label'    => 'Sign here',
					'settings' => array( 'esign_mode' => 'type' ),
				),
			)
		);
		self::assertSame( array(), $ok['blocked'] );
	}

	public function test_a_plan_gate_gets_the_closest_field_and_names_the_plan(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		$gate = EfbFeatureGate::new_form_fields(
			array(
				array( 'type' => 'mobile', 'label' => 'Mobile' ),
				array( 'type' => 'yes_no', 'label' => 'Newsletter?' ),
			)
		);
		self::assertSame( 'tel', $gate['fields'][0]['type'] );
		self::assertSame( 'radio', $gate['fields'][1]['type'] );
		self::assertSame( array( 'Yes', 'No' ), $gate['fields'][1]['options'] );
		self::assertSame( 'plan', $gate['blocked'][0]['reason'] );
		self::assertTrue( EfbFeatureGate::needs_plan( $gate['blocked'] ) );
		self::assertStringContainsString( 'needs Easy Form Builder Free Plus', EfbFeatureGate::messages( $gate['blocked'] )[0] );

		// A Pro-only date picker on Free Plus becomes the plain date field.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$jalali = EfbFeatureGate::new_form_fields( array( array( 'type' => 'persian_date', 'label' => 'Birthday' ) ) );
		self::assertSame( 'date', $jalali['fields'][0]['type'] );
		self::assertSame( 'pro', $jalali['blocked'][0]['package'] );
	}

	public function test_a_gateway_is_never_a_field_the_gate_lets_through(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$state = EfbCapabilities::state( 'field:stripe' );
		self::assertFalse( $state['available'] );
		self::assertSame( 'addon', $state['reason'] );
		self::assertSame( 'AdnSPF', $state['addon'] );
		$check = EfbFeatureGate::check( 'stripe', array( 'label' => 'Pay' ), array( 'stripe' ) );
		self::assertNotNull( $check );
		self::assertNull( $check['alternative'] );
		self::assertStringContainsString( 'left it out', EfbFeatureGate::messages( array( $check ) )[0] );
	}

	public function test_an_add_on_older_than_its_contract_asks_for_an_update(): void {
		$caps                                     = EfbRegistryFixture::site( 'free_plus' );
		$caps['split']['addons']['AdnCLC']['state'] = 'update';
		EfbRegistryFixture::apply( $caps );
		$state = EfbCapabilities::state( 'field:calc' );
		self::assertFalse( $state['available'] );
		self::assertSame( 'update', $state['reason'] );
		$check = EfbFeatureGate::check( 'calc', array( 'label' => 'Total' ) );
		self::assertStringContainsString( 'newer version', EfbFeatureGate::messages( array( $check ) )[0] );
	}

	public function test_efb_save_answers_become_plain_sentences(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnCLC' => false ) ) );
		$notes = EfbSaveNotes::messages(
			array(
				array(
					'code'   => 'addon_inactive',
					'reason' => 'addon_inactive',
					'addon'  => 'AdnCLC',
					'key'    => 'formula',
				),
				array(
					'code' => 'ids_remapped',
				),
			),
			array(
				array( 'code' => 'addon_keys_kept', 'addon' => 'AdnCLC' ),
				array( 'code' => 'logic_addon_inactive', 'addon' => 'AdnSMF' ),
				array( 'code' => 'step_added' ),
				array(
					'code'  => 'pay_gw_custom',
					'level' => 'warning',
					'm'     => 'The gateway needs a payer e-mail.',
				),
				array(
					'code'  => 'pay_test_mode',
					'level' => 'info',
					'm'     => 'Test mode.',
				),
			),
			array(
				'ready'  => false,
				'issues' => array( array( 'code' => 'pay_calc_unavailable' ) ),
			)
		);
		$text = implode( "\n", $notes );
		self::assertStringContainsString( 'leaves out 1 setting of the Numbers & Calculations add-on', $text );
		self::assertStringContainsString( 'kept the saved settings of the Numbers & Calculations add-on', $text );
		self::assertStringContainsString( 'Conditional Logic add-on is active', $text );
		self::assertStringContainsString( 'The gateway needs a payer e-mail.', $text );
		self::assertStringNotContainsString( 'Test mode.', $text );
		self::assertStringContainsString( 'refuses the payment', $text );
		self::assertStringContainsString( 'Visitors cannot pay through this form yet', $text );
		self::assertStringNotContainsString( 'ids_remapped', $text );
	}
}
