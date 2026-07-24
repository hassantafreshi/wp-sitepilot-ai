<?php
declare(strict_types=1);
namespace SitePilot\Admin;

use SitePilot\Connectors\WooCommerceConnector;

/**
 * WooCommerce settings page for the admin dashboard.
 */
final class WooCommerceSettings {

	public const SLUG = 'sitepilot-ai-woocommerce';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$health = WooCommerceConnector::get_health();
		$saved  = $this->maybe_save();
		$s      = get_option( 'sitepilot_ai_settings', array() );
		$w      = is_array( $s ) ? $s : array();
		$woo    = $w['woocommerce'] ?? array( 'enabled' => true );
		?>
		<div class="wrap sitepilot-wrap sitepilot-wrap--narrow">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-products"></span>
				<?php esc_html_e( 'WooCommerce AI', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</h1>
			<p class="sitepilot-subtitle">
				<?php esc_html_e( 'AI-powered product management: create, edit, search, and bulk-update products using natural language.', 'sitepilot-ai' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sitepilot-ai' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $health['active'] ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn">
					<span class="dashicons dashicons-warning"></span>
					<span>
						<?php esc_html_e( 'WooCommerce is not installed or active.', 'sitepilot-ai' ); ?>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
							<?php esc_html_e( 'Install WooCommerce', 'sitepilot-ai' ); ?>
						</a>
					</span>
				</div>
			<?php else : ?>
				<div class="sitepilot-banner sitepilot-banner--info">
					<span class="dashicons dashicons-yes-alt"></span>
					<span>
						<?php printf( esc_html__( 'WooCommerce %s is active.', 'sitepilot-ai' ), esc_html( $health['version'] ) ); ?>
					</span>
				</div>
			<?php endif; ?>

			<form method="post" class="sitepilot-card">
				<?php wp_nonce_field( 'sp_woo_save', 'sp_woo_nonce' ); ?>

				<p>
					<label>
						<input type="checkbox" name="woocommerce[enabled]" value="1" <?php checked( $woo['enabled'] ?? true ); ?> />
						<strong><?php esc_html_e( 'Enable WooCommerce AI Connector', 'sitepilot-ai' ); ?></strong>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'When enabled, users can create, edit, and search products using natural language through the SitePilot AI chat.', 'sitepilot-ai' ); ?></p>

				<hr />

				<p>
					<label>
						<input type="checkbox" name="woocommerce[auto_draft]" value="1" <?php checked( $woo['auto_draft'] ?? true ); ?> />
						<?php esc_html_e( 'Create products as draft by default', 'sitepilot-ai' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Products will be saved as draft and require manual publishing.', 'sitepilot-ai' ); ?></p>

				<p>
					<label>
						<input type="checkbox" name="woocommerce[enable_search]" value="1" <?php checked( $woo['enable_search'] ?? true ); ?> />
						<?php esc_html_e( 'Enable smart product search', 'sitepilot-ai' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="checkbox" name="woocommerce[enable_edit]" value="1" <?php checked( $woo['enable_edit'] ?? true ); ?> />
						<?php esc_html_e( 'Enable product editing via AI', 'sitepilot-ai' ); ?>
					</label>
				</p>

				<div class="sitepilot-settings-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'sitepilot-ai' ); ?></button>
				</div>
			</form>

			<div class="sitepilot-card">
				<h2><?php esc_html_e( 'WooCommerce Status', 'sitepilot-ai' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr><td><?php esc_html_e( 'Active', 'sitepilot-ai' ); ?></td><td><?php echo $health['active'] ? '✅' : '❌'; ?></td></tr>
						<tr><td><?php esc_html_e( 'Version', 'sitepilot-ai' ); ?></td><td><?php echo esc_html( $health['version'] ?: '—' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Access', 'sitepilot-ai' ); ?></td><td><?php echo $health['can_manage'] ? '✅' : '❌'; ?></td></tr>
						<tr><td><?php esc_html_e( 'Categories', 'sitepilot-ai' ); ?></td><td><?php echo count( WooCommerceConnector::get_categories() ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Total Products', 'sitepilot-ai' ); ?></td><td><?php echo (int) wp_count_posts( 'product' )->publish; ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['sp_woo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sp_woo_nonce'] ), 'sp_woo_save' ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$settings        = get_option( 'sitepilot_ai_settings', array() );
		$woo             = $settings['woocommerce'] ?? array();
		$woo['enabled']  = ! empty( $_POST['woocommerce']['enabled'] );
		$woo['auto_draft']   = ! empty( $_POST['woocommerce']['auto_draft'] );
		$woo['enable_search'] = ! empty( $_POST['woocommerce']['enable_search'] );
		$woo['enable_edit']   = ! empty( $_POST['woocommerce']['enable_edit'] );

		$settings['woocommerce'] = $woo;
		update_option( 'sitepilot_ai_settings', $settings, false );

		return true;
	}
}
