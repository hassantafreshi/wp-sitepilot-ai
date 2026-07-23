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
		<div class="wrap sitepilot-wrap sitepilot-dashboard">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-dashboard"></span>
				<?php esc_html_e( 'WooCommerce Dashboard', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</h1>

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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sitepilot-ai-woocommerce' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">🛍️</span>
						<span class="sp-action-label"><?php esc_html_e( 'Products', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sitepilot-ai-woocommerce-orders' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">📋</span>
						<span class="sp-action-label"><?php esc_html_e( 'Orders', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sitepilot-ai-woocommerce-analytics' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">📊</span>
						<span class="sp-action-label"><?php esc_html_e( 'Analytics', 'sitepilot-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sitepilot-ai-woocommerce-content' ) ); ?>" class="sp-action-btn">
						<span class="sp-action-icon">✍️</span>
						<span class="sp-action-label"><?php esc_html_e( 'Content AI', 'sitepilot-ai' ); ?></span>
					</a>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var restUrl = '<?php echo esc_url( rest_url( 'sitepilot/v1/' ) ); ?>';
			var nonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
			var wcActive = <?php echo $wc_active ? 'true' : 'false'; ?>;

			if (!wcActive) {
				document.querySelectorAll('.sp-stat-value').forEach(function(el) {
					el.textContent = 'N/A';
				});
				return;
			}

			// Load dashboard data
			function loadDashboard() {
				// Load sales stats
				fetch(restUrl + 'woocommerce/analytics/dashboard', {
					headers: { 'X-WP-Nonce': nonce }
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.ok && data.dashboard) {
						var d = data.dashboard;
						if (d.sales) {
							document.getElementById('sp-stat-revenue').textContent = d.sales.total_revenue + ' ' + d.currency;
							document.getElementById('sp-stat-orders').textContent = d.sales.total_orders;
							document.getElementById('sp-stat-avg').textContent = d.sales.avg_order + ' ' + d.currency;
						}
						renderSalesChart(d.sales ? d.sales.daily_sales : {});
						renderTopProducts(d.top_products || []);
						renderLowStock(d.low_stock || []);
					}
				})
				.catch(function(err) {
					console.error('Dashboard load error:', err);
				});

				// Load recent orders
				fetch(restUrl + 'woocommerce/orders/recent?limit=5', {
					headers: { 'X-WP-Nonce': nonce }
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.ok) {
						renderRecentOrders(data.orders || []);
					}
				});

				// Load product count
				fetch(restUrl + 'woocommerce/check', {
					headers: { 'X-WP-Nonce': nonce }
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.ok && data.health) {
						document.getElementById('sp-stat-products').textContent = data.health.product_count || '—';
					}
				});
			}

			function renderSalesChart(dailySales) {
				var container = document.getElementById('sp-sales-chart');
				var days = Object.keys(dailySales).sort();
				
				if (days.length === 0) {
					container.innerHTML = '<div class="sp-chart-empty">No sales data</div>';
					return;
				}

				var maxRevenue = Math.max.apply(null, days.map(function(d) { return dailySales[d].revenue; }));
				var html = '<div class="sp-bar-chart">';
				
				days.forEach(function(day) {
					var data = dailySales[day];
					var height = maxRevenue > 0 ? (data.revenue / maxRevenue * 100) : 0;
					var date = new Date(day);
					var label = date.toLocaleDateString('fa-IR', { weekday: 'short' });
					
					html += '<div class="sp-bar-item">';
					html += '<div class="sp-bar" style="height: ' + Math.max(height, 5) + '%" title="' + data.revenue + '"></div>';
					html += '<div class="sp-bar-label">' + label + '</div>';
					html += '</div>';
				});
				
				html += '</div>';
				container.innerHTML = html;
			}

			function renderTopProducts(products) {
				var container = document.getElementById('sp-top-products');
				
				if (products.length === 0) {
					container.innerHTML = '<div class="sp-list-empty">No products</div>';
					return;
				}

				var html = '<ul class="sp-product-list">';
				products.slice(0, 5).forEach(function(p, i) {
					html += '<li class="sp-product-item">';
					html += '<span class="sp-product-rank">' + (i + 1) + '</span>';
					html += '<span class="sp-product-name">' + p.name + '</span>';
					html += '<span class="sp-product-revenue">' + p.revenue + '</span>';
					html += '</li>';
				});
				html += '</ul>';
				container.innerHTML = html;
			}

			function renderLowStock(products) {
				var container = document.getElementById('sp-low-stock');
				var countEl = document.getElementById('sp-low-stock-count');
				
				countEl.textContent = products.length;
				
				if (products.length === 0) {
					container.innerHTML = '<div class="sp-list-empty sp-list-empty--success">All products in stock ✓</div>';
					return;
				}

				var html = '<ul class="sp-stock-list">';
				products.slice(0, 5).forEach(function(p) {
					var statusClass = p.stock <= 2 ? 'sp-stock--critical' : 'sp-stock--low';
					html += '<li class="sp-stock-item ' + statusClass + '">';
					html += '<span class="sp-stock-name">' + p.name + '</span>';
					html += '<span class="sp-stock-qty">' + p.stock + ' left</span>';
					html += '</li>';
				});
				html += '</ul>';
				container.innerHTML = html;
			}

			function renderRecentOrders(orders) {
				var container = document.getElementById('sp-recent-orders');
				
				if (orders.length === 0) {
					container.innerHTML = '<div class="sp-list-empty">No orders</div>';
					return;
				}

				var html = '<ul class="sp-order-list">';
				orders.forEach(function(o) {
					var statusClass = 'sp-status--' + o.status;
					html += '<li class="sp-order-item">';
					html += '<span class="sp-order-id">#' + o.number + '</span>';
					html += '<span class="sp-order-customer">' + o.customer.name + '</span>';
					html += '<span class="sp-order-total">' + o.total + ' ' + o.currency + '</span>';
					html += '<span class="sp-order-status ' + statusClass + '">' + o.status + '</span>';
					html += '</li>';
				});
				html += '</ul>';
				container.innerHTML = html;
			}

			// Load on DOM ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', loadDashboard);
			} else {
				loadDashboard();
			}
		})();
		</script>
		<?php
	}
}
