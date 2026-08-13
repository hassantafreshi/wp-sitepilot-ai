<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Logging\Logger;

/**
 * Read-only activity log with a clear action.
 */
final class LogsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$logger = new Logger();

		if ( ! empty( $_POST['sitepilot_logs_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sitepilot_logs_nonce'] ) ), 'sitepilot_logs_clear' ) ) {
			$logger->clear();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Logs cleared.', 'sitepilot-ai' ) . '</p></div>';
		}

		$logs        = $logger->recent( 100 );
		$level_class = static function ( string $level ): string {
			switch ( $level ) {
				case 'error':
					return 'sitepilot-pill--err';
				case 'warning':
					return 'sitepilot-pill--warn';
				case 'info':
					return 'sitepilot-pill--info';
				case 'debug':
					return 'sitepilot-pill--muted';
				default:
					return 'sitepilot-pill--ok';
			}
		};
		// Distinct levels present, for the filter dropdown.
		$levels = array();
		foreach ( $logs as $log ) {
			$lvl = (string) ( $log['level'] ?? '' );
			if ( '' !== $lvl ) {
				$levels[ $lvl ] = true;
			}
		}
		?>
		<div class="wrap sitepilot-wrap">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Action history', 'sitepilot-ai' ); ?>
			</h1>
			<p class="sitepilot-subtitle"><?php esc_html_e( 'Review recent Copilot actions and operational events. Secrets and visitor submissions are never stored here.', 'sitepilot-ai' ); ?></p>
			<div class="sitepilot-card sp-logs-card">
				<div class="sp-logs-toolbar">
					<div class="sp-logs-filters">
						<input type="search" id="sp-logs-search" class="sp-logs-search"
							placeholder="<?php esc_attr_e( 'Search messages, events, traces…', 'sitepilot-ai' ); ?>"
							aria-label="<?php esc_attr_e( 'Search activity log', 'sitepilot-ai' ); ?>" />
						<select id="sp-logs-level" class="sp-logs-level" aria-label="<?php esc_attr_e( 'Filter by level', 'sitepilot-ai' ); ?>">
							<option value=""><?php esc_html_e( 'All levels', 'sitepilot-ai' ); ?></option>
							<?php foreach ( array_keys( $levels ) as $lvl ) : ?>
								<option value="<?php echo esc_attr( $lvl ); ?>"><?php echo esc_html( ucfirst( $lvl ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<form method="post" class="sp-logs-clear">
						<?php wp_nonce_field( 'sitepilot_logs_clear', 'sitepilot_logs_nonce' ); ?>
						<button type="submit" class="button">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear log', 'sitepilot-ai' ); ?>
						</button>
					</form>
				</div>

				<table class="widefat striped sp-logs-table">
					<thead>
						<tr>
							<th style="width:170px;"><?php esc_html_e( 'Time (UTC)', 'sitepilot-ai' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'Level', 'sitepilot-ai' ); ?></th>
							<th style="width:160px;"><?php esc_html_e( 'Event', 'sitepilot-ai' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Trace', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Message', 'sitepilot-ai' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Details', 'sitepilot-ai' ); ?></th>
						</tr>
					</thead>
					<tbody id="sp-logs-tbody">
						<?php if ( array() === $logs ) : ?>
							<tr class="sp-logs-empty-row"><td colspan="6">
								<div class="sp-logs-empty">
									<span class="sp-logs-empty__icon">📭</span>
									<?php esc_html_e( 'No activity yet.', 'sitepilot-ai' ); ?>
								</div>
							</td></tr>
						<?php else : ?>
							<?php foreach ( $logs as $log ) : ?>
								<?php
								$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();
								$trace   = sanitize_text_field( (string) ( $context['trace_id'] ?? '' ) );
								$detail  = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
								$level   = (string) ( $log['level'] ?? '' );
								?>
								<tr class="sp-logs-row" data-level="<?php echo esc_attr( $level ); ?>">
									<td class="sp-logs-time"><?php echo esc_html( (string) ( $log['time'] ?? '' ) ); ?></td>
									<td>
										<span class="sitepilot-pill <?php echo esc_attr( $level_class( $level ) ); ?>">
											<?php echo esc_html( $level ); ?>
										</span>
									</td>
									<td><code><?php echo esc_html( (string) ( $log['event'] ?? '' ) ); ?></code></td>
									<td>
										<?php if ( '' !== $trace ) : ?>
											<button type="button" class="sp-logs-trace" data-trace="<?php echo esc_attr( $trace ); ?>"
												title="<?php esc_attr_e( 'Copy trace ID', 'sitepilot-ai' ); ?>">
												<code><?php echo esc_html( $trace ); ?></code>
											</button>
										<?php else : ?>
											<span class="sp-logs-muted">—</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></td>
									<td>
										<details>
											<summary><?php esc_html_e( 'View', 'sitepilot-ai' ); ?></summary>
											<pre class="sp-logs-context"><?php echo esc_html( is_string( $detail ) ? $detail : '{}' ); ?></pre>
										</details>
									</td>
								</tr>
							<?php endforeach; ?>
							<tr class="sp-logs-no-match" hidden><td colspan="6">
								<div class="sp-logs-empty">
									<span class="sp-logs-empty__icon">🔍</span>
									<?php esc_html_e( 'No entries match your filter.', 'sitepilot-ai' ); ?>
								</div>
							</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
