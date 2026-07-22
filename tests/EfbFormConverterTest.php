<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Efb\EfbFormConverter;

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

		// amount is a sequential string starting at the step.
		$this->assertSame( '1', $items[1]['amount'] );
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
}
