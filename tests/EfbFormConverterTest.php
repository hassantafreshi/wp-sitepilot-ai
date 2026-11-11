<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Efb\EfbFormConverter;

final class EfbFormConverterTest extends TestCase {

	private function plan(): array {
		return array(
			'title'                => 'Contact us',
			'confirmation_message' => 'Thanks, we will reply soon.',
			'fields'               => array(
				array( 'key' => 'full_name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => 'Your name', 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
				array( 'key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => false, 'placeholder' => '', 'options' => array( 'Sales', 'Support' ) ),
			),
		);
	}

	public function test_structure_starts_with_form_then_step(): void {
		$items = ( new EfbFormConverter() )->convert( $this->plan() );

		$this->assertSame( 'form', $items[0]['type'] );
		$this->assertSame( 'Contact us', $items[0]['formName'] );
		$this->assertSame( 'Thanks, we will reply soon.', $items[0]['thank_you_message']['thankYou'] );
		$this->assertSame( 'step', $items[1]['type'] );
		$this->assertSame( '2', $items[0]['EfbVersion'] );
	}

	/** Every text EFB shows visitors is written by the converter, so it can follow the site language. */
	public function test_visitor_texts_are_set_even_when_the_plan_gives_none(): void {
		$plan = $this->plan();
		unset( $plan['confirmation_message'] );
		$items = ( new EfbFormConverter() )->convert( $plan );

		$this->assertSame( 'Submit', $items[0]['button_single_text'] );
		$this->assertSame( 'Next', $items[0]['button_Next_text'] );
		$this->assertSame( 'Previous', $items[0]['button_Previous_text'] );
		$this->assertSame( 'Thanks for filling out the form.', $items[0]['thank_you_message']['thankYou'] );
		$this->assertSame( 'You’re all done', $items[0]['thank_you_message']['done'] );
		$this->assertSame( 'Please fill in all required fields.', $items[0]['thank_you_message']['pleaseFillInRequiredFields'] );

		$plan['fields'][] = array( 'key' => 'more', 'type' => 'steps', 'label' => '', 'required' => false, 'placeholder' => '', 'options' => array() );
		$plan['fields'][] = array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Note', 'required' => false, 'placeholder' => '', 'options' => array() );
		$items = ( new EfbFormConverter() )->convert( $plan );
		$steps = array_values( array_filter( $items, static fn ( array $i ): bool => 'step' === $i['type'] ) );
		$this->assertSame( 'Step 2', $steps[1]['name'] );
	}

	public function test_fields_and_options_present_with_parent(): void {
		$items = ( new EfbFormConverter() )->convert( $this->plan() );

		$types = array_column( $items, 'type' );
		$this->assertContains( 'text', $types );
		$this->assertContains( 'email', $types );
		$this->assertContains( 'select', $types );
		$this->assertContains( 'option', $types );

		// Options must reference their select field via parent.
		$select = null;
		foreach ( $items as $it ) {
			if ( ( $it['type'] ?? '' ) === 'select' ) { $select = $it; }
		}
		$this->assertNotNull( $select );

		$options = array_values( array_filter( $items, static fn ( $it ) => ( $it['type'] ?? '' ) === 'option' ) );
		$this->assertCount( 2, $options );
		foreach ( $options as $opt ) {
			$this->assertSame( $select['id_'], $opt['parent'] );
			$this->assertSame( $opt['id_'], $opt['id_op'] );
		}
	}

	public function test_designed_fields_carry_their_own_settings(): void {
		$items = ( new EfbFormConverter() )->convert(
			array(
				'title'  => 'Booking',
				'fields' => array(
					array( 'key' => 'when', 'type' => 'date', 'label' => 'Preferred date', 'settings' => array( 'minimum' => '1', 'maximum' => '2026-12-31' ) ),
					array( 'key' => 'cv', 'type' => 'file', 'label' => 'CV', 'settings' => array( 'file_category' => 'document', 'max_file_size_mb' => 10 ) ),
					array( 'key' => 'services', 'type' => 'multiselect', 'label' => 'Services', 'options' => array( 'Cut', 'Colour', 'Style', 'Wash' ), 'settings' => array( 'max_selections' => 3 ) ),
					// Settings that belong to another type, or sit behind EFB's Pro
					// padlock, must not be baked into a brand-new form.
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Full name', 'settings' => array( 'record_quality' => 'high', 'mobile_width' => 'half' ) ),
				),
			)
		);

		$by = array();
		foreach ( $items as $item ) {
			if ( isset( $item['name'] ) && ( $item['type'] ?? '' ) !== 'step' ) { $by[ $item['name'] ] = $item; }
		}

		$this->assertSame( '1', $by['Preferred date']['milen'] );
		$this->assertSame( '2026-12-31', $by['Preferred date']['mlen'] );
		$this->assertSame( 'document', $by['CV']['file'] );
		$this->assertSame( 'document', $by['CV']['value'] );
		$this->assertSame( '10', $by['CV']['max_fsize'] );
		$this->assertSame( '3', $by['Services']['maxSelect'] );

		$this->assertArrayNotHasKey( 'record_quality', $by['Full name'] );
		$this->assertSame( '100', $by['Full name']['mobile_size'] );
	}

	public function test_step_breaks_split_the_form_into_pages(): void {
		$items = ( new EfbFormConverter() )->convert(
			array(
				'title'  => 'Job application',
				'fields' => array(
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true ),
					array( 'key' => 'experience', 'type' => 'steps', 'label' => 'Experience' ),
					array( 'key' => 'years', 'type' => 'select', 'label' => 'Years', 'options' => array( '0-2', '3-5' ) ),
				),
			)
		);

		$steps = array_values( array_filter( $items, static fn ( $it ) => ( $it['type'] ?? '' ) === 'step' ) );
		$this->assertCount( 2, $steps );
		$this->assertSame( 'Job application', $steps[0]['name'] );
		$this->assertSame( '', $steps[0]['classes'] );
		$this->assertSame( 'Experience', $steps[1]['name'] );
		$this->assertSame( '2', $steps[1]['id_'] );
		$this->assertSame( '2', $steps[1]['dataId'] );
		$this->assertSame( 'stepNavEfb', $steps[1]['classes'] );

		$pages = array();
		foreach ( $items as $it ) {
			if ( isset( $it['name'], $it['step'] ) && ( $it['type'] ?? '' ) !== 'step' ) { $pages[ $it['name'] ] = $it['step']; }
		}
		$this->assertSame( '1', $pages['Full name'] );
		$this->assertSame( '2', $pages['Years'] );

		$options = array_values( array_filter( $items, static fn ( $it ) => ( $it['type'] ?? '' ) === 'option' ) );
		$this->assertSame( '2', $options[0]['step'] );

		$this->assertSame( '2', $items[0]['steps'] );
		$this->assertSame( 'multi', $items[0]['button_state'] );
	}

	public function test_degenerate_step_breaks_do_not_create_empty_pages(): void {
		$items = ( new EfbFormConverter() )->convert(
			array(
				'title'  => 'Survey',
				'fields' => array(
					array( 'key' => 'about', 'type' => 'steps', 'label' => 'About you' ),
					array( 'key' => 'name', 'type' => 'text', 'label' => 'Name' ),
					array( 'key' => 'tail', 'type' => 'steps', 'label' => 'Nothing here' ),
				),
			)
		);

		$steps = array_values( array_filter( $items, static fn ( $it ) => ( $it['type'] ?? '' ) === 'step' ) );
		$this->assertCount( 1, $steps );
		$this->assertSame( 'About you', $steps[0]['name'] );
		$this->assertSame( '1', $items[0]['steps'] );
		$this->assertSame( 'single', $items[0]['button_state'] );
	}

	public function test_single_page_plans_stay_single_step(): void {
		$items = ( new EfbFormConverter() )->convert( $this->plan() );

		$this->assertSame( '1', $items[0]['steps'] );
		$this->assertSame( 'single', $items[0]['button_state'] );
		$this->assertCount( 1, array_filter( $items, static fn ( $it ) => ( $it['type'] ?? '' ) === 'step' ) );
	}

	public function test_multiselect_cap_allows_every_option_it_owns(): void {
		$plan = $this->plan();
		$plan['fields'][] = array( 'key' => 'colors', 'type' => 'multiselect', 'label' => 'Colors', 'required' => false, 'placeholder' => '', 'options' => array( 'Blue', 'Red', 'Yellow' ) );

		$items = ( new EfbFormConverter() )->convert( $plan );

		$multiselect = null;
		foreach ( $items as $it ) {
			if ( ( $it['type'] ?? '' ) === 'multiselect' ) { $multiselect = $it; }
		}
		$this->assertNotNull( $multiselect );
		$this->assertSame( '3', $multiselect['maxSelect'] );
		$this->assertSame( '0', $multiselect['minSelect'] );

		// A field added to a live form gets the same treatment.
		$added = ( new EfbFormConverter() )->build_field(
			array( 'type' => 'multiselect', 'label' => 'Sizes', 'options' => array( 'S', 'M' ) )
		);
		$this->assertSame( '2', $added[0]['maxSelect'] );

		// Types without the key are untouched.
		$select = null;
		foreach ( $items as $it ) {
			if ( ( $it['type'] ?? '' ) === 'select' ) { $select = $it; }
		}
		$this->assertArrayNotHasKey( 'maxSelect', $select );
	}

	public function test_required_and_ids_and_amounts(): void {
		$items = ( new EfbFormConverter() )->convert( $this->plan() );

		$name = null;
		foreach ( $items as $it ) {
			if ( ( $it['name'] ?? '' ) === 'Full name' ) { $name = $it; }
		}
		$this->assertNotNull( $name );
		$this->assertSame( '1', $name['required'] );          // bool true -> "1"
		$this->assertSame( 'full_name-id', $name['dataId'] ); // dataId = id_ + "-id"
		$this->assertSame( '1', $name['step'] );

		// amount is a sequential number starting at the step: EFB compares it as a number (a state list is
		// tied to the country list before it that way), so it is never a string.
		$this->assertSame( 1, $items[1]['amount'] );
		$this->assertSame( 2, $items[2]['amount'] );
	}

	public function test_fields_carry_full_efb_properties(): void {
		$plan = array(
			'title'  => 'Rich',
			'fields' => array(
				array( 'key' => 'first', 'type' => 'text', 'label' => 'First', 'required' => true, 'placeholder' => '', 'width' => 'half', 'options' => array() ),
				array( 'key' => 'cv', 'type' => 'file', 'label' => 'CV', 'required' => false, 'placeholder' => '', 'width' => 'full', 'options' => array() ),
				array( 'key' => 'agree', 'type' => 'checkbox', 'label' => 'Agree', 'required' => true, 'placeholder' => '', 'width' => 'full', 'options' => array( 'Yes' ) ),
			),
		);

		$items = ( new EfbFormConverter() )->convert( $plan );
		$by = array();
		foreach ( $items as $it ) {
			if ( isset( $it['id_'] ) ) { $by[ $it['id_'] ] = $it; }
		}

		// Common styling exists on every field.
		$this->assertSame( '50', $by['first']['size'] );          // half width
		$this->assertSame( '100', $by['first']['mobile_size'] );  // mobile full
		$this->assertSame( 'h-d-efb', $by['first']['el_height'] );
		$this->assertSame( 'efb-square', $by['first']['corner'] );
		$this->assertSame( 'text-labelEfb', $by['first']['label_text_color'] );
		$this->assertSame( 'border-d', $by['first']['el_border_color'] );

		// File-specific properties.
		$this->assertSame( 'document', $by['cv']['file'] );
		$this->assertSame( '8', $by['cv']['max_fsize'] );

		// Checkbox-specific property.
		$this->assertSame( '#0d6efd', $by['agree']['checked_color'] );
	}

	public function test_encode_escapes_quotes_like_efb(): void {
		$items   = ( new EfbFormConverter() )->convert( $this->plan() );
		$encoded = EfbFormConverter::encode_for_storage( $items );

		// EFB stores JSON with every " escaped to \".
		$this->assertStringContainsString( '\\"type\\":\\"form\\"', $encoded );
		$this->assertStringNotContainsString( '"type":"form"', $encoded );

		// And it must decode back after unescaping.
		$decoded = json_decode( str_replace( '\\"', '"', $encoded ), true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'form', $decoded[0]['type'] );
	}

	public function test_build_step_uses_the_native_efb_step_shape(): void {
		$step = ( new EfbFormConverter() )->build_step( 'Personal information' );

		$this->assertSame( 'step', $step['type'] );
		$this->assertSame( 'stepNavEfb', $step['classes'] );
		$this->assertSame( 'Personal information', $step['name'] );
		$this->assertSame( '', $step['id_'] ); // Assigned against the live form in the browser.
	}
}
