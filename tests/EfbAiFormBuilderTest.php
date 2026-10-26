<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbChatSkill;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Efb\EfbFormReview;
use AISAAutoPilot\Skills\FormPlanValidator;
use PHPUnit\Framework\TestCase;

/**
 * T74: the AI form-builder additions — new field types, help text, submit
 * text, notifications, consent links, review findings and new edit operations.
 */
final class EfbAiFormBuilderTest extends TestCase {

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @return array<int, array<string, mixed>>
	 */
	private static function of_type( array $items, string $type ): array {
		return array_values( array_filter( $items, static fn ( array $it ): bool => ( $it['type'] ?? '' ) === $type ) );
	}

	private function validator(): FormPlanValidator {
		return new FormPlanValidator(
			EfbFormModel::designable_types(),
			EfbFormModel::option_types(),
			EfbFormModel::forbidden_types(),
			EfbFormModel::aliases(),
			30
		);
	}

	public function test_every_designable_type_is_a_creatable_catalog_type(): void {
		foreach ( EfbFormModel::allowed_types() as $type ) {
			if ( 'steps' === $type ) {
				continue;
			}
			$definition = EfbFormModel::field_type( $type );
			$this->assertIsArray( $definition, $type );
			$this->assertTrue( ! empty( $definition['ai_create'] ), $type . ' is offered to the designer but not creatable' );
			$this->assertContains( $type, EfbFormModel::addable_types(), $type );
		}

		foreach ( array( 'mobile', 'rating', 'pointr5', 'pointr10', 'yesNo', 'switch', 'trmCheckbox', 'color', 'dadfile', 'chlCheckBox', 'table_matrix' ) as $added ) {
			$this->assertContains( $added, EfbFormModel::allowed_types(), $added );
		}
		// Choice lists that own their rows must be given them.
		$this->assertContains( 'chlCheckBox', EfbFormModel::option_types() );
		$this->assertContains( 'table_matrix', EfbFormModel::option_types() );
	}

	public function test_friendly_names_resolve_to_efb_types(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Feedback',
				'fields' => array(
					array( 'type' => 'nps', 'label' => 'How likely are you to recommend us?' ),
					array( 'type' => 'stars', 'label' => 'Overall rating' ),
					array( 'type' => 'yes_no', 'label' => 'Would you buy again?' ),
					array( 'type' => 'consent', 'label' => 'Consent', 'options' => array( 'I agree.' ) ),
					array( 'type' => 'likert', 'label' => 'Rate each part', 'options' => array( 'Speed', 'Price' ) ),
					array( 'type' => 'full_name', 'label' => 'Name' ),
				),
			)
		);

		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame(
			array( 'pointr10', 'rating', 'yesNo', 'trmCheckbox', 'table_matrix', 'text' ),
			array_column( $result->plan['fields'], 'type' )
		);
	}

	public function test_validator_keeps_help_and_notification_switches(): void {
		$result = $this->validator()->validate(
			array(
				'title'                 => 'Booking',
				'submit_button'         => 'Book now',
				'notify_admin'          => 'yes',
				'confirm_to_respondent' => true,
				'fields'                => array(
					array( 'type' => 'email', 'label' => 'Email', 'help' => '<b>We send the confirmation here.</b>' ),
				),
			)
		);

		$this->assertTrue( $result->ok );
		$this->assertSame( 'We send the confirmation here.', $result->plan['fields'][0]['help'] );
		$this->assertSame( 'Book now', $result->plan['submit_button'] );
		$this->assertTrue( $result->plan['notify_admin'] );
		$this->assertTrue( $result->plan['confirm_to_respondent'] );
	}

	/**
	 * EFB names some types in camelCase. The validator lowercases what the model
	 * wrote, so without mapping back every form with a consent checkbox was
	 * rejected as "unsupported type (trmcheckbox)" — found with a real model.
	 */
	public function test_camel_case_efb_types_survive_validation(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Contact',
				'fields' => array(
					array( 'type' => 'email', 'label' => 'Email' ),
					array( 'type' => 'trmCheckbox', 'label' => 'Consent', 'required' => true, 'options' => array( 'I agree.' ) ),
					array( 'type' => 'YESNO', 'label' => 'Newsletter?' ),
					array( 'type' => 'chlcheckbox', 'label' => 'Order', 'options' => array( 'Tea', 'Coffee' ) ),
				),
			)
		);

		$this->assertTrue( $result->ok, implode( ' ', $result->errors ) );
		$this->assertSame( array( 'email', 'trmCheckbox', 'yesNo', 'chlCheckBox' ), array_column( $result->plan['fields'], 'type' ) );
	}

	public function test_a_matrix_without_rows_is_rejected(): void {
		$result = $this->validator()->validate(
			array(
				'title'  => 'Survey',
				'fields' => array( array( 'type' => 'table_matrix', 'label' => 'Rate us' ) ),
			)
		);

		$this->assertFalse( $result->ok );
	}

	public function test_converter_builds_native_children_for_the_new_types(): void {
		$items = ( new EfbFormConverter() )->convert(
			array(
				'title'         => 'Order',
				'submit_button' => 'Send order',
				'fields'        => array(
					array( 'key' => 'items', 'type' => 'chlCheckBox', 'label' => 'Items', 'required' => true, 'options' => array( 'Coffee', 'Tea' ) ),
					array( 'key' => 'grid', 'type' => 'table_matrix', 'label' => 'Rate', 'required' => false, 'options' => array( 'Speed', 'Price', 'Taste' ) ),
					array( 'key' => 'again', 'type' => 'yesNo', 'label' => 'Order again?', 'required' => false, 'options' => array( 'Sure', 'Never' ) ),
					array( 'key' => 'stars', 'type' => 'rating', 'label' => 'Rating', 'required' => false, 'help' => 'One to five stars.', 'options' => array() ),
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'options' => array() ),
				),
			)
		);

		$this->assertSame( 'Send order', $items[0]['button_single_text'] );

		$checklist = self::of_type( $items, 'chlCheckBox' )[0];
		$this->assertSame( 'Qty', $checklist['pholder_chl_value'] );
		$this->assertSame( '1', $checklist['pro'] );
		$options = array_values( array_filter( self::of_type( $items, 'option' ), static fn ( $o ) => $o['parent'] === $checklist['id_'] ) );
		$this->assertSame( array( 'Coffee', 'Tea' ), array_column( $options, 'value' ) );

		$matrix = self::of_type( $items, 'table_matrix' )[0];
		$rows   = self::of_type( $items, 'r_matrix' );
		$this->assertSame( array( 'Speed', 'Price', 'Taste' ), array_column( $rows, 'value' ) );
		foreach ( $rows as $row ) {
			$this->assertSame( $matrix['id_'], $row['parent'] );
			$this->assertSame( $row['id_'], $row['id_op'] );
		}

		$yes_no = self::of_type( $items, 'yesNo' )[0];
		$this->assertSame( 'Sure', $yes_no['button_1_text'] );
		$this->assertSame( 'Never', $yes_no['button_2_text'] );
		// The captions are the field's own, not option children.
		$this->assertCount( 0, array_filter( self::of_type( $items, 'option' ), static fn ( $o ) => $o['parent'] === $yes_no['id_'] ) );

		$rating = self::of_type( $items, 'rating' )[0];
		$this->assertSame( 'One to five stars.', $rating['message'] );
		$this->assertSame( '', self::of_type( $items, 'text' )[0]['pro'] );

		// IDs stay unique and amounts strictly increase in document order.
		$ids = array_column( array_slice( $items, 1 ), 'id_' );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
		$amounts = array_map( 'intval', array_column( array_slice( $items, 1 ), 'amount' ) );
		$sorted  = $amounts;
		sort( $sorted );
		$this->assertSame( $sorted, $amounts );
		$this->assertSame( count( $amounts ), count( array_unique( $amounts ) ) );
	}

	public function test_consent_links_only_the_site_privacy_page(): void {
		$plan  = array(
			'title'        => 'Contact',
			'privacy_link' => array(
				'url'   => 'https://example.com/privacy/',
				'label' => 'Privacy Policy',
			),
			'fields'       => array(
				array( 'key' => 'consent', 'type' => 'trmCheckbox', 'label' => 'Consent', 'required' => true, 'options' => array( 'I agree to the [terms](https://evil.example/x) at www.evil.example/y.' ) ),
			),
		);
		$items = ( new EfbFormConverter() )->convert( $plan );

		$consent = self::of_type( $items, 'trmCheckbox' )[0];
		$this->assertSame( '1', $consent['required'] );
		$option = self::of_type( $items, 'option' )[0];
		$this->assertSame( $consent['id_'], $option['parent'] );
		$this->assertStringNotContainsString( 'evil.example', $option['value'] );
		$this->assertSame( 'I agree to the terms at ([Privacy Policy](https://example.com/privacy/))', $option['value'] );

		// No privacy page: the sentence stands alone, without any link.
		$this->assertSame( 'I agree.', EfbFormConverter::consent_text( 'I agree.', array() ) );
		$this->assertSame( 'I agree.', EfbFormConverter::consent_text( 'I agree.', array( 'url' => 'javascript:alert(1)' ) ) );
	}

	public function test_notifications_mirror_efbs_own_contact_template(): void {
		$base = array(
			'title'  => 'Contact',
			'fields' => array(
				array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'options' => array() ),
			),
		);

		$items = ( new EfbFormConverter() )->convert( $base + array( 'notify_email' => 'admin@example.com', 'confirm_to_respondent' => true ) );
		$this->assertSame( 'admin@example.com', $items[0]['email'] );
		$this->assertTrue( $items[0]['sendEmail'] );
		$this->assertSame( self::of_type( $items, 'email' )[0]['id_'], $items[0]['email_to'] );

		// Off by default, and an invalid address is never written.
		$plain = ( new EfbFormConverter() )->convert( $base + array( 'notify_email' => 'not an email' ) );
		$this->assertSame( '', $plain[0]['email'] );
		$this->assertSame( '', $plain[0]['sendEmail'] );
		$this->assertSame( '', $plain[0]['email_to'] );

		// A respondent copy needs somewhere to send it.
		$no_email = ( new EfbFormConverter() )->convert(
			array(
				'title'                 => 'Poll',
				'confirm_to_respondent' => true,
				'fields'                => array( array( 'key' => 'q', 'type' => 'radio', 'label' => 'Pick', 'required' => true, 'options' => array( 'A', 'B' ) ) ),
			)
		);
		$this->assertSame( '', $no_email[0]['sendEmail'] );
	}

	public function test_build_field_adds_a_consent_checkbox_to_a_live_form(): void {
		$items = ( new EfbFormConverter() )->build_field(
			array(
				'type'         => 'trmCheckbox',
				'label'        => 'Consent',
				'required'     => true,
				'options'      => array( 'I agree.' ),
				'privacy_link' => array(
					'url'   => 'https://example.com/privacy/',
					'label' => 'Privacy',
				),
			),
			array( 'consent' )
		);

		$this->assertCount( 2, $items );
		$this->assertSame( 'consent_2', $items[0]['id_'] );
		$this->assertSame( 'option', $items[1]['type'] );
		$this->assertSame( 'consent_2', $items[1]['parent'] );
		$this->assertSame( 'I agree. ([Privacy](https://example.com/privacy/))', $items[1]['value'] );
		$this->assertSame( '0', $items[1]['amount'] );
	}

	public function test_review_flags_what_a_form_designer_would(): void {
		$fields = array(
			array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
			array( 'type' => 'email', 'label' => 'Email', 'required' => true ),
			array( 'type' => 'text', 'label' => 'email', 'required' => true ),
			array( 'type' => 'radio', 'label' => 'Plan', 'required' => true, 'options' => array( 'Only one' ) ),
			array( 'type' => 'text', 'label' => '', 'required' => false ),
		);
		$codes  = array_column( EfbFormReview::review( $fields ), 'code' );

		$this->assertContains( 'personal_data_without_consent', $codes );
		$this->assertContains( 'duplicate_label', $codes );
		$this->assertContains( 'choice_too_few_options', $codes );
		$this->assertContains( 'missing_label', $codes );
		$this->assertContains( 'spam_protection', $codes );

		$consent = array_values( array_filter( EfbFormReview::review( $fields ), static fn ( $f ) => 'personal_data_without_consent' === $f['code'] ) )[0];
		$this->assertSame( 'trmCheckbox', $consent['fix']['field']['type'] );
		// The suggested fix is a valid edit operation.
		$this->assertCount( 1, EfbCustomizeSkill::validate_ops_verbose( array( $consent['fix'] ) )['ops'] );
	}

	public function test_review_stays_quiet_on_a_sound_form(): void {
		$fields = array(
			array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
			array( 'type' => 'email', 'label' => 'Email', 'required' => true ),
			array( 'type' => 'textarea', 'label' => 'Message', 'required' => false ),
			array( 'type' => 'trmCheckbox', 'label' => 'Consent', 'required' => true ),
			array( 'type' => 'heading', 'label' => '' ),
		);

		$this->assertSame( array(), EfbFormReview::review( $fields, array( 'captcha' => true ) ) );
		// EFB's site-wide Human Shield add-on covers spam without reCAPTCHA.
		$this->assertSame( array(), EfbFormReview::review( $fields, array( 'shield' => true ) ) );
		$this->assertSame( array( 'spam_protection' ), array_column( EfbFormReview::review( $fields ), 'code' ) );
	}

	public function test_review_suggests_steps_and_fewer_required_fields_on_long_forms(): void {
		$fields = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$fields[] = array( 'type' => 'text', 'label' => 'Question ' . $i, 'required' => true );
		}
		$codes = array_column( EfbFormReview::review( $fields, array( 'captcha' => true ) ), 'code' );
		$this->assertSame( array( 'too_many_required', 'long_single_page' ), $codes );

		$paged = array_column( EfbFormReview::review( $fields, array( 'captcha' => true, 'steps' => 3 ) ), 'code' );
		$this->assertNotContains( 'long_single_page', $paged );
	}

	public function test_rename_option_and_add_field_help_are_validated(): void {
		$ops = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'rename_option', 'target' => 'Topic', 'from' => 'Sales', 'value' => 'فروش' ),
				array( 'op' => 'rename_option', 'target' => 'Topic', 'from' => 'Same', 'value' => 'Same' ),
				array( 'op' => 'rename_option', 'target' => '', 'from' => 'A', 'value' => 'B' ),
				array(
					'op'    => 'add_field',
					'field' => array(
						'type'  => 'rating',
						'label' => 'Rating',
						'help'  => 'Tap a star',
					),
				),
				array(
					'op'    => 'add_field',
					'field' => array(
						'type'  => 'table_matrix',
						'label' => 'Grid',
					),
				),
			)
		);

		$this->assertSame( array( 'rename_option', 'add_field' ), array_column( $ops['ops'], 'op' ) );
		$this->assertSame( 'فروش', $ops['ops'][0]['value'] );
		$this->assertSame( 'Tap a star', $ops['ops'][1]['field']['help'] );
		$this->assertSame(
			array( 'no_change', 'missing_target', 'choice_needs_options:table_matrix' ),
			array_column( $ops['dropped'], 'reason' )
		);
	}

	public function test_chat_prompt_carries_options_and_logic_status(): void {
		$provider = new CapturingEfbProvider();
		$fields   = array(
			array(
				'id'      => 'type',
				'label'   => 'Customer type',
				'type'    => 'radio',
				'options' => array(
					array(
						'id'    => 'o1',
						'value' => 'Business',
					),
					array(
						'id'    => 'o2',
						'value' => 'Individual',
					),
				),
			),
		);

		( new EfbChatSkill( $provider ) )->respond(
			'Show company only for businesses',
			array(),
			true,
			$fields,
			array(),
			array(),
			null,
			array(
				'logic'       => true,
				'logic_rules' => array( 'If “Customer type” is “Business”, show “Company”.' ),
			)
		);

		$this->assertStringContainsString( 'LOGIC_ADDON=yes', $provider->user_prompt );
		$this->assertStringContainsString( 'options=[Business | Individual]', $provider->user_prompt );
		$this->assertStringContainsString( 'CURRENT_LOGIC:', $provider->user_prompt );
	}
}
