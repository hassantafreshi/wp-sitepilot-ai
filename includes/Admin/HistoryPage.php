<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Logging\Logger;

/**
 * WP-05 — action history.
 *
 * Every proposal, approval and result in one place. The rows are derived from
 * the operational log plus the forms AISAAutoPilot actually created, so the screen
 * reflects real activity rather than a mock-up.
 */
final class HistoryPage {

	/** Log events that represent a user-visible action, mapped to a status. */
	private const EVENT_STATUS = array(
		'plan.created'           => 'pending',
		'form.created'           => 'executed',
		'form.create.failed'     => 'failed',
		'plan.provider_error'    => 'failed',
		'action.rejected'        => 'rejected',
		'action.expired'         => 'expired',
		'efb.apply.ok'           => 'executed',
		'efb.op.rejected'        => 'rejected',
		'product.created'        => 'executed',
	);

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$rows = $this->rows();

		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen" data-sp-history>
				<?php Ui::title( __( 'Action history', 'ai-site-assistant-autopilot' ), 'clock' ); ?>
				<p class="sp-lead"><?php esc_html_e( 'Every proposal, approval and result in one place.', 'ai-site-assistant-autopilot' ); ?></p>

				<div class="sp-card sp-card--flat sp-filters sp-filters--card" style="margin-bottom:14px">
					<input type="search" id="sp-history-search" class="sp-input sp-input--filter sp-input--grow" placeholder="<?php esc_attr_e( 'Search actions', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Search actions', 'ai-site-assistant-autopilot' ); ?>" />
					<select id="sp-history-status" class="sp-select sp-input--filter" style="width:auto" aria-label="<?php esc_attr_e( 'Filter by status', 'ai-site-assistant-autopilot' ); ?>">
						<option value=""><?php esc_html_e( 'All statuses', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending approval', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="executed"><?php esc_html_e( 'Executed', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="expired"><?php esc_html_e( 'Expired', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="failed"><?php esc_html_e( 'Failed', 'ai-site-assistant-autopilot' ); ?></option>
					</select>
					<select id="sp-history-connector" class="sp-select sp-input--filter" style="width:auto" aria-label="<?php esc_attr_e( 'Filter by connector', 'ai-site-assistant-autopilot' ); ?>">
						<option value=""><?php esc_html_e( 'All connectors', 'ai-site-assistant-autopilot' ); ?></option>
						<option value="AISAAutoPilot Forms">AISAAutoPilot Forms</option>
						<option value="Easy Form Builder">Easy Form Builder</option>
					</select>
				</div>

				<div class="sp-card sp-card--bare sp-card--flat">
					<div class="sp-tr sp-tr--head" style="--sp-cols:2fr 1.3fr .8fr .9fr">
						<span><?php esc_html_e( 'Action', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Destination', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Time', 'ai-site-assistant-autopilot' ); ?></span>
					</div>
					<div id="sp-history-rows">
						<?php if ( array() === $rows ) : ?>
							<div class="sp-empty">
								<span class="sp-empty__icon">🗂️</span>
								<?php esc_html_e( 'No actions have been proposed yet.', 'ai-site-assistant-autopilot' ); ?>
							</div>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<?php $tone = $this->status_tone( $row['status'] ); ?>
								<div class="sp-tr" style="--sp-cols:2fr 1.3fr .8fr .9fr"
									data-row
									data-status="<?php echo esc_attr( $row['status'] ); ?>"
									data-connector="<?php echo esc_attr( $row['connector'] ); ?>"
									data-search="<?php echo esc_attr( mb_strtolower( $row['label'] . ' ' . $row['connector'] ) ); ?>">
									<span class="sp-td sp-td--strong sp-td--clip"><?php echo esc_html( $row['label'] ); ?></span>
									<span class="sp-td sp-td--muted sp-td--clip"><?php echo esc_html( $row['connector'] ); ?></span>
									<span class="sp-td"><?php echo Ui::pill( $this->status_label( $row['status'] ), $tone, false, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?></span>
									<span class="sp-td sp-td--muted sp-td--nowrap"><?php echo esc_html( $row['when'] ); ?></span>
								</div>
							<?php endforeach; ?>
							<div class="sp-empty" id="sp-history-nomatch" hidden>
								<span class="sp-empty__icon">🔍</span>
								<?php esc_html_e( 'No actions match your filter.', 'ai-site-assistant-autopilot' ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * Merge the created forms and the operational log into one action list.
	 *
	 * @return array<int, array{label:string,connector:string,status:string,when:string,sort:int}>
	 */
	private function rows(): array {
		$rows = array();

		$forms = get_posts(
			array(
				'post_type'        => NativeFormConnector::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending' ),
				'numberposts'      => 40,
				'suppress_filters' => false,
			)
		);
		foreach ( $forms as $form ) {
			$stamp  = (int) get_post_time( 'U', true, $form );
			$rows[] = array(
				'label'     => sprintf( /* translators: %s: form title. */ __( 'Create form — %s', 'ai-site-assistant-autopilot' ), $form->post_title ),
				'connector' => 'AISAAutoPilot Forms',
				'status'    => 'executed',
				'when'      => $this->ago( $stamp ),
				'sort'      => $stamp,
			);
		}

		foreach ( ( new Logger() )->recent( 60 ) as $entry ) {
			$event = (string) ( $entry['event'] ?? '' );
			if ( ! isset( self::EVENT_STATUS[ $event ] ) ) {
				continue;
			}
			$stamp   = (int) strtotime( (string) ( $entry['time'] ?? '' ) );
			$context = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();
			$rows[]  = array(
				'label'     => (string) ( $entry['message'] ?? $event ),
				'connector' => $this->connector_label( (string) ( $context['connector'] ?? '' ), $event ),
				'status'    => self::EVENT_STATUS[ $event ],
				'when'      => $this->ago( $stamp ),
				'sort'      => $stamp,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return $b['sort'] <=> $a['sort'];
			}
		);

		return array_slice( $rows, 0, 60 );
	}

	private function connector_label( string $id, string $event ): string {
		if ( 'easy_form_builder' === $id || 0 === strpos( $event, 'efb.' ) ) {
			return 'Easy Form Builder';
		}
		if ( 0 === strpos( $event, 'product.' ) ) {
			return 'WooCommerce';
		}

		return 'AISAAutoPilot Forms';
	}

	private function status_tone( string $status ): string {
		switch ( $status ) {
			case 'executed':
				return 'ok';
			case 'pending':
				return 'info';
			case 'failed':
			case 'rejected':
				return 'err';
			default:
				return 'off';
		}
	}

	private function status_label( string $status ): string {
		switch ( $status ) {
			case 'executed':
				return __( 'Executed', 'ai-site-assistant-autopilot' );
			case 'pending':
				return __( 'Pending', 'ai-site-assistant-autopilot' );
			case 'rejected':
				return __( 'Rejected', 'ai-site-assistant-autopilot' );
			case 'failed':
				return __( 'Failed', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Expired', 'ai-site-assistant-autopilot' );
		}
	}

	private function ago( int $stamp ): string {
		if ( $stamp <= 0 ) {
			return '—';
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. "12 mins". */
			__( '%s ago', 'ai-site-assistant-autopilot' ),
			human_time_diff( $stamp, time() )
		);
	}
}
