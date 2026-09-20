<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Activity\ActivityTimeline;

/**
 * Canonical mockup-shaped Activity Log.
 *
 * This is a redacted operational timeline, not a generic action console. Row
 * actions appear only when the timeline can prove that a generated WordPress
 * draft still exists and the current administrator may edit it.
 */
final class LogsPage {

	/** Day cards shown before "Show earlier activity". */
	private const VISIBLE_DAYS = 3;

	/** Rows shown in a day card before "Show N more events". */
	private const VISIBLE_ROWS = 12;

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$groups = ( new ActivityTimeline() )->groups( 100 );
		$recent = array_slice( $groups, 0, self::VISIBLE_DAYS );
		$older  = array_slice( $groups, self::VISIBLE_DAYS );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-logs>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Activity Log', 'ai-site-assistant-autopilot' ),
					__( 'Recorded AutoPilot activity, with a real draft link when one is available.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Export CSV', 'ai-site-assistant-autopilot' ),
							'url'   => LogExport::url(),
						),
					)
				);
				OverviewNavigation::render( OverviewNavigation::TAB_LOGS );
				?>
				<div class="sp-pagebody">
					<?php if ( array() === $groups ) : ?>
						<section class="sp-activity-empty" data-sp-activity-empty aria-labelledby="sp-activity-empty-title">
							<span class="sp-activity-empty__icon" aria-hidden="true"><?php echo Ui::icon( 'clock', 21, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
							<h2 class="sp-activity-empty__title" id="sp-activity-empty-title"><?php esc_html_e( 'No changes yet', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-activity-empty__copy"><?php esc_html_e( 'No activity is currently stored. New AutoPilot events will appear here.', 'ai-site-assistant-autopilot' ); ?></p>
						</section>
					<?php else : ?>
						<div class="sp-activity-timeline" data-sp-activity-timeline>
							<?php foreach ( $recent as $index => $group ) : ?>
								<?php $this->render_group( (array) $group, (int) $index ); ?>
							<?php endforeach; ?>
							<?php if ( array() !== $older ) : ?>
								<details class="sp-activity-older" data-sp-activity-older>
									<summary>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: number of older days in the activity log. */
												_n( 'Show earlier activity (%s more day)', 'Show earlier activity (%s more days)', count( $older ), 'ai-site-assistant-autopilot' ),
												number_format_i18n( count( $older ) )
											)
										);
										?>
									</summary>
									<div class="sp-activity-older__groups">
										<?php foreach ( $older as $index => $group ) : ?>
											<?php $this->render_group( (array) $group, self::VISIBLE_DAYS + (int) $index ); ?>
										<?php endforeach; ?>
									</div>
								</details>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * One day card. Rows beyond VISIBLE_ROWS fold into a native disclosure.
	 *
	 * @param array<string, mixed> $group
	 */
	private function render_group( array $group, int $index ): void {
		$group_id = 'sp-activity-day-' . (string) $index;
		$rows     = array_values( (array) ( $group['rows'] ?? array() ) );
		$visible  = array_slice( $rows, 0, self::VISIBLE_ROWS );
		$folded   = array_slice( $rows, self::VISIBLE_ROWS );
		?>
		<section class="sp-activity-card" data-sp-activity-group data-sp-activity-day="<?php echo esc_attr( (string) ( $group['day_key'] ?? '' ) ); ?>" aria-labelledby="<?php echo esc_attr( $group_id ); ?>">
			<header class="sp-activity-card__head">
				<h2 id="<?php echo esc_attr( $group_id ); ?>"><?php echo esc_html( (string) ( $group['label'] ?? '' ) ); ?></h2>
				<span class="sp-activity-card__summary"><?php echo esc_html( (string) ( $group['summary'] ?? '' ) ); ?></span>
			</header>
			<div class="sp-activity-card__rows">
				<?php foreach ( $visible as $row ) : ?>
					<?php $this->render_row( (array) $row ); ?>
				<?php endforeach; ?>
				<?php if ( array() !== $folded ) : ?>
					<details class="sp-activity-more" data-sp-activity-more>
						<summary>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of hidden events for this day. */
									_n( 'Show %s more event', 'Show %s more events', count( $folded ), 'ai-site-assistant-autopilot' ),
									number_format_i18n( count( $folded ) )
								)
							);
							?>
						</summary>
						<?php foreach ( $folded as $row ) : ?>
							<?php $this->render_row( (array) $row ); ?>
						<?php endforeach; ?>
					</details>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function render_row( array $row ): void {
		$tone = (string) ( $row['tone'] ?? 'off' );
		if ( ! in_array( $tone, array( 'off', 'warn', 'bad' ), true ) ) {
			$tone = 'off';
		}
		$action = is_array( $row['action'] ?? null ) ? $row['action'] : null;
		?>
		<article class="sp-activity-row" data-sp-activity-row>
			<?php if ( '' !== (string) ( $row['time_iso'] ?? '' ) ) : ?>
				<time class="sp-activity-row__when" datetime="<?php echo esc_attr( (string) $row['time_iso'] ); ?>"><bdi><?php echo esc_html( (string) ( $row['when'] ?? '' ) ); ?></bdi></time>
			<?php else : ?>
				<span class="sp-activity-row__when"><bdi><?php echo esc_html( (string) ( $row['when'] ?? '' ) ); ?></bdi></span>
			<?php endif; ?>
			<span class="sp-activity-row__feature" dir="auto"><?php echo esc_html( (string) ( $row['feature'] ?? '' ) ); ?></span>
			<p class="sp-activity-row__summary" dir="auto"><?php echo esc_html( (string) ( $row['summary'] ?? '' ) ); ?></p>
			<span class="sp-activity-row__state sp-activity-row__state--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( (string) ( $row['state'] ?? '' ) ); ?></span>
			<?php if ( is_array( $action ) && '' !== (string) ( $action['url'] ?? '' ) ) : ?>
				<a class="sp-activity-row__action" href="<?php echo esc_url( (string) $action['url'] ); ?>"><?php echo esc_html( (string) ( $action['label'] ?? '' ) ); ?></a>
			<?php else : ?>
				<span class="sp-activity-row__action sp-activity-row__action--empty" aria-hidden="true"></span>
			<?php endif; ?>
		</article>
		<?php
	}
}
