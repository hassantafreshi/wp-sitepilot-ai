<?php
declare(strict_types=1);

namespace SitePilot\Efb;

use SitePilot\Admin\AdminMenu;
use SitePilot\Api\RestRoutes;
use SitePilot\Core\SiteLanguage;
use SitePilot\Providers\ProviderManager;

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

		wp_enqueue_style( 'sitepilot-efb', SITEPILOT_AI_URL . 'assets/admin/efb-copilot.css', array(), SITEPILOT_AI_VERSION );
		wp_enqueue_script( 'sitepilot-efb', SITEPILOT_AI_URL . 'assets/admin/efb-copilot.js', array(), SITEPILOT_AI_VERSION, true );

		wp_localize_script(
			'sitepilot-efb',
			'SitePilotEFB',
			array(
				'restUrl'         => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'configured'      => ( new ProviderManager() )->is_configured(),
				'settingsUrl'     => admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ),
				'logsUrl'         => admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ),
				'isRTL'           => is_rtl(),
				'language'        => SiteLanguage::primary( SiteLanguage::site() ),
				'voiceResponses'  => (bool) \SitePilot\Core\Settings::get( 'voice_responses_enabled', false ),
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
		<button type="button" id="sitepilot-efb-launch" class="sitepilot-efb-fab" title="<?php esc_attr_e( 'Chat with SitePilot AI', 'sitepilot-ai' ); ?>">
			<span class="dashicons dashicons-superhero"></span>
			<span class="sitepilot-efb-fab__label"><?php esc_html_e( 'Ask SitePilot AI', 'sitepilot-ai' ); ?></span>
		</button>

		<div id="sitepilot-efb-chat" class="sitepilot-efb-chat" hidden aria-hidden="true" role="dialog"
			aria-label="<?php esc_attr_e( 'SitePilot AI chat', 'sitepilot-ai' ); ?>">
			<div class="sitepilot-efb-chat__head">
				<div class="sitepilot-efb-chat__title">
					<span class="dashicons dashicons-superhero"></span>
					<span><?php esc_html_e( 'SitePilot AI', 'sitepilot-ai' ); ?></span>
				</div>
				<div class="sitepilot-efb-chat__head-actions">
					<button type="button" id="sitepilot-efb-live-voice" class="sitepilot-efb-chat__icon" title="<?php esc_attr_e( 'Start live voice conversation', 'sitepilot-ai' ); ?>" aria-label="<?php esc_attr_e( 'Start live voice conversation', 'sitepilot-ai' ); ?>" aria-pressed="false"><span class="dashicons dashicons-format-chat"></span></button>
					<button type="button" id="sitepilot-efb-reset" class="sitepilot-efb-chat__icon" title="<?php esc_attr_e( 'New chat', 'sitepilot-ai' ); ?>" aria-label="<?php esc_attr_e( 'New chat', 'sitepilot-ai' ); ?>"><span class="dashicons dashicons-update"></span></button>
					<button type="button" id="sitepilot-efb-min" class="sitepilot-efb-chat__icon" title="<?php esc_attr_e( 'Close', 'sitepilot-ai' ); ?>" aria-label="<?php esc_attr_e( 'Close', 'sitepilot-ai' ); ?>"><span class="dashicons dashicons-minus"></span></button>
				</div>
			</div>

			<div class="sitepilot-efb-chat__messages" id="sitepilot-efb-messages" aria-live="polite">
				<!-- messages rendered here (newest at the bottom) -->
			</div>

			<form class="sitepilot-efb-chat__composer" id="sitepilot-efb-form">
				<textarea id="sitepilot-efb-input" class="sitepilot-efb-chat__input" rows="1"
					placeholder="<?php esc_attr_e( 'Message SitePilot… (e.g. build a booking form)', 'sitepilot-ai' ); ?>"></textarea>
				<button type="button" id="sitepilot-efb-voice" class="sitepilot-efb-chat__voice" aria-label="<?php esc_attr_e( 'Speak a message', 'sitepilot-ai' ); ?>"><span class="dashicons dashicons-microphone"></span></button>
				<button type="submit" id="sitepilot-efb-send" class="sitepilot-efb-chat__send" aria-label="<?php esc_attr_e( 'Send', 'sitepilot-ai' ); ?>">
					<span class="dashicons dashicons-arrow-up-alt"></span>
				</button>
			</form>
			<div id="sitepilot-efb-voice-status" class="screen-reader-text" aria-live="polite"></div>
		</div>
		<?php
	}
}
