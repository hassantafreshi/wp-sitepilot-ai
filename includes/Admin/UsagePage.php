<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Usage\UsageTracker;

/**
 * Usage Dashboard — modern analytics page with charts, credit health,
 * per-model breakdown, and budget management.
 *
 * All data comes from UsageTracker (zero dependencies, no external API).
 */
final class UsagePage {

	public const SLUG = 'sitepilot-ai-usage';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'sitepilot-ai' ) );
		}

		$tracker = new UsageTracker();
		$report  = $tracker->get_report();
		$totals  = $report['totals'];
		$budget  = $report['budget'];
		$percent = $report['used_percent'];
		$remaining = $report['remaining'];
		$warning = $report['warning'];
		$by_model = $report['by_model'];
		$by_context = $report['by_context'];
		$recent  = $report['recent'];
		?>
		<div class="wrap sitepilot-wrap sitepilot-usage-wrap">
			<!-- Header -->
			<div class="sp-usage-header">
				<div>
					<h1 class="sitepilot-title">
						<span class="dashicons dashicons-chart-area"></span>
						<?php esc_html_e( 'Usage Dashboard', 'sitepilot-ai' ); ?>
						<span class="sp-ai-badge">AI</span>
					</h1>
					<p class="sitepilot-subtitle">
						<?php esc_html_e( 'Monitor your AI token consumption, credit balance, and usage patterns across all providers and features.', 'sitepilot-ai' ); ?>
					</p>
				</div>
				<button type="button" id="sp-usage-refresh" class="button sp-btn-ghost">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Refresh', 'sitepilot-ai' ); ?>
				</button>
			</div>

			<?php if ( $warning ) : ?>
				<div class="sp-credit-banner sp-credit-banner--<?php echo esc_attr( $warning['level'] ); ?>">
					<span class="dashicons dashicons-<?php echo $warning['level'] === 'critical' ? 'warning' : 'info'; ?>"></span>
					<span><?php echo esc_html( $warning['message'] ); ?></span>
				</div>
			<?php endif; ?>

			<!-- ── Stats Cards ── -->
			<div class="sp-usage-stats">
				<div class="sp-stat-card">
					<div class="sp-stat-icon sp-stat-icon--primary">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="sp-stat-body">
						<span class="sp-stat-number"><?php echo esc_html( number_format_i18n( $totals['total_tokens'] ) ); ?></span>
						<span class="sp-stat-label"><?php esc_html_e( 'Total Tokens', 'sitepilot-ai' ); ?></span>
					</div>
				</div>
				<div class="sp-stat-card">
					<div class="sp-stat-icon sp-stat-icon--success">
						<span class="dashicons dashicons-yes"></span>
					</div>
					<div class="sp-stat-body">
						<span class="sp-stat-number"><?php echo esc_html( number_format_i18n( $totals['request_count'] ) ); ?></span>
						<span class="sp-stat-label"><?php esc_html_e( 'Total Requests', 'sitepilot-ai' ); ?></span>
					</div>
				</div>
				<div class="sp-stat-card">
					<div class="sp-stat-icon sp-stat-icon--accent">
						<span class="dashicons dashicons-cloud"></span>
					</div>
					<div class="sp-stat-body">
						<span class="sp-stat-number"><?php echo $budget > 0 ? esc_html( number_format_i18n( $budget ) ) : '∞'; ?></span>
						<span class="sp-stat-label"><?php esc_html_e( 'Monthly Budget', 'sitepilot-ai' ); ?></span>
					</div>
				</div>
				<div class="sp-stat-card">
					<div class="sp-stat-icon sp-stat-icon--<?php echo $remaining > 0 && $remaining < $budget * 0.25 ? 'danger' : 'success'; ?>">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="sp-stat-body">
						<span class="sp-stat-number"><?php echo $remaining >= 0 ? esc_html( number_format_i18n( $remaining ) ) : '∞'; ?></span>
						<span class="sp-stat-label"><?php esc_html_e( 'Remaining Tokens', 'sitepilot-ai' ); ?></span>
					</div>
				</div>
			</div>

			<!-- ── Credit Health ── -->
			<div class="sp-card sp-credit-health">
				<div class="sp-credit-health__header">
					<h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Credit Health', 'sitepilot-ai' ); ?></h2>
					<span class="sp-credit-health__pct"><?php echo esc_html( round( $percent, 1 ) ); ?>% <?php esc_html_e( 'used', 'sitepilot-ai' ); ?></span>
				</div>

				<div class="sp-credit-bar-container">
					<div class="sp-credit-bar-track">
						<div class="sp-credit-bar-fill" style="width: <?php echo esc_attr( min( 100, $percent ) ); ?>%"></div>
						<?php $markers = [50, 75, 85, 95]; foreach ( $markers as $m ) : ?>
							<div class="sp-credit-marker" style="left: <?php echo esc_attr( $m ); ?>%">
								<span class="sp-credit-marker-dot"></span>
								<span class="sp-credit-marker-label"><?php echo esc_html( $m ); ?>%</span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="sp-credit-scale">
						<span>0%</span>
						<span class="sp-credit-scale-green">50%</span>
						<span class="sp-credit-scale-yellow">75%</span>
						<span class="sp-credit-scale-orange">85%</span>
						<span class="sp-credit-scale-red">95%</span>
						<span>100%</span>
					</div>
				</div>

				<div class="sp-credit-legend">
					<span class="sp-legend-item"><span class="sp-legend-dot sp-dot-safe"></span> Safe</span>
					<span class="sp-legend-item"><span class="sp-legend-dot sp-dot-info"></span> 50%</span>
					<span class="sp-legend-item"><span class="sp-legend-dot sp-dot-warn"></span> 25%</span>
					<span class="sp-legend-item"><span class="sp-legend-dot sp-dot-danger"></span> 15%</span>
					<span class="sp-legend-item"><span class="sp-legend-dot sp-dot-critical"></span> 5%</span>
				</div>
			</div>

			<!-- ── Charts Row ── -->
			<div class="sp-usage-grid">
				<!-- Per-Model Breakdown -->
				<div class="sp-card">
					<h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'By Model', 'sitepilot-ai' ); ?></h2>
					<div class="sp-bar-chart">
						<?php if ( empty( $by_model ) ) : ?>
							<p class="sp-empty"><?php esc_html_e( 'No usage data yet.', 'sitepilot-ai' ); ?></p>
						<?php else :
							$max_model = max( array_column( $by_model, 'tokens' ) );
							foreach ( $by_model as $model => $data ) : ?>
								<div class="sp-bar-row">
									<span class="sp-bar-label"><?php echo esc_html( $model ); ?></span>
									<div class="sp-bar-track">
										<div class="sp-bar-fill" style="width: <?php echo esc_attr( $max_model > 0 ? ( $data['tokens'] / $max_model * 100 ) : 0 ); ?>%">
											<span class="sp-bar-val"><?php echo esc_html( number_format_i18n( $data['tokens'] ) ); ?></span>
										</div>
									</div>
									<span class="sp-bar-count"><?php echo esc_html( $data['requests'] ); ?> req</span>
								</div>
							<?php endforeach;
						endif; ?>
					</div>
				</div>

				<!-- Per-Context Breakdown -->
				<div class="sp-card">
					<h2><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'By Feature', 'sitepilot-ai' ); ?></h2>
					<div class="sp-bar-chart">
						<?php if ( empty( $by_context ) ) : ?>
							<p class="sp-empty"><?php esc_html_e( 'No usage data yet.', 'sitepilot-ai' ); ?></p>
						<?php else :
							$max_ctx = max( array_column( $by_context, 'tokens' ) );
							foreach ( $by_context as $ctx => $data ) : ?>
								<div class="sp-bar-row">
									<span class="sp-bar-label"><?php echo esc_html( $ctx ); ?></span>
									<div class="sp-bar-track">
										<div class="sp-bar-fill sp-bar-fill--accent" style="width: <?php echo esc_attr( $max_ctx > 0 ? ( $data['tokens'] / $max_ctx * 100 ) : 0 ); ?>%">
											<span class="sp-bar-val"><?php echo esc_html( number_format_i18n( $data['tokens'] ) ); ?></span>
										</div>
									</div>
									<span class="sp-bar-count"><?php echo esc_html( $data['requests'] ); ?> req</span>
								</div>
							<?php endforeach;
						endif; ?>
					</div>
				</div>
			</div>

			<!-- ── Recent Usage Log ── -->
			<div class="sp-card">
				<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Recent Activity', 'sitepilot-ai' ); ?></h2>
				<?php if ( empty( $recent ) ) : ?>
					<p class="sp-empty"><?php esc_html_e( 'No recent activity.', 'sitepilot-ai' ); ?></p>
				<?php else : ?>
					<div class="sp-usage-table-wrap">
						<table class="wp-list-table widefat fixed striped sp-usage-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Time', 'sitepilot-ai' ); ?></th>
									<th><?php esc_html_e( 'Model', 'sitepilot-ai' ); ?></th>
									<th><?php esc_html_e( 'Feature', 'sitepilot-ai' ); ?></th>
									<th class="sp-num"><?php esc_html_e( 'Input', 'sitepilot-ai' ); ?></th>
									<th class="sp-num"><?php esc_html_e( 'Output', 'sitepilot-ai' ); ?></th>
									<th class="sp-num"><?php esc_html_e( 'Total', 'sitepilot-ai' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent as $entry ) : ?>
									<tr>
										<td class="sp-time"><?php echo esc_html( wp_date( 'M j, H:i', strtotime( $entry['time'] ) ) ); ?></td>
										<td><code><?php echo esc_html( $entry['model'] ?? '—' ); ?></code></td>
										<td><span class="sp-feature-tag"><?php echo esc_html( $entry['context'] ?: 'general' ); ?></span></td>
										<td class="sp-num"><?php echo esc_html( number_format_i18n( (int) $entry['prompt'] ) ); ?></td>
										<td class="sp-num"><?php echo esc_html( number_format_i18n( (int) $entry['completion'] ) ); ?></td>
										<td class="sp-num sp-bold"><?php echo esc_html( number_format_i18n( (int) $entry['total'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

			<!-- ── Budget Settings ── -->
			<div class="sp-card sp-budget-card">
				<h2><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Budget Settings', 'sitepilot-ai' ); ?></h2>
				<div class="sp-budget-form">
					<div class="sp-field">
						<label for="sp-monthly-budget"><?php esc_html_e( 'Monthly Token Budget', 'sitepilot-ai' ); ?></label>
						<div class="sp-field-row">
							<input type="number" id="sp-monthly-budget" class="regular-text" value="<?php echo esc_attr( $budget ); ?>"
								min="0" step="1000" placeholder="0 = unlimited" />
							<span class="sp-field-hint"><?php esc_html_e( 'Set to 0 for unlimited usage.', 'sitepilot-ai' ); ?></span>
						</div>
					</div>
					<div class="sp-budget-actions">
						<button type="button" id="sp-save-budget" class="button button-primary">
							<?php esc_html_e( 'Save Budget', 'sitepilot-ai' ); ?>
						</button>
						<button type="button" id="sp-reset-usage" class="button sp-btn-danger">
							<?php esc_html_e( 'Reset All Usage', 'sitepilot-ai' ); ?>
						</button>
						<span id="sp-budget-status" class="sp-status-text"></span>
					</div>
				</div>
			</div>

			<p class="sp-usage-footnote">
				<?php esc_html_e( 'Usage data is stored locally on this site. No data is sent to external servers.', 'sitepilot-ai' ); ?>
				<?php if ( $totals['last_used'] ) : ?>
					<br /><?php esc_html_e( 'Last AI request:', 'sitepilot-ai' ); ?>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $totals['last_used'] ) ) ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
