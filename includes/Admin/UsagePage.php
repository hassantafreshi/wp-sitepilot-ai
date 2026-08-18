<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Usage\UsageTracker;

/**
 * Usage dashboard — token consumption, credit health and budget management.
 * Every number comes from UsageTracker; nothing leaves this site.
 */
final class UsagePage {

	public const SLUG = 'aisaautopilot-usage';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'ai-site-assistant-autopilot' ) );
		}

		$report     = ( new UsageTracker() )->get_report();
		$totals     = $report['totals'];
		$budget     = (int) $report['budget'];
		$percent    = (float) $report['used_percent'];
		$remaining  = (int) $report['remaining'];
		$warning    = $report['warning'];
		$by_model   = $report['by_model'];
		$by_context = $report['by_context'];
		$low        = null !== $warning;

		$max_model = ! empty( $by_model ) ? max( array_column( $by_model, 'tokens' ) ) : 0;
		$bar_tones = array( '', 'sp-bar__fill--violet', 'sp-bar__fill--orchid' );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-usage>
			<section class="sp-screen">
				<div class="sp-page-head">
					<div>
						<?php Ui::title( __( 'Usage Dashboard', 'ai-site-assistant-autopilot' ), 'bars' ); ?>
						<p class="sp-lead sp-mb0"><?php esc_html_e( 'Monitor your AI token consumption, credit balance, and usage patterns across all providers and features.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<button type="button" id="sp-usage-refresh" class="sp-btn sp-btn--ghost sp-btn--r8" style="padding:10px 16px;font-size:13.5px">
						<?php echo Ui::icon( 'refresh', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php esc_html_e( 'Refresh', 'ai-site-assistant-autopilot' ); ?></span>
					</button>
				</div>

				<div class="sp-tiles" style="margin-bottom:24px">
					<?php
					Ui::stat( 'spark', number_format_i18n( (int) $totals['total_tokens'] ), __( 'Total Tokens', 'ai-site-assistant-autopilot' ) );
					Ui::stat( 'pie', number_format_i18n( (int) $totals['request_count'] ), __( 'Total Requests', 'ai-site-assistant-autopilot' ), 'violet' );
					Ui::stat( 'card', $budget > 0 ? number_format_i18n( $budget ) : '∞', __( 'Monthly Budget', 'ai-site-assistant-autopilot' ), 'ok' );
					Ui::stat( 'shield', $remaining >= 0 ? number_format_i18n( $remaining ) : '∞', __( 'Remaining Tokens', 'ai-site-assistant-autopilot' ), $low ? 'err' : 'ok' );
					?>
				</div>

				<div class="sp-card sp-card--24" style="margin-bottom:16px">
					<div class="sp-card__split" style="margin-bottom:14px">
						<h2 class="sp-h2 sp-h2--md sp-mb0" style="display:flex;align-items:center;gap:9px">
							<?php echo Ui::icon( 'shield', 18, '#4f46e5', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Credit Health', 'ai-site-assistant-autopilot' ); ?></span>
						</h2>
						<span style="font-size:14px;font-weight:700;color:<?php echo $low ? '#92400e' : '#646970'; ?>">
							<?php
							printf(
								/* translators: %s: percentage of the monthly budget used. */
								esc_html__( '%s used', 'ai-site-assistant-autopilot' ),
								esc_html( $budget > 0 ? number_format_i18n( $percent, 1 ) . '%' : '—' )
							);
							?>
						</span>
					</div>
					<?php Ui::bar( $budget > 0 ? $percent : 0.0, 'sp-bar--thick', $low ? 'sp-bar__fill--warn' : 'sp-bar__fill--grad' ); ?>
					<p style="margin:10px 0 0;font-size:13px;color:<?php echo $low ? '#92400e' : '#646970'; ?>">
						<?php
						echo $low
							? esc_html( (string) $warning['message'] )
							: esc_html( $budget > 0 ? __( 'Resets at the start of next month.', 'ai-site-assistant-autopilot' ) : __( 'No monthly budget is set — usage is unlimited.', 'ai-site-assistant-autopilot' ) );
						?>
					</p>
				</div>

				<div class="sp-cols sp-cols--16" style="margin-bottom:16px">
					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,300px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'By Model', 'ai-site-assistant-autopilot' ); ?></h2>
						<?php if ( empty( $by_model ) ) : ?>
							<p class="sp-empty"><?php esc_html_e( 'No usage data yet.', 'ai-site-assistant-autopilot' ); ?></p>
						<?php else : ?>
							<div class="sp-stack sp-stack--md">
								<?php $i = 0; ?>
								<?php foreach ( $by_model as $model => $data ) : ?>
									<div>
										<div class="sp-bar-head" style="margin-bottom:5px">
											<span class="sp-mono"><?php echo esc_html( (string) $model ); ?></span>
											<span style="font-weight:600"><?php echo esc_html( number_format_i18n( (int) $data['tokens'] ) ); ?></span>
										</div>
										<?php Ui::bar( $max_model > 0 ? ( (int) $data['tokens'] / $max_model * 100 ) : 0.0, 'sp-bar--thin', $bar_tones[ $i % 3 ] ); ?>
									</div>
									<?php ++$i; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="sp-card sp-card--24" style="flex:1 1 min(100%,300px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'By Feature', 'ai-site-assistant-autopilot' ); ?></h2>
						<?php if ( empty( $by_context ) ) : ?>
							<p class="sp-empty"><?php esc_html_e( 'No usage data yet.', 'ai-site-assistant-autopilot' ); ?></p>
						<?php else : ?>
							<div class="sp-kv">
								<?php foreach ( $by_context as $context => $data ) : ?>
									<div class="sp-kv__row sp-kv__row--ruled">
										<span><?php echo esc_html( (string) $context ); ?></span>
										<span style="font-weight:600"><?php echo esc_html( number_format_i18n( (int) $data['tokens'] ) ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="sp-card sp-card--24">
					<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( 'Budget Settings', 'ai-site-assistant-autopilot' ); ?></h2>
					<div class="sp-row-wrap" style="align-items:flex-end;margin-top:12px">
						<label class="sp-field" style="flex:1 1 220px">
							<span class="sp-label sp-mb0"><?php esc_html_e( 'Monthly Token Budget', 'ai-site-assistant-autopilot' ); ?></span>
							<input type="number" id="sp-monthly-budget" class="sp-input sp-mono" min="0" step="1000" value="<?php echo esc_attr( (string) $budget ); ?>" />
							<span class="sp-help"><?php esc_html_e( 'Set to 0 for unlimited usage.', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
						<button type="button" id="sp-save-budget" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Save Budget', 'ai-site-assistant-autopilot' ); ?></button>
						<button type="button" id="sp-reset-usage" class="sp-btn sp-btn--danger sp-btn--r8"><?php esc_html_e( 'Reset All Usage', 'ai-site-assistant-autopilot' ); ?></button>
						<span id="sp-budget-status" class="sp-status-text"></span>
					</div>
					<p class="sp-help sp-help--12" style="margin-top:16px">
						<?php esc_html_e( 'Usage data is stored locally on this site. No data is sent to external servers.', 'ai-site-assistant-autopilot' ); ?>
						<?php if ( ! empty( $totals['last_used'] ) ) : ?>
							<br /><?php esc_html_e( 'Last AI request:', 'ai-site-assistant-autopilot' ); ?>
							<?php echo esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) strtotime( (string) $totals['last_used'] ) ) ); ?>
						<?php endif; ?>
					</p>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-usage-i18n',
			array(
				'saving'      => __( 'Saving…', 'ai-site-assistant-autopilot' ),
				'saved'       => __( 'Budget saved.', 'ai-site-assistant-autopilot' ),
				'failed'      => __( 'Could not save.', 'ai-site-assistant-autopilot' ),
				'confirmWipe' => __( 'Reset all recorded usage? This cannot be undone.', 'ai-site-assistant-autopilot' ),
				'resetting'   => __( 'Resetting…', 'ai-site-assistant-autopilot' ),
				'resetDone'   => __( 'Usage reset.', 'ai-site-assistant-autopilot' ),
			)
		);
	}
}
