<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\ContentStudioReadModel;

/**
 * Canonical Content Studio History surface.
 *
 * This is intentionally separate from Activity Log. It shows only durable
 * WordPress output actually created by Content Studio, never proposals or
 * operational events from other AutoPilot products.
 */
final class HistoryPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$rows = ( new ContentStudioReadModel() )->history( 50 );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-app--narrow" data-sp-content-studio data-sp-content-tab="history">
			<section class="sp-screen" data-sp-history>
				<?php
				Ui::page_header(
					__( 'Content Studio', 'ai-site-assistant-autopilot' ),
					__( 'Drafts written for your site, in the voice you choose.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'    => ConnectionChip::derive(),
						'action'  => array(
							'label' => __( 'New draft', 'ai-site-assistant-autopilot' ),
							'url'   => ContentStudioNavigation::url( ContentStudioNavigation::TAB_WRITE ),
						),
						'subtabs' => ContentStudioNavigation::subtabs( ContentStudioNavigation::TAB_HISTORY ),
					)
				);
				?>
				<div class="sp-pagebody">
					<section class="sp-card sp-content-history" data-content-history aria-labelledby="aisaautopilot-content-history-title">
						<div class="sp-content-history__head">
							<div class="sp-content-history__heading">
								<h2 id="aisaautopilot-content-history-title"><?php esc_html_e( 'History', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'Recent saved output, usage when recorded, and its WordPress destination', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<a class="sp-btn sp-btn--plain sp-content-history__export" href="<?php echo esc_url( ContentStudioHistoryExport::url() ); ?>">
								<?php esc_html_e( 'Export CSV', 'ai-site-assistant-autopilot' ); ?>
							</a>
						</div>
						<div class="sp-content-history__rows">
							<?php if ( array() === $rows ) : ?>
								<div class="sp-content-history__empty" data-content-history-empty>
									<strong><?php esc_html_e( 'Nothing written yet', 'ai-site-assistant-autopilot' ); ?></strong>
									<p><?php esc_html_e( 'Content Studio drafts appear here after you explicitly create them.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							<?php else : ?>
								<?php foreach ( $rows as $row ) : ?>
									<?php
									$edit_url          = (string) $row['edit_url'];
									$destination_label = sprintf(
										/* translators: %s: WordPress content type. */
										__( 'Open %s in WordPress', 'ai-site-assistant-autopilot' ),
										(string) $row['kind']
									);
									?>
									<article class="sp-content-history__row" data-content-history-row>
										<time class="sp-content-history__when" datetime="<?php echo esc_attr( (string) $row['created_at_iso'] ); ?>">
											<?php echo esc_html( (string) $row['when'] ); ?>
										</time>
										<div class="sp-content-history__body">
											<?php if ( '' !== $edit_url ) : ?>
												<a class="sp-content-history__title" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( $destination_label ); ?>">
													<?php echo esc_html( (string) $row['title'] ); ?>
												</a>
											<?php else : ?>
												<span class="sp-content-history__title"><?php echo esc_html( (string) $row['title'] ); ?></span>
											<?php endif; ?>
											<p class="sp-content-history__meta" dir="auto">
												<?php if ( '' !== (string) ( $row['voice'] ?? '' ) ) : ?>
													<span data-sp-history-voice>
													<?php
													/* translators: %s: Content Studio voice (writing style), such as Formal. */
													echo esc_html( sprintf( __( 'Voice: %s', 'ai-site-assistant-autopilot' ), (string) $row['voice'] ) );
													?>
													</span><span aria-hidden="true">&nbsp;·&nbsp;</span>
												<?php endif; ?>
												<span><?php echo esc_html( (string) $row['template'] ); ?></span><span aria-hidden="true">&nbsp;·&nbsp;</span><span><?php echo esc_html( (string) $row['usage'] ); ?></span>
											</p>
										</div>
										<div class="sp-content-history__state">
											<span class="sp-content-history__destination"><?php echo esc_html( (string) $row['kind'] ); ?></span>
											<?php echo Ui::pill( (string) $row['state'], (string) $row['tone'], false, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::pill() escapes its own text. ?>
										</div>
									</article>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</section>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}
}
