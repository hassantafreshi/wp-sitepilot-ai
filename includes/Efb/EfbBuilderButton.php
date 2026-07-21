<?php
declare(strict_types=1);

namespace SitePilot\Efb;

use SitePilot\Admin\AdminMenu;
use SitePilot\Api\RestRoutes;
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
				'restUrl'     => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'configured'  => ( new ProviderManager() )->is_configured(),
				'settingsUrl' => admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ),
				'isRTL'       => is_rtl(),
			)
		);
	}

	public function render(): void {
		if ( ! $this->is_efb_page() ) {
			return;
		}
		?>
		<button type="button" id="sitepilot-efb-launch" class="sitepilot-efb-fab" title="<?php esc_attr_e( 'Build a form with AI', 'sitepilot-ai' ); ?>">
			<span class="dashicons dashicons-superhero"></span>
			<span class="sitepilot-efb-fab__label"><?php esc_html_e( 'Build with AI', 'sitepilot-ai' ); ?></span>
		</button>

		<div id="sitepilot-efb-modal" class="sitepilot-efb-modal" hidden aria-hidden="true">
			<div class="sitepilot-efb-modal__backdrop" data-close="1"></div>
			<div class="sitepilot-efb-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'AI Form Builder', 'sitepilot-ai' ); ?>">
				<div class="sitepilot-efb-modal__head">
					<h2><span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'SitePilot AI — Build a form', 'sitepilot-ai' ); ?></h2>
					<button type="button" class="sitepilot-efb-modal__close" data-close="1" aria-label="<?php esc_attr_e( 'Close', 'sitepilot-ai' ); ?>">&times;</button>
				</div>
				<div class="sitepilot-efb-modal__body" id="sitepilot-efb-body">
					<!-- filled by JS -->
				</div>
			</div>
		</div>
		<?php
	}
}
