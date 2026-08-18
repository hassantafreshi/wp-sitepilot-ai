<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\NativeFormConnector;

/**
 * A first-class list for forms made by the independent AISAAutoPilot connector.
 */
final class NativeFormsPage {

	private const COLS = '2fr 1fr 1.4fr 1fr auto';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$forms = get_posts(
			array(
				'post_type'      => NativeFormConnector::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen">
				<div class="sp-page-head">
					<div>
						<?php Ui::title( __( 'AISAAutoPilot Forms', 'ai-site-assistant-autopilot' ), 'form' ); ?>
						<p class="sp-lead sp-mb0"><?php esc_html_e( 'Manage the simple, single-step forms created by AISAAutoPilot. Visitor submissions are sent by email and are not stored here.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<a class="sp-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER ) ); ?>">
						<?php echo Ui::icon( 'spark', 16, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php esc_html_e( 'Create a form', 'ai-site-assistant-autopilot' ); ?></span>
					</a>
				</div>

				<div class="sp-card sp-card--bare sp-card--flat">
					<?php if ( array() === $forms ) : ?>
						<div class="sp-empty">
							<span class="sp-empty__icon">📄</span>
							<?php esc_html_e( 'No AISAAutoPilot forms have been created yet.', 'ai-site-assistant-autopilot' ); ?>
						</div>
					<?php else : ?>
						<div class="sp-tr sp-tr--head" style="--sp-cols:<?php echo esc_attr( self::COLS ); ?>">
							<span><?php esc_html_e( 'Form', 'ai-site-assistant-autopilot' ); ?></span>
							<span><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></span>
							<span><?php esc_html_e( 'Shortcode', 'ai-site-assistant-autopilot' ); ?></span>
							<span><?php esc_html_e( 'Last updated', 'ai-site-assistant-autopilot' ); ?></span>
							<span class="sp-sr"><?php esc_html_e( 'Actions', 'ai-site-assistant-autopilot' ); ?></span>
						</div>
						<?php foreach ( $forms as $form ) : ?>
							<?php $published = 'publish' === $form->post_status; ?>
							<div class="sp-tr" style="--sp-cols:<?php echo esc_attr( self::COLS ); ?>">
								<span class="sp-td sp-td--strong sp-td--clip"><?php echo esc_html( get_the_title( $form ) ); ?></span>
								<span class="sp-td">
									<?php
									echo wp_kses_post(
										Ui::pill(
											$published ? __( 'Published', 'ai-site-assistant-autopilot' ) : __( 'Draft', 'ai-site-assistant-autopilot' ),
											$published ? 'ok' : 'off',
											true,
											'sp-pill--sm'
										)
									);
									?>
								</span>
								<span class="sp-td sp-mono sp-td--clip"><?php echo esc_html( sprintf( '[aisaautopilot_form id="%d"]', (int) $form->ID ) ); ?></span>
								<span class="sp-td sp-td--muted sp-td--nowrap"><?php echo esc_html( (string) get_the_modified_date( '', $form ) ); ?></span>
								<span class="sp-td">
									<a class="sp-btn sp-btn--ghost sp-btn--xs" href="<?php echo esc_url( (string) get_edit_post_link( $form->ID, 'raw' ) ); ?>"><?php esc_html_e( 'Open', 'ai-site-assistant-autopilot' ); ?></a>
								</span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}
}
