<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Core\Settings;

/**
 * Compact, mockup-aligned Widget surface within the Answer Desk entry route.
 */
final class AnswerDeskWidgetPage {

	/** @var array<int, string> */
	private const ACCENTS = array( '#202a8d', '#ff455f', '#198754', '#14152b' );

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$widget       = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		$brand        = sanitize_hex_color( (string) ( $widget['color'] ?? '' ) ) ?: '#202a8d';
		$theme        = 'dark' === ( $widget['theme'] ?? 'light' ) ? 'dark' : 'light';
		$derived      = Settings::widget_derived_colors( $brand, $theme );
		$custom       = ! empty( $widget['custom_colors'] );
		$colors       = $custom ? array_merge( $derived, (array) ( $widget['colors'] ?? array() ) ) : $derived;
		$preview      = sanitize_hex_color( (string) ( $colors['head_from'] ?? '' ) ) ?: $brand;
		$integration  = ! empty( Settings::get( 'public_assistant_enabled', false ) );
		$position     = (string) ( $widget['position'] ?? 'bottom-right' );
		$position     = in_array( $position, array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ? $position : 'bottom-right';
		$position_msg = 'bottom-right' === $position
			? __( 'Current position: bottom right.', 'ai-site-assistant-autopilot' )
			: sprintf(
				/* translators: %s: current widget position. */
				__( 'Current position: %s. Change it in Advanced settings.', 'ai-site-assistant-autopilot' ),
				$this->position_label( $position )
			);

		Ui::grad_defs();
		?>
		<div
			class="wrap sp-app"
			data-sp-answer-desk
			data-sp-answer-tab="widget"
			data-sp-widget-appearance
			data-sp-widget-enabled="<?php echo ! empty( $widget['enabled'] ) ? '1' : '0'; ?>"
			data-sp-widget-color="<?php echo esc_attr( $brand ); ?>"
			data-sp-widget-custom-colors="<?php echo $custom ? '1' : '0'; ?>"
			data-sp-widget-integration="<?php echo $integration ? 'on' : 'off'; ?>"
		>
			<section class="sp-screen">
			<?php
			Ui::page_header(
				__( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				__( 'The support chat on your site, and every conversation it handled.', 'ai-site-assistant-autopilot' ),
				array(
					'chip'    => ConnectionChip::derive(),
					'action'  => array(
						'label' => __( 'Widget settings', 'ai-site-assistant-autopilot' ),
						'url'   => AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ),
					),
					'subtabs' => AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_WIDGET ),
				)
			);
			?>
			<div class="sp-pagebody">
				<?php if ( ! $integration ) : ?>
					<?php Ui::alert( 'warn', 'warning', esc_html__( 'Answer Desk is off in Integrations. You can save the widget appearance here, but visitors will not see it until the integration is enabled.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<div class="sp-answer-widget-grid">
					<section class="sp-card sp-answer-widget-card" data-sp-answer-widget-controls aria-labelledby="sp-answer-widget-appearance-title">
						<h2 class="sp-h3" id="sp-answer-widget-appearance-title"><?php esc_html_e( 'Appearance & behaviour', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-answer-widget-rows">
							<div class="sp-answer-widget-row">
								<button class="sp-answer-widget-switch<?php echo ! empty( $widget['enabled'] ) ? ' is-on' : ''; ?>" type="button" role="switch" aria-checked="<?php echo ! empty( $widget['enabled'] ) ? 'true' : 'false'; ?>" data-sp-widget-enabled aria-label="<?php esc_attr_e( 'Show the widget on your public site', 'ai-site-assistant-autopilot' ); ?>">
									<span aria-hidden="true"></span>
								</button>
								<div class="sp-answer-widget-row__body">
									<strong><?php esc_html_e( 'Show the widget on your public site', 'ai-site-assistant-autopilot' ); ?></strong>
									<p><?php esc_html_e( 'This controls visitor visibility. Answer Desk must also be enabled in Integrations.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>

							<div class="sp-answer-widget-row">
								<span class="sp-answer-widget-row__mark<?php echo 'bottom-right' === $position ? ' is-on' : ''; ?>" aria-hidden="true"></span>
								<div class="sp-answer-widget-row__body">
									<strong><?php esc_html_e( 'Sit in the bottom-right corner', 'ai-site-assistant-autopilot' ); ?></strong>
									<p><?php echo esc_html( $position_msg ); ?></p>
								</div>
								<a class="sp-answer-widget-row__link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIDGET . '#answer-desk-widget' ) ); ?>"><?php esc_html_e( 'Advanced', 'ai-site-assistant-autopilot' ); ?></a>
							</div>

							<div class="sp-answer-widget-row is-locked" data-sp-widget-theme-locked>
								<span class="sp-answer-widget-row__mark" aria-hidden="true"></span>
								<div class="sp-answer-widget-row__body">
									<strong><?php esc_html_e( 'Match my theme colours', 'ai-site-assistant-autopilot' ); ?></strong>
									<p><?php esc_html_e( 'Automatic theme detection is not available. The standard palette below is used instead.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
								<span class="sp-answer-widget-state"><?php esc_html_e( 'Manual palette', 'ai-site-assistant-autopilot' ); ?></span>
							</div>

							<div class="sp-answer-widget-row" data-sp-widget-sources-state>
								<span class="sp-answer-widget-row__mark is-on" aria-hidden="true"></span>
								<div class="sp-answer-widget-row__body">
									<strong><?php esc_html_e( 'Show the source page under each answer', 'ai-site-assistant-autopilot' ); ?></strong>
									<p><?php esc_html_e( 'Sources appear automatically, but only when a response contains a real saved citation.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
								<span class="sp-answer-widget-state is-on"><?php esc_html_e( 'Automatic', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
						</div>

						<div class="sp-answer-widget-accent" aria-labelledby="sp-answer-widget-accent-title">
							<h3 id="sp-answer-widget-accent-title"><?php esc_html_e( 'Accent colour', 'ai-site-assistant-autopilot' ); ?></h3>
							<div class="sp-answer-widget-swatches" role="group" aria-label="<?php esc_attr_e( 'Accent colour', 'ai-site-assistant-autopilot' ); ?>">
								<?php foreach ( self::ACCENTS as $accent ) : ?>
									<button type="button" class="sp-answer-widget-swatch<?php echo strtolower( $brand ) === $accent ? ' is-on' : ''; ?>" data-sp-widget-color="<?php echo esc_attr( $accent ); ?>" aria-pressed="<?php echo strtolower( $brand ) === $accent ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $accent ); ?>" <?php disabled( $custom ); ?>><span style="background:<?php echo esc_attr( $accent ); ?>"></span></button>
								<?php endforeach; ?>
							</div>
							<?php if ( $custom ) : ?>
								<p class="sp-answer-widget-note" data-sp-widget-custom-warning><?php esc_html_e( 'An advanced custom palette is active, so it overrides the standard accent choices. Edit or reset it in Advanced settings.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php else : ?>
								<p class="sp-answer-widget-note"><?php esc_html_e( 'Selecting a colour saves it immediately and updates this preview.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php endif; ?>
						</div>
						<p class="sp-answer-widget-save" data-sp-widget-save-status role="status" aria-live="polite"></p>
					</section>

					<section class="sp-card sp-answer-widget-preview" data-sp-widget-preview aria-labelledby="sp-answer-widget-preview-title" style="--sp-answer-widget-accent:<?php echo esc_attr( $preview ); ?>">
						<h2 class="sp-h3" id="sp-answer-widget-preview-title"><?php esc_html_e( 'Live preview', 'ai-site-assistant-autopilot' ); ?></h2>
						<figure class="sp-answer-widget-preview__frame" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
							<div class="sp-answer-widget-preview__head">
								<strong><?php echo esc_html( (string) $widget['title'] ); ?></strong>
								<span><?php echo esc_html( (string) $widget['subtitle'] ); ?></span>
							</div>
							<div class="sp-answer-widget-preview__messages" aria-hidden="true">
								<p class="sp-answer-widget-preview__question"><?php esc_html_e( 'Do you ship to Canada?', 'ai-site-assistant-autopilot' ); ?></p>
								<p class="sp-answer-widget-preview__reply"><?php esc_html_e( 'Yes — Canada Post, 5–8 business days, free over $80.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<div class="sp-answer-widget-preview__composer" aria-hidden="true"><?php esc_html_e( 'Type your question...', 'ai-site-assistant-autopilot' ); ?></div>
							<figcaption class="screen-reader-text"><?php esc_html_e( 'Preview only. It does not send a visitor message.', 'ai-site-assistant-autopilot' ); ?></figcaption>
						</figure>
					</section>
				</div>
			</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	private function position_label( string $position ): string {
		return ucwords( str_replace( '-', ' ', $position ) );
	}
}
