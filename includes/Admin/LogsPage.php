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

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$groups = ( new ActivityTimeline() )->groups( 100 );

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
							<?php foreach ( $groups as $index => $group ) : ?>
								<?php
								$group_id = 'sp-activity-day-' . (string) $index;
								$rows     = (array) ( $group['rows'] ?? array() );
								?>
								<section class="sp-activity-card" data-sp-activity-group data-sp-activity-day="<?php echo esc_attr( (string) ( $group['day_key'] ?? '' ) ); ?>" aria-labelledby="<?php echo esc_attr( $group_id ); ?>">
									<header class="sp-activity-card__head">
										<h2 id="<?php echo esc_attr( $group_id ); ?>"><?php echo esc_html( (string) ( $group['label'] ?? '' ) ); ?></h2>
										<span class="sp-activity-card__summary"><?php echo esc_html( (string) ( $group['summary'] ?? '' ) ); ?></span>
									</header>
									<div class="sp-activity-card__rows">
										<?php foreach ( $rows as $row ) : ?>
											<?php
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
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}
}
