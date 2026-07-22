<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Efb\EfbCustomizeSkill;
use SitePilot\Efb\EfbFormModel;

final class EfbCapabilityCatalogTest extends TestCase {

	public function test_catalog_loads_core_field_addon_and_storage_contracts(): void {
		$catalog = EfbFormModel::capability_catalog();

		$this->assertSame( '1.0.0', $catalog['schema_version'] );
		$this->assertSame( 'form_structer', $catalog['storage']['form_definition']['columns']['structure'] );
		$this->assertTrue( $catalog['field_types']['audio_recorder']['fillable'] );
		$this->assertSame( 'AdnGoS', array_key_first( array_filter( $catalog['addon_catalog'], static fn ( array $addon ): bool => 'Google Sheets' === $addon['name'] ) ) );
		$this->assertContains( 'calculation addon and calculate action', $catalog['scope_exclusions'] );
	}

	public function test_client_catalog_only_contains_safe_writable_properties(): void {
		$field = EfbFormModel::client_field_properties();
		$form  = EfbFormModel::client_form_properties();

		$this->assertArrayHasKey( 'mobile_width', $field );
		$this->assertSame( 'mobile_size', $field['mobile_width']['storage_key'] );
		$this->assertArrayHasKey( 'recorder_watermark', $field );
		$this->assertArrayNotHasKey( 'href', $field );
		$this->assertArrayHasKey( 'loading_type', $form );
		$this->assertArrayNotHasKey( 'currency', $form );
		$this->assertArrayNotHasKey( 'conditional_logic', $form );
	}

	public function test_customizer_accepts_a_type_specific_safe_property(): void {
		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Video', 'property' => 'recorder_facing', 'value' => 'environment' ),
				array( 'op' => 'set_field_property', 'target' => 'Topic', 'property' => 'option_columns', 'value' => 'two' ),
			),
			array(
				array( 'id' => 'video', 'label' => 'Video', 'type' => 'video_recorder' ),
				array( 'id' => 'topic', 'label' => 'Topic', 'type' => 'radio' ),
			)
		);

		$this->assertCount( 2, $result['ops'] );
		$this->assertSame( 'recorder_facing', $result['ops'][0]['property'] );
		$this->assertSame( 'environment', $result['ops'][0]['value'] );
		$this->assertSame( 'option_columns', $result['ops'][1]['property'] );
		$this->assertSame( 'two', $result['ops'][1]['value'] );
	}

	public function test_customizer_rejects_unsafe_or_wrong_type_properties(): void {
		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Email', 'property' => 'recorder_facing', 'value' => 'environment' ),
				array( 'op' => 'set_field_property', 'target' => 'Email', 'property' => 'href', 'value' => 'https://example.test' ),
				array( 'op' => 'set_form_property', 'property' => 'currency', 'value' => 'USD' ),
			),
			array( array( 'id' => 'email', 'label' => 'Email', 'type' => 'email' ) )
		);

		$this->assertSame( array(), $result['ops'] );
		$this->assertCount( 3, $result['dropped'] );
		$this->assertSame( 'property_not_available_for_type:recorder_facing:email', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_writable:href', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_writable:currency', $result['dropped'][2]['reason'] );
	}

	public function test_customizer_accepts_safe_form_property(): void {
		$ops = EfbCustomizeSkill::validate_ops(
			array( array( 'op' => 'set_form_property', 'property' => 'loading_type', 'value' => 'spinner' ) )
		);

		$this->assertSame( array( 'op' => 'set_form_property', 'property' => 'loading_type', 'value' => 'spinner' ), $ops[0] );
	}

	public function test_customizer_accepts_submit_button_colours_in_persian_or_english(): void {
		$ops = EfbCustomizeSkill::validate_ops(
			array(
				array( 'op' => 'set_form_property', 'property' => 'button_color', 'value' => 'قرمز' ),
				array( 'op' => 'set_form_property', 'property' => 'button_text_color', 'value' => 'white' ),
			)
		);

		$this->assertSame( '#ff455f', $ops[0]['value'] );
		$this->assertSame( '#ffffff', $ops[1]['value'] );
	}

	public function test_customizer_accepts_an_electronic_signature_field(): void {
		$ops = EfbCustomizeSkill::validate_ops(
			array(
				array(
					'op' => 'add_field',
					'field' => array( 'type' => 'signature', 'label' => 'Signature', 'required' => true ),
				),
			)
		);

		$this->assertSame( 'esign', $ops[0]['field']['type'] );
		$this->assertSame( 'Signature', $ops[0]['field']['label'] );
	}

	public function test_step_is_a_structural_operation_not_a_heading_field(): void {
		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'add_step', 'label' => 'Personal information', 'step_ref' => 'personal_info' ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'steps', 'label' => 'Wrong representation' ) ),
			)
		);

		$this->assertCount( 1, $result['ops'] );
		$this->assertSame( 'add_step', $result['ops'][0]['op'] );
		$this->assertSame( 'personal_info', $result['ops'][0]['step_ref'] );
		$this->assertSame( 'step_requires_add_step', $result['dropped'][0]['reason'] );
	}
}
