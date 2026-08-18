<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Skills\FormPlanValidator;

/**
 * The EFB-configured validator accepts EFB field types, resolves aliases, and
 * requires options for all choice types (select/radio/checkbox/multiselect).
 */
final class EfbValidatorConfigTest extends TestCase {

	private function validator(): FormPlanValidator {
		$limits = EfbFormModel::limits();
		return new FormPlanValidator(
			EfbFormModel::allowed_types(),
			EfbFormModel::option_types(),
			EfbFormModel::forbidden_types(),
			EfbFormModel::aliases(),
			(int) ( $limits['max_fields'] ?? 30 )
		);
	}

	public function test_model_loads_expected_types(): void {
		$allowed = EfbFormModel::allowed_types();
		$this->assertContains( 'tel', $allowed );
		$this->assertContains( 'multiselect', $allowed );
		$this->assertContains( 'date', $allowed );
		$this->assertContains( 'file', $allowed );
		$this->assertContains( 'multiselect', EfbFormModel::option_types() );
		$this->assertContains( 'checkbox', EfbFormModel::option_types() );
	}

	public function test_alias_and_efb_types_pass(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Lead',
				'fields' => array(
					array( 'type' => 'text', 'label' => 'Name' ),
					array( 'type' => 'phone', 'label' => 'Phone' ),            // alias -> tel
					array( 'type' => 'dropdown', 'label' => 'Plan', 'options' => array( 'Basic', 'Pro' ) ), // alias -> select
				),
			)
		);
		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame( 'tel', $result->plan['fields'][1]['type'] );
		$this->assertSame( 'select', $result->plan['fields'][2]['type'] );
	}

	public function test_multiselect_requires_options(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Pick',
				'fields' => array( array( 'type' => 'multiselect', 'label' => 'Choose' ) ),
			)
		);
		$this->assertFalse( $result->ok );
	}

	public function test_forbidden_payment_rejected(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Buy',
				'fields' => array(
					array( 'type' => 'text', 'label' => 'Name' ),
					array( 'type' => 'payment', 'label' => 'Card' ),
				),
			)
		);
		$this->assertFalse( $result->ok );
	}
}
