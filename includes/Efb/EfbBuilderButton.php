<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Api\RestRoutes;
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
		if ( ! $this->is_efb_page() ) {
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
			)
		);
	}

	public function render(): void {
		if ( ! $this->is_efb_page() ) {
			return;
		}
		?>
		<button type="button" id="aisaautopilot-efb-launch" class="aisaautopilot-efb-fab" title="<?php esc_attr_e( 'Chat with AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ); ?>">
			<span class="dashicons dashicons-superhero"></span>
			<span class="aisaautopilot-efb-fab__label"><?php esc_html_e( 'Ask AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ); ?></span>
		</button>

		<div id="aisaautopilot-efb-chat" class="aisaautopilot-efb-chat" hidden aria-hidden="true" role="dialog"
			aria-label="<?php esc_attr_e( 'AI Site Assistant AutoPilot chat', 'ai-site-assistant-autopilot' ); ?>">
			<div class="aisaautopilot-efb-chat__head">
				<div class="aisaautopilot-efb-chat__title">
					<span class="dashicons dashicons-superhero"></span>
					<span><?php esc_html_e( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
				<div class="aisaautopilot-efb-chat__head-actions">
					<button type="button" id="aisaautopilot-efb-live-voice" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'Start live voice conversation', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Start live voice conversation', 'ai-site-assistant-autopilot' ); ?>" aria-pressed="false"><span class="dashicons dashicons-format-chat"></span></button>
					<button type="button" id="aisaautopilot-efb-reset" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'New chat', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-update"></span></button>
					<button type="button" id="aisaautopilot-efb-min" class="aisaautopilot-efb-chat__icon" title="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-minus"></span></button>
				</div>
			</div>

			<div class="aisaautopilot-efb-chat__messages" id="aisaautopilot-efb-messages" aria-live="polite">
				<!-- messages rendered here (newest at the bottom) -->
			</div>

			<form class="aisaautopilot-efb-chat__composer" id="aisaautopilot-efb-form">
				<textarea id="aisaautopilot-efb-input" class="aisaautopilot-efb-chat__input" rows="1"
					placeholder="<?php esc_attr_e( 'Message AISAAutoPilot… (e.g. build a booking form)', 'ai-site-assistant-autopilot' ); ?>"></textarea>
				<button type="button" id="aisaautopilot-efb-voice" class="aisaautopilot-efb-chat__voice" aria-label="<?php esc_attr_e( 'Speak a message', 'ai-site-assistant-autopilot' ); ?>"><span class="dashicons dashicons-microphone"></span></button>
				<button type="submit" id="aisaautopilot-efb-send" class="aisaautopilot-efb-chat__send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>">
					<span class="dashicons dashicons-arrow-up-alt"></span>
				</button>
			</form>
			<div id="aisaautopilot-efb-voice-status" class="screen-reader-text" aria-live="polite"></div>
		</div>
		<?php
	}
}
