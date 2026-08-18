<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbFormModel;

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

	public function test_customizer_handles_multiselect_and_range_specific_properties(): void {
		$fields = array(
			array( 'id' => 'colors', 'label' => 'Colors', 'type' => 'multiselect' ),
			array( 'id' => 'budget', 'label' => 'Budget', 'type' => 'range' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Colors', 'property' => 'max_select', 'value' => 3 ),
				array( 'op' => 'set_field_property', 'target' => 'Colors', 'property' => 'min_selections', 'value' => 1 ),
				array( 'op' => 'set_field_property', 'target' => 'Budget', 'property' => 'maximum', 'value' => '5000' ),
				// An unfillable cap and a preselection the AI must not write itself.
				array( 'op' => 'set_field_property', 'target' => 'Colors', 'property' => 'max_selections', 'value' => 0 ),
				array( 'op' => 'set_field_property', 'target' => 'Colors', 'property' => 'selected_options', 'value' => 'blue' ),
				array( 'op' => 'set_field_property', 'target' => 'Budget', 'property' => 'min_selections', 'value' => 1 ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( 'max_selections', $result['ops'][0]['property'] );
		$this->assertSame( 3, $result['ops'][0]['value'] );
		$this->assertSame( 'min_selections', $result['ops'][1]['property'] );
		$this->assertSame( 'maximum', $result['ops'][2]['property'] );

		$this->assertSame( 'invalid_property_value:max_selections', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_writable:selected_options', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:min_selections:range', $result['dropped'][2]['reason'] );
	}

	public function test_range_is_addable_but_only_on_a_pro_featured_licence(): void {
		$ops = EfbCustomizeSkill::validate_ops_verbose(
			array( array( 'op' => 'add_field', 'field' => array( 'type' => 'range', 'label' => 'Budget' ) ) )
		);
		$this->assertCount( 1, $ops['ops'] );
		$this->assertSame( 'range', $ops['ops'][0]['field']['type'] );

		// EFB renders a slider on every plan, so its plan stays free; AISAAutoPilot
		// only creates one for Free Plus and Pro installs.
		$this->assertFalse( EfbFormModel::requires_pro_field( 'range' ) );
		$this->assertTrue( EfbFormModel::requires_pro_to_create( 'range' ) );
		$this->assertFalse( EfbFormModel::requires_pro_to_create( 'text' ) );

		// Configuring an existing slider is never gated.
		$edit = EfbCustomizeSkill::validate_ops_verbose(
			array( array( 'op' => 'set_field_property', 'target' => 'Budget', 'property' => 'maximum', 'value' => '5000' ) ),
			array( array( 'id' => 'budget', 'label' => 'Budget', 'type' => 'range' ) )
		);
		$this->assertCount( 1, $edit['ops'] );

		// Designing a form from scratch still does not reach for a slider.
		$this->assertNotContains( 'range', EfbFormModel::allowed_types() );
	}

	public function test_prompt_tells_the_model_what_the_validator_enforces(): void {
		$properties = EfbFormModel::customization_capabilities_for_prompt();
		$types      = EfbFormModel::field_types_for_prompt();

		// Per-type value limits: without these the model proposes 1080p audio and
		// only finds out when the validator rejects it.
		$this->assertStringContainsString( 'only audio_recorder: low|standard|high', $properties );
		$this->assertStringContainsString( 'video_recorder: 480p|720p|1080p', $properties );
		$this->assertStringContainsString( 'file: allformat|document|image|media|zip', $properties );

		// Controls a type's panel does not have.
		$this->assertStringContainsString( 'has no ', $types );
		$this->assertMatchesRegularExpression( '/- steps: .*has no .*width/', $types );

		// Meanings a value type cannot carry on its own.
		$this->assertStringContainsString( 'literal 1 meaning today', $properties );

		// Prose notes stay local — they are reference material, not prompt payload.
		$this->assertStringNotContainsString( 'Drag and drop', $properties . $types );
	}

	public function test_heading_panel_and_licence_aware_designer(): void {
		$fields = array(
			array( 'id' => 'head', 'label' => 'Heading', 'type' => 'heading' ),
			array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Heading', 'property' => 'default_value', 'value' => 'Your details' ),
				array( 'op' => 'set_field_property', 'target' => 'Heading', 'property' => 'input_text_size', 'value' => 'display-4' ),
				// A heading has no name key, no required flag and no alignment
				// control, and its size select offers only the display steps.
				array( 'op' => 'set_field_property', 'target' => 'Heading', 'property' => 'label', 'value' => 'Nope' ),
				array( 'op' => 'set_field_property', 'target' => 'Heading', 'property' => 'input_align', 'value' => 'center' ),
				array( 'op' => 'set_field_property', 'target' => 'Heading', 'property' => 'input_text_size', 'value' => 'fs-5' ),
			),
			$fields
		);

		$this->assertCount( 2, $result['ops'] );
		$this->assertSame( 'property_not_available_for_type:label:heading', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:input_align:heading', $result['dropped'][1]['reason'] );
		$this->assertSame( 'invalid_property_value:input_text_size', $result['dropped'][2]['reason'] );

		// The same size is ordinary on a normal field.
		$text = EfbCustomizeSkill::validate_ops_verbose(
			array( array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'input_text_size', 'value' => 'fs-5' ) ),
			$fields
		);
		$this->assertCount( 1, $text['ops'] );

		// Heading and esign are Pro. The designer still knows about them on a Free
		// install — flagged, not hidden — so the admin gets their form plus an
		// honest note about what a licence would add.
		$this->assertContains( 'heading', EfbFormModel::designable_types( false ) );
		$this->assertStringContainsString( '- heading:', EfbFormModel::capabilities_for_prompt( false ) );
		$this->assertStringContainsString( 'needs Easy Form Builder Free Plus or Pro', EfbFormModel::capabilities_for_prompt( false ) );
		$this->assertStringNotContainsString( 'needs Easy Form Builder Free Plus or Pro', EfbFormModel::capabilities_for_prompt( true ) );
	}

	public function test_pro_fields_are_split_out_of_a_free_plan_not_silently_kept(): void {
		$plan = array(
			'title'  => 'Contract',
			'fields' => array(
				array( 'type' => 'heading', 'label' => 'Your details' ),
				array( 'type' => 'text', 'label' => 'Full name' ),
				array( 'type' => 'esign', 'label' => 'Signature' ),
			),
		);

		// A licence with Pro features loses nothing.
		$pro = EfbFormModel::split_pro_fields( $plan, true );
		$this->assertCount( 3, $pro['plan']['fields'] );
		$this->assertSame( array(), $pro['pro_fields'] );

		// Without it the form is still built, and the omissions are named so the
		// admin can be told rather than handed a lesser form or an upgrade wall.
		$free = EfbFormModel::split_pro_fields( $plan, false );
		$this->assertCount( 1, $free['plan']['fields'] );
		$this->assertSame( 'Full name', $free['plan']['fields'][0]['label'] );
		$this->assertCount( 2, $free['pro_fields'] );
		$this->assertSame( 'heading', $free['pro_fields'][0]['type'] );
		$this->assertSame( 'Your details', $free['pro_fields'][0]['label'] );
		$this->assertSame( 'esign', $free['pro_fields'][1]['type'] );
	}

	public function test_quantity_checklist_settings(): void {
		$fields = array(
			array( 'id' => 'chk', 'label' => 'Box Checklist', 'type' => 'chlCheckBox' ),
			array( 'id' => 'topic', 'label' => 'Topic', 'type' => 'radio' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Box Checklist', 'property' => 'quantity_placeholder', 'value' => 'Amount' ),
				array( 'op' => 'set_field_property', 'target' => 'Box Checklist', 'property' => 'option_columns', 'value' => 'two' ),
				array( 'op' => 'set_field_property', 'target' => 'Box Checklist', 'property' => 'checked_color', 'value' => '#004cbb' ),
				// Only the quantity lists have that caption, and EFB reserves
				// corner/height for dropdown-style types.
				array( 'op' => 'set_field_property', 'target' => 'Topic', 'property' => 'quantity_placeholder', 'value' => 'Amount' ),
				array( 'op' => 'set_field_property', 'target' => 'Box Checklist', 'property' => 'corner', 'value' => 'rounded-3' ),
				array( 'op' => 'set_field_property', 'target' => 'Box Checklist', 'property' => 'input_height', 'value' => 'h-l-efb' ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( 'Amount', $result['ops'][0]['value'] );
		$this->assertSame( 'pholder_chl_value', EfbFormModel::field_property( 'quantity_placeholder' )['storage_key'] );
		$this->assertSame( 'property_not_available_for_type:quantity_placeholder:radio', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:corner:chlCheckBox', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:input_height:chlCheckBox', $result['dropped'][2]['reason'] );

		// chlRadio exists in stored forms but not in EFB's palette.
		$chl_radio = EfbFormModel::field_type( 'chlRadio' );
		$this->assertSame( 'pro', $chl_radio['plan'] );
		$this->assertArrayNotHasKey( 'ai_create', array_filter( $chl_radio, static fn ( $v, $k ): bool => 'ai_create' === $k && $v, ARRAY_FILTER_USE_BOTH ) );
	}

	public function test_switch_captions_and_colours(): void {
		$fields = array(
			array( 'id' => 'sw', 'label' => 'Switch', 'type' => 'switch' ),
			array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Switch', 'property' => 'switch_on_text', 'value' => 'Yes' ),
				array( 'op' => 'set_field_property', 'target' => 'Switch', 'property' => 'off_label', 'value' => 'No' ),
				array( 'op' => 'set_field_property', 'target' => 'Switch', 'property' => 'switch_on_color', 'value' => '#3644d2' ),
				// The captions belong to this type only, the colours are raw hex,
				// and the panel has no corner or border-colour control.
				array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'switch_on_text', 'value' => 'Yes' ),
				array( 'op' => 'set_field_property', 'target' => 'Switch', 'property' => 'switch_off_color', 'value' => 'green' ),
				array( 'op' => 'set_field_property', 'target' => 'Switch', 'property' => 'corner', 'value' => 'rounded-3' ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( 'Yes', $result['ops'][0]['value'] );
		$this->assertSame( 'switch_off_text', $result['ops'][1]['property'] );
		$this->assertSame( 'property_not_available_for_type:switch_on_text:text', $result['dropped'][0]['reason'] );
		$this->assertSame( 'invalid_property_value:switch_off_color', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:corner:switch', $result['dropped'][2]['reason'] );

		// EFB writes the captions to the bare `on`/`off` keys.
		$this->assertSame( 'on', EfbFormModel::field_property( 'switch_on_text' )['storage_key'] );
		$this->assertSame( 'off', EfbFormModel::field_property( 'switch_off_text' )['storage_key'] );

		// Its colour pickers sit behind EFB's Pro padlock; the captions do not.
		$this->assertTrue( EfbFormModel::requires_pro_property( 'switch_on_color' ) );
		$this->assertFalse( EfbFormModel::requires_pro_property( 'switch_on_text' ) );
	}

	public function test_screen_recorder_has_no_camera_controls_and_records_its_limits(): void {
		$fields = array( array( 'id' => 'scr', 'label' => 'Screen Recorder', 'type' => 'screen_recorder' ) );

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'record_quality', 'value' => '1080p' ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_watermark', 'value' => false ),
				// There is no camera and no microphone-only toggle here.
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_facing', 'value' => 'user' ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_mirror', 'value' => true ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_noise', 'value' => true ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'record_quality', 'value' => 'high' ),
			),
			$fields
		);

		$this->assertCount( 2, $result['ops'] );
		$this->assertSame( 'property_not_available_for_type:recorder_facing:screen_recorder', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:recorder_mirror:screen_recorder', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:recorder_noise:screen_recorder', $result['dropped'][2]['reason'] );
		$this->assertSame( 'invalid_property_value:record_quality', $result['dropped'][3]['reason'] );

		// Platform limits from EFB's own recorder documentation.
		$notes = implode( ' ', (array) EfbFormModel::field_type( 'screen_recorder' )['notes'] );
		$this->assertStringContainsString( 'getDisplayMedia', $notes );
		$this->assertStringContainsString( 'secure context', $notes );
	}

	public function test_mobile_settings_are_marked_pro_like_efb_marks_them(): void {
		// EFB wraps every mobile block in efbMobileProControlEfb(), which padlocks
		// the controls on a Free install. Writing those keys anyway would move
		// settings the admin can neither see nor undo.
		foreach ( array( 'mobile_width', 'mobile_label_position', 'mobile_label_size', 'mobile_label_align', 'mobile_help_align', 'mobile_hide_label', 'mobile_hide_help', 'mobile_option_columns' ) as $property ) {
			$this->assertTrue( EfbFormModel::requires_pro_property( $property ), $property . ' is behind EFB\'s mobile padlock' );
		}
		$this->assertTrue( EfbFormModel::requires_pro_form_property( 'global_mobile_hide_labels' ) );

		// The desktop equivalents are ordinary settings on every plan.
		foreach ( array( 'width', 'label_size', 'label_align', 'help_align', 'corner', 'css_classes' ) as $property ) {
			$this->assertFalse( EfbFormModel::requires_pro_property( $property ), $property . ' must stay ungated' );
		}

		// Gating belongs to the licence-aware controller, so the validator still
		// passes them through.
		$ops = EfbCustomizeSkill::validate_ops_verbose(
			array( array( 'op' => 'set_field_property', 'target' => 'Name', 'property' => 'mobile_width', 'value' => 'full' ) ),
			array( array( 'id' => 'name', 'label' => 'Name', 'type' => 'text' ) )
		);
		$this->assertCount( 1, $ops['ops'] );
	}

	public function test_video_recorder_semantics_reach_the_model(): void {
		$fields = array(
			array( 'id' => 'vid', 'label' => 'Video Recorder', 'type' => 'video_recorder' ),
			array( 'id' => 'scr', 'label' => 'Screen Recorder', 'type' => 'screen_recorder' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'recorder_facing', 'value' => 'environment' ),
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'recorder_mirror', 'value' => false ),
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'recorder_watermark', 'value' => false ),
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'recorder_facing', 'value' => 'rear' ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_facing', 'value' => 'user' ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( 'environment', $result['ops'][0]['value'] );
		$this->assertSame( 'invalid_property_value:recorder_facing', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:recorder_facing:screen_recorder', $result['dropped'][1]['reason'] );

		// Per EFB's recorder registry the mirror and watermark only affect the
		// preview. The model has to know that, or it will promise the wrong thing.
		$prompt = EfbFormModel::customization_capabilities_for_prompt();
		$this->assertStringContainsString( 'never the saved video', $prompt );
		$this->assertStringContainsString( 'not burned into the recording', $prompt );
		$this->assertStringContainsString( 'front/selfie lens', $prompt );
	}

	public function test_recorder_quality_and_toggles_stay_with_their_own_recorder(): void {
		$fields = array(
			array( 'id' => 'aud', 'label' => 'Audio Recorder', 'type' => 'audio_recorder' ),
			array( 'id' => 'vid', 'label' => 'Video Recorder', 'type' => 'video_recorder' ),
			array( 'id' => 'scr', 'label' => 'Screen Recorder', 'type' => 'screen_recorder' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Audio Recorder', 'property' => 'record_quality', 'value' => 'high' ),
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'record_quality', 'value' => '1080p' ),
				array( 'op' => 'set_field_property', 'target' => 'Audio Recorder', 'property' => 'recorder_noise', 'value' => true ),
				// Audio has no pixel heights, video has no audio grades, and each
				// exclusive toggle belongs to exactly one recorder.
				array( 'op' => 'set_field_property', 'target' => 'Audio Recorder', 'property' => 'record_quality', 'value' => '1080p' ),
				array( 'op' => 'set_field_property', 'target' => 'Video Recorder', 'property' => 'record_quality', 'value' => 'high' ),
				array( 'op' => 'set_field_property', 'target' => 'Audio Recorder', 'property' => 'recorder_watermark', 'value' => true ),
				array( 'op' => 'set_field_property', 'target' => 'Screen Recorder', 'property' => 'recorder_facing', 'value' => 'user' ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( 'high', $result['ops'][0]['value'] );
		$this->assertSame( '1080p', $result['ops'][1]['value'] );
		$this->assertSame( 'invalid_property_value:record_quality', $result['dropped'][0]['reason'] );
		$this->assertSame( 'invalid_property_value:record_quality', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:recorder_watermark:audio_recorder', $result['dropped'][2]['reason'] );
		$this->assertSame( 'property_not_available_for_type:recorder_facing:screen_recorder', $result['dropped'][3]['reason'] );

		// EFB's palette marks all three Pro, so a Free install cannot render them.
		$this->assertTrue( EfbFormModel::requires_pro_field( 'audio_recorder' ) );
		$this->assertTrue( EfbFormModel::requires_pro_field( 'video_recorder' ) );
		$this->assertTrue( EfbFormModel::requires_pro_field( 'screen_recorder' ) );
	}

	public function test_dadfile_extras_do_not_leak_onto_the_plain_file_field(): void {
		$fields = array(
			array( 'id' => 'dnd', 'label' => 'Drop files', 'type' => 'dadfile' ),
			array( 'id' => 'upload', 'label' => 'File upload', 'type' => 'file' ),
			array( 'id' => 'sign', 'label' => 'Signature', 'type' => 'esign' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Drop files', 'property' => 'icon_color', 'value' => '#ff4b93' ),
				array( 'op' => 'set_field_property', 'target' => 'Drop files', 'property' => 'button_color', 'value' => 'blue' ),
				array( 'op' => 'set_field_property', 'target' => 'Signature', 'property' => 'button_text', 'value' => 'Clear' ),
				// The plain upload field has neither colour control, and the drop
				// zone's icon is stored without a picker to change it.
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'button_color', 'value' => 'blue' ),
				array( 'op' => 'set_field_property', 'target' => 'Drop files', 'property' => 'icon', 'value' => 'bi-cloud-arrow-up-fill' ),
			),
			$fields
		);

		$this->assertCount( 3, $result['ops'] );
		$this->assertSame( '#ff4b93', $result['ops'][0]['value'] );
		$this->assertSame( '#0d6efd', $result['ops'][1]['value'] );
		$this->assertSame( 'Clear', $result['ops'][2]['value'] );
		$this->assertSame( 'property_not_available_for_type:button_color:file', $result['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:icon:dadfile', $result['dropped'][1]['reason'] );

		// A field's button colour is not the form's submit button colour.
		$this->assertSame( 'button_color', EfbFormModel::field_property( 'button_color' )['storage_key'] );
		$this->assertSame( 'style_btn_color', EfbFormModel::field_property( 'button_color' )['style_path'] );
		$this->assertSame( 'button_color', EfbFormModel::form_property( 'button_color' )['storage_path'] );
	}

	public function test_file_category_matches_the_formats_efb_offers(): void {
		$fields = array(
			array( 'id' => 'upload', 'label' => 'File upload', 'type' => 'file' ),
			array( 'id' => 'dnd', 'label' => 'Drop files', 'type' => 'dadfile' ),
		);

		$result = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'file_category', 'value' => 'zip' ),
				array( 'op' => 'set_field_property', 'target' => 'Drop files', 'property' => 'file_category', 'value' => 'customize' ),
				// Never in EFB's select, and customize is drag-and-drop only.
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'file_category', 'value' => 'audio' ),
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'file_category', 'value' => 'customize' ),
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'custom_file_types', 'value' => 'jpg, png' ),
				array( 'op' => 'set_field_property', 'target' => 'File upload', 'property' => 'max_file_size_mb', 'value' => 301 ),
			),
			$fields
		);

		$this->assertCount( 2, $result['ops'] );
		$this->assertSame( 'zip', $result['ops'][0]['value'] );
		$this->assertSame( 'customize', $result['ops'][1]['value'] );
		$this->assertSame( 'invalid_property_value:file_category', $result['dropped'][0]['reason'] );
		$this->assertSame( 'invalid_property_value:file_category', $result['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:custom_file_types:file', $result['dropped'][2]['reason'] );
		$this->assertSame( 'invalid_property_value:max_file_size_mb', $result['dropped'][3]['reason'] );

		// EFB stores the category in both `file` and `value`; the browser contract
		// has to carry that or the two keys drift apart.
		$client = EfbFormModel::client_field_properties();
		$this->assertSame( 'file', $client['file_category']['storage_key'] );
		$this->assertSame( array( 'value' ), $client['file_category']['mirror_keys'] );
		$this->assertNull( $client['label_size']['mirror_keys'] );
	}

	public function test_batch_truncation_is_reported_not_silent(): void {
		$fields = array( array( 'id' => 'when', 'label' => 'Appointment', 'type' => 'date' ) );
		$ops    = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$ops[] = array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'label', 'value' => 'Date ' . $i );
		}

		$result = EfbCustomizeSkill::validate_ops_verbose( $ops, $fields );

		$this->assertCount( 20, $result['ops'] );
		$this->assertSame( 'batch', $result['dropped'][0]['op'] );
		$this->assertSame( 'operation_limit_reached:20:5_skipped', $result['dropped'][0]['reason'] );

		// A batch that fits must not claim anything was skipped.
		$exact = EfbCustomizeSkill::validate_ops_verbose( array_slice( $ops, 0, 20 ), $fields );
		$this->assertCount( 20, $exact['ops'] );
		$this->assertSame( array(), $exact['dropped'] );
	}

	public function test_width_covers_every_percentage_efb_offers(): void {
		$fields = array( array( 'id' => 'when', 'label' => 'Appointment', 'type' => 'date' ) );

		foreach ( array( '8', '17', '25', '33', '42', '50', '58', '67', '75', '83', '92', '100' ) as $percent ) {
			$result = EfbCustomizeSkill::validate_ops_verbose(
				array( array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'width', 'value' => $percent ) ),
				$fields
			);
			$this->assertCount( 1, $result['ops'], sprintf( 'width %s%% should be expressible', $percent ) );
		}

		// Keywords keep working alongside the raw percentages.
		$keywords = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'width', 'value' => 'half' ),
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'width', 'value' => 'three_quarters' ),
			),
			$fields
		);
		$this->assertCount( 2, $keywords['ops'] );
	}

	public function test_date_bounds_are_dates_not_bare_numbers(): void {
		$fields = array(
			array( 'id' => 'when', 'label' => 'Appointment', 'type' => 'date' ),
			array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		);

		// EFB resolves the literal 1 to today and feeds anything else to strtotime.
		$accepted = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'minimum', 'value' => '1' ),
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'maximum', 'value' => '2026-12-31' ),
				array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'maximum', 'value' => '80' ),
			),
			$fields
		);
		$this->assertCount( 3, $accepted['ops'] );
		$this->assertSame( '1', $accepted['ops'][0]['value'] );
		$this->assertSame( '2026-12-31', $accepted['ops'][1]['value'] );
		$this->assertSame( '80', $accepted['ops'][2]['value'] );

		// A bare number would bound nothing at submit time; a date is not a length.
		$refused = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'minimum', 'value' => 18 ),
				array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'minimum', 'value' => '2026-08-10' ),
				array( 'op' => 'set_field_property', 'target' => 'Appointment', 'property' => 'placeholder', 'value' => 'dd/mm/yyyy' ),
			),
			$fields
		);
		$this->assertSame( array(), $refused['ops'] );
		$this->assertSame( 'invalid_property_value:minimum', $refused['dropped'][0]['reason'] );
		$this->assertSame( 'invalid_property_value:minimum', $refused['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_available_for_type:placeholder:date', $refused['dropped'][2]['reason'] );
	}

	public function test_step_accepts_its_panel_controls_and_refuses_the_rest(): void {
		$fields = array(
			array( 'id' => '2', 'label' => 'Experience', 'type' => 'step' ),
			array( 'id' => 'name', 'label' => 'Full name', 'type' => 'text' ),
		);

		// Label, description, icon and the three colours are the whole step panel.
		$accepted = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'icon', 'value' => 'bi-person-fill' ),
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'help', 'value' => 'Tell us about your work' ),
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'icon_color', 'value' => 'red' ),
			),
			$fields
		);
		$this->assertCount( 3, $accepted['ops'] );
		$this->assertSame( 'bi-person-fill', $accepted['ops'][0]['value'] );
		$this->assertSame( '#ff455f', $accepted['ops'][2]['value'] );

		// A step has no width, corner, CSS-class or required control, so writing
		// those keys would look accepted while changing nothing.
		$refused = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'width', 'value' => 'half' ),
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'corner', 'value' => 'rounded-3' ),
				array( 'op' => 'set_field_property', 'target' => 'Experience', 'property' => 'step_visible', 'value' => false ),
			),
			$fields
		);
		$this->assertSame( array(), $refused['ops'] );
		$this->assertSame( 'property_not_available_for_type:width:step', $refused['dropped'][0]['reason'] );
		$this->assertSame( 'property_not_available_for_type:corner:step', $refused['dropped'][1]['reason'] );
		$this->assertSame( 'property_not_writable:step_visible', $refused['dropped'][2]['reason'] );

		// The same properties stay available on an ordinary field.
		$text = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'width', 'value' => 'half' ),
				array( 'op' => 'set_field_property', 'target' => 'Full name', 'property' => 'corner', 'value' => 'rounded-3' ),
			),
			$fields
		);
		$this->assertCount( 2, $text['ops'] );
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
