<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Skills\FormPlanValidator;

final class FormPlanValidatorTest extends TestCase {

	private FormPlanValidator $validator;

	protected function setUp(): void {
		$this->validator = new FormPlanValidator();
	}

	public function test_valid_contact_form_passes_and_normalizes(): void {
		$raw = array(
			'form'       => array(
				'title'  => 'Contact form',
				'fields' => array(
					array(
						'type'     => 'text',
						'label'    => 'Full Name',
						'required' => true,
					),
					array(
						'type'  => 'email',
						'label' => 'Email',
					),
					array(
						'type'    => 'select',
						'label'   => 'Topic',
						'options' => array( 'Sales', 'Support', 'Support' ),
					),
				),
			),
			'risk_level' => 'medium',
		);

		$result = $this->validator->validate( $raw );

		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame( 'Contact form', $result->plan['title'] );
		$this->assertCount( 3, $result->plan['fields'] );
		$this->assertSame( 'full_name', $result->plan['fields'][0]['key'] );
		$this->assertTrue( $result->plan['fields'][0]['required'] );
		// Options deduped.
		$this->assertSame( array( 'Sales', 'Support' ), $result->plan['fields'][2]['options'] );
		// Always requires approval.
		$this->assertTrue( $result->plan['requires_approval'] );
	}

	public function test_missing_title_fails(): void {
		$result = $this->validator->validate(
			array( 'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ) )
		);
		$this->assertFalse( $result->ok );
		$this->assertNotEmpty( $result->errors );
	}

	public function test_no_fields_fails(): void {
		$result = $this->validator->validate( array( 'title' => 'Empty', 'fields' => array() ) );
		$this->assertFalse( $result->ok );
	}

	public function test_forbidden_payment_field_is_rejected(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Donate',
				'fields' => array(
					array( 'type' => 'text', 'label' => 'Name' ),
					array( 'type' => 'payment', 'label' => 'Card' ),
				),
			)
		);
		$this->assertFalse( $result->ok );
		$this->assertStringContainsStringIgnoringCase( 'forbidden', implode( ' ', $result->errors ) );
	}

	public function test_password_field_is_rejected(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Login',
				'fields' => array( array( 'type' => 'password', 'label' => 'Password' ) ),
			)
		);
		$this->assertFalse( $result->ok );
	}

	public function test_unsupported_type_is_rejected(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Weird',
				'fields' => array( array( 'type' => 'signature', 'label' => 'Sign here' ) ),
			)
		);
		$this->assertFalse( $result->ok );
	}

	public function test_select_without_options_is_rejected(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Pick',
				'fields' => array( array( 'type' => 'select', 'label' => 'Choose' ) ),
			)
		);
		$this->assertFalse( $result->ok );
	}

	public function test_duplicate_labels_get_unique_keys(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Dupes',
				'fields' => array(
					array( 'type' => 'text', 'label' => 'Name' ),
					array( 'type' => 'text', 'label' => 'Name' ),
				),
			)
		);
		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame( 'name', $result->plan['fields'][0]['key'] );
		$this->assertSame( 'name_2', $result->plan['fields'][1]['key'] );
	}

	public function test_invalid_risk_defaults_to_medium(): void {
		$result = $this->validator->validate(
			array(
				'title'      => 'X',
				'fields'     => array( array( 'type' => 'text', 'label' => 'Name' ) ),
				'risk_level' => 'banana',
			)
		);
		$this->assertTrue( $result->ok );
		$this->assertSame( 'medium', $result->plan['risk_level'] );
	}

	public function test_html_in_labels_is_stripped(): void {
		$result = $this->validator->validate(
			array(
				'title'  => '<b>Contact</b>',
				'fields' => array( array( 'type' => 'text', 'label' => '<script>x</script>Name' ) ),
			)
		);
		$this->assertTrue( $result->ok );
		$this->assertSame( 'Contact', $result->plan['title'] );
		$this->assertStringNotContainsString( '<', $result->plan['fields'][0]['label'] );
	}

	public function test_phone_alias_normalizes_to_tel(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Call me',
				'fields' => array( array( 'type' => 'phone', 'label' => 'Phone' ) ),
			)
		);
		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame( 'tel', $result->plan['fields'][0]['type'] );
	}

	public function test_file_upload_is_not_available_in_independent_forms(): void {
		$result = $this->validator->validate(
			array(
				'title'  => 'Upload',
				'fields' => array( array( 'type' => 'file', 'label' => 'Attachment' ) ),
			)
		);
		$this->assertFalse( $result->ok );
	}
}
