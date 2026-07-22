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

		$logs = $logger->recent( 100 );
		?>
		<div class="wrap sitepilot-wrap">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'SitePilot AI — Activity Log', 'sitepilot-ai' ); ?>
			</h1>
			<p class="sitepilot-subtitle"><?php esc_html_e( 'Recent Copilot activity. Secrets are never stored here.', 'sitepilot-ai' ); ?></p>

			<form method="post" style="margin-bottom:12px;">
				<?php wp_nonce_field( 'sitepilot_logs_clear', 'sitepilot_logs_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'sitepilot-ai' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:180px;"><?php esc_html_e( 'Time (UTC)', 'sitepilot-ai' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Level', 'sitepilot-ai' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Event', 'sitepilot-ai' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Trace', 'sitepilot-ai' ); ?></th>
						<th><?php esc_html_e( 'Message', 'sitepilot-ai' ); ?></th>
						<th style="width:260px;"><?php esc_html_e( 'Details', 'sitepilot-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $logs ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No activity yet.', 'sitepilot-ai' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();
							$trace   = sanitize_text_field( (string) ( $context['trace_id'] ?? '' ) );
							$detail  = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
							?>
							<tr>
								<td><?php echo esc_html( (string) ( $log['time'] ?? '' ) ); ?></td>
								<td>
									<span class="sitepilot-pill <?php echo 'error' === ( $log['level'] ?? '' ) ? 'sitepilot-pill--err' : 'sitepilot-pill--ok'; ?>">
										<?php echo esc_html( (string) ( $log['level'] ?? '' ) ); ?>
									</span>
								</td>
								<td><code><?php echo esc_html( (string) ( $log['event'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( $trace ); ?></code></td>
								<td><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></td>
								<td>
									<details>
										<summary><?php esc_html_e( 'View context', 'sitepilot-ai' ); ?></summary>
										<pre style="max-width:420px;max-height:260px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html( is_string( $detail ) ? $detail : '{}' ); ?></pre>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
