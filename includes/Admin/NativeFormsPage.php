<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Forms\FormOutbox;

/**
 * "Your forms": the simple forms made with Form Studio (native connector).
 *
 * Submissions are emailed and never stored, so the delivery address and its
 * test live on the same screen as the list.
 */
final class NativeFormsPage {

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

		$recipient    = Settings::form_notification_email();
		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$keep         = FormOutbox::enabled();
		$outbox       = ( new FormOutbox() )->list( 100 );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-native-forms>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Your forms', 'ai-site-assistant-autopilot' ),
					$keep
						? __( 'Forms made with Form Studio. Submissions are emailed to you; one whose email fails is kept here until it is sent.', 'ai-site-assistant-autopilot' )
						: __( 'Forms made with Form Studio. Submissions are emailed to you and never stored here.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Create a form', 'ai-site-assistant-autopilot' ),
							'url'   => ContentStudioNavigation::forms_url(),
						),
					)
				);
				?>
				<div class="sp-pagebody sp-native-forms">

					<?php if ( '' === $recipient ) : ?>
						<div class="sp-native-forms__notice" data-sp-form-delivery-missing>
							<span class="sp-native-forms__icon sp-native-forms__icon--warn" aria-hidden="true"><?php echo Ui::icon( 'triangle', 17, 'currentColor', 2.1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div class="sp-native-forms__notice-body">
								<strong><?php esc_html_e( 'No one receives submissions yet', 'ai-site-assistant-autopilot' ); ?></strong>
								<span><?php esc_html_e( 'Forms are not stored here, so set an email address before you publish a form.', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<a class="sp-btn sp-btn--ghost sp-native-forms__notice-action" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Set email', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					<?php else : ?>
						<div class="sp-native-forms__delivery" data-sp-form-delivery>
							<span class="sp-native-forms__icon" aria-hidden="true"><?php echo Ui::icon( 'mail', 16, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div class="sp-native-forms__delivery-body">
								<strong>
									<?php
									printf(
										/* translators: %s: the email address that receives form submissions. */
										esc_html__( 'Submissions email to %s', 'ai-site-assistant-autopilot' ),
										'<bdi class="sp-native-forms__email">' . esc_html( $recipient ) . '</bdi>'
									);
									?>
								</strong>
								<span>
									<?php
									echo esc_html(
										$keep
											? __( 'Only completed entries are sent. A submission whose email fails is kept below until it is sent.', 'ai-site-assistant-autopilot' )
											: __( 'Only completed entries are sent. Nothing is saved in WordPress.', 'ai-site-assistant-autopilot' )
									);
									?>
								</span>
								<span class="sp-native-forms__status" data-sp-form-delivery-status role="status" aria-live="polite"></span>
							</div>
							<button type="button" class="sp-btn sp-btn--ghost sp-native-forms__test" data-sp-form-delivery-test><?php esc_html_e( 'Send a test email', 'ai-site-assistant-autopilot' ); ?></button>
							<a class="sp-native-forms__change" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Change', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					<?php endif; ?>

					<?php if ( array() !== $outbox ) : ?>
						<section class="sp-native-forms__outbox" data-sp-form-outbox aria-labelledby="sp-form-outbox-title">
							<div class="sp-native-forms__outbox-head">
								<span class="sp-native-forms__icon sp-native-forms__icon--warn" aria-hidden="true"><?php echo Ui::icon( 'triangle', 17, 'currentColor', 2.1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<div>
									<h2 id="sp-form-outbox-title" data-sp-form-outbox-title>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: number of form submissions whose email failed. */
												_n( '%s submission could not be emailed', '%s submissions could not be emailed', count( $outbox ), 'ai-site-assistant-autopilot' ),
												number_format_i18n( count( $outbox ) )
											)
										);
										?>
									</h2>
									<p><?php esc_html_e( 'The answers are safe here. They are retried every hour up to 5 times; resend one yourself once the site\'s email works again, for example after setting up an SMTP plugin.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>
							<ul class="sp-native-forms__outbox-list">
								<?php foreach ( $outbox as $kept ) : ?>
									<li class="sp-native-forms__outbox-row" data-sp-outbox-row="<?php echo esc_attr( (string) $kept['id'] ); ?>">
										<div class="sp-native-forms__outbox-main">
											<strong><?php echo esc_html( $kept['form_title'] ); ?></strong>
											<small>
												<?php
												echo esc_html(
													sprintf(
														/* translators: 1: date and time the visitor sent the form, 2: number of send attempts. */
														_n( 'Sent by the visitor %1$s · %2$s attempt', 'Sent by the visitor %1$s · %2$s attempts', $kept['attempts'], 'ai-site-assistant-autopilot' ),
														wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $kept['created_at'] . ' UTC' ) ),
														number_format_i18n( $kept['attempts'] )
													)
												);
												?>
											</small>
											<details>
												<summary><?php esc_html_e( 'Show the answers', 'ai-site-assistant-autopilot' ); ?></summary>
												<pre dir="auto"><?php echo esc_html( $kept['body'] ); ?></pre>
											</details>
										</div>
										<div class="sp-native-forms__outbox-actions">
											<button type="button" class="sp-btn sp-btn--xs" data-sp-outbox-resend="<?php echo esc_attr( (string) $kept['id'] ); ?>"><?php esc_html_e( 'Resend', 'ai-site-assistant-autopilot' ); ?></button>
											<button type="button" class="sp-btn sp-btn--ghost sp-btn--xs" data-sp-outbox-delete="<?php echo esc_attr( (string) $kept['id'] ); ?>"><?php esc_html_e( 'Delete', 'ai-site-assistant-autopilot' ); ?></button>
										</div>
										<span class="sp-native-forms__status" data-sp-outbox-status role="status" aria-live="polite"></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( array() === $forms ) : ?>
						<div class="sp-native-forms__empty" data-sp-native-forms-empty>
							<span class="sp-native-forms__empty-icon" aria-hidden="true"><?php echo Ui::icon( 'form-lines', 22, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<h2><?php esc_html_e( 'No forms yet', 'ai-site-assistant-autopilot' ); ?></h2>
							<p><?php esc_html_e( 'Describe a form in one sentence. You review every field before anything is created.', 'ai-site-assistant-autopilot' ); ?></p>
							<a class="sp-btn" href="<?php echo esc_url( ContentStudioNavigation::forms_url() ); ?>"><?php esc_html_e( 'Describe your first form', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					<?php else : ?>
						<div class="sp-native-forms__table" role="table" aria-label="<?php esc_attr_e( 'Your forms', 'ai-site-assistant-autopilot' ); ?>">
							<div class="sp-native-forms__row sp-native-forms__row--head" role="row">
								<span role="columnheader"><?php esc_html_e( 'Form', 'ai-site-assistant-autopilot' ); ?></span>
								<span role="columnheader"><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></span>
								<span role="columnheader"><?php esc_html_e( 'Shortcode', 'ai-site-assistant-autopilot' ); ?></span>
								<span role="columnheader"><?php esc_html_e( 'Updated', 'ai-site-assistant-autopilot' ); ?></span>
								<span role="columnheader" class="sp-sr"><?php esc_html_e( 'Actions', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<?php foreach ( $forms as $form ) : ?>
								<?php
								$published = 'publish' === $form->post_status;
								$shortcode = sprintf( '[aisaautopilot_form id="%d"]', (int) $form->ID );
								$fields    = $this->field_count( $form );
								$title     = get_the_title( $form );
								?>
								<div class="sp-native-forms__row" role="row" data-sp-native-form="<?php echo esc_attr( (string) $form->ID ); ?>">
									<span role="cell" class="sp-native-forms__name">
										<strong><?php echo esc_html( '' !== $title ? $title : __( '(no title)', 'ai-site-assistant-autopilot' ) ); ?></strong>
										<small>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: number of fields in the form. */
													_n( '%s field', '%s fields', $fields, 'ai-site-assistant-autopilot' ),
													number_format_i18n( $fields )
												)
											);
											?>
										</small>
									</span>
									<span role="cell">
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
									<span role="cell" class="sp-native-forms__code">
										<code dir="ltr"><?php echo esc_html( $shortcode ); ?></code>
										<button type="button" class="sp-native-forms__copy" data-sp-form-copy="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: form title. */ __( 'Copy the shortcode for %s', 'ai-site-assistant-autopilot' ), $title ) ); ?>">
											<span data-sp-form-copy-label><?php esc_html_e( 'Copy', 'ai-site-assistant-autopilot' ); ?></span>
										</button>
									</span>
									<span role="cell" class="sp-native-forms__date"><?php echo esc_html( (string) get_the_modified_date( '', $form ) ); ?></span>
									<span role="cell">
										<a class="sp-btn sp-btn--ghost sp-btn--xs" href="<?php echo esc_url( (string) get_edit_post_link( $form->ID, 'raw' ) ); ?>"><?php esc_html_e( 'Open', 'ai-site-assistant-autopilot' ); ?></a>
									</span>
								</div>
							<?php endforeach; ?>
							<p class="sp-native-forms__foot"><?php esc_html_e( 'Paste a shortcode into any page or post. Open a form to edit it in WordPress.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/** Number of fields in the stored form plan; 0 when the plan is missing. */
	private function field_count( \WP_Post $form ): int {
		$plan = get_post_meta( $form->ID, NativeFormConnector::META_KEY, true );
		return is_array( $plan ) && isset( $plan['fields'] ) && is_array( $plan['fields'] ) ? count( $plan['fields'] ) : 0;
	}
}
