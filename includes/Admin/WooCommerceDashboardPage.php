<?php
declare(strict_types=1);
namespace SitePilot\Admin;

use SitePilot\Connectors\WooCommerceConnector;

/**
 * WooCommerce Dashboard Page.
 *
 * Displays a comprehensive dashboard with:
 * - Sales overview (revenue, orders, avg order)
 * - Top selling products
 * - Low stock alerts
 * - Recent orders
 * - Quick actions
 */
class WooCommerceDashboardPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$wc_active = WooCommerceConnector::is_active();
		?>
		<div class="wrap sitepilot-wrap sitepilot-screen sitepilot-screen--woo-dashboard sitepilot-dashboard">
			<nav class="sitepilot-screen__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'sitepilot-ai' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME ) ); ?>"><?php esc_html_e( 'Home', 'sitepilot-ai' ); ?></a>
				<span aria-hidden="true">/</span>
				<span><?php esc_html_e( 'WooCommerce Dashboard', 'sitepilot-ai' ); ?></span>
			</nav>
			<header class="sitepilot-screen__header">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Store overview', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><span class="dashicons dashicons-store" aria-hidden="true"></span><?php esc_html_e( 'WooCommerce Dashboard', 'sitepilot-ai' ); ?> <span class="sp-ai-badge">AI</span></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Review sales, stock, recent orders, and the SitePilot tools available for this store.', 'sitepilot-ai' ); ?></p>
				</div>
				<div class="sitepilot-screen__actions">
					<span class="sitepilot-screen__badge <?php echo $wc_active ? 'sitepilot-screen__badge--ready' : 'sitepilot-screen__badge--attention'; ?>"><?php echo esc_html( $wc_active ? __( 'WooCommerce active', 'sitepilot-ai' ) : __( 'WooCommerce inactive', 'sitepilot-ai' ) ); ?></span>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WooCommerceSettings::SLUG ) ); ?>"><?php esc_html_e( 'Woo AI settings', 'sitepilot-ai' ); ?></a>
				</div>
			</header>
			<?php if ( ! $wc_active ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn">
					<span class="dashicons dashicons-warning"></span>
					<span>
						<?php esc_html_e( 'WooCommerce is not active. Please install and activate WooCommerce to use this dashboard.', 'sitepilot-ai' ); ?>
					</span>
				</div>
			<?php endif; ?>

			<!-- Stats Cards -->
			<div class="sp-dashboard-stats">
				<div class="sp-stat-card sp-stat-card--revenue">
					<div class="sp-stat-icon">💰</div>
					<div class="sp-stat-content">
						<div class="sp-stat-value" id="sp-stat-revenue">—</div>
						<div class="sp-stat-label"><?php esc_html_e( 'Total Revenue', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
				<div class="sp-stat-card sp-stat-card--orders">
					<div class="sp-stat-icon">📦</div>
					<div class="sp-stat-content">
						<div class="sp-stat-value" id="sp-stat-orders">—</div>
						<div class="sp-stat-label"><?php esc_html_e( 'Total Orders', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
				<div class="sp-stat-card sp-stat-card--avg">
					<div class="sp-stat-icon">📊</div>
					<div class="sp-stat-content">
						<div class="sp-stat-value" id="sp-stat-avg">—</div>
						<div class="sp-stat-label"><?php esc_html_e( 'Avg Order Value', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
				<div class="sp-stat-card sp-stat-card--products">
					<div class="sp-stat-icon">🏷️</div>
					<div class="sp-stat-content">
						<div class="sp-stat-value" id="sp-stat-products">—</div>
						<div class="sp-stat-label"><?php esc_html_e( 'Products', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Charts Row -->
			<div class="sp-dashboard-row">
				<!-- Sales Chart -->
				<div class="sp-dashboard-card sp-dashboard-card--chart">
					<h2><?php esc_html_e( 'Sales (Last 7 Days)', 'sitepilot-ai' ); ?></h2>
					<div class="sp-chart-container" id="sp-sales-chart">
						<div class="sp-chart-loading"><?php esc_html_e( 'Loading...', 'sitepilot-ai' ); ?></div>
					</div>
				</div>

				<!-- Top Products -->
				<div class="sp-dashboard-card sp-dashboard-card--list">
					<h2><?php esc_html_e( 'Top Products', 'sitepilot-ai' ); ?></h2>
					<div class="sp-list-container" id="sp-top-products">
						<div class="sp-list-loading"><?php esc_html_e( 'Loading...', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Second Row -->
			<div class="sp-dashboard-row">
				<!-- Low Stock -->
				<div class="sp-dashboard-card sp-dashboard-card--list">
					<h2>
						<?php esc_html_e( 'Low Stock Alerts', 'sitepilot-ai' ); ?>
						<span class="sp-badge sp-badge--warning" id="sp-low-stock-count">0</span>
					</h2>
					<div class="sp-list-container" id="sp-low-stock">
						<div class="sp-list-loading"><?php esc_html_e( 'Loading...', 'sitepilot-ai' ); ?></div>
					</div>
				</div>

				<!-- Recent Orders -->
				<div class="sp-dashboard-card sp-dashboard-card--list">
					<h2><?php esc_html_e( 'Recent Orders', 'sitepilot-ai' ); ?></h2>
					<div class="sp-list-container" id="sp-recent-orders">
						<div class="sp-list-loading"><?php esc_html_e( 'Loading...', 'sitepilot-ai' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="sp-dashboard-card sp-dashboard-card--actions">
				<h2><?php esc_html_e( 'Quick Actions', 'sitepilot-ai' ); ?></h2>
				<div class="sp-actions-grid">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">🛍️</span>
						<span class="sp-action-label"><?php esc_html_e( 'Products', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">📋</span>
						<span class="sp-action-label"><?php esc_html_e( 'Orders', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">📊</span>
						<span class="sp-action-label"><?php esc_html_e( 'Analytics', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \SitePilot\Admin\WooCommerceSettings::SLUG ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">✍️</span>
						<span class="sp-action-label"><?php esc_html_e( 'Content AI', 'sitepilot-ai' ); ?></span>
					</a>
				</div>
			</div>
		</div>

		<?php
	}
}
