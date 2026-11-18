<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Injects the floating "Ask AI Site Assistant" button and its chat panel onto
 * the Easy Form Builder admin pages (page=Emsfb and page=Emsfb_create).
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

		wp_enqueue_style( 'aisaautopilot-ai-dock', AISAAUTOPILOT_URL . 'assets/admin/ai-dock.css', array(), AISAAUTOPILOT_VERSION );
		wp_enqueue_style( 'aisaautopilot-efb', AISAAUTOPILOT_URL . 'assets/admin/efb-copilot.css', array( 'aisaautopilot-ai-dock' ), AISAAUTOPILOT_VERSION );
		wp_enqueue_script( 'aisaautopilot-ai-dock', AISAAUTOPILOT_URL . 'assets/admin/ai-dock.js', array(), AISAAUTOPILOT_VERSION, true );
		wp_enqueue_script( 'aisaautopilot-efb', AISAAUTOPILOT_URL . 'assets/admin/efb-copilot.js', array( 'aisaautopilot-ai-dock' ), AISAAUTOPILOT_VERSION, true );

		wp_localize_script(
			'aisaautopilot-efb',
			'AISAAutoPilotEFB',
			array(
				'restUrl'         => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'ajaxUrl'         => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
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
					'formPrivate'                    => __( 'The form is now private: only logged-in users can see and send it', 'ai-site-assistant-autopilot' ),
					'formPublic'                     => __( 'The form is public again', 'ai-site-assistant-autopilot' ),
					/* translators: %s: the new subject. */
					'emailSubjectSet'                => __( 'Email subject set to “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: a web address. */
					'redirectSet'                    => __( 'After sending, visitors go to %s', 'ai-site-assistant-autopilot' ),
					'thankYouMessageMode'            => __( 'After sending, visitors see the thank-you message', 'ai-site-assistant-autopilot' ),
					/* translators: %s: form type name. */
					'formTypeSet'                    => __( 'Form type set to %s', 'ai-site-assistant-autopilot' ),
					'formTypeForm'                   => __( 'form', 'ai-site-assistant-autopilot' ),
					'formTypeSurvey'                 => __( 'survey', 'ai-site-assistant-autopilot' ),
					'formTypeLogin'                  => __( 'sign-in form', 'ai-site-assistant-autopilot' ),
					'formTypePayment'                => __( 'payment form', 'ai-site-assistant-autopilot' ),
					'smsOn'                          => __( 'SMS notifications turned on', 'ai-site-assistant-autopilot' ),
					'smsOff'                         => __( 'SMS notifications turned off', 'ai-site-assistant-autopilot' ),
					/* translators: %s: phone numbers. */
					'smsNumbersSet'                  => __( 'SMS goes to %s', 'ai-site-assistant-autopilot' ),
					'smsTextSet'                     => __( 'SMS wording updated', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'smsRespondentOn'                => __( 'A confirmation SMS goes to the number typed in “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'smsRespondentOff'               => __( 'No SMS goes to the number typed in “%s” any more', 'ai-site-assistant-autopilot' ),
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
					/* translators: 1: old option text, 2: new option text. */
					'optionRenamed'                  => __( 'Renamed the option “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: plain-language description of the rule. */
					'logicRuleAdded'                 => __( 'Added the rule: %s', 'ai-site-assistant-autopilot' ),
					'logicReviewNote'                => __( 'Open Conditional Logic to review the rule and test it in Preview before you save.', 'ai-site-assistant-autopilot' ),
					'undoChange'                     => __( 'Undo', 'ai-site-assistant-autopilot' ),
					'undone'                         => __( 'Undone. The canvas is back to how it was before this change.', 'ai-site-assistant-autopilot' ),
					'applySuggestion'                => __( 'Apply', 'ai-site-assistant-autopilot' ),
					'suggestionApplied'              => __( 'Applied the suggestion.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: page title. */
					'previewStep'                    => __( 'New page: %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: submit button text. */
					'previewSubmit'                  => __( 'Button: %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: email address. */
					'previewNotifyAdmin'             => __( 'Each submission is emailed to %s', 'ai-site-assistant-autopilot' ),
					'previewNotifyRespondent'        => __( 'The person who fills it in gets a confirmation email', 'ai-site-assistant-autopilot' ),
					/* translators: %s: reason the change was not applied. */
					'noChangeApplied'                => __( 'No change was applied: %s.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: reason the change was not applied. */
					'notApplied'                     => __( 'Not applied: %s', 'ai-site-assistant-autopilot' ),
					'appliedSaveNote'                => __( 'Click Save at the top of Easy Form Builder to keep the changes.', 'ai-site-assistant-autopilot' ),
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
					'reasonLogicAddonInactive'       => __( 'the Conditional Logic add-on is not active on this form', 'ai-site-assistant-autopilot' ),
					'reasonAnchorNotFound'           => __( 'the field to place it next to is not on this form', 'ai-site-assistant-autopilot' ),
					/* translators: 1: moved field, 2: field it now follows. */
					'fieldMovedAfter'                => __( 'Moved “%1$s” after “%2$s”', 'ai-site-assistant-autopilot' ),
					/* translators: 1: moved field, 2: field it now precedes. */
					'fieldMovedBefore'               => __( 'Moved “%1$s” before “%2$s”', 'ai-site-assistant-autopilot' ),
					'reasonPaymentConflict'          => __( 'this form already takes payment through another gateway', 'ai-site-assistant-autopilot' ),
					'reasonPaymentTemplate'          => __( 'the payment fields could not be prepared', 'ai-site-assistant-autopilot' ),
					'reasonUnsupportedOperation'     => __( 'this change is not supported', 'ai-site-assistant-autopilot' ),
					'reasonFormHasPayment'           => __( 'this form takes a payment, so it stays a payment form; remove the payment first', 'ai-site-assistant-autopilot' ),
					'reasonNoOperation'              => __( 'the reply contained no change to apply', 'ai-site-assistant-autopilot' ),
					'reasonException'                => __( 'an unexpected error stopped this change', 'ai-site-assistant-autopilot' ),
					'reasonRender'                   => __( 'the Easy Form Builder canvas could not redraw', 'ai-site-assistant-autopilot' ),
					/* translators: 1: field label, 2: add-on name, 3: plan name. */
					'needsAddonPlan'                 => __( '%1$s: needs the %2$s add-on (%3$s)', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'needsBuilder'                   => __( '%s: add it in the Easy Form Builder editor', 'ai-site-assistant-autopilot' ),
					/* translators: 1: field label, 2: plan name. */
					'needsPlan'                      => __( '%1$s: needs Easy Form Builder %2$s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field type name. */
					'usedInstead'                    => __( 'in the form as: %s', 'ai-site-assistant-autopilot' ),
					'leftOut'                        => __( 'left out', 'ai-site-assistant-autopilot' ),
					'planPro'                        => __( 'Pro', 'ai-site-assistant-autopilot' ),
					'planFreePlus'                   => __( 'Free Plus', 'ai-site-assistant-autopilot' ),
					'planFree'                       => __( 'Free', 'ai-site-assistant-autopilot' ),
					'undoCreate'                     => __( 'Undo: delete this new form', 'ai-site-assistant-autopilot' ),
					/* translators: %s: form title. */
					'createdTitle'                   => __( 'Created “%s”', 'ai-site-assistant-autopilot' ),
					'undoing'                        => __( 'Deleting…', 'ai-site-assistant-autopilot' ),
					'reasonEmailFieldMissing'        => __( 'the form has no Email field to send the confirmation to', 'ai-site-assistant-autopilot' ),
					'reasonFieldNeedsAddon'          => __( 'that field needs an Easy Form Builder add-on that is not active on this page', 'ai-site-assistant-autopilot' ),
					'reasonFieldNeedsPlan'           => __( 'that field needs a higher Easy Form Builder plan', 'ai-site-assistant-autopilot' ),
					'reasonUnknown'                  => __( 'this change could not be applied', 'ai-site-assistant-autopilot' ),
					// The dock, its launcher and its cards (owner-approved redesign, 2026-10-28).
					'tipTitle'                       => __( 'Ask AI to change this form', 'ai-site-assistant-autopilot' ),
					'tipText'                        => __( 'Add fields, show or hide them with logic, or get a review. Nothing is saved until you click Save.', 'ai-site-assistant-autopilot' ),
					'tipNewTitle'                    => __( 'Ask AI to build a form', 'ai-site-assistant-autopilot' ),
					'tipNewText'                     => __( 'Describe the form you need. I draft it for you to check, and nothing is created until you click Create.', 'ai-site-assistant-autopilot' ),
					'tipShortcut'                    => __( 'opens it from anywhere', 'ai-site-assistant-autopilot' ),
					'nudgeBadge'                     => __( 'NEW', 'ai-site-assistant-autopilot' ),
					'nudgeTitle'                     => __( 'Describe a change, I do the clicking', 'ai-site-assistant-autopilot' ),
					'nudgeText'                      => __( '“Add a phone field after Email and make it required.”', 'ai-site-assistant-autopilot' ),
					'nudgeTry'                       => __( 'Try it', 'ai-site-assistant-autopilot' ),
					'nudgeLater'                     => __( 'Not now', 'ai-site-assistant-autopilot' ),
					'offlineTitle'                   => __( 'AI is not connected yet', 'ai-site-assistant-autopilot' ),
					'offlineText'                    => __( 'Connect a service once (about 2 minutes) and the assistant can change this form for you.', 'ai-site-assistant-autopilot' ),
					'unsavedOne'                     => __( '1 AI change is not saved yet', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of changes. */
					'unsavedMany'                    => __( '%s AI changes are not saved yet', 'ai-site-assistant-autopilot' ),
					'unsavedText'                    => __( 'Click Save at the top of Easy Form Builder to keep them, or open the assistant to undo.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'askAbout'                       => __( 'Ask AI about “%s”', 'ai-site-assistant-autopilot' ),
					/* translators: %s: form name. */
					'ctxEditing'                     => __( 'Editing “%s”', 'ai-site-assistant-autopilot' ),
					'oneField'                       => __( '1 field', 'ai-site-assistant-autopilot' ),
					'ctxNewForm'                     => __( 'New form', 'ai-site-assistant-autopilot' ),
					'ctxOffline'                     => __( 'Not connected yet', 'ai-site-assistant-autopilot' ),
					'starterFields'                  => __( 'Add or change fields', 'ai-site-assistant-autopilot' ),
					'starterFieldsExample'           => __( 'Add a phone field after Email', 'ai-site-assistant-autopilot' ),
					/* translators: %s: an example request, shown in quotation marks. */
					'quoted'                         => __( '“%s”', 'ai-site-assistant-autopilot' ),
					'starterLogic'                   => __( 'Show or hide fields', 'ai-site-assistant-autopilot' ),
					'starterLogicExample'            => __( 'Show Company only for business customers', 'ai-site-assistant-autopilot' ),
					'starterReview'                  => __( 'Review this form', 'ai-site-assistant-autopilot' ),
					'starterReviewHint'              => __( 'Missing consent, spam protection, too many questions', 'ai-site-assistant-autopilot' ),
					'starterHelp'                    => __( 'How do I…?', 'ai-site-assistant-autopilot' ),
					'starterHelpHint'                => __( 'Ask anything about Easy Form Builder', 'ai-site-assistant-autopilot' ),
					'starterHelpPrefill'             => __( 'How do I ', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of fields. */
					'stepReadForm'                   => __( 'Read your form (%s fields)', 'ai-site-assistant-autopilot' ),
					'stepReadRequest'                => __( 'Read your request', 'ai-site-assistant-autopilot' ),
					'stepWorking'                    => __( 'Working out the answer…', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder plan, such as Free Plus. */
					'stepCheckPlan'                  => __( 'Check it against your Easy Form Builder plan (%s)', 'ai-site-assistant-autopilot' ),
					'changesOne'                     => __( '1 change on the form', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of changes. */
					'changesMany'                    => __( '%s changes on the form', 'ai-site-assistant-autopilot' ),
					'notSavedYet'                    => __( 'Not saved yet', 'ai-site-assistant-autopilot' ),
					'showOnForm'                     => __( 'Show on form', 'ai-site-assistant-autopilot' ),
					'flashNew'                       => __( 'AI · new', 'ai-site-assistant-autopilot' ),
					'flashChanged'                   => __( 'AI · changed', 'ai-site-assistant-autopilot' ),
					'nothingChanged'                 => __( 'Nothing changed', 'ai-site-assistant-autopilot' ),
					'formUnchanged'                  => __( 'Your form is exactly as it was.', 'ai-site-assistant-autopilot' ),
					'newRule'                        => __( 'New conditional rule', 'ai-site-assistant-autopilot' ),
					'ruleIf'                         => __( 'IF', 'ai-site-assistant-autopilot' ),
					'ruleThen'                       => __( 'THEN', 'ai-site-assistant-autopilot' ),
					'ruleAll'                        => __( 'and', 'ai-site-assistant-autopilot' ),
					'ruleAny'                        => __( 'or', 'ai-site-assistant-autopilot' ),
					'testPreview'                    => __( 'Test in Preview', 'ai-site-assistant-autopilot' ),
					'suggestionsOne'                 => __( '1 suggestion', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of suggestions. */
					'suggestionsMany'                => __( '%s suggestions', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of fields. */
					'fieldsChecked'                  => __( '%s fields checked', 'ai-site-assistant-autopilot' ),
					'howTo'                          => __( 'How?', 'ai-site-assistant-autopilot' ),
					/* translators: %s: a review finding. */
					'howToPrefill'                   => __( 'How do I fix this: %s', 'ai-site-assistant-autopilot' ),
					'onePage'                        => __( '1 page', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of pages. */
					'pagesCount'                     => __( '%s pages', 'ai-site-assistant-autopilot' ),
					'createThisForm'                 => __( 'Create this form', 'ai-site-assistant-autopilot' ),
					'phLive'                         => __( 'Describe a change, or click a field on the form…', 'ai-site-assistant-autopilot' ),
					'phNew'                          => __( 'Describe the form you need…', 'ai-site-assistant-autopilot' ),
					'phNext'                         => __( 'Ask for another change…', 'ai-site-assistant-autopilot' ),
					'phBeforeCreate'                 => __( 'Ask for a change before creating it…', 'ai-site-assistant-autopilot' ),
					'phOffline'                      => __( 'Connect an AI service to start…', 'ai-site-assistant-autopilot' ),
					'phWorking'                      => __( 'Working on it…', 'ai-site-assistant-autopilot' ),
					'footLive'                       => __( 'AI suggests, you decide. Nothing is saved until you click Save.', 'ai-site-assistant-autopilot' ),
					'footNew'                        => __( 'AI suggests, you decide. Nothing is created until you click Create.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'nextRequired'                   => __( 'Make “%s” required', 'ai-site-assistant-autopilot' ),
					/* translators: %s: field label. */
					'nextHelp'                       => __( 'Add help text to “%s”', 'ai-site-assistant-autopilot' ),
					'nextReview'                     => __( 'Review this form', 'ai-site-assistant-autopilot' ),
					'nextPhoneOptional'              => __( 'Make the phone number optional', 'ai-site-assistant-autopilot' ),
					'nextSteps'                      => __( 'Split it into 2 steps', 'ai-site-assistant-autopilot' ),
					'setupTitle'                     => __( 'Connect an AI service to start', 'ai-site-assistant-autopilot' ),
					'setupIntro'                     => __( 'I can change this form for you: add fields, show or hide them with logic, review it, or answer Easy Form Builder questions. First I need an AI service.', 'ai-site-assistant-autopilot' ),
					'setupWhy'                       => __( 'The assistant needs an AI service such as OpenAI, Claude or Gemini. You use your own API key; it is stored encrypted on this site and used only for your requests.', 'ai-site-assistant-autopilot' ),
					'setupStep1'                     => __( 'Pick a service', 'ai-site-assistant-autopilot' ),
					'setupStep2'                     => __( 'Paste its API key', 'ai-site-assistant-autopilot' ),
					'setupStep3'                     => __( 'Test the connection', 'ai-site-assistant-autopilot' ),
					'setupTime'                      => __( 'about 2 minutes', 'ai-site-assistant-autopilot' ),
					'setupCta'                       => __( 'Set up AI', 'ai-site-assistant-autopilot' ),
					'setupHint'                      => __( 'Setup opens in a new tab, so nothing on this form is lost. Come back here and I will be ready.', 'ai-site-assistant-autopilot' ),
					/* translators: 1: AI service name, 2: model name. */
					'connectedTo'                    => __( 'Connected to %1$s · %2$s', 'ai-site-assistant-autopilot' ),
					'connectedText'                  => __( 'You are all set. Tell me what to change on this form.', 'ai-site-assistant-autopilot' ),
					'couldNotDo'                     => __( 'I could not do that', 'ai-site-assistant-autopilot' ),
					'typeText'                       => __( 'Text', 'ai-site-assistant-autopilot' ),
					'typeEmail'                      => __( 'Email', 'ai-site-assistant-autopilot' ),
					'typePhone'                      => __( 'Phone', 'ai-site-assistant-autopilot' ),
					'typeLongText'                   => __( 'Long text', 'ai-site-assistant-autopilot' ),
					'typeNumber'                     => __( 'Number', 'ai-site-assistant-autopilot' ),
					'typeDropdown'                   => __( 'Dropdown', 'ai-site-assistant-autopilot' ),
					'typeChoice'                     => __( 'Choice', 'ai-site-assistant-autopilot' ),
					'typeCheckboxes'                 => __( 'Checkboxes', 'ai-site-assistant-autopilot' ),
					'typeDate'                       => __( 'Date', 'ai-site-assistant-autopilot' ),
					'typeFile'                       => __( 'File upload', 'ai-site-assistant-autopilot' ),
					'typeWebsite'                    => __( 'Website', 'ai-site-assistant-autopilot' ),
					'typeRating'                     => __( 'Rating', 'ai-site-assistant-autopilot' ),
					'typeConsent'                    => __( 'Consent', 'ai-site-assistant-autopilot' ),
					'typePayChoice'                  => __( 'Priced choice', 'ai-site-assistant-autopilot' ),
					'typePayAmount'                  => __( 'Amount', 'ai-site-assistant-autopilot' ),
					'typePayTotal'                   => __( 'Total', 'ai-site-assistant-autopilot' ),
					'typeMap'                        => __( 'Location picker', 'ai-site-assistant-autopilot' ),
					'typeCountry'                    => __( 'Country', 'ai-site-assistant-autopilot' ),
					'typeState'                      => __( 'State / Province', 'ai-site-assistant-autopilot' ),
					'typeCity'                       => __( 'City', 'ai-site-assistant-autopilot' ),
					'typeAudio'                      => __( 'Sound recorder', 'ai-site-assistant-autopilot' ),
					'typeVideo'                      => __( 'Video recorder', 'ai-site-assistant-autopilot' ),
					'typeScreen'                     => __( 'Screen recorder', 'ai-site-assistant-autopilot' ),
					'typeLink'                       => __( 'Link', 'ai-site-assistant-autopilot' ),
					'typeHtml'                       => __( 'HTML block', 'ai-site-assistant-autopilot' ),
					'typeHeading'                    => __( 'Heading', 'ai-site-assistant-autopilot' ),
					'typeSignature'                  => __( 'Signature', 'ai-site-assistant-autopilot' ),
					'typeColor'                      => __( 'Colour picker', 'ai-site-assistant-autopilot' ),
					'typeMatrix'                     => __( 'Matrix', 'ai-site-assistant-autopilot' ),
					'typePassword'                   => __( 'Password', 'ai-site-assistant-autopilot' ),
					'typePayment'                    => __( 'Payment', 'ai-site-assistant-autopilot' ),
					'previewLogic'                   => __( 'Conditional logic', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder add-on name. */
					'addonNeedsPlan'                 => __( '%s needs a higher Easy Form Builder plan', 'ai-site-assistant-autopilot' ),
					'addonPlanFreePlus'              => __( 'This add-on comes with Easy Form Builder Free Plus and Pro.', 'ai-site-assistant-autopilot' ),
					'addonPlanPro'                   => __( 'This add-on comes with Easy Form Builder Pro.', 'ai-site-assistant-autopilot' ),
					'seePlans'                       => __( 'See plans', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder add-on name. */
					'installAddon'                   => __( 'Install %s', 'ai-site-assistant-autopilot' ),
					'notNow'                         => __( 'Not now', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder add-on name. */
					'addonNeeded'                    => __( 'This needs the %s add-on', 'ai-site-assistant-autopilot' ),
					/* translators: %s: Easy Form Builder add-on name. */
					'addonAsk'                       => __( 'May I install the %s add-on from Easy Form Builder now? The page reloads when it is done.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of unsaved changes. */
					'addonSaveFirst'                 => __( 'Save the form in Easy Form Builder first: %s change(s) are not saved yet and a reload would lose them.', 'ai-site-assistant-autopilot' ),
					'addonDeclined'                  => __( 'Nothing was installed.', 'ai-site-assistant-autopilot' ),
					'addonInstalling'                => __( 'Installing…', 'ai-site-assistant-autopilot' ),
					'addonInstalledSave'             => __( 'Installed. Save the form, then reload the page to use it.', 'ai-site-assistant-autopilot' ),
					'addonInstalled'                 => __( 'Installed. Reloading so Easy Form Builder can load it…', 'ai-site-assistant-autopilot' ),
					'addonInstallFailed'             => __( 'Easy Form Builder could not install it. Try again from Easy Form Builder → Add-ons.', 'ai-site-assistant-autopilot' ),
					'handoffResume'                  => __( 'Your request from Form Studio is in the message box. Send it and I will build the full form here.', 'ai-site-assistant-autopilot' ),
					'addonResume'                    => __( 'The add-on is ready. Your request is in the message box: send it again when you are ready.', 'ai-site-assistant-autopilot' ),
					'typeYesNo'                      => __( 'Yes / No', 'ai-site-assistant-autopilot' ),
					'required'                       => __( 'required', 'ai-site-assistant-autopilot' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! $this->is_efb_page() || ! Settings::get( 'efb_copilot_enabled', true ) ) {
			return;
		}
		?>
		<button type="button" id="aisaautopilot-efb-launch" class="aisaautopilot-efb-fab" aria-controls="aisaautopilot-efb-chat" aria-expanded="false" aria-keyshortcuts="Control+/ Meta+/" aria-describedby="aisaautopilot-efb-pop-text">
			<span class="aisaautopilot-efb-fab__ic" aria-hidden="true" data-aisa-icon="spark" data-aisa-size="20" data-aisa-stroke="1.8"></span>
			<span class="aisaautopilot-efb-fab__label"><?php esc_html_e( 'Ask AI', 'ai-site-assistant-autopilot' ); ?></span>
			<kbd class="aisaautopilot-efb-fab__kbd" aria-hidden="true" data-aisa-kbd>Ctrl /</kbd>
			<span class="aisaautopilot-efb-fab__badge" data-aisa-unsaved hidden></span>
			<span class="aisaautopilot-efb-fab__dot" data-aisa-offline hidden></span>
		</button>
		<div id="aisaautopilot-efb-pop" class="aisaautopilot-efb-pop">
			<span class="aisaautopilot-efb-pop__new" data-aisa-pop-badge hidden></span>
			<strong data-aisa-pop-title></strong>
			<p id="aisaautopilot-efb-pop-text" data-aisa-pop-text></p>
			<div class="aisaautopilot-efb-pop__row" data-aisa-pop-row></div>
		</div>

		<aside id="aisaautopilot-efb-chat" class="aisa-ai aisaautopilot-efb-chat is-closed" aria-hidden="true" aria-label="<?php esc_attr_e( 'AI Site Assistant', 'ai-site-assistant-autopilot' ); ?>">
			<div class="aisa-ai-head aisaautopilot-efb-chat__head">
				<span class="aisa-ai-grabber" aria-hidden="true"></span>
				<span class="aisa-ai-logo" aria-hidden="true" data-aisa-icon="spark" data-aisa-size="19" data-aisa-stroke="1.8"></span>
				<div class="aisa-ai-title aisaautopilot-efb-chat__title-copy">
					<strong><?php esc_html_e( 'AI Site Assistant', 'ai-site-assistant-autopilot' ); ?></strong>
					<div class="aisa-ai-ctx"><span class="aisa-ai-ctx__dot" aria-hidden="true"></span><span id="aisaautopilot-efb-context"><?php esc_html_e( 'Editing the current form', 'ai-site-assistant-autopilot' ); ?></span></div>
				</div>
				<button type="button" id="aisaautopilot-efb-reset" class="aisa-ai-hbtn" title="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>"><span data-aisa-icon="newchat"></span></button>
				<button type="button" id="aisaautopilot-efb-min" class="aisa-ai-hbtn" title="<?php esc_attr_e( 'Close (Esc)', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>"><span data-aisa-icon="x"></span></button>
			</div>

			<div class="aisa-ai-thread aisaautopilot-efb-chat__messages" id="aisaautopilot-efb-messages" aria-live="polite"></div>
			<div class="aisa-ai-suggest" id="aisaautopilot-efb-suggest"></div>

			<form class="aisa-ai-composer" id="aisaautopilot-efb-form">
				<div class="aisa-ai-input" id="aisaautopilot-efb-inputbox">
					<div class="aisa-ai-input__ctx" id="aisaautopilot-efb-selection" hidden>
						<span class="aisa-ai-ctxchip"><span data-aisa-icon="edit" data-aisa-size="13"></span><span id="aisaautopilot-efb-selection-label"></span><button type="button" id="aisaautopilot-efb-selection-clear" aria-label="<?php esc_attr_e( 'Clear the selected field', 'ai-site-assistant-autopilot' ); ?>"><span data-aisa-icon="x" data-aisa-size="13"></span></button></span>
					</div>
					<textarea id="aisaautopilot-efb-input" class="aisa-ai-textarea" rows="1" maxlength="2000" dir="auto"
						placeholder="<?php esc_attr_e( 'Describe a change, or click a field on the form…', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Message AI Site Assistant', 'ai-site-assistant-autopilot' ); ?>"></textarea>
					<div class="aisa-ai-input__bar">
						<button type="button" id="aisaautopilot-efb-voice" class="aisa-ai-ibtn" aria-label="<?php esc_attr_e( 'Speak a message', 'ai-site-assistant-autopilot' ); ?>" aria-pressed="false"><span data-aisa-icon="mic" data-aisa-size="18"></span></button>
						<span class="aisa-ai-sp"></span>
						<button type="submit" id="aisaautopilot-efb-send" class="aisa-ai-send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>"><span data-aisa-icon="up" data-aisa-size="18" data-aisa-stroke="2.2"></span></button>
					</div>
				</div>
				<div id="aisaautopilot-efb-voice-status" class="aisa-ai-voice" aria-live="polite"></div>
				<p class="aisa-ai-foot"><span data-aisa-icon="shield" data-aisa-size="13"></span><span id="aisaautopilot-efb-foot"><?php esc_html_e( 'AI suggests, you decide. Nothing is saved until you click Save.', 'ai-site-assistant-autopilot' ); ?></span></p>
			</form>
		</aside>
		<?php
	}
}
