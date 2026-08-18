<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Forms\NativeFormSubmission;

final class NativeFormSubmissionTest extends TestCase {

	private function fields(): array {
		return array(
			array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ),
			array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ),
			array( 'key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true, 'options' => array( 'Sales', 'Support' ) ),
			array( 'key' => 'consent', 'type' => 'checkbox', 'label' => 'Consent', 'required' => true ),
		);
	}

	public function test_valid_submission_is_normalized_for_email_only(): void {
		$result = ( new NativeFormSubmission() )->validate(
			$this->fields(),
			array( 'sp_field_name' => '<b>Ada</b>', 'sp_field_email' => 'ada@example.test', 'sp_field_topic' => 'Support', 'sp_field_consent' => '1' )
		);
		self::assertTrue( $result['ok'] );
		self::assertSame( 'Ada', $result['values']['name'] );
		self::assertSame( 'Yes', $result['values']['consent'] );
	}

	public function test_tampered_choice_and_invalid_email_are_rejected(): void {
		$result = ( new NativeFormSubmission() )->validate(
			$this->fields(),
			array( 'sp_field_name' => 'Ada', 'sp_field_email' => 'not an email', 'sp_field_topic' => 'Admin access', 'sp_field_consent' => '1' )
		);
		self::assertFalse( $result['ok'] );
		self::assertContains( 'invalid_email', $result['errors'] );
		self::assertContains( 'invalid_topic', $result['errors'] );
	}

	public function test_required_fields_cannot_be_omitted(): void {
		$result = ( new NativeFormSubmission() )->validate( $this->fields(), array() );
		self::assertFalse( $result['ok'] );
		self::assertContains( 'required_name', $result['errors'] );
		self::assertContains( 'required_consent', $result['errors'] );
	}
}
