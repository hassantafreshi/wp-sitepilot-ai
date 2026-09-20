<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Content\ContentStudioReadModel;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * WP-02 / WP-03 / WP-04 — compose, review, result.
 *
 * Three mutually exclusive stages. JavaScript moves between them; only one is
 * ever visible, exactly like the wireframe's screen switch.
 */
final class CopilotPage {


	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}
		$view = ContentStudioNavigation::current_view();
		if ( ContentStudioNavigation::TAB_WRITE === $view ) {
			$this->render_content_drafts();
			return;
		}
		if ( ContentStudioNavigation::TAB_TEMPLATES === $view ) {
			$this->render_templates();
			return;
		}
		if ( ContentStudioNavigation::TAB_HISTORY === $view ) {
			( new HistoryPage() )->render();
			return;
		}

		$this->render_forms();
	}

	/**
	 * Form Studio (D-23, 2026-09-17; formerly the "Form Composer" mockup name).
	 *
	 * Registered directly as the Form Studio top-level page and also reached
	 * as Content Studio's `mode=forms` deep link via `render()` above, so old
	 * bookmarks and generated links keep resolving unchanged. Three mutually
	 * exclusive stages — compose, review, result. JavaScript moves between
	 * them; only one is ever visible, exactly like the wireframe's screen switch.
	 */
	public function render_forms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$configured   = ( new ProviderManager() )->is_configured();
		$recipient    = Settings::form_notification_email();
		$efb_ready    = ( new EasyFormBuilderConnector() )->is_available();
		$form_enabled = (bool) Settings::get( 'form_composer_enabled', true );
		$ready        = $form_enabled && $configured && '' !== $recipient;

		$settings_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$integrations_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS );
		$efb_url          = admin_url( 'admin.php?page=' . AdminMenu::SLUG_EFB );
		$disabled_title   = ! $form_enabled
			? __( 'Form Studio is turned off', 'ai-site-assistant-autopilot' )
			: ( $configured ? __( 'Form delivery is not configured', 'ai-site-assistant-autopilot' ) : __( 'AI provider unavailable', 'ai-site-assistant-autopilot' ) );
		$disabled_note    = ! $form_enabled
			? __( 'Turn it on in Integrations before building a form plan.', 'ai-site-assistant-autopilot' )
			: ( $configured ? __( 'Set the notification email before a plan can be built.', 'ai-site-assistant-autopilot' ) : __( 'Connect an AI provider before a preview can be built.', 'ai-site-assistant-autopilot' ) );
		$disabled_url     = ! $form_enabled ? $integrations_url : $settings_url;
		$disabled_action  = ! $form_enabled ? __( 'Open Integrations', 'ai-site-assistant-autopilot' ) : __( 'Check connection', 'ai-site-assistant-autopilot' );

		// A prompt handed over from the Home quick-start box.
		$seed = isset( $_GET['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_GET['prompt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of a textarea.

		$examples = array(
			__( 'Create a contact form with name, email, phone, and message.', 'ai-site-assistant-autopilot' ),
			__( 'Build a job application form with full name, email, phone, and a cover letter.', 'ai-site-assistant-autopilot' ),
			__( 'Make a newsletter signup with first name and email.', 'ai-site-assistant-autopilot' ),
			__( 'Support request form with name, email, priority (low, medium, high) and details.', 'ai-site-assistant-autopilot' ),
		);
		// Short chip labels (mockup) that still insert the full, reviewable prompt.
		$example_chips = array(
			array( __( 'A contact form', 'ai-site-assistant-autopilot' ), __( 'Contact form', 'ai-site-assistant-autopilot' ), $examples[0] ),
			array( __( 'A newsletter signup', 'ai-site-assistant-autopilot' ), __( 'Newsletter signup', 'ai-site-assistant-autopilot' ), $examples[2] ),
			array( __( 'A support request', 'ai-site-assistant-autopilot' ), __( 'Support request', 'ai-site-assistant-autopilot' ), $examples[3] ),
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-form-composer" data-aisaautopilot-copilot>
			<header class="sp-fc-header">
				<span class="sp-fc-header__icon" aria-hidden="true"><?php echo Ui::icon( 'form', 18, '#fff', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-fc-header__titles">
					<h1><?php esc_html_e( 'Form Studio', 'ai-site-assistant-autopilot' ); ?></h1>
					<p><?php esc_html_e( 'Describe a form, review every field, then create it. Simple fields, emailed to you.', 'ai-site-assistant-autopilot' ); ?></p>
				</div>
				<span class="sp-fc-header__badge"><?php esc_html_e( 'Native form · simple fields', 'ai-site-assistant-autopilot' ); ?></span>
				<button type="button" class="sp-fc-header__new" data-sp-form-new><?php esc_html_e( 'New form', 'ai-site-assistant-autopilot' ); ?></button>
			</header>

			<!-- ── WP-02 compose ─────────────────────────────────── -->
			<section id="aisaautopilot-compose-screen" class="sp-screen">
				<div class="sp-fc-layout">
					<main class="sp-fc-canvas" data-sp-form-canvas aria-labelledby="sp-fc-canvas-title">
						<div class="sp-fc-canvas__inner">
							<?php if ( '' !== $recipient ) : ?>
								<a class="sp-fc-delivery" data-sp-form-delivery href="<?php echo esc_url( $settings_url ); ?>">
									<span class="sp-fc-delivery__icon" aria-hidden="true"><?php echo Ui::icon( 'mail', 17, '#202a8d', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
									<span><strong><?php /* translators: %s: configured form notification email. */ printf( esc_html__( 'Submissions email to %s', 'ai-site-assistant-autopilot' ), esc_html( $recipient ) ); ?></strong><small><?php esc_html_e( 'The recipient is set by you and cannot be changed by the AI.', 'ai-site-assistant-autopilot' ); ?></small></span>
									<b><?php esc_html_e( 'Change', 'ai-site-assistant-autopilot' ); ?></b>
								</a>
							<?php endif; ?>
							<section class="sp-fc-empty" data-sp-form-empty>
								<span class="sp-fc-empty__icon" aria-hidden="true"><?php echo Ui::icon( 'form', 27, '#202a8d', 1.7 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
								<h2 id="sp-fc-canvas-title"><?php esc_html_e( 'Describe your form', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'Say what you need in one line. The assistant drafts a plan here; you review every field before anything is created.', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-fc-empty__examples" id="aisaautopilot-examples">
									<?php foreach ( $example_chips as $chip ) : ?>
										<button type="button" class="sp-chip" data-example="<?php echo esc_attr( $chip[2] ); ?>" title="<?php echo esc_attr( $chip[2] ); ?>"><?php echo esc_html( $chip[0] ); ?></button>
									<?php endforeach; ?>
								</div>
							</section>
							<div class="sp-fc-advanced">
								<span class="sp-fc-advanced__icon" aria-hidden="true"><?php echo Ui::icon( 'database', 16, '#5b5e7d', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
								<span><strong><?php esc_html_e( 'Need file uploads, payments, multi-step or logic?', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'Those features belong in Easy Form Builder. Native forms stay simple and email-only.', 'ai-site-assistant-autopilot' ); ?></small></span>
								<?php if ( $efb_ready ) : ?>
									<a href="<?php echo esc_url( $efb_url ); ?>"><?php esc_html_e( 'Open EFB', 'ai-site-assistant-autopilot' ); ?></a>
								<?php else : ?>
									<span class="sp-fc-advanced__locked"><?php esc_html_e( 'EFB unavailable', 'ai-site-assistant-autopilot' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</main>

					<aside class="sp-fc-assistant" data-sp-form-assistant aria-labelledby="sp-fc-assistant-title">
						<header class="sp-fc-assistant__head">
							<span aria-hidden="true"><?php echo Ui::icon( 'shield', 19, '#fff', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
							<span><strong id="sp-fc-assistant-title"><?php esc_html_e( 'Form Assistant', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'New native form', 'ai-site-assistant-autopilot' ); ?></small></span>
						</header>
						<div class="sp-fc-assistant__thread">
							<p><?php esc_html_e( 'Describe the form you need. I will build a reviewable plan and will not create anything until you approve it.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php if ( $ready ) : ?>
								<div class="sp-fc-assistant__chips" data-sp-fc-suggestions>
									<?php foreach ( $example_chips as $chip ) : ?>
										<button type="button" class="sp-chip" data-example="<?php echo esc_attr( $chip[2] ); ?>" title="<?php echo esc_attr( $chip[2] ); ?>"><?php echo esc_html( $chip[1] ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<div class="sp-fc-assistant__footer">
						<div class="sp-fc-composer">

							<div class="sp-composer">
								<label class="sp-sr" for="aisaautopilot-prompt"><?php esc_html_e( 'Describe the form you want to create', 'ai-site-assistant-autopilot' ); ?></label>
								<textarea id="aisaautopilot-prompt" placeholder="<?php esc_attr_e( 'Describe the form you need — fields, purpose and where it will be used.', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>><?php echo esc_textarea( $seed ); ?></textarea>
								<div class="sp-composer__bar">
									<button type="button" id="aisaautopilot-voice-toggle" class="sp-btn--icon" aria-pressed="false" aria-label="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>>
										<?php echo Ui::icon( 'mic', 16, '#1d2327' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<span class="sp-sr aisaautopilot-voice-button__label"><?php esc_html_e( 'Speak', 'ai-site-assistant-autopilot' ); ?></span>
									</button>
									<span id="aisaautopilot-status" class="sp-composer__hint" role="status" aria-live="polite"><?php esc_html_e( 'Ctrl + Enter to submit', 'ai-site-assistant-autopilot' ); ?></span>
					<button type="button" id="aisaautopilot-generate" class="sp-fc-send" <?php disabled( ! $ready ); ?>><?php echo Ui::icon( 'send', 17, '#fff', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span class="sp-sr"><?php esc_html_e( 'Generate preview', 'ai-site-assistant-autopilot' ); ?></span></button>
								</div>
							</div>

		<?php if ( ! $ready ) : ?>
								<div class="sp-notice sp-notice--err" style="margin-top:11px">
			<?php echo Ui::icon( 'alert-circle', 16, '#dc2626', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<div class="sp-notice__body">
										<div class="sp-notice__title" style="color:#b91c1c">
			<?php echo esc_html( $disabled_title ); ?>
										</div>
										<div class="sp-notice__text">
			<?php echo esc_html( $disabled_note ); ?>
										</div>
									</div>
									<a class="sp-btn sp-btn--danger sp-btn--xs" href="<?php echo esc_url( $disabled_url ); ?>"><?php echo esc_html( $disabled_action ); ?></a>
								</div>
		<?php endif; ?>
						</div>

						<div id="aisaautopilot-generation-progress" class="sp-gen" hidden aria-live="polite">
							<div class="sp-gen__head">
								<span class="sp-spinner sp-spinner--18"></span>
								<span><?php esc_html_e( 'Building a reviewable plan', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<div class="sp-stack sp-stack--md">
								<div class="sp-gen__step" data-gen-step="1"><span class="sp-mark">&bull;</span><span><?php esc_html_e( 'Analysing your request', 'ai-site-assistant-autopilot' ); ?></span></div>
								<div class="sp-gen__step" data-gen-step="2"><span class="sp-mark">&bull;</span><span><?php esc_html_e( 'Running safety checks', 'ai-site-assistant-autopilot' ); ?></span></div>
								<div class="sp-gen__step" data-gen-step="3"><span class="sp-mark">&bull;</span><span><?php esc_html_e( 'Building the preview', 'ai-site-assistant-autopilot' ); ?></span></div>
							</div>
							<p class="sp-help sp-help--12" style="margin-top:14px"><?php esc_html_e( 'You can keep working; we’ll show the plan here when it’s ready.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<p class="sp-fc-assistant__safety"><?php esc_html_e( 'Drafts a plan you approve. Nothing is created on its own.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</aside>
				</div>
			</section>

			<!-- ── WP-03 review ──────────────────────────────────── -->
			<section id="aisaautopilot-review-screen" class="sp-screen" hidden>
				<div id="aisaautopilot-review-content"></div>
			</section>

			<!-- ── WP-04 result ──────────────────────────────────── -->
			<section id="aisaautopilot-result-screen" class="sp-screen" hidden>
				<div id="aisaautopilot-result-content"></div>
			</section>

			<!-- Publish confirmation, only for the higher-risk publish path. -->
			<div id="sp-confirm" class="sp-modal" hidden>
				<div class="sp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sp-confirm-title">
					<h2 class="sp-modal__title" id="sp-confirm-title"><?php esc_html_e( 'Publish this form immediately?', 'ai-site-assistant-autopilot' ); ?></h2>
					<p class="sp-modal__text"><?php esc_html_e( 'The form becomes visible to site visitors as soon as you confirm. You can unpublish it later.', 'ai-site-assistant-autopilot' ); ?></p>
					<label class="sp-modal__ack">
						<input type="checkbox" id="sp-confirm-ack" />
						<span>
			<?php
			printf(
			/* translators: %s: site host name. */
				esc_html__( 'I understand this creates a publicly visible form on %s', 'ai-site-assistant-autopilot' ),
				esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) )
			);
			?>
						</span>
					</label>
					<div class="sp-modal__foot">
						<button type="button" class="sp-btn sp-btn--ghost" data-sp-confirm-cancel><?php esc_html_e( 'Cancel', 'ai-site-assistant-autopilot' ); ?></button>
						<button type="button" class="sp-btn sp-btn--confirm" id="sp-confirm-go" disabled><?php esc_html_e( 'Approve & publish', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
				</div>
			</div>

		<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-copilot-boot',
			array(
				'historyUrl' => admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ),
				'efbUrl'     => $efb_url,
				'efbReady'   => $efb_ready,
				'i18n'       => self::strings(),
			)
		);
	}

	/**
	 * Content Studio’s safe post/page-draft flow, separate from native forms.
	 */
	private function render_content_drafts(): void {
		$content_enabled  = (bool) Settings::get( 'content_studio_enabled', true );
		$cap_reached      = ( new UsageTracker() )->is_exhausted();
		$ready            = $content_enabled && ! $cap_reached && ( new ProviderManager() )->is_configured();
		$drafts           = ( new ContentStudioReadModel() )->recent_drafts( 3 );
		$has_drafts       = array() !== $drafts;
		$brief            = ContentStudioNavigation::write_brief();
		$source_gap       = ContentStudioNavigation::write_source_gap();
		$gap              = '' !== $source_gap ? ( new \AISAAutoPilot\Content\KbGaps() )->get( $source_gap ) : null;
		$source_gap       = null !== $gap ? $source_gap : '';
		$integrations_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS );
		$usage_url        = admin_url( 'admin.php?page=' . AdminMenu::SLUG_USAGE );
		$disabled_title   = ! $content_enabled
			? __( 'Content Studio is turned off', 'ai-site-assistant-autopilot' )
			: ( $cap_reached ? __( 'AI usage cap reached', 'ai-site-assistant-autopilot' ) : __( 'AI provider unavailable', 'ai-site-assistant-autopilot' ) );
		$disabled_note    = ! $content_enabled
			? __( 'Turn it on in Integrations before creating a reviewable draft.', 'ai-site-assistant-autopilot' )
			: ( $cap_reached ? __( 'Increase or reset the usage budget before building a draft.', 'ai-site-assistant-autopilot' ) : __( 'Connect an AI provider before a draft can be built.', 'ai-site-assistant-autopilot' ) );
		$disabled_url     = ! $content_enabled ? $integrations_url : ( $cap_reached ? $usage_url : admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP ) );
		$disabled_action  = ! $content_enabled
			? __( 'Open Integrations', 'ai-site-assistant-autopilot' )
			: ( $cap_reached ? __( 'Open Usage', 'ai-site-assistant-autopilot' ) : __( 'Check connection', 'ai-site-assistant-autopilot' ) );
		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-app--narrow" data-sp-content-studio data-sp-content-tab="write" data-aisaautopilot-content data-content-has-drafts="<?php echo $has_drafts ? 'true' : 'false'; ?>">
			<section class="sp-screen">
		<?php
		Ui::page_header(
			__( 'Content Studio', 'ai-site-assistant-autopilot' ),
			__( 'Drafts written in the voice it learned from your site.', 'ai-site-assistant-autopilot' ),
			array(
				'chip'    => ConnectionChip::derive(),
				'action'  => array(
					'label' => __( 'New draft', 'ai-site-assistant-autopilot' ),
					'url'   => ContentStudioNavigation::url( ContentStudioNavigation::TAB_WRITE ),
				),
				'subtabs' => ContentStudioNavigation::subtabs( ContentStudioNavigation::TAB_WRITE ),
			)
		);
		?>
		<div class="sp-pagebody sp-content-write">
				<div class="sp-content-empty" data-content-empty<?php echo $has_drafts ? ' hidden="hidden"' : ''; ?>>
					<div class="sp-content-empty__icon" aria-hidden="true">
						<?php echo Ui::icon( 'doc', 21, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
					</div>
					<h2 class="sp-content-empty__title"><?php esc_html_e( 'Nothing written yet', 'ai-site-assistant-autopilot' ); ?></h2>
					<p class="sp-content-empty__copy"><?php esc_html_e( 'Describe what you need in the box below and Content Studio writes a first draft in the voice it learned from your site. Drafts are never published on their own.', 'ai-site-assistant-autopilot' ); ?></p>
				</div>

				<div class="sp-card sp-content-compose" data-content-compose data-content-source-gap="<?php echo esc_attr( $source_gap ); ?>">
					<?php if ( null !== $gap ) : ?>
						<p class="sp-content-compose__source" data-content-source-note>
							<?php echo Ui::icon( 'chat', 14, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span>
								<?php esc_html_e( 'Drafting an answer to:', 'ai-site-assistant-autopilot' ); ?>
								<bdi dir="auto"><?php echo esc_html( $gap['question'] ); ?></bdi>
							</span>
						</p>
					<?php endif; ?>
					<label class="sp-content-compose__label" for="aisaautopilot-content-prompt"><?php esc_html_e( 'What should it write?', 'ai-site-assistant-autopilot' ); ?></label>
					<textarea id="aisaautopilot-content-prompt" class="sp-textarea sp-content-compose__prompt" rows="4" maxlength="6000" placeholder="<?php esc_attr_e( 'A post about why multi-step forms convert better, for shop owners, around 900 words…', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>><?php echo esc_textarea( $brief ); ?></textarea>
					<div class="sp-content-compose__footer">
						<div class="sp-content-voice" aria-label="<?php esc_attr_e( 'Voice', 'ai-site-assistant-autopilot' ); ?>">
							<span class="sp-content-voice__label"><?php esc_html_e( 'Voice', 'ai-site-assistant-autopilot' ); ?></span>
							<span class="sp-content-voice__chip sp-content-voice__chip--active"><?php esc_html_e( 'Learned from your site', 'ai-site-assistant-autopilot' ); ?></span>
							<span class="sp-content-voice__chip sp-content-voice__chip--locked"><?php esc_html_e( 'Formal', 'ai-site-assistant-autopilot' ); ?></span>
							<span class="sp-content-voice__chip sp-content-voice__chip--locked"><?php esc_html_e( 'Plain', 'ai-site-assistant-autopilot' ); ?></span>
						</div>
						<button type="button" class="sp-btn" id="aisaautopilot-content-plan" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Write a draft', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
					<span class="sp-help sp-help--12 sp-content-compose__status" id="aisaautopilot-content-status" role="status" aria-live="polite"></span>
					<?php if ( ! $ready ) : ?>
						<div class="sp-notice sp-notice--err sp-content-compose__notice">
							<?php echo Ui::icon( 'alert-circle', 16, '#dc2626', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<div class="sp-notice__body">
								<div class="sp-notice__title" style="color:#b91c1c"><?php echo esc_html( $disabled_title ); ?></div>
								<div class="sp-notice__text"><?php echo esc_html( $disabled_note ); ?></div>
							</div>
							<a class="sp-btn sp-btn--danger sp-btn--xs" href="<?php echo esc_url( $disabled_url ); ?>"><?php echo esc_html( $disabled_action ); ?></a>
						</div>
					<?php endif; ?>
				</div>

				<div class="sp-card sp-content-review" data-content-preview hidden>
					<div class="sp-card__split">
						<h2 class="sp-h2 sp-mb0"><?php esc_html_e( 'Review the draft before saving', 'ai-site-assistant-autopilot' ); ?></h2>
						<?php echo Ui::pill( __( 'Draft only', 'ai-site-assistant-autopilot' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Ui. ?>
					</div>
					<div class="sp-stack sp-stack--md">
						<div><span class="sp-label"><?php esc_html_e( 'Title', 'ai-site-assistant-autopilot' ); ?></span><p data-content-title></p></div>
						<div><span class="sp-label"><?php esc_html_e( 'URL slug', 'ai-site-assistant-autopilot' ); ?></span><p class="sp-mono" data-content-slug></p></div>
						<div><span class="sp-label"><?php esc_html_e( 'Meta description', 'ai-site-assistant-autopilot' ); ?></span><p data-content-meta></p></div>
						<div><span class="sp-label"><?php esc_html_e( 'Body', 'ai-site-assistant-autopilot' ); ?></span><div class="sp-content-preview" data-content-body></div></div>
					</div>
					<div class="sp-btn-group sp-content-review__actions"><button type="button" class="sp-btn" data-content-approve><?php esc_html_e( 'Approve & create draft', 'ai-site-assistant-autopilot' ); ?></button><button type="button" class="sp-btn sp-btn--ghost" data-content-restart><?php esc_html_e( 'Start over', 'ai-site-assistant-autopilot' ); ?></button></div>
				</div>

				<div class="sp-card sp-card--ok sp-content-result" data-content-result hidden></div>

				<section class="sp-content-recent" data-content-recent aria-labelledby="aisaautopilot-content-recent-heading">
					<h2 class="sp-content-recent__heading" id="aisaautopilot-content-recent-heading"><?php esc_html_e( 'Recent drafts', 'ai-site-assistant-autopilot' ); ?></h2>
					<div data-content-recent-list>
					<?php foreach ( $drafts as $draft ) : ?>
						<div class="sp-content-recent__row" data-content-recent-row>
							<div class="sp-content-recent__main">
								<div class="sp-content-recent__title"><?php echo esc_html( $draft['title'] ); ?></div>
								<div class="sp-content-recent__meta"><?php echo esc_html( $draft['kind'] . ' · ' . $draft['when'] ); ?></div>
							</div>
							<span class="sp-content-recent__state sp-content-recent__state--<?php echo esc_attr( $draft['tone'] ); ?>"><?php echo esc_html( $draft['state'] ); ?></span>
						</div>
					<?php endforeach; ?>
					</div>
				</section>
		</div>
			</section>
		</div>
		<?php
	}

	private function render_templates(): void {
		$templates = array(
			array(
				'name'  => __( 'Product description', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Write 60–90 words for {product}. Lead with the material, then the use. No superlatives.', 'ai-site-assistant-autopilot' ),
				'brief' => __( 'Write 60–90 words for {product}. Lead with the material, then the use. No superlatives.', 'ai-site-assistant-autopilot' ),
			),
			array(
				'name'  => __( 'Blog post outline', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Outline a post on {topic} for {audience}. Six headings, one sentence each.', 'ai-site-assistant-autopilot' ),
				'brief' => __( 'Outline a post on {topic} for {audience}. Six headings, one sentence each.', 'ai-site-assistant-autopilot' ),
			),
			array(
				'name'  => __( 'Meta description', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Summarise {page} in under 155 characters. Say what the reader gets.', 'ai-site-assistant-autopilot' ),
				'brief' => __( 'Summarise {page} in under 155 characters. Say what the reader gets.', 'ai-site-assistant-autopilot' ),
			),
			array(
				'name'  => __( 'Support answer', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Answer {question} using only {sources}. If the answer is not there, say so.', 'ai-site-assistant-autopilot' ),
				'brief' => __( 'Answer {question} using only {sources}. If the answer is not there, say so.', 'ai-site-assistant-autopilot' ),
			),
		);
		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-app--narrow" data-sp-content-studio data-sp-content-tab="templates">
			<section class="sp-screen">
		<?php
		Ui::page_header(
			__( 'Content Studio', 'ai-site-assistant-autopilot' ),
			__( 'Reusable writing starting points.', 'ai-site-assistant-autopilot' ),
			array(
				'chip'    => ConnectionChip::derive(),
				'action'  => array(
					'label' => __( 'New draft', 'ai-site-assistant-autopilot' ),
					'url'   => ContentStudioNavigation::url( ContentStudioNavigation::TAB_WRITE ),
				),
				'subtabs' => ContentStudioNavigation::subtabs( ContentStudioNavigation::TAB_TEMPLATES ),
			)
		);
		?>
		<div class="sp-pagebody sp-content-templates">
				<div class="sp-content-template-grid">
		<?php foreach ( $templates as $template ) : ?>
						<article class="sp-card sp-content-template" data-content-template>
							<div class="sp-content-template__head">
								<h2 class="sp-content-template__title"><?php echo esc_html( $template['name'] ); ?></h2>
								<span class="sp-content-template__source"><?php esc_html_e( 'Built-in', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<div class="sp-content-template__body sp-mono" dir="auto"><?php echo esc_html( $template['body'] ); ?></div>
							<a class="sp-btn sp-btn--plain sp-content-template__cta" href="<?php echo esc_url( ContentStudioNavigation::write_url( $template['brief'] ) ); ?>"><?php esc_html_e( 'Use template', 'ai-site-assistant-autopilot' ); ?></a>
						</article>
		<?php endforeach; ?>
				</div>
		</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Every string the review/result renderer needs, translated server-side.
	 *
	 * @return array<string, string>
	 */
	private static function strings(): array {
		return array(
			'formAssistant'   => __( 'Form Assistant', 'ai-site-assistant-autopilot' ),
			'reviewReady'     => __( 'Your form plan is ready. Review every field and the final status below; nothing is created until you approve.', 'ai-site-assistant-autopilot' ),
			/* translators: %d: number of fields in the proposed form. */
			'fieldCount'      => __( '%d fields ready to review', 'ai-site-assistant-autopilot' ),
			'createWithAi'    => __( 'Create with AI', 'ai-site-assistant-autopilot' ),
			'waiting'         => __( 'Waiting for your approval', 'ai-site-assistant-autopilot' ),
			'expired'         => __( 'Expired', 'ai-site-assistant-autopilot' ),
			/* translators: %s: human-readable time remaining, e.g. "14 minutes". */
			'validFor'        => __( 'Valid for %s more', 'ai-site-assistant-autopilot' ),
			'proposalExpired' => __( 'This proposal has expired', 'ai-site-assistant-autopilot' ),
			'safeText'        => __( 'Nothing changes on your site until you approve.', 'ai-site-assistant-autopilot' ),
			'expiredText'     => __( 'This action expired and can no longer be approved. Rebuild the request to continue.', 'ai-site-assistant-autopilot' ),
			'preview'         => __( 'Preview', 'ai-site-assistant-autopilot' ),
			'structure'       => __( 'Structure details', 'ai-site-assistant-autopilot' ),
			'desktop'         => __( 'Desktop', 'ai-site-assistant-autopilot' ),
			'mobile'          => __( 'Mobile', 'ai-site-assistant-autopilot' ),
			'required'        => __( 'required', 'ai-site-assistant-autopilot' ),
			'optional'        => __( 'optional', 'ai-site-assistant-autopilot' ),
			'notCreated'      => __( 'What will not be created', 'ai-site-assistant-autopilot' ),
			'notCreatedNote'  => __( 'These were removed from the plan by policy and cannot be approved.', 'ai-site-assistant-autopilot' ),
			'noPayment'       => __( 'Payment field — not allowed', 'ai-site-assistant-autopilot' ),
			'noPassword'      => __( 'Password field — not allowed', 'ai-site-assistant-autopilot' ),
			'noRegistration'  => __( 'User registration — not allowed', 'ai-site-assistant-autopilot' ),
			'whenApprove'     => __( 'What happens when you approve', 'ai-site-assistant-autopilot' ),
			'connector'       => __( 'Connector', 'ai-site-assistant-autopilot' ),
			'destination'     => __( 'Destination', 'ai-site-assistant-autopilot' ),
			'state'           => __( 'State', 'ai-site-assistant-autopilot' ),
			'draftNotPublic'  => __( 'Draft — not public', 'ai-site-assistant-autopilot' ),
			'publishedState'  => __( 'Published immediately', 'ai-site-assistant-autopilot' ),
			'riskLevel'       => __( 'Risk level', 'ai-site-assistant-autopilot' ),
			'notifications'   => __( 'Notifications', 'ai-site-assistant-autopilot' ),
			'approveDraft'    => __( 'Approve & save as draft', 'ai-site-assistant-autopilot' ),
			'approvePublish'  => __( 'Approve & publish', 'ai-site-assistant-autopilot' ),
			'cannotApprove'   => __( 'Expired', 'ai-site-assistant-autopilot' ),
			'backAndEdit'     => __( 'Back and edit', 'ai-site-assistant-autopilot' ),
			'rejectProposal'  => __( 'Reject this proposal', 'ai-site-assistant-autopilot' ),
			'approveNote'     => __( 'Single use · creates exactly the reviewed status', 'ai-site-assistant-autopilot' ),
			'expiredNote'     => __( 'Rebuild the request to continue.', 'ai-site-assistant-autopilot' ),
			'rejected'        => __( 'Proposal rejected. Nothing was created.', 'ai-site-assistant-autopilot' ),
			'creating'        => __( 'Creating the form in WordPress', 'ai-site-assistant-autopilot' ),
			'okTitle'         => __( 'The form was created as a draft.', 'ai-site-assistant-autopilot' ),
			'okTitlePub'      => __( 'The form was created and published.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: the title of the form that was created. */
			/* translators: %s: title of the form that was created. */
			'okBodyDraft'     => __( '“%s” was saved as a draft. Open and publish it before its shortcode can show the form to visitors.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: title of the form that was created. */
			'okBodyPublished' => __( '“%s” was published. Place the shortcode on a page to show it to visitors.', 'ai-site-assistant-autopilot' ),
			'failTitle'       => __( 'The form was not created', 'ai-site-assistant-autopilot' ),
			'formCreated'     => __( 'Form created', 'ai-site-assistant-autopilot' ),
			'creationFailed'  => __( 'Creation failed', 'ai-site-assistant-autopilot' ),
			'createdInWp'     => __( 'Created in WordPress', 'ai-site-assistant-autopilot' ),
			'entriesEmailTo'  => __( 'Entries email to', 'ai-site-assistant-autopilot' ),
			'resultDraft'     => __( 'Done. The form was saved as a draft. Open and publish it before its shortcode can display it to visitors.', 'ai-site-assistant-autopilot' ),
			'resultPublished' => __( 'Done. The reviewed form was published and its shortcode is ready to place.', 'ai-site-assistant-autopilot' ),
			'resultFailed'    => __( 'The approved action could not be completed. Nothing new was created.', 'ai-site-assistant-autopilot' ),
			'shortcode'       => __( 'Shortcode', 'ai-site-assistant-autopilot' ),
			'copy'            => __( 'Copy', 'ai-site-assistant-autopilot' ),
			'copied'          => __( 'Copied to clipboard', 'ai-site-assistant-autopilot' ),
			'openDraft'       => __( 'Open the draft', 'ai-site-assistant-autopilot' ),
			'openForm'        => __( 'Open the form', 'ai-site-assistant-autopilot' ),
			'editInEfb'       => __( 'Edit in Easy Form Builder', 'ai-site-assistant-autopilot' ),
			'tryAgain'        => __( 'Try again', 'ai-site-assistant-autopilot' ),
			'buildAnother'    => __( 'Build another form', 'ai-site-assistant-autopilot' ),
			'viewInActivity'  => __( 'View in activity', 'ai-site-assistant-autopilot' ),
			'low'             => __( 'Low', 'ai-site-assistant-autopilot' ),
			'medium'          => __( 'Medium', 'ai-site-assistant-autopilot' ),
			'high'            => __( 'High', 'ai-site-assistant-autopilot' ),
			'emailToAdmin'    => __( 'Email to site admin', 'ai-site-assistant-autopilot' ),
			'typeLabel'       => __( 'type', 'ai-site-assistant-autopilot' ),
			/* translators: %d: number of choices in a select or radio field. */
			'optionsCount'    => __( '%d options', 'ai-site-assistant-autopilot' ),
			'promptFirst'     => __( 'Type or speak a description of the form first.', 'ai-site-assistant-autopilot' ),
			'building'        => __( 'Building a reviewable plan…', 'ai-site-assistant-autopilot' ),
			'networkError'    => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'genericError'    => __( 'Could not generate a safe form.', 'ai-site-assistant-autopilot' ),
			'submitHint'      => __( 'Ctrl + Enter to submit', 'ai-site-assistant-autopilot' ),
			'recording'       => __( 'Recording — the transcript will land in the box, editable.', 'ai-site-assistant-autopilot' ),
			'stop'            => __( 'Stop', 'ai-site-assistant-autopilot' ),
			'speak'           => __( 'Speak', 'ai-site-assistant-autopilot' ),
			'transcribing'    => __( 'Transcribing your voice command…', 'ai-site-assistant-autopilot' ),
			'voiceAdded'      => __( 'Voice command added. Review the text, then generate the preview.', 'ai-site-assistant-autopilot' ),
			'voiceFailed'     => __( 'Voice transcription failed. You can type the command instead.', 'ai-site-assistant-autopilot' ),
			'noMic'           => __( 'This browser does not support voice recording. Please type the command.', 'ai-site-assistant-autopilot' ),
			'micDenied'       => __( 'Microphone access was not granted. Please type the command.', 'ai-site-assistant-autopilot' ),
		);
	}
}
