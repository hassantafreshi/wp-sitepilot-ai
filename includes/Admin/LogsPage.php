<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Logging\Logger;

/**
 * Activity log — the read-only operational trail, with a clear action.
 */
final class LogsPage {

	private const COLS = '170px 90px 170px 130px 1fr';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$logger  = new Logger();
		$cleared = false;

		if ( ! empty( $_POST['aisaautopilot_logs_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aisaautopilot_logs_nonce'] ) ), 'aisaautopilot_logs_clear' ) ) {
			$logger->clear();
			$cleared = true;
		}

		$logs   = $logger->recent( 100 );
		$levels = array();
		foreach ( $logs as $log ) {
			$level = (string) ( $log['level'] ?? '' );
			if ( '' !== $level ) {
				$levels[ $level ] = true;
			}
		}

		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen" data-sp-logs>
				<?php Ui::title( __( 'Activity Log', 'ai-site-assistant-autopilot' ), 'pulse' ); ?>
				<p class="sp-lead"><?php esc_html_e( 'Recent Copilot activity. Secrets are never stored here.', 'ai-site-assistant-autopilot' ); ?></p>

				<?php if ( $cleared ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Logs cleared.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<div class="sp-card sp-card--bare sp-card--flat">
					<div class="sp-filters">
						<input type="search" id="sp-logs-search" class="sp-input sp-input--filter sp-input--grow" placeholder="<?php esc_attr_e( 'Search messages, events, traces…', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Search activity log', 'ai-site-assistant-autopilot' ); ?>" />
						<select id="sp-logs-level" class="sp-select sp-input--filter" style="width:auto" aria-label="<?php esc_attr_e( 'Filter by level', 'ai-site-assistant-autopilot' ); ?>">
							<option value=""><?php esc_html_e( 'All levels', 'ai-site-assistant-autopilot' ); ?></option>
							<?php foreach ( array_keys( $levels ) as $level ) : ?>
								<option value="<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="sp-spacer"></span>
						<form method="post">
							<?php wp_nonce_field( 'aisaautopilot_logs_clear', 'aisaautopilot_logs_nonce' ); ?>
							<button type="submit" class="sp-btn sp-btn--danger sp-btn--r8" style="padding:9px 14px;font-size:13px"><?php esc_html_e( 'Clear log', 'ai-site-assistant-autopilot' ); ?></button>
						</form>
					</div>

					<div class="sp-table__scroll">
						<div class="sp-table__inner">
							<div class="sp-tr sp-tr--head" style="--sp-cols:<?php echo esc_attr( self::COLS ); ?>">
								<span><?php esc_html_e( 'Time (UTC)', 'ai-site-assistant-autopilot' ); ?></span>
								<span><?php esc_html_e( 'Level', 'ai-site-assistant-autopilot' ); ?></span>
								<span><?php esc_html_e( 'Event', 'ai-site-assistant-autopilot' ); ?></span>
								<span><?php esc_html_e( 'Trace', 'ai-site-assistant-autopilot' ); ?></span>
								<span><?php esc_html_e( 'Message', 'ai-site-assistant-autopilot' ); ?></span>
							</div>

							<div id="sp-logs-rows">
								<?php if ( array() === $logs ) : ?>
									<div class="sp-empty">
										<span class="sp-empty__icon">📭</span>
										<?php esc_html_e( 'No activity yet.', 'ai-site-assistant-autopilot' ); ?>
									</div>
								<?php else : ?>
									<?php foreach ( $logs as $log ) : ?>
										<?php
										$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();
										$trace   = sanitize_text_field( (string) ( $context['trace_id'] ?? '' ) );
										$level   = (string) ( $log['level'] ?? '' );
										$event   = (string) ( $log['event'] ?? '' );
										$message = (string) ( $log['message'] ?? '' );
										$time    = (string) ( $log['time'] ?? '' );
										?>
										<div class="sp-tr sp-tr--sm" style="--sp-cols:<?php echo esc_attr( self::COLS ); ?>"
											data-row
											data-level="<?php echo esc_attr( $level ); ?>"
											data-search="<?php echo esc_attr( mb_strtolower( $message . ' ' . $event . ' ' . $trace ) ); ?>">
											<span class="sp-mono sp-td--muted"><?php echo esc_html( str_replace( array( 'T', '+00:00' ), array( ' ', '' ), $time ) ); ?></span>
											<span><?php echo Ui::pill( $level, $this->level_tone( $level ), false, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?></span>
											<span class="sp-mono"><?php echo esc_html( $event ); ?></span>
											<span class="sp-mono sp-td--muted"><?php echo esc_html( '' !== $trace ? $trace : '—' ); ?></span>
											<span><?php echo esc_html( $message ); ?></span>
										</div>
									<?php endforeach; ?>
									<div class="sp-empty" id="sp-logs-nomatch" hidden>
										<span class="sp-empty__icon">🔍</span>
										<?php esc_html_e( 'No entries match your filter.', 'ai-site-assistant-autopilot' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	private function level_tone( string $level ): string {
		switch ( $level ) {
			case 'error':
				return 'err';
			case 'warning':
				return 'warn';
			case 'debug':
				return 'off';
			default:
				return 'info';
		}
	}
}
