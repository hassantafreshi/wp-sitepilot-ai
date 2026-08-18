<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\WooCommerceConnector;

/**
 * WooCommerce dashboard — sales, stock, orders and the AISAAutoPilot store tools.
 * Numbers are filled in by dashboard.js from the analytics REST routes.
 */
final class WooCommerceDashboardPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$active = WooCommerceConnector::is_active();
		$wc_ai  = admin_url( 'admin.php?page=' . WooCommerceSettings::SLUG );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-wc-dashboard>
			<section class="sp-screen">
				<?php Ui::title( __( 'WooCommerce Dashboard', 'ai-site-assistant-autopilot' ), 'cart', true ); ?>
				<p class="sp-lead" style="margin-bottom:16px !important"><?php esc_html_e( 'AI-powered product management: create, edit, search, and bulk-update products using natural language.', 'ai-site-assistant-autopilot' ); ?></p>

				<?php if ( ! $active ) : ?>
					<?php Ui::alert( 'warn', 'warning', esc_html__( 'WooCommerce is not active. Please install and activate WooCommerce to use this dashboard.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<div class="sp-tiles" style="margin-bottom:24px">
					<div class="sp-stat">
						<span class="sp-stat__icon sp-stat__icon--ok"><?php echo Ui::icon( 'money', 24, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<span class="sp-stat__body">
							<span class="sp-stat__num" id="sp-stat-revenue">—</span>
							<span class="sp-stat__label"><?php esc_html_e( 'Total Revenue', 'ai-site-assistant-autopilot' ); ?></span>
						</span>
					</div>
					<div class="sp-stat">
						<span class="sp-stat__icon"><?php echo Ui::icon( 'bag', 24, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<span class="sp-stat__body">
							<span class="sp-stat__num" id="sp-stat-orders">—</span>
							<span class="sp-stat__label"><?php esc_html_e( 'Total Orders', 'ai-site-assistant-autopilot' ); ?></span>
						</span>
					</div>
					<div class="sp-stat">
						<span class="sp-stat__icon sp-stat__icon--violet"><?php echo Ui::icon( 'chart', 24, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<span class="sp-stat__body">
							<span class="sp-stat__num" id="sp-stat-avg">—</span>
							<span class="sp-stat__label"><?php esc_html_e( 'Avg Order Value', 'ai-site-assistant-autopilot' ); ?></span>
						</span>
					</div>
					<div class="sp-stat">
						<span class="sp-stat__icon sp-stat__icon--err"><?php echo Ui::icon( 'box', 24, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<span class="sp-stat__body">
							<span class="sp-stat__num" id="sp-stat-products">—</span>
							<span class="sp-stat__label"><?php esc_html_e( 'Products', 'ai-site-assistant-autopilot' ); ?></span>
						</span>
					</div>
				</div>

				<div class="sp-cols sp-cols--16" style="margin-bottom:16px">
					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,420px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:16px !important"><?php esc_html_e( 'Sales (Last 7 Days)', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-chart" id="sp-sales-chart"></div>
						<div class="sp-chart__labels" id="sp-sales-labels"></div>
					</div>
					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,280px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'Top Products', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-kv" id="sp-top-products">
							<p class="sp-empty"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</div>
				</div>

				<div class="sp-cols sp-cols--16" style="margin-bottom:16px">
					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,300px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="display:flex;align-items:center;gap:9px;margin-bottom:14px !important">
							<?php echo Ui::icon( 'warning', 18, '#d97706', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Low Stock Alerts', 'ai-site-assistant-autopilot' ); ?></span>
							<span class="sp-pill sp-pill--warn sp-pill--xs" id="sp-low-stock-count">0</span>
						</h2>
						<div class="sp-stack" id="sp-low-stock">
							<p class="sp-empty"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</div>
					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,300px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'Recent Orders', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-stack sp-stack--md" id="sp-recent-orders">
							<p class="sp-empty"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</div>
				</div>

				<div class="sp-card sp-card--24">
					<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'Quick Actions', 'ai-site-assistant-autopilot' ); ?></h2>
					<div class="sp-tiles sp-tiles--160">
						<a class="sp-quick" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
							<?php echo Ui::icon( 'box', 22, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Products', 'ai-site-assistant-autopilot' ); ?></span>
						</a>
						<a class="sp-quick" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">
							<?php echo Ui::icon( 'bag', 22, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Orders', 'ai-site-assistant-autopilot' ); ?></span>
						</a>
						<a class="sp-quick" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ) ); ?>">
							<?php echo Ui::icon( 'chart', 22, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Analytics', 'ai-site-assistant-autopilot' ); ?></span>
						</a>
						<a class="sp-quick" href="<?php echo esc_url( $wc_ai ); ?>">
							<?php echo Ui::icon( 'spark', 22, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Content AI', 'ai-site-assistant-autopilot' ); ?></span>
						</a>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-wc-i18n',
			array(
				'empty'      => __( 'No data yet.', 'ai-site-assistant-autopilot' ),
				/* translators: %s: number of units sold. */
				'sold'       => __( '%s sold', 'ai-site-assistant-autopilot' ),
				/* translators: %s: number of units still in stock. */
				'left'       => __( '%s left', 'ai-site-assistant-autopilot' ),
				'completed'  => __( 'Completed', 'ai-site-assistant-autopilot' ),
				'processing' => __( 'Processing', 'ai-site-assistant-autopilot' ),
				'onHold'     => __( 'On hold', 'ai-site-assistant-autopilot' ),
				'days'       => array(
					__( 'Mon', 'ai-site-assistant-autopilot' ),
					__( 'Tue', 'ai-site-assistant-autopilot' ),
					__( 'Wed', 'ai-site-assistant-autopilot' ),
					__( 'Thu', 'ai-site-assistant-autopilot' ),
					__( 'Fri', 'ai-site-assistant-autopilot' ),
					__( 'Sat', 'ai-site-assistant-autopilot' ),
					__( 'Sun', 'ai-site-assistant-autopilot' ),
				),
			)
		);
	}
}
