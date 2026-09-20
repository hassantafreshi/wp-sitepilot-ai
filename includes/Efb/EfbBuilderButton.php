<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Injects a floating "Build with AI" button + modal onto the Easy Form Builder
 * admin pages (page=Emsfb and page=Emsfb_create).
 */
final class EfbBuilderButton {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer', array( $this, 'render' ) );
	}

	private function is_efb_page(): bool {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		return in_array( $page, array( 'Emsfb', 'Emsfb_create' ), true );
	}

	public function enqueue( string $hook ): void {
		unset( $hook );
		if ( ! $this->is_efb_page() || ! Settings::get( 'efb_copilot_enabled', true ) ) {
			return;
		}

		wp_enqueue_style( 'aisaautopilot-efb', AISAAUTOPILOT_URL . 'assets/admin/efb-copilot.css', array(), AISAAUTOPILOT_VERSION );
		wp_enqueue_script( 'aisaautopilot-efb', AISAAUTOPILOT_URL . 'assets/admin/efb-copilot.js', array(), AISAAUTOPILOT_VERSION, true );

		wp_localize_script(
			'aisaautopilot-efb',
			'AISAAutoPilotEFB',
			array(
				'restUrl'         => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'configured'      => ( new ProviderManager() )->is_configured(),
				'settingsUrl'     => admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP ),
				'logsUrl'         => admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ),
				'isRTL'           => is_rtl(),
				'language'        => SiteLanguage::primary( SiteLanguage::site() ),
				'voiceResponses'  => (bool) \AISAAutoPilot\Core\Settings::get( 'voice_responses_enabled', false ),
				// Same option and destination used by Easy Form Builder itself.
				'license'         => EfbLicense::status(),
				// This is the allowlisted, browser-safe subset of the full EFB
				// capability catalog. It keeps the operation executor in lockstep
				// with the server validator without exposing credentials/settings.
				'fieldProperties' => EfbFormModel::client_field_properties(),
				'formProperties'  => EfbFormModel::client_form_properties(),
				'i18n'            => array(
					'editingForm'                    => __( 'Editing the current form', 'ai-site-assistant-autopilot' ),
					'designingForm'                  => __( 'Designing a new form', 'ai-site-assistant-autopilot' ),
					/* translators: %s: selected Easy Form Builder field label. */
					'workingOn'                      => __( 'Working on %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: selected Easy Form Builder field label. */
					'fieldIntro'                     => __( 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', 'ai-site-assistant-autopilot' ),
					'formIntro'                      => __( 'Hi! I can change this form, design a new one, or explain how to do something in Easy Form Builder.', 'ai-site-assistant-autopilot' ),
					'newFormIntro'                   => __( 'Hi! Tell me what form you need and I will design a complete one, or ask me an Easy Form Builder question.', 'ai-site-assistant-autopilot' ),
					'makeRequired'                   => __( 'Make Email required', 'ai-site-assistant-autopilot' ),
					'renameForm'                     => __( 'Rename the form to Contact us', 'ai-site-assistant-autopilot' ),
					'addOptionsHelp'                 => __( 'How do I add options to a dropdown?', 'ai-site-assistant-autopilot' ),
					'buildContact'                   => __( 'Build a contact form', 'ai-site-assistant-autopilot' ),
					'buildBooking'                   => __( 'A booking form for a dental clinic', 'ai-site-assistant-autopilot' ),
					'buildJob'                       => __( 'Job application form', 'ai-site-assistant-autopilot' ),
					'notificationHelp'               => __( 'How do I turn on email notifications?', 'ai-site-assistant-autopilot' ),
					'upgrade'                        => __( 'Upgrade to EFB Pro', 'ai-site-assistant-autopilot' ),
					'pricing'                        => __( 'View pricing', 'ai-site-assistant-autopilot' ),
					'label'                          => __( 'Label', 'ai-site-assistant-autopilot' ),
					'type'                           => __( 'Type', 'ai-site-assistant-autopilot' ),
					'requiredShort'                  => __( 'Req.', 'ai-site-assistant-autopilot' ),
					'widthShort'                     => __( 'W', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of form fields. */
					'fieldsCount'                    => __( '%s fields', 'ai-site-assistant-autopilot' ),
					'approveCreate'                  => __( 'Approve & create', 'ai-site-assistant-autopilot' ),
					'discard'                        => __( 'Discard', 'ai-site-assistant-autopilot' ),
					'creating'                       => __( 'Creating…', 'ai-site-assistant-autopilot' ),
					'createdOpening'                 => __( 'Created! Opening the editor…', 'ai-site-assistant-autopilot' ),
					'networkError'                   => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
					'voiceUnsupported'               => __( 'Voice recording is not supported by this browser. Please type your message.', 'ai-site-assistant-autopilot' ),
					'noAudio'                        => __( 'No audio was captured.', 'ai-site-assistant-autopilot' ),
					'audioLarge'                     => __( 'That recording is too large. Please record a shorter message.', 'ai-site-assistant-autopilot' ),
					'transcribing'                   => __( 'Transcribing…', 'ai-site-assistant-autopilot' ),
					'transcriptReady'                => __( 'Transcript ready. Review it, then send.', 'ai-site-assistant-autopilot' ),
					'transcriptionFail'              => __( 'Voice transcription failed. Please try again.', 'ai-site-assistant-autopilot' ),
					'recording'                      => __( 'Recording — words appear in the message box. Stops after 45 seconds.', 'ai-site-assistant-autopilot' ),
					'micDenied'                      => __( 'Microphone permission was denied. You can type your message instead.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder steps style name, such as Pills. */
					'stepsStyleSet'                  => __( 'Steps style set to %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder progress bar style name, such as Ring. */
					'progressStyleSet'               => __( 'Progress bar style set to %s', 'ai-site-assistant-autopilot' ),
					'stepsRowHidden'                 => __( 'Steps row hidden', 'ai-site-assistant-autopilot' ),
					'stepsRowShown'                  => __( 'Steps row shown', 'ai-site-assistant-autopilot' ),
					'progressBarHidden'              => __( 'Progress bar hidden', 'ai-site-assistant-autopilot' ),
					'progressBarShown'               => __( 'Progress bar shown', 'ai-site-assistant-autopilot' ),
					// Results, notes and fallbacks the Copilot builds in the browser (T03).
					'invalidResponse'                => __( 'Invalid server response.', 'ai-site-assistant-autopilot' ),
					'genericError'                   => __( 'Sorry, something went wrong.', 'ai-site-assistant-autopilot' ),
					'requestFailed'                  => __( 'The request could not be completed.', 'ai-site-assistant-autopilot' ),
					'createFailed'                   => __( 'The form could not be created. Please try again.', 'ai-site-assistant-autopilot' ),
					'openFormFirst'                  => __( 'Open a form in the editor first (create one here, or open Easy Form Builder → Forms and edit a form), then ask me to change it.', 'ai-site-assistant-autopilot' ),
					'done'                           => __( 'Done.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: new form name. */
					'formRenamed'                    => __( 'Renamed the form to “%s”', 'ai-site-assistant-autopilot' ),
					'confirmationSet'                => __( 'Updated the confirmation message', 'ai-site-assistant-autopilot' ),
					/* translators: 1: current field label or ID, 2: new field label. */
					'fieldRenamed'                   => __( 'Renamed “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label or ID. */
					'fieldRequired'                  => __( 'Made “%s” required', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label or ID. */
					'fieldOptional'                  => __( 'Made “%s” optional', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label or ID. */
					'placeholderSet'                 => __( 'Set the placeholder of “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: 1: field label or ID, 2: width name, such as half width. */
					'widthSet'                       => __( 'Set the width of “%1$s” to %2$s', 'ai-site-assistant-autopilot' ),
					'widthFull'                      => __( 'full width', 'ai-site-assistant-autopilot' ),
					'widthHalf'                      => __( 'half width', 'ai-site-assistant-autopilot' ),
					'widthThird'                     => __( 'one third', 'ai-site-assistant-autopilot' ),
					'widthQuarter'                   => __( 'one quarter', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label or ID. */
					'helpTextSet'                    => __( 'Set the help text of “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: 1: Easy Form Builder setting key, such as button color, 2: field label or ID. */
					'fieldSettingSet'                => __( 'Updated %1$s for “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder form setting key, such as button color. */
					'formPropertyUpdated'            => __( 'Updated the form setting “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: label of the added field. */
					'fieldAdded'                     => __( 'Added “%s”', 'ai-site-assistant-autopilot' ),
					'newFieldLabel'                  => __( 'New field', 'ai-site-assistant-autopilot' ),
					/* translators: %s: step title. */
					'stepAdded'                      => __( 'Added the step “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: step number. */
					'stepDefaultName'                => __( 'Step %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label or ID. */
					'fieldRemoved'                   => __( 'Removed “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: 1: option text, 2: field label or ID. */
					'optionAdded'                    => __( 'Added the option “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: 1: option text, 2: field label or ID. */
					'optionRemoved'                  => __( 'Removed the option “%1$s” from “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: reason the change was not applied. */
					'noChangeApplied'                => __( 'No change was applied: %s.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: reason the change was not applied. */
					'notApplied'                     => __( 'Not applied: %s', 'ai-site-assistant-autopilot' ),
					'appliedSaveNote'                => __( 'Applied to the canvas. Review it, then click Easy Form Builder’s green Save button to keep the changes.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: trace ID to look up in the Activity Log. */
					'canvasRefreshFail'              => __( 'The Easy Form Builder canvas could not redraw, so the editor reloads now. Check the form before you save it; trace %s is in AI Site Assistant AutoPilot → Activity Log.', 'ai-site-assistant-autopilot' ),
					'reasonInvalidOperation'         => __( 'the operation was malformed', 'ai-site-assistant-autopilot' ),
					'reasonFieldNotFound'            => __( 'the field was not found in this form', 'ai-site-assistant-autopilot' ),
					'reasonUnsupportedFieldProperty' => __( 'that field setting cannot be changed here', 'ai-site-assistant-autopilot' ),
					'reasonPropertyNotAvailable'     => __( 'that setting is not available for this field type', 'ai-site-assistant-autopilot' ),
					'reasonInvalidColour'            => __( 'the colour or setting value is not valid', 'ai-site-assistant-autopilot' ),
					'reasonFormPropertyNotAvailable' => __( 'that form setting is not available', 'ai-site-assistant-autopilot' ),
					'reasonInvalidFormPropertyValue' => __( 'Easy Form Builder does not accept that value', 'ai-site-assistant-autopilot' ),
					'reasonStepTemplateMissing'      => __( 'the step template was missing from the reply', 'ai-site-assistant-autopilot' ),
					'reasonFieldTemplateMissing'     => __( 'the field template was missing from the reply', 'ai-site-assistant-autopilot' ),
					'reasonInvalidFieldTemplate'     => __( 'the new field was invalid or its ID already exists', 'ai-site-assistant-autopilot' ),
					'reasonNotChoiceField'           => __( 'options can only be added to a choice field', 'ai-site-assistant-autopilot' ),
					'reasonOptionNotFound'           => __( 'the option was not found', 'ai-site-assistant-autopilot' ),
					'reasonUnsupportedOperation'     => __( 'this change is not supported', 'ai-site-assistant-autopilot' ),
					'reasonNoOperation'              => __( 'the reply contained no change to apply', 'ai-site-assistant-autopilot' ),
					'reasonException'                => __( 'an unexpected error stopped this change', 'ai-site-assistant-autopilot' ),
					'reasonRender'                   => __( 'the Easy Form Builder canvas could not redraw', 'ai-site-assistant-autopilot' ),
					'reasonUnknown'                  => __( 'this change could not be applied', 'ai-site-assistant-autopilot' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! $this->is_efb_page() || ! Settings::get( 'efb_copilot_enabled', true ) ) {
			return;
		}
		?>
		<button type="button" id="aisaautopilot-efb-launch" class="aisaautopilot-efb-fab" title="<?php esc_attr_e( 'Chat with AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ); ?>" aria-controls="aisaautopilot-efb-chat" aria-expanded="false">
			<span class="aisaautopilot-efb-brandmark" aria-hidden="true"><span class="dashicons dashicons-shield"></span></span>
			<span class="aisaautopilot-efb-fab__label"><?php esc_html_e( 'Ask AI Site Assistant', 'ai-site-assistant-autopilot' ); ?></span>
		</button>

		<div id="aisaautopilot-efb-chat" class="aisaautopilot-efb-chat" hidden aria-hidden="true" role="dialog"
			aria-label="<?php esc_attr_e( 'AI Site Assistant AutoPilot chat', 'ai-site-assistant-autopilot' ); ?>">
			<div class="aisaautopilot-efb-chat__head">
				<div class="aisaautopilot-efb-chat__title">
					<span class="aisaautopilot-efb-brandmark" aria-hidden="true"><span class="dashicons dashicons-shield"></span></span>
					<span class="aisaautopilot-efb-chat__title-copy"><strong><?php esc_html_e( 'AI Site Assistant', 'ai-site-assistant-autopilot' ); ?></strong><small id="aisaautopilot-efb-context"><?php esc_html_e( 'Editing the current form', 'ai-site-assistant-autopilot' ); ?></small></span>
				</div>
				<div class="aisaautopilot-efb-chat__head-actions">
					<button type="button" id="aisaautopilot-efb-live-voice" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'Start voice input', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Start voice input', 'ai-site-assistant-autopilot' ); ?>" aria-pressed="false"><span class="dashicons dashicons-microphone"></span></button>
					<button type="button" id="aisaautopilot-efb-reset" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-plus-alt2"></span></button>
					<button type="button" id="aisaautopilot-efb-min" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>

			<div id="aisaautopilot-efb-selection" class="aisaautopilot-efb-selection" hidden>
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span id="aisaautopilot-efb-selection-label" class="aisaautopilot-efb-selection__label"></span>
				<button type="button" id="aisaautopilot-efb-selection-clear"><?php esc_html_e( 'Clear', 'ai-site-assistant-autopilot' ); ?></button>
			</div>

			<div class="aisaautopilot-efb-chat__messages" id="aisaautopilot-efb-messages" aria-live="polite">
				<!-- messages rendered here (newest at the bottom) -->
			</div>

			<form class="aisaautopilot-efb-chat__composer" id="aisaautopilot-efb-form">
				<textarea id="aisaautopilot-efb-input" class="aisaautopilot-efb-chat__input" rows="1" maxlength="2000"
					placeholder="<?php esc_attr_e( 'Ask about this form…', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Message AI Site Assistant', 'ai-site-assistant-autopilot' ); ?>"></textarea>
				<button type="button" id="aisaautopilot-efb-voice" class="aisaautopilot-efb-chat__voice" aria-label="<?php esc_attr_e( 'Speak a message', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-microphone"></span></button>
				<button type="submit" id="aisaautopilot-efb-send" class="aisaautopilot-efb-chat__send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>">
					<span class="dashicons dashicons-arrow-up-alt"></span>
				</button>
			</form>
			<div id="aisaautopilot-efb-voice-status" class="aisaautopilot-efb-voice-status" aria-live="polite"></div>
		</div>
		<?php
	}
}
