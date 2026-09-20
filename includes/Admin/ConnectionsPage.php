<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Canonical Health page.
 *
 * The mockup intentionally gives Health one compact, ordered diagnostic list.
 * Its eight rows are supplied by HealthDiagnostics, whose provider result is
 * always tied to the saved configuration rather than a Settings draft.
 */
final class ConnectionsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$report = ( new HealthDiagnostics() )->report();
		$checks = $report['checks'];

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-connections data-sp-health-check-count="<?php echo esc_attr( (string) count( $checks ) ); ?>">
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Health', 'ai-site-assistant-autopilot' ),
					__( 'Eight checks that explain any problem before your visitors find it.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Run all checks', 'ai-site-assistant-autopilot' ),
							'type'  => 'button',
							'id'    => 'sp-run-diagnostics',
						),
					)
				);
				OverviewNavigation::render( OverviewNavigation::TAB_HEALTH );
				?>
				<div class="sp-pagebody">
					<div class="sp-health-card" id="sp-health-list" data-sp-health-list aria-live="polite">
						<?php foreach ( $checks as $check ) : ?>
							<?php
							$tone = (string) $check['tone'];
							if ( ! in_array( $tone, array( 'ok', 'warn', 'bad', 'idle' ), true ) ) {
								$tone = 'idle';
							}
							?>
							<div class="sp-health-check sp-health-check--<?php echo esc_attr( $tone ); ?>" data-sp-health-row data-sp-health-id="<?php echo esc_attr( $check['id'] ); ?>">
								<span class="sp-health-check__dot" aria-hidden="true"></span>
								<div class="sp-health-check__body">
									<div class="sp-health-check__label"><?php echo esc_html( $check['label'] ); ?></div>
									<div class="sp-health-check__detail" data-sp-health-detail><?php echo esc_html( $check['detail'] ); ?></div>
								</div>
								<span class="sp-health-check__state" data-sp-health-state><?php echo esc_html( $check['state'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="screen-reader-text" id="sp-health-status" role="status" aria-live="polite"></p>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-conn-i18n',
			array(
				'button'    => __( 'Run all checks', 'ai-site-assistant-autopilot' ),
				'busy'      => __( 'Checking…', 'ai-site-assistant-autopilot' ),
				'checking'  => __( 'Checking…', 'ai-site-assistant-autopilot' ),
				'queued'    => __( 'Queued', 'ai-site-assistant-autopilot' ),
				'waiting'   => __( 'Waiting', 'ai-site-assistant-autopilot' ),
				'completed' => __( 'Eight checks finished.', 'ai-site-assistant-autopilot' ),
				'failed'    => __( 'Health checks could not be completed. Please try again.', 'ai-site-assistant-autopilot' ),
			)
		);
	}
}
