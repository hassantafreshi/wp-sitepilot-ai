<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Efb\EfbReplyText;
use PHPUnit\Framework\TestCase;

/**
 * Form settings changed by chat (private form, email subject, redirect, form type, SMS), the palette
 * fields added later (address block, geographic lists, map, recorders, link, HTML, image picker), and the
 * rules that keep an address or a phone number the admin's own.
 */
final class EfbFormSettingsTest extends TestCase {

	private const FORM = array(
		array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		array( 'id' => 'email', 'label' => 'Email', 'type' => 'email' ),
	);

	/** @param array<int,array<string,mixed>> $ops @param array<string,mixed> $context @param array<int,array<string,mixed>>|null $fields */
	private function run_ops( array $ops, array $context = array(), ?array $fields = null ): array {
		return EfbCustomizeSkill::validate_ops_verbose( $ops, $fields ?? self::FORM, $context + array( 'home_url' => 'https://shop.example/' ) );
	}

	public function test_replies_lose_internal_names_and_keep_ordinary_text(): void {
		$this->assertSame(
			'Open the SMS section and turn on the switch. The SMS add-on must be active.',
			EfbReplyText::clean( 'Open the SMS section (sms_collapse) and turn on the switch (smsEnableEl). The SMS add-on (AdnSS) must be active.' )
		);
		$this->assertSame( 'گزینه را روشن کنید', EfbReplyText::clean( 'گزینه (smsEnableEl) را روشن کنید' ) );
		$this->assertSame( 'Set it there.', EfbReplyText::clean( 'Set `email_sub` it there.' ) );
		$this->assertSame( 'The price is 5 (USD), as agreed (see above).', EfbReplyText::clean( 'The price is 5 (USD), as agreed (see above).' ) );
		$this->assertSame( 'Use [confirmation_code] in the text.', EfbReplyText::clean( 'Use [confirmation_code] in the text.' ) );
	}

	public function test_an_address_is_only_taken_from_the_admin_or_the_home_page(): void {
		$context = array(
			'said'     => 'change thank you message to redirect : google.com',
			'home_url' => 'https://shop.example/',
		);
		$this->assertSame( 'https://google.com', EfbCustomizeSkill::admin_url_value( 'https://google.com', $context ) );
		$this->assertSame( 'https://google.com', EfbCustomizeSkill::admin_url_value( 'google.com', $context ) );
		$this->assertSame( 'https://shop.example/', EfbCustomizeSkill::admin_url_value( 'https://www.shop.example', $context ) );
		$this->assertNull( EfbCustomizeSkill::admin_url_value( 'https://invented.example/thanks', $context ) );
		$this->assertNull( EfbCustomizeSkill::admin_url_value( 'javascript:alert(1)', array( 'said' => 'javascript:alert(1)' ) ) );
		$this->assertNull( EfbCustomizeSkill::admin_url_value( 'https://a.example/x"y', array( 'said' => 'https://a.example/x"y' ) ) );
		$this->assertSame( 'http://127.0.0.1/wp/', EfbCustomizeSkill::admin_url_value( 'http://127.0.0.1/wp/', array( 'said' => 'http://127.0.0.1/wp/' ) ) );
	}

	public function test_sms_numbers_must_have_been_typed(): void {
		$context = array( 'said' => 'send an SMS to +49 170 1234567 and ۰۹۱۲۱۲۳۴۵۶۷' );
		$this->assertSame( '+491701234567', EfbCustomizeSkill::admin_phone_list( '+491701234567', $context ) );
		$this->assertSame( '+491701234567,+9121234567', EfbCustomizeSkill::admin_phone_list( array( '+49 170 1234567', '09121234567' ), $context ) );
		$this->assertNull( EfbCustomizeSkill::admin_phone_list( '+15550001111', $context ) );
		$this->assertNull( EfbCustomizeSkill::admin_phone_list( '', $context ) );
	}

	public function test_private_form_email_subject_and_redirect(): void {
		$result = $this->run_ops(
			array(
				array( 'op' => 'set_form_property', 'property' => 'require_login', 'value' => true ),
				array( 'op' => 'set_form_property', 'property' => 'email_subject', 'value' => 'New "order" <received>' ),
				array( 'op' => 'set_form_property', 'property' => 'thank_you_redirect', 'value' => 'https://example.com/thanks' ),
				array( 'op' => 'set_form_property', 'property' => 'redirect', 'value' => 'home page' ),
				array( 'op' => 'set_form_property', 'property' => 'thank_you_mode', 'value' => 'rdrct' ),
				array( 'op' => 'set_form_property', 'property' => 'thank_you_mode', 'value' => 'msg' ),
			),
			array( 'said' => 'after submit go to https://example.com/thanks' )
		);
		$this->assertSame( array( 'require_login', 'email_subject', 'thank_you_redirect', 'thank_you_mode' ), array_column( $result['ops'], 'property' ) );
		$this->assertTrue( $result['ops'][0]['value'] );
		$this->assertSame( 'New order received', $result['ops'][1]['value'] );
		$this->assertSame( 'https://example.com/thanks', $result['ops'][2]['value'] );
		$this->assertSame( 'msg', $result['ops'][3]['value'] );
		$this->assertSame( array( 'value_not_given:thank_you_redirect', 'value_not_given:thank_you_redirect' ), array_column( $result['dropped'], 'reason' ) );
		$this->assertSame( 'plus', EfbFormModel::plan_of( EfbFormModel::form_property( 'email_subject' ) ) );
		$this->assertSame( 'plus', EfbFormModel::plan_of( EfbFormModel::form_property( 'thank_you_redirect' ) ) );
		$this->assertSame( 'pro', EfbFormModel::plan_of( EfbFormModel::form_property( 'global_mobile_hide_labels' ) ) );
		$this->assertSame( '', EfbFormModel::plan_of( EfbFormModel::form_property( 'require_login' ) ) );
	}

	public function test_form_type_switches_to_form_survey_or_sign_in_only(): void {
		$result = $this->run_ops(
			array(
				array( 'op' => 'set_form_property', 'property' => 'form_type', 'value' => 'login' ),
				array( 'op' => 'set_form_property', 'property' => 'form_type', 'value' => 'survey' ),
				array( 'op' => 'set_form_property', 'property' => 'form_type', 'value' => 'payment' ),
				array( 'op' => 'set_form_property', 'property' => 'form_type', 'value' => 'register' ),
			)
		);
		$this->assertSame( array( 'login', 'survey' ), array_column( $result['ops'], 'value' ) );
		$this->assertSame( array( 'invalid_property_value:form_type', 'invalid_property_value:form_type' ), array_column( $result['dropped'], 'reason' ) );

		$fields = ( new EfbFormConverter() )->build_login_fields( array( 'name', 'emaillogin' ) );
		$this->assertSame( array( 'passwordlogin' ), array_column( $fields, 'id_' ) );
		$this->assertSame( 'password', $fields[0]['type'] );
		$this->assertSame( array( 'emaillogin', 'passwordlogin' ), array_column( ( new EfbFormConverter() )->build_login_fields(), 'id_' ) );
	}

	public function test_sms_needs_its_add_on_and_reaches_the_respondent_through_a_mobile_field(): void {
		$ops = array(
			array( 'op' => 'set_form_property', 'property' => 'sms_notification', 'value' => true ),
			array( 'op' => 'set_form_property', 'property' => 'sms_admin_numbers', 'value' => '+491701234567' ),
			array( 'op' => 'add_field', 'field' => array( 'type' => 'mobile', 'label' => 'Mobile' ) ),
			array( 'op' => 'set_field_property', 'target' => 'Mobile', 'property' => 'sms_to_respondent', 'value' => true ),
			array( 'op' => 'set_field_property', 'target' => 'Email', 'property' => 'sms_to_respondent', 'value' => true ),
		);
		$said = array( 'said' => 'text +491701234567 and the person who fills it in' );

		$without = $this->run_ops( $ops, $said );
		$this->assertSame( array( 'add_field' ), array_column( $without['ops'], 'op' ) );
		$this->assertContains( 'addon_inactive:AdnSS', array_column( $without['dropped'], 'reason' ) );

		$with = $this->run_ops( $ops, $said + array( 'addons' => array( 'AdnSS' => 1 ) ) );
		$this->assertSame( array( 'set_form_property', 'set_form_property', 'add_field', 'set_field_property' ), array_column( $with['ops'], 'op' ) );
		$this->assertSame( '+491701234567', $with['ops'][1]['value'] );
		$this->assertSame( 'Mobile', $with['ops'][3]['target'] );
		$this->assertSame( array( 'property_not_available_for_type:sms_to_respondent:email' ), array_column( $with['dropped'], 'reason' ) );

		// Turning SMS off never needs the add-on; a number nobody typed is refused.
		$off = $this->run_ops(
			array(
				array( 'op' => 'set_form_property', 'property' => 'sms_notification', 'value' => false ),
				array( 'op' => 'set_form_property', 'property' => 'sms_admin_numbers', 'value' => '+15550001111' ),
			),
			array( 'said' => 'turn sms off', 'addons' => array( 'AdnSS' => 1 ) )
		);
		$this->assertFalse( $off['ops'][0]['value'] );
		$this->assertSame( array( 'value_not_given:sms_admin_numbers' ), array_column( $off['dropped'], 'reason' ) );
	}

	public function test_link_html_and_map_rules(): void {
		$result = $this->run_ops(
			array(
				array( 'op' => 'add_field', 'field' => array( 'type' => 'link', 'label' => 'Terms', 'url' => 'example.com/terms' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'link', 'label' => 'Elsewhere', 'url' => 'https://made-up.example' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'html', 'label' => 'Intro', 'html' => '<p>Hello <strong>there</strong></p><script>alert(1)</script>' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'html', 'label' => 'Empty', 'html' => '<script>alert(1)</script>' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'location_picker', 'label' => 'Where' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'maps', 'label' => 'Again' ) ),
			),
			array( 'said' => 'link to example.com/terms' )
		);
		$fields = array_column( $result['ops'], 'field' );
		$this->assertSame( array( 'link', 'html', 'maps' ), array_column( $fields, 'type' ) );
		$this->assertSame( 'https://example.com/terms', $fields[0]['url'] );
		$this->assertStringContainsString( '<strong>there</strong>', $fields[1]['html'] );
		$this->assertStringNotContainsString( '<script', $fields[1]['html'] );
		$this->assertSame( array( 'value_not_given:link', 'html_empty', 'map_exists' ), array_column( $result['dropped'], 'reason' ) );

		$converter = new EfbFormConverter();
		$link      = $converter->build_field( $fields[0] );
		$this->assertSame( array( 'link', 'Terms', 'https://example.com/terms' ), array( $link[0]['type'], $link[0]['value'], $link[0]['href'] ) );
		$html = $converter->build_field( array( 'type' => 'html', 'label' => 'x', 'html' => "<p class=\"a\">Hi</p>\n<b>x</b>" ) );
		$this->assertSame( '<p class=@!a@!>Hi</p>@efb@nq#<b>x</b>', $html[0]['value'] );
		$map = $converter->build_field( $fields[2] );
		$this->assertSame( array( 'maps', 1, 12 ), array( $map[0]['type'], $map[0]['mark'], $map[0]['zoom'] ) );
	}

	public function test_a_state_list_follows_a_country_list_and_a_city_list_a_state_list(): void {
		// Nothing to attach to: the lists they depend on come along.
		$alone = $this->run_ops( array( array( 'op' => 'add_field', 'field' => array( 'type' => 'city', 'label' => 'City' ) ) ) );
		$this->assertSame( array( 'conturyList', 'stateProvince' ), $alone['ops'][0]['field']['with'] );

		// A country list already in the form, with no state after it: the state goes right behind it.
		$form  = array_merge( self::FORM, array( array( 'id' => 'land', 'label' => 'Country', 'type' => 'conturyList' ) ) );
		$after = $this->run_ops( array( array( 'op' => 'add_field', 'field' => array( 'type' => 'state', 'label' => 'State' ) ) ), array(), $form );
		$this->assertSame( 'land', $after['ops'][0]['after'] );
		$this->assertArrayNotHasKey( 'with', $after['ops'][0]['field'] );

		// Added one after the other in one message: nothing extra.
		$chain = $this->run_ops(
			array(
				array( 'op' => 'add_field', 'field' => array( 'type' => 'country', 'label' => 'Country' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'stateProvince', 'label' => 'State' ) ),
				array( 'op' => 'add_field', 'field' => array( 'type' => 'cityList', 'label' => 'City' ) ),
			)
		);
		foreach ( $chain['ops'] as $op ) {
			$this->assertArrayNotHasKey( 'with', $op['field'] );
			$this->assertArrayNotHasKey( 'after', $op );
		}

		$items = ( new EfbFormConverter() )->build_field( $alone['ops'][0]['field'] );
		$heads = array_values( array_filter( $items, static fn ( array $item ): bool => 'option' !== $item['type'] ) );
		$this->assertSame( array( 'conturyList', 'stateProvince', 'cityList' ), array_column( $heads, 'type' ) );
		$this->assertCount( 241, array_filter( $items, static fn ( array $item ): bool => 'option' === $item['type'] && $item['parent'] === $heads[0]['id_'] ) );
		$this->assertSame( 'GB', $heads[1]['country'] );
		$this->assertSame( count( $items ), count( array_unique( array_column( $items, 'id_' ) ) ) );
	}

	public function test_the_address_block_is_five_linked_fields_numbered_the_way_efb_numbers_them(): void {
		$items = ( new EfbFormConverter() )->convert(
			array(
				'title'  => 'Delivery',
				'fields' => array(
					array( 'type' => 'text', 'label' => 'Name' ),
					array( 'type' => 'address', 'label' => 'Delivery address', 'required' => true ),
					array( 'type' => 'textarea', 'label' => 'Notes' ),
				),
			)
		);
		$heads = array_values( array_filter( $items, static fn ( array $item ): bool => ! in_array( $item['type'], array( 'form', 'option' ), true ) ) );
		$this->assertSame( array( 'step', 'text', 'conturyList', 'stateProvince', 'cityList', 'text', 'text', 'textarea' ), array_column( $heads, 'type' ) );
		$this->assertSame( array( 'conturyList', 'stateProvince', 'cityList', 'postalcode', 'address_line' ), array_column( array_slice( $heads, 2, 5 ), 'elementId' ) );
		$this->assertSame( 'Delivery address', $heads[6]['name'] );
		// Fields are numbered 1, 2, 3 ... and an option carries its field's number: after EFB's own Save the
		// numbers are strings, and a state list is found by comparing it with the country list before it.
		$this->assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), array_column( $heads, 'amount' ) );
		foreach ( $items as $item ) {
			if ( 'option' === $item['type'] ) {
				$parent = current( array_filter( $heads, static fn ( array $head ): bool => $head['id_'] === $item['parent'] ) );
				$this->assertSame( $parent['amount'], $item['amount'] );
			}
		}
	}

	public function test_a_new_form_gets_the_same_rules(): void {
		$prepared = EfbCustomizeSkill::prepare_new_form_fields(
			array(
				array( 'type' => 'text', 'label' => 'Name' ),
				array( 'type' => 'cityList', 'label' => 'City' ),
				array( 'type' => 'link', 'label' => 'Terms' ),
				array( 'type' => 'link', 'label' => 'Shop' ),
				array( 'type' => 'maps', 'label' => 'A' ),
				array( 'type' => 'maps', 'label' => 'B' ),
			),
			array(
				array( 'type' => 'link', 'label' => 'Terms', 'url' => 'https://example.com/terms' ),
				array( 'type' => 'link', 'label' => 'Shop', 'url' => 'https://nobody-said-this.example' ),
			),
			array( 'said' => 'with a link to https://example.com/terms' ),
			array( 'conturyList' => 'Country', 'stateProvince' => 'State' )
		);
		$this->assertSame( array( 'text', 'conturyList', 'stateProvince', 'cityList', 'link', 'maps' ), array_column( $prepared['fields'], 'type' ) );
		$this->assertSame( 'https://example.com/terms', $prepared['fields'][4]['url'] );
		$this->assertSame( array( 'value_not_given:link', 'map_exists' ), array_column( $prepared['dropped'], 'reason' ) );
	}

	public function test_recorders_and_the_image_picker_take_efbs_own_shape(): void {
		$converter = new EfbFormConverter();
		$video     = $converter->build_field( array( 'type' => 'video_recorder', 'label' => 'Video' ) )[0];
		$this->assertSame( array( 'media', 'media', '720p', 'user' ), array( $video['value'], $video['file'], $video['record_quality'], $video['rec_facing'] ) );
		$this->assertArrayNotHasKey( 'icon_input', $video );
		$this->assertArrayNotHasKey( '__drop__', $video );

		$picker = $converter->build_field( array( 'type' => 'imgRadio', 'label' => 'Size', 'options' => array( 'Small', 'Large' ), 'descriptions' => array( 'Up to 2 kg' ) ) );
		$this->assertCount( 3, $picker );
		$this->assertSame( array( 'Small', 'Up to 2 kg' ), array( $picker[1]['value'], $picker[1]['sub_value'] ) );
		$this->assertArrayHasKey( 'src', $picker[2] );
	}
}
