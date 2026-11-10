<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Forms\NativeFormSubmission;
use AISAAutoPilot\Skills\FormPlanEditor;
use AISAAutoPilot\Skills\FormPlanSchema;
use AISAAutoPilot\Skills\FormPlanValidator;
use PHPUnit\Framework\TestCase;

/**
 * Form Studio layout and editing: column widths, stable field keys, the deterministic editor,
 * and the three field types added to native forms (number, date, web address).
 */
final class FormStudioLayoutTest extends TestCase {

	private function plan( array $fields ): array {
		return array(
			'title'  => 'Layout',
			'fields' => $fields,
		);
	}

	/** @return array<string,mixed> */
	private function valid( array $fields ): array {
		$result = ( new FormPlanValidator() )->validate( $this->plan( $fields ) );
		self::assertTrue( $result->ok, implode( ' ', $result->errors ) );
		return $result->plan;
	}

	public function test_every_width_the_ai_may_use_is_kept_and_unknown_ones_become_full(): void {
		$cases = array(
			'half'           => 'half',
			'50%'            => 'half',
			'1/2'            => 'half',
			'2'              => 'half',
			'third'          => 'third',
			'33'             => 'third',
			'quarter'        => 'quarter',
			'two_thirds'     => 'two_thirds',
			'two-thirds'     => 'two_thirds',
			'2/3'            => 'two_thirds',
			'three_quarters' => 'three_quarters',
			'75%'            => 'three_quarters',
			'full'           => 'full',
			'huge'           => 'full',
			''               => 'full',
		);
		foreach ( $cases as $given => $expected ) {
			$plan = $this->valid( array( array( 'type' => 'text', 'label' => 'A', 'width' => $given ) ) );
			self::assertSame( $expected, $plan['fields'][0]['width'], "width '{$given}'" );
			self::assertArrayHasKey( $plan['fields'][0]['width'], FormPlanSchema::WIDTHS );
		}
	}

	public function test_widths_are_twelfths_of_a_row(): void {
		self::assertSame( 12, FormPlanSchema::WIDTHS['full'] );
		self::assertSame( 12, FormPlanSchema::WIDTHS['half'] * 2 );
		self::assertSame( 12, FormPlanSchema::WIDTHS['third'] * 3 );
		self::assertSame( 12, FormPlanSchema::WIDTHS['quarter'] * 4 );
		self::assertSame( 12, FormPlanSchema::WIDTHS['two_thirds'] + FormPlanSchema::WIDTHS['third'] );
		self::assertSame( 12, FormPlanSchema::WIDTHS['three_quarters'] + FormPlanSchema::WIDTHS['quarter'] );
	}

	public function test_an_existing_key_is_kept_so_a_selection_survives_a_change(): void {
		$plan = $this->valid(
			array(
				array( 'key' => 'name', 'type' => 'text', 'label' => 'Full name' ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email' ),
			)
		);
		self::assertSame( array( 'name', 'email' ), array_column( $plan['fields'], 'key' ) );

		// A new label does not rename the key; a clashing or malformed key falls back to the label.
		$again = $this->valid(
			array(
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Work email' ),
				array( 'key' => 'email', 'type' => 'text', 'label' => 'Second' ),
				array( 'key' => 'Bad Key!', 'type' => 'text', 'label' => 'Third' ),
			)
		);
		self::assertSame( array( 'email', 'second', 'third' ), array_column( $again['fields'], 'key' ) );
	}

	public function test_number_date_and_url_are_native_field_types(): void {
		$plan = $this->valid(
			array(
				array( 'type' => 'number', 'label' => 'Guests', 'settings' => array( 'min' => '1', 'max' => '10' ) ),
				array( 'type' => 'date', 'label' => 'Day', 'settings' => array( 'min' => 'today' ) ),
				array( 'type' => 'url', 'label' => 'Website' ),
			)
		);
		self::assertSame( array( 'number', 'date', 'url' ), array_column( $plan['fields'], 'type' ) );
		self::assertSame( '10', $plan['fields'][0]['settings']['max'] );
	}

	public function test_payment_and_upload_are_still_refused(): void {
		$result = ( new FormPlanValidator() )->validate( $this->plan( array( array( 'type' => 'payment', 'label' => 'Pay' ), array( 'type' => 'file', 'label' => 'CV' ) ) ) );
		self::assertFalse( $result->ok );
	}

	public function test_editor_changes_width_required_text_and_order_without_touching_the_rest(): void {
		$plan = $this->valid(
			array(
				array( 'key' => 'first', 'type' => 'text', 'label' => 'First', 'required' => true, 'placeholder' => 'Ada', 'help' => 'Given name' ),
				array( 'key' => 'last', 'type' => 'text', 'label' => 'Last' ),
				array( 'key' => 'mail', 'type' => 'email', 'label' => 'Email' ),
			)
		);
		$edited = FormPlanEditor::apply(
			$plan,
			array(
				array( 'op' => 'set_width', 'key' => 'first', 'value' => 'half' ),
				array( 'op' => 'set_width', 'key' => 'last', 'value' => 'half' ),
				array( 'op' => 'set_required', 'key' => 'last', 'value' => true ),
				array( 'op' => 'set_text', 'key' => 'mail', 'prop' => 'help', 'value' => 'We never share it' ),
				array( 'op' => 'move', 'key' => 'mail', 'direction' => 'top' ),
			)
		);
		self::assertNotNull( $edited );
		$result = ( new FormPlanValidator() )->validate( $edited );
		self::assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$fields = $result->plan['fields'];
		self::assertSame( array( 'mail', 'first', 'last' ), array_column( $fields, 'key' ) );
		self::assertSame( 'half', $fields[1]['width'] );
		self::assertSame( 'half', $fields[2]['width'] );
		self::assertTrue( $fields[2]['required'] );
		self::assertSame( 'We never share it', $fields[0]['help'] );
		// Untouched details of "first" are intact.
		self::assertSame( 'Ada', $fields[1]['placeholder'] );
		self::assertSame( 'Given name', $fields[1]['help'] );
		self::assertTrue( $fields[1]['required'] );
	}

	public function test_editor_refuses_an_unknown_field_or_operation(): void {
		$plan = $this->valid( array( array( 'key' => 'a', 'type' => 'text', 'label' => 'A' ) ) );
		self::assertNull( FormPlanEditor::apply( $plan, array( array( 'op' => 'set_width', 'key' => 'zzz', 'value' => 'half' ) ) ) );
		self::assertNull( FormPlanEditor::apply( $plan, array( array( 'op' => 'delete_everything', 'key' => 'a' ) ) ) );
		self::assertNull( FormPlanEditor::apply( $plan, array( array( 'op' => 'set_text', 'key' => 'a', 'prop' => 'type', 'value' => 'payment' ) ) ) );
		self::assertNull( FormPlanEditor::apply( $plan, array( array( 'op' => 'move', 'key' => 'a', 'direction' => 'sideways' ) ) ) );
		self::assertNull( FormPlanEditor::apply( $plan, array( 'not an operation' ) ) );
	}

	public function test_removing_every_field_leaves_a_plan_the_validator_rejects(): void {
		$plan   = $this->valid( array( array( 'key' => 'a', 'type' => 'text', 'label' => 'A' ) ) );
		$edited = FormPlanEditor::apply( $plan, array( array( 'op' => 'remove', 'key' => 'a' ) ) );
		self::assertNotNull( $edited );
		self::assertFalse( ( new FormPlanValidator() )->validate( $edited )->ok );
	}

	public function test_submission_checks_number_date_and_url(): void {
		$fields = array(
			array( 'key' => 'guests', 'type' => 'number', 'label' => 'Guests', 'required' => true, 'settings' => array( 'min' => '1', 'max' => '10' ) ),
			array( 'key' => 'day', 'type' => 'date', 'label' => 'Day', 'settings' => array( 'min' => '2030-01-01', 'max' => '2030-12-31' ) ),
			array( 'key' => 'site', 'type' => 'url', 'label' => 'Website' ),
		);
		$check  = static fn ( array $input ): array => ( new NativeFormSubmission() )->validate( $fields, $input );

		$ok = $check( array( 'sp_field_guests' => '4', 'sp_field_day' => '2030-06-15', 'sp_field_site' => 'https://example.test/me' ) );
		self::assertTrue( $ok['ok'] );
		self::assertSame( '4', $ok['values']['guests'] );

		foreach (
			array(
				'too many guests'  => array( 'sp_field_guests' => '11' ),
				'zero guests'      => array( 'sp_field_guests' => '0' ),
				'exponent'         => array( 'sp_field_guests' => '1e1' ),
				'text number'      => array( 'sp_field_guests' => 'four' ),
				'too early'        => array( 'sp_field_guests' => '2', 'sp_field_day' => '2029-12-31' ),
				'too late'         => array( 'sp_field_guests' => '2', 'sp_field_day' => '2031-01-01' ),
				'impossible date'  => array( 'sp_field_guests' => '2', 'sp_field_day' => '2030-02-30' ),
				'wrong date shape' => array( 'sp_field_guests' => '2', 'sp_field_day' => '15/06/2030' ),
				'javascript url'   => array( 'sp_field_guests' => '2', 'sp_field_site' => 'javascript:alert(1)' ),
				'plain text url'   => array( 'sp_field_guests' => '2', 'sp_field_site' => 'my website' ),
			) as $name => $input
		) {
			self::assertFalse( $check( $input )['ok'], $name );
		}
		// An optional date or url may be left empty.
		self::assertTrue( $check( array( 'sp_field_guests' => '2' ) )['ok'] );
	}

	public function test_what_needs_easy_form_builder_is_named_from_a_closed_list(): void {
		$result = ( new FormPlanValidator() )->validate(
			array(
				'form'      => array( 'title' => 'Booking', 'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ) ),
				'needs_efb' => array( 'Payment', 'file upload', 'logic', 'payment', 'teleport', array( 'x' ) ),
			)
		);
		self::assertTrue( $result->ok );
		self::assertSame( array( 'payment', 'file_upload', 'conditional_logic' ), $result->plan['needs_efb'] );
		$plain = ( new FormPlanValidator() )->validate( array( 'form' => array( 'title' => 'A', 'fields' => array( array( 'type' => 'text', 'label' => 'A' ) ) ) ) );
		self::assertSame( array(), $plain->plan['needs_efb'] );
	}

	public function test_codes_keep_their_length_and_digits(): void {
		$fields = array( array( 'key' => 'postal', 'type' => 'text', 'label' => 'Postal code', 'required' => true, 'settings' => array( 'digits_only' => true, 'min_length' => '10', 'max_length' => '10' ) ) );
		$check  = static fn ( string $v ): bool => ( new NativeFormSubmission() )->validate( $fields, array( 'sp_field_postal' => $v ) )['ok'];
		self::assertTrue( $check( '0123456789' ), 'leading zero kept' );
		self::assertTrue( $check( '۱۲۳۴۵۶۷۸۹۰' ), 'Persian digits' );
		self::assertFalse( $check( '12345' ), 'too short' );
		self::assertFalse( $check( '12345678901' ), 'too long' );
		self::assertFalse( $check( '12345abcde' ), 'letters' );
	}
}
