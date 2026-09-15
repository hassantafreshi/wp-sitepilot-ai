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
					'editingForm'       => __( 'Editing the current form', 'ai-site-assistant-autopilot' ),
					'designingForm'     => __( 'Designing a new form', 'ai-site-assistant-autopilot' ),
					/* translators: %s: selected Easy Form Builder field label. */
					'workingOn'         => __( 'Working on %s', 'ai-site-assistant-autopilot' ),
					/* translators: %s: selected Easy Form Builder field label. */
					'fieldIntro'        => __( 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', 'ai-site-assistant-autopilot' ),
					'formIntro'         => __( 'Hi! I can change this form, design a new one, or explain how to do something in Easy Form Builder.', 'ai-site-assistant-autopilot' ),
					'newFormIntro'      => __( 'Hi! Tell me what form you need and I will design a complete one, or ask me an Easy Form Builder question.', 'ai-site-assistant-autopilot' ),
					'makeRequired'      => __( 'Make Email required', 'ai-site-assistant-autopilot' ),
					'renameForm'        => __( 'Rename the form to Contact us', 'ai-site-assistant-autopilot' ),
					'addOptionsHelp'    => __( 'How do I add options to a dropdown?', 'ai-site-assistant-autopilot' ),
					'buildContact'      => __( 'Build a contact form', 'ai-site-assistant-autopilot' ),
					'buildBooking'      => __( 'A booking form for a dental clinic', 'ai-site-assistant-autopilot' ),
					'buildJob'          => __( 'Job application form', 'ai-site-assistant-autopilot' ),
					'notificationHelp'  => __( 'How do I turn on email notifications?', 'ai-site-assistant-autopilot' ),
					'upgrade'           => __( 'Upgrade to EFB Pro', 'ai-site-assistant-autopilot' ),
					'pricing'           => __( 'View pricing', 'ai-site-assistant-autopilot' ),
					'label'             => __( 'Label', 'ai-site-assistant-autopilot' ),
					'type'              => __( 'Type', 'ai-site-assistant-autopilot' ),
					'requiredShort'     => __( 'Req.', 'ai-site-assistant-autopilot' ),
					'widthShort'        => __( 'W', 'ai-site-assistant-autopilot' ),
					/* translators: %s: number of form fields. */
					'fieldsCount'       => __( '%s fields', 'ai-site-assistant-autopilot' ),
					'approveCreate'     => __( 'Approve & create', 'ai-site-assistant-autopilot' ),
					'discard'           => __( 'Discard', 'ai-site-assistant-autopilot' ),
					'creating'          => __( 'Creating…', 'ai-site-assistant-autopilot' ),
					'createdOpening'    => __( 'Created! Opening the editor…', 'ai-site-assistant-autopilot' ),
					'networkError'      => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
					'voiceUnsupported'  => __( 'Voice recording is not supported by this browser. Please type your message.', 'ai-site-assistant-autopilot' ),
					'noAudio'           => __( 'No audio was captured.', 'ai-site-assistant-autopilot' ),
					'audioLarge'        => __( 'That recording is too large. Please record a shorter message.', 'ai-site-assistant-autopilot' ),
					'transcribing'      => __( 'Transcribing…', 'ai-site-assistant-autopilot' ),
					'transcriptReady'   => __( 'Transcript ready. Review it, then send.', 'ai-site-assistant-autopilot' ),
					'transcriptionFail' => __( 'Voice transcription failed. Please try again.', 'ai-site-assistant-autopilot' ),
					'recording'         => __( 'Recording — words appear in the message box. Stops after 45 seconds.', 'ai-site-assistant-autopilot' ),
					'micDenied'         => __( 'Microphone permission was denied. You can type your message instead.', 'ai-site-assistant-autopilot' ),
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
