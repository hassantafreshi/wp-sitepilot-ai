<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\WooCommerceConnector;

/** Phase 1 WooCommerce screen: configuration plus reviewed draft creation. */
final class WooCommerceSettings {

	public const SLUG = 'sitepilot-ai-woocommerce';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$saved   = $this->maybe_save();
		$health  = WooCommerceConnector::get_health();
		$stored  = get_option( 'sitepilot_ai_settings', array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$woo     = is_array( $stored['woocommerce'] ?? null ) ? $stored['woocommerce'] : array( 'enabled' => true );
		$enabled = ! empty( $woo['enabled'] );
		$ready   = ! empty( $health['active'] ) && ! empty( $health['can_manage'] ) && $enabled;
		?>
		<div class="wrap sitepilot-wrap sitepilot-screen sitepilot-screen--woo-settings sitepilot-wrap--narrow">
			<nav class="sitepilot-screen__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'sitepilot-ai' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD ) ); ?>"><?php esc_html_e( 'WooCommerce Dashboard', 'sitepilot-ai' ); ?></a><span aria-hidden="true">/</span><span><?php esc_html_e( 'WooCommerce AI', 'sitepilot-ai' ); ?></span>
			</nav>
			<header class="sitepilot-screen__header">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Safe store automation', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><span class="dashicons dashicons-products" aria-hidden="true"></span><?php esc_html_e( 'WooCommerce AI', 'sitepilot-ai' ); ?> <span class="sp-ai-badge">AI</span></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Create one reviewed simple-product draft at a time. Store changes are never published automatically.', 'sitepilot-ai' ); ?></p>
				</div>
				<div class="sitepilot-screen__status">
					<span class="sitepilot-screen__badge <?php echo ! empty( $health['active'] ) ? 'sitepilot-screen__badge--ready' : 'sitepilot-screen__badge--attention'; ?>"><?php echo esc_html( ! empty( $health['active'] ) ? __( 'WooCommerce active', 'sitepilot-ai' ) : __( 'WooCommerce inactive', 'sitepilot-ai' ) ); ?></span>
					<span class="sitepilot-screen__badge <?php echo $enabled ? 'sitepilot-screen__badge--info' : ''; ?>"><?php echo esc_html( $enabled ? __( 'AI connector enabled', 'sitepilot-ai' ) : __( 'AI connector off', 'sitepilot-ai' ) ); ?></span>
				</div>
			</header>

			<?php
			if ( $saved ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sitepilot-ai' ); ?></p></div><?php endif; ?>
			<?php if ( empty( $health['active'] ) ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span><?php esc_html_e( 'WooCommerce is not installed or active.', 'sitepilot-ai' ); ?> <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install WooCommerce', 'sitepilot-ai' ); ?></a></span></div>
			<?php endif; ?>

			<div class="sitepilot-woo-settings-layout">
				<form method="post" class="sitepilot-card sitepilot-woo-settings-form">
					<?php wp_nonce_field( 'sp_woo_save', 'sp_woo_nonce' ); ?>
					<h2 class="sitepilot-section-intro"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php esc_html_e( 'AI capabilities', 'sitepilot-ai' ); ?></h2>
					<label class="sitepilot-toggle-row"><input type="checkbox" name="woocommerce[enabled]" value="1" <?php checked( $enabled ); ?> /><span><strong><?php esc_html_e( 'Enable WooCommerce AI Connector', 'sitepilot-ai' ); ?></strong><small><?php esc_html_e( 'Allow SitePilot to prepare a single product draft from a natural-language request.', 'sitepilot-ai' ); ?></small></span></label>
					<div class="sitepilot-banner sitepilot-banner--info"><span class="dashicons dashicons-shield" aria-hidden="true"></span><span><?php esc_html_e( 'Phase 1 creates only simple product drafts after explicit review. Publishing, edits, bulk operations, and order changes are reserved for Phase 2.', 'sitepilot-ai' ); ?></span></div>
					<div class="sitepilot-settings-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'sitepilot-ai' ); ?></button></div>
				</form>

				<aside class="sitepilot-status-panel sitepilot-woo-status-panel">
					<h2><?php esc_html_e( 'WooCommerce status', 'sitepilot-ai' ); ?></h2>
					<dl>
						<div><dt><?php esc_html_e( 'Active', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( ! empty( $health['active'] ) ? __( 'Yes', 'sitepilot-ai' ) : __( 'No', 'sitepilot-ai' ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Version', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( (string) ( $health['version'] ?: '—' ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Access', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( ! empty( $health['can_manage'] ) ? __( 'Granted', 'sitepilot-ai' ) : __( 'Unavailable', 'sitepilot-ai' ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Categories', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( (string) count( WooCommerceConnector::get_categories() ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Products', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( (string) ( $health['product_count'] ?? 0 ) ); ?></dd></div>
					</dl>
				</aside>
			</div>

			<section class="sitepilot-card sitepilot-woo-draft-card" aria-labelledby="sp-woo-draft-title">
				<div class="sitepilot-woo-draft-card__header"><div><p class="sitepilot-eyebrow"><?php esc_html_e( 'Product drafts', 'sitepilot-ai' ); ?></p><h2 id="sp-woo-draft-title"><?php esc_html_e( 'Create a product draft with AI', 'sitepilot-ai' ); ?></h2><p><?php esc_html_e( 'Describe one product. Review every generated detail, then create a WooCommerce draft for manual publishing.', 'sitepilot-ai' ); ?></p></div><span class="sitepilot-screen__badge sitepilot-screen__badge--info"><?php esc_html_e( 'Draft only', 'sitepilot-ai' ); ?></span></div>
				<div id="sp-woo-compose" class="sitepilot-woo-draft-compose">
					<label for="sp-woo-prompt"><?php esc_html_e( 'What product should be drafted?', 'sitepilot-ai' ); ?></label>
					<textarea id="sp-woo-prompt" rows="5" maxlength="2000" placeholder="<?php esc_attr_e( 'For example: Create a simple draft for a 500g organic Arabic coffee product, with an introductory price and a short product description.', 'sitepilot-ai' ); ?>" <?php disabled( ! $ready ); ?>></textarea>
					<div class="sitepilot-settings-actions"><button type="button" id="sp-woo-plan" class="button button-primary" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Generate draft proposal', 'sitepilot-ai' ); ?></button><span id="sp-woo-plan-status" class="sitepilot-status" role="status" aria-live="polite"></span></div>
				</div>
				<div id="sp-woo-review" class="sitepilot-woo-draft-review" hidden><h3><?php esc_html_e( 'Review the proposal', 'sitepilot-ai' ); ?></h3><dl id="sp-woo-review-fields" class="sitepilot-woo-review-fields"></dl><p class="description"><?php esc_html_e( 'Approving creates a single WooCommerce product in draft status. The proposal expires after 15 minutes and cannot be used twice.', 'sitepilot-ai' ); ?></p><div class="sitepilot-settings-actions"><button type="button" id="sp-woo-approve" class="button button-primary"><?php esc_html_e( 'Create product draft', 'sitepilot-ai' ); ?></button><button type="button" id="sp-woo-cancel" class="button button-secondary"><?php esc_html_e( 'Start over', 'sitepilot-ai' ); ?></button></div></div>
				<div id="sp-woo-result" class="sitepilot-woo-draft-result" hidden aria-live="polite"></div>
			</section>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['sp_woo_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sp_woo_nonce'] ) ), 'sp_woo_save' ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$settings                = get_option( 'sitepilot_ai_settings', array() );
		$settings                = is_array( $settings ) ? $settings : array();
		$input                   = isset( $_POST['woocommerce'] ) && is_array( $_POST['woocommerce'] ) ? wp_unslash( $_POST['woocommerce'] ) : array();
		$settings['woocommerce'] = array(
			'enabled'       => ! empty( $input['enabled'] ),
			'auto_draft'    => true,
			'enable_search' => false,
			'enable_edit'   => false,
		);
		update_option( 'sitepilot_ai_settings', $settings, false );
		return true;
	}
}
