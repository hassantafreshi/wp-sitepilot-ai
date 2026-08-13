/**
 * SitePilot AI — WooCommerce Dashboard
 *
 * Handles dashboard data loading and chart rendering.
 */
(function() {
	'use strict';

	var SitePilotDashboard = {
		restUrl: '',
		nonce: '',
		wcActive: false,

		init: function() {
			this.restUrl = window.SitePilotDashboard ? window.SitePilotDashboard.restUrl : '';
			this.nonce = window.SitePilotDashboard ? window.SitePilotDashboard.nonce : '';
			this.wcActive = window.SitePilotDashboard ? window.SitePilotDashboard.wcActive : false;

			if (!this.wcActive) {
				this.showInactiveState();
				return;
			}

			this.loadDashboard();
		},

		showInactiveState: function() {
			document.querySelectorAll('.sp-stat-value').forEach(function(el) {
				el.textContent = 'N/A';
			});
		},

		loadDashboard: function() {
			var self = this;

			// Load main dashboard data
			this.fetch('woocommerce/analytics/dashboard')
				.then(function(data) {
					if (data.ok && data.dashboard) {
						self.renderStats(data.dashboard);
						self.renderSalesChart(data.dashboard.sales ? data.dashboard.sales.daily_sales : {});
						self.renderTopProducts(data.dashboard.top_products || []);
						self.renderLowStock(data.dashboard.low_stock || []);
					}
				})
				.catch(function(err) {
					console.error('Dashboard load error:', err);
				});

			// Load recent orders
			this.fetch('woocommerce/orders/recent?limit=5')
				.then(function(data) {
					if (data.ok) {
						self.renderRecentOrders(data.orders || []);
					}
				});

			// Load product count
			this.fetch('woocommerce/check')
				.then(function(data) {
					if (data.ok && data.health) {
						var el = document.getElementById('sp-stat-products');
						if (el) {
							el.textContent = data.health.product_count || '—';
						}
					}
				});
		},

		fetch: function(endpoint) {
			return fetch(this.restUrl + endpoint, {
				headers: { 'X-WP-Nonce': this.nonce }
			}).then(function(r) {
				return r.json();
			});
		},

		escapeHtml: function(value) {
			return String(value === undefined || value === null ? '' : value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},

		renderStats: function(dashboard) {
			if (dashboard.sales) {
				this.setText('sp-stat-revenue', dashboard.sales.total_revenue + ' ' + dashboard.currency);
				this.setText('sp-stat-orders', dashboard.sales.total_orders);
				this.setText('sp-stat-avg', dashboard.sales.avg_order + ' ' + dashboard.currency);
			}
		},

		renderSalesChart: function(dailySales) {
			var container = document.getElementById('sp-sales-chart');
			var self = this;
			if (!container) return;

			var days = Object.keys(dailySales).sort();

			if (days.length === 0) {
				container.innerHTML = '<div class="sp-chart-empty">' + this.i18n('No sales data') + '</div>';
				return;
			}

			var maxRevenue = Math.max.apply(null, days.map(function(d) {
				return dailySales[d].revenue;
			}));

			var html = '<div class="sp-bar-chart">';

			days.forEach(function(day) {
				var data = dailySales[day];
				var height = maxRevenue > 0 ? (data.revenue / maxRevenue * 100) : 0;
				var date = new Date(day);
				var label = date.toLocaleDateString('fa-IR', { weekday: 'short' });

				html += '<div class="sp-bar-item">';
				html += '<div class="sp-bar" style="height: ' + Math.max(height, 5) + '%" title="' + self.escapeHtml(data.revenue) + '"></div>';
				html += '<div class="sp-bar-label">' + self.escapeHtml(label) + '</div>';
				html += '</div>';
			});

			html += '</div>';
			container.innerHTML = html;
		},

		renderTopProducts: function(products) {
			var container = document.getElementById('sp-top-products');
			var self = this;
			if (!container) return;

			if (products.length === 0) {
				container.innerHTML = '<div class="sp-list-empty">' + this.i18n('No products') + '</div>';
				return;
			}

			var html = '<ul class="sp-product-list">';
			products.slice(0, 5).forEach(function(p, i) {
				html += '<li class="sp-product-item">';
				html += '<span class="sp-product-rank">' + (i + 1) + '</span>';
				 html += '<span class="sp-product-name">' + self.escapeHtml(p.name) + '</span>';
				 html += '<span class="sp-product-revenue">' + self.escapeHtml(p.revenue) + '</span>';
				html += '</li>';
			});
			html += '</ul>';
			container.innerHTML = html;
		},

		renderLowStock: function(products) {
			var container = document.getElementById('sp-low-stock');
			var countEl = document.getElementById('sp-low-stock-count');
			var self = this;
			if (!container) return;

			if (countEl) {
				countEl.textContent = products.length;
			}

			if (products.length === 0) {
				container.innerHTML = '<div class="sp-list-empty sp-list-empty--success">' + this.i18n('All products in stock') + ' ✓</div>';
				return;
			}

			var html = '<ul class="sp-stock-list">';
			products.slice(0, 5).forEach(function(p) {
				var statusClass = p.stock <= 2 ? 'sp-stock--critical' : 'sp-stock--low';
				html += '<li class="sp-stock-item ' + statusClass + '">';
				html += '<span class="sp-stock-name">' + self.escapeHtml(p.name) + '</span>';
				html += '<span class="sp-stock-qty">' + self.escapeHtml(p.stock) + ' ' + self.escapeHtml(SitePilotDashboard.i18n('left')) + '</span>';
				html += '</li>';
			});
			html += '</ul>';
			container.innerHTML = html;
		},

		renderRecentOrders: function(orders) {
			var container = document.getElementById('sp-recent-orders');
			var self = this;
			if (!container) return;

			if (orders.length === 0) {
				container.innerHTML = '<div class="sp-list-empty">' + this.i18n('No orders') + '</div>';
				return;
			}

			var html = '<ul class="sp-order-list">';
			orders.forEach(function(o) {
				var statusClass = 'sp-status--' + self.escapeHtml(o.status).replace(/[^a-z0-9_-]/gi, '');
				html += '<li class="sp-order-item">';
				html += '<span class="sp-order-id">#' + self.escapeHtml(o.number) + '</span>';
				html += '<span class="sp-order-customer">' + self.escapeHtml(o.customer && o.customer.name) + '</span>';
				html += '<span class="sp-order-total">' + self.escapeHtml(o.total) + ' ' + self.escapeHtml(o.currency) + '</span>';
				html += '<span class="sp-order-status ' + statusClass + '">' + self.escapeHtml(o.status) + '</span>';
				html += '</li>';
			});
			html += '</ul>';
			container.innerHTML = html;
		},

		setText: function(id, text) {
			var el = document.getElementById(id);
			if (el) {
				el.textContent = text;
			}
		},

		i18n: function(text) {
			return text; // Can be extended with wp.i18n
		}
	};

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			SitePilotDashboard.init();
		});
	} else {
		SitePilotDashboard.init();
	}

	// Expose for external use
	window.SitePilotDashboard = SitePilotDashboard;
})();
