<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Content\ContentStudioReadModel;
use AISAAutoPilot\Content\ContentTone;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;
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
	 * bookmarks and generated links keep resolving unchanged.
	 *
	 * One full-height workspace (owner-approved redesign, 2026-10-28): the canvas shows a
	 * gallery of examples, the form taking shape, the plan beside a full-size preview, or the
	 * result; the chat beside it carries the whole conversation, so a plan is changed by
	 * asking. Nothing is created until the administrator approves a plan.
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

		$setup_url        = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$settings_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) . '#sp-adv-forms';
		$integrations_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS );
		$efb_url          = admin_url( 'admin.php?page=' . AdminMenu::SLUG_EFB );

		// A prompt handed over from the Home quick-start box.
		$seed = isset( $_GET['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_GET['prompt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of a textarea.

		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-form-composer" data-aisaautopilot-copilot data-sp-fc-state="start">
			<header class="sp-fc-header">
				<span class="sp-fc-header__icon" aria-hidden="true"><?php echo Ui::icon( 'form', 18, '#fff', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-fc-header__titles">
					<h1><?php esc_html_e( 'Form Studio', 'ai-site-assistant-autopilot' ); ?></h1>
					<p><?php esc_html_e( 'Simple forms, emailed to you. Describe one, check the plan, then create it.', 'ai-site-assistant-autopilot' ); ?></p>
				</div>
				<?php if ( '' !== $recipient ) : ?>
					<a class="sp-fc-delivery" data-sp-form-delivery href="<?php echo esc_url( $settings_url ); ?>" title="<?php esc_attr_e( 'The recipient is set by you and cannot be changed by the AI.', 'ai-site-assistant-autopilot' ); ?>">
						<span aria-hidden="true"><?php echo Ui::icon( 'mail', 15, '#202a8d', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
						<span><?php /* translators: %s: configured form notification email. */ printf( esc_html__( 'Answers go to %s', 'ai-site-assistant-autopilot' ), '<bdi>' . esc_html( $recipient ) . '</bdi>' ); ?></span>
						<b><?php esc_html_e( 'Change', 'ai-site-assistant-autopilot' ); ?></b>
					</a>
				<?php endif; ?>
				<button type="button" class="sp-fc-header__new" data-sp-form-new><?php esc_html_e( 'New form', 'ai-site-assistant-autopilot' ); ?></button>
			</header>

			<div class="sp-fc-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Form Studio', 'ai-site-assistant-autopilot' ); ?>">
				<button type="button" role="tab" id="sp-fc-tab-chat" aria-controls="sp-fc-chat" aria-selected="true" data-sp-fc-tab="chat"><?php esc_html_e( 'Chat', 'ai-site-assistant-autopilot' ); ?></button>
				<button type="button" role="tab" id="sp-fc-tab-preview" aria-controls="sp-fc-canvas" aria-selected="false" data-sp-fc-tab="preview"><?php esc_html_e( 'Preview', 'ai-site-assistant-autopilot' ); ?><span class="sp-fc-tabs__dot" data-sp-fc-tab-dot hidden></span></button>
			</div>

			<div class="sp-fc-work" id="aisaautopilot-compose-screen">
				<main class="sp-fc-canvas" id="sp-fc-canvas" data-sp-form-canvas aria-labelledby="sp-fc-canvas-title">
					<section class="sp-fc-gallery" data-sp-form-empty>
						<h2 id="sp-fc-canvas-title"><?php esc_html_e( 'Start from an example', 'ai-site-assistant-autopilot' ); ?></h2>
						<p><?php esc_html_e( 'Pick one and it lands in the chat, ready to edit. Or describe your own form there.', 'ai-site-assistant-autopilot' ); ?></p>
						<div class="sp-fc-gallery__grid" id="aisaautopilot-examples">
							<?php foreach ( self::examples() as $example ) : ?>
								<button type="button" class="sp-fc-example" data-example="<?php echo esc_attr( $example['prompt'] ); ?>" <?php disabled( ! $ready ); ?>>
									<strong><?php echo esc_html( $example['title'] ); ?></strong>
									<span class="sp-fc-example__fields">
										<?php foreach ( $example['fields'] as $field ) : ?>
											<span class="sp-fc-example__field sp-fc-example__field--<?php echo esc_attr( $field[1] ); ?>"><?php echo esc_html( $field[0] ); ?></span>
										<?php endforeach; ?>
									</span>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="sp-fc-advanced">
							<span class="sp-fc-advanced__icon" aria-hidden="true"><?php echo Ui::icon( 'database', 16, '#5b5e7d', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
							<span><strong><?php esc_html_e( 'Need file uploads, payments, multi-step or logic?', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'Those belong in Easy Form Builder. Native forms stay simple and email-only.', 'ai-site-assistant-autopilot' ); ?></small></span>
							<?php if ( $efb_ready ) : ?>
								<a href="<?php echo esc_url( $efb_url ); ?>"><?php esc_html_e( 'Open EFB', 'ai-site-assistant-autopilot' ); ?></a>
							<?php else : ?>
								<?php $efb_next = PluginDependency::status( PluginDependency::EFB, AdminMenu::SLUG_FORM_STUDIO )['action']; ?>
								<?php if ( null !== $efb_next ) : ?>
									<a href="<?php echo esc_url( $efb_next['url'] ); ?>" data-sp-dependency-action="<?php echo esc_attr( $efb_next['kind'] ); ?>"><?php echo esc_html( $efb_next['label'] ); ?></a>
								<?php else : ?>
									<span class="sp-fc-advanced__locked"><?php esc_html_e( 'EFB unavailable', 'ai-site-assistant-autopilot' ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</section>

					<section class="sp-fc-ghost" data-sp-form-ghost hidden aria-hidden="true">
						<span class="sp-fc-ghost__title"></span>
						<?php for ( $i = 0; $i < 4; $i++ ) : ?>
							<span class="sp-fc-ghost__label"></span><span class="sp-fc-ghost__input"></span>
						<?php endfor; ?>
						<span class="sp-fc-ghost__button"></span>
					</section>

					<section id="aisaautopilot-review-screen" class="sp-fc-plan" hidden>
						<div id="aisaautopilot-review-content"></div>
					</section>

					<section id="aisaautopilot-result-screen" class="sp-fc-result" hidden>
						<div id="aisaautopilot-result-content"></div>
					</section>

					<div class="sp-fc-stickybar" data-sp-fc-stickybar hidden></div>
				</main>

				<aside class="aisa-ai sp-fc-chat<?php echo $ready ? '' : ' is-offline'; ?>" id="sp-fc-chat" data-sp-form-assistant aria-labelledby="sp-fc-assistant-title">
					<div class="aisa-ai-head">
						<span class="aisa-ai-logo" aria-hidden="true" data-aisa-icon="spark" data-aisa-size="19" data-aisa-stroke="1.8"></span>
						<div class="aisa-ai-title">
							<strong id="sp-fc-assistant-title"><?php esc_html_e( 'Form Assistant', 'ai-site-assistant-autopilot' ); ?></strong>
							<div class="aisa-ai-ctx"><span class="aisa-ai-ctx__dot" aria-hidden="true"></span><span data-sp-fc-ctx><?php echo esc_html( $ready ? __( 'New native form', 'ai-site-assistant-autopilot' ) : __( 'Setup needed', 'ai-site-assistant-autopilot' ) ); ?></span></div>
						</div>
					</div>
					<div class="aisa-ai-thread" id="aisaautopilot-thread" aria-live="polite">
						<?php if ( ! $form_enabled ) : ?>
							<?php // The chat draws the full checklist; without scripts the way back still shows. ?>
							<noscript><p class="sp-fc-noscript"><strong><?php esc_html_e( 'Form Studio is turned off', 'ai-site-assistant-autopilot' ); ?></strong> <a href="<?php echo esc_url( $integrations_url ); ?>"><?php esc_html_e( 'Open Integrations', 'ai-site-assistant-autopilot' ); ?></a></p></noscript>
						<?php endif; ?>
					</div>
					<div class="aisa-ai-suggest" id="aisaautopilot-suggest"></div>
					<form class="aisa-ai-composer" id="aisaautopilot-compose">
						<div class="aisa-ai-input<?php echo $ready ? '' : ' is-off'; ?>">
							<label class="sp-sr" for="aisaautopilot-prompt"><?php esc_html_e( 'Describe the form you want to create', 'ai-site-assistant-autopilot' ); ?></label>
							<textarea id="aisaautopilot-prompt" class="aisa-ai-textarea" rows="2" maxlength="1500" dir="auto" placeholder="<?php echo esc_attr( $ready ? __( 'Describe the form: who fills it in and what you need to know…', 'ai-site-assistant-autopilot' ) : __( 'Finish the setup to start…', 'ai-site-assistant-autopilot' ) ); ?>" <?php disabled( ! $ready ); ?>><?php echo esc_textarea( $seed ); ?></textarea>
							<div class="aisa-ai-input__bar">
								<button type="button" id="aisaautopilot-voice-toggle" class="aisa-ai-ibtn" aria-pressed="false" aria-label="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>><span data-aisa-icon="mic" data-aisa-size="18"></span><span class="sp-sr aisaautopilot-voice-button__label"><?php esc_html_e( 'Speak', 'ai-site-assistant-autopilot' ); ?></span></button>
								<span class="aisa-ai-sp"></span>
								<button type="submit" id="aisaautopilot-generate" class="aisa-ai-send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>><span data-aisa-icon="up" data-aisa-size="18" data-aisa-stroke="2.2"></span></button>
							</div>
						</div>
						<div id="aisaautopilot-status" class="aisa-ai-voice" role="status" aria-live="polite"></div>
						<p class="aisa-ai-foot"><span data-aisa-icon="shield" data-aisa-size="13"></span><span><?php esc_html_e( 'AI drafts a plan. Nothing is created until you approve.', 'ai-site-assistant-autopilot' ); ?></span></p>
					</form>
				</aside>
			</div>

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
				// Binds a plan kept across a reload (sessionStorage) to the administrator who drafted it.
				'user'       => get_current_user_id(),
				// The preview shows the form as visitors read it: in the site language and direction.
				'site'       => array(
					'locale' => get_locale(),
					'rtl'    => SiteLanguage::is_rtl( get_locale() ),
				),
				// What the readiness checklist shows; the chat checks again when the tab regains focus.
				'ready'      => array(
					'studio'    => $form_enabled,
					'ai'        => $configured,
					'recipient' => $recipient,
				),
				'urls'       => array(
					'integrations' => $integrations_url,
					'setup'        => $setup_url,
					'settings'     => $settings_url,
				),
				'examples'   => array_map(
					static function ( array $example ): array {
						return array(
							'title'  => $example['title'],
							'prompt' => $example['prompt'],
						);
					},
					self::examples()
				),
				'i18n'       => self::strings(),
			)
		);
	}

	/**
	 * The gallery: six forms made only of the field types native forms have.
	 *
	 * @return array<int, array{title:string, prompt:string, fields:array<int, array{0:string, 1:string}>}>
	 */
	private static function examples(): array {
		return array(
			array(
				'title'  => __( 'Contact form', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'Create a contact form with name, email, phone, and message.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'Name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Email', 'ai-site-assistant-autopilot' ), 'email' ), array( __( 'Phone', 'ai-site-assistant-autopilot' ), 'tel' ), array( __( 'Message', 'ai-site-assistant-autopilot' ), 'textarea' ) ),
			),
			array(
				'title'  => __( 'Newsletter signup', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'Make a newsletter signup with first name, email and the topics they want: news, offers or events.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'First name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Email', 'ai-site-assistant-autopilot' ), 'email' ), array( __( 'Topics', 'ai-site-assistant-autopilot' ), 'checkbox' ) ),
			),
			array(
				'title'  => __( 'Support request', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'Support request form with name, email, priority (low, medium, high) and details.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'Name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Email', 'ai-site-assistant-autopilot' ), 'email' ), array( __( 'Priority', 'ai-site-assistant-autopilot' ), 'radio' ), array( __( 'Details', 'ai-site-assistant-autopilot' ), 'textarea' ) ),
			),
			array(
				'title'  => __( 'Job application', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'Build a job application form with full name, email, phone, the position they apply for as a dropdown, and a cover letter.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'Full name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Email', 'ai-site-assistant-autopilot' ), 'email' ), array( __( 'Position', 'ai-site-assistant-autopilot' ), 'select' ), array( __( 'Cover letter', 'ai-site-assistant-autopilot' ), 'textarea' ) ),
			),
			array(
				'title'  => __( 'Event RSVP', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'An event RSVP form with name, email, whether they will come (yes or no), how many guests (0 to 3) and any dietary needs.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'Name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Attending', 'ai-site-assistant-autopilot' ), 'radio' ), array( __( 'Guests', 'ai-site-assistant-autopilot' ), 'select' ), array( __( 'Dietary needs', 'ai-site-assistant-autopilot' ), 'textarea' ) ),
			),
			array(
				'title'  => __( 'Quote request', 'ai-site-assistant-autopilot' ),
				'prompt' => __( 'A quote request form with name, email, phone, the service they need as a dropdown, a budget range and project details.', 'ai-site-assistant-autopilot' ),
				'fields' => array( array( __( 'Name', 'ai-site-assistant-autopilot' ), 'text' ), array( __( 'Service', 'ai-site-assistant-autopilot' ), 'select' ), array( __( 'Budget', 'ai-site-assistant-autopilot' ), 'radio' ), array( __( 'Project details', 'ai-site-assistant-autopilot' ), 'textarea' ) ),
			),
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
		$tone             = ContentTone::preferred( get_current_user_id() );
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
						<div class="sp-content-voice">
							<span class="sp-content-voice__label" id="sp-content-voice-label"><?php echo esc_html_x( 'Voice', 'writing style of a Content Studio draft', 'ai-site-assistant-autopilot' ); ?></span>
							<div class="sp-content-voice__options" role="radiogroup" aria-labelledby="sp-content-voice-label" data-content-voice>
								<?php foreach ( ContentTone::options() as $tone_id => $tone_label ) : ?>
									<?php $checked = $tone_id === $tone; ?>
									<button type="button" role="radio" class="sp-content-voice__chip" data-content-voice-option="<?php echo esc_attr( $tone_id ); ?>" aria-checked="<?php echo $checked ? 'true' : 'false'; ?>" tabindex="<?php echo $checked ? '0' : '-1'; ?>" <?php disabled( ! $ready ); ?>><?php echo esc_html( $tone_label ); ?></button>
								<?php endforeach; ?>
							</div>
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
						<div><span class="sp-label"><?php echo esc_html_x( 'Voice', 'writing style of a Content Studio draft', 'ai-site-assistant-autopilot' ); ?></span><p data-content-voice-label></p></div>
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
			'formAssistant'       => __( 'Form Assistant', 'ai-site-assistant-autopilot' ),
			'reviewReady'         => __( 'Your form plan is ready. Review every field and the final status below; nothing is created until you approve.', 'ai-site-assistant-autopilot' ),
			/* translators: %d: number of fields in the proposed form. */
			'fieldCount'          => __( '%d fields ready to review', 'ai-site-assistant-autopilot' ),
			'createWithAi'        => __( 'Create with AI', 'ai-site-assistant-autopilot' ),
			'waiting'             => __( 'Waiting for your approval', 'ai-site-assistant-autopilot' ),
			'expired'             => __( 'Expired', 'ai-site-assistant-autopilot' ),
			/* translators: %s: human-readable time remaining, e.g. "14 minutes". */
			'validFor'            => __( 'Valid for %s more', 'ai-site-assistant-autopilot' ),
			'proposalExpired'     => __( 'This proposal has expired', 'ai-site-assistant-autopilot' ),
			'safeText'            => __( 'Nothing changes on your site until you approve.', 'ai-site-assistant-autopilot' ),
			'expiredText'         => __( 'This action expired and can no longer be approved. Rebuild the request to continue.', 'ai-site-assistant-autopilot' ),
			'preview'             => __( 'Preview', 'ai-site-assistant-autopilot' ),
			'structure'           => __( 'Structure details', 'ai-site-assistant-autopilot' ),
			'desktop'             => __( 'Desktop', 'ai-site-assistant-autopilot' ),
			'mobile'              => __( 'Mobile', 'ai-site-assistant-autopilot' ),
			'required'            => __( 'required', 'ai-site-assistant-autopilot' ),
			'optional'            => __( 'optional', 'ai-site-assistant-autopilot' ),
			'notCreated'          => __( 'What will not be created', 'ai-site-assistant-autopilot' ),
			'notCreatedNote'      => __( 'These were removed from the plan by policy and cannot be approved.', 'ai-site-assistant-autopilot' ),
			'noPayment'           => __( 'Payment field — not allowed', 'ai-site-assistant-autopilot' ),
			'noPassword'          => __( 'Password field — not allowed', 'ai-site-assistant-autopilot' ),
			'noRegistration'      => __( 'User registration — not allowed', 'ai-site-assistant-autopilot' ),
			'whenApprove'         => __( 'What happens when you approve', 'ai-site-assistant-autopilot' ),
			'connector'           => __( 'Connector', 'ai-site-assistant-autopilot' ),
			'destination'         => __( 'Destination', 'ai-site-assistant-autopilot' ),
			'state'               => __( 'State', 'ai-site-assistant-autopilot' ),
			'draftNotPublic'      => __( 'Draft — not public', 'ai-site-assistant-autopilot' ),
			'publishedState'      => __( 'Published immediately', 'ai-site-assistant-autopilot' ),
			'riskLevel'           => __( 'Risk level', 'ai-site-assistant-autopilot' ),
			'notifications'       => __( 'Notifications', 'ai-site-assistant-autopilot' ),
			'approveDraft'        => __( 'Approve & save as draft', 'ai-site-assistant-autopilot' ),
			'approvePublish'      => __( 'Approve & publish', 'ai-site-assistant-autopilot' ),
			'cannotApprove'       => __( 'Expired', 'ai-site-assistant-autopilot' ),
			'backAndEdit'         => __( 'Back and edit', 'ai-site-assistant-autopilot' ),
			'rejectProposal'      => __( 'Reject this proposal', 'ai-site-assistant-autopilot' ),
			'approveNote'         => __( 'Single use · creates exactly the reviewed status', 'ai-site-assistant-autopilot' ),
			'expiredNote'         => __( 'Rebuild the request to continue.', 'ai-site-assistant-autopilot' ),
			'rejected'            => __( 'Proposal rejected. Nothing was created.', 'ai-site-assistant-autopilot' ),
			'creating'            => __( 'Creating the form in WordPress', 'ai-site-assistant-autopilot' ),
			'okTitle'             => __( 'The form was created as a draft.', 'ai-site-assistant-autopilot' ),
			'okTitlePub'          => __( 'The form was created and published.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: the title of the form that was created. */
			/* translators: %s: title of the form that was created. */
			'okBodyDraft'         => __( '“%s” was saved as a draft. Open and publish it before its shortcode can show the form to visitors.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: title of the form that was created. */
			'okBodyPublished'     => __( '“%s” was published. Place the shortcode on a page to show it to visitors.', 'ai-site-assistant-autopilot' ),
			'failTitle'           => __( 'The form was not created', 'ai-site-assistant-autopilot' ),
			'formCreated'         => __( 'Form created', 'ai-site-assistant-autopilot' ),
			'creationFailed'      => __( 'Creation failed', 'ai-site-assistant-autopilot' ),
			'createdInWp'         => __( 'Created in WordPress', 'ai-site-assistant-autopilot' ),
			'entriesEmailTo'      => __( 'Entries email to', 'ai-site-assistant-autopilot' ),
			'resultDraft'         => __( 'Done. The form was saved as a draft. Open and publish it before its shortcode can display it to visitors.', 'ai-site-assistant-autopilot' ),
			'resultPublished'     => __( 'Done. The reviewed form was published and its shortcode is ready to place.', 'ai-site-assistant-autopilot' ),
			'resultFailed'        => __( 'The approved action could not be completed. Nothing new was created.', 'ai-site-assistant-autopilot' ),
			'shortcode'           => __( 'Shortcode', 'ai-site-assistant-autopilot' ),
			'copy'                => __( 'Copy', 'ai-site-assistant-autopilot' ),
			'copied'              => __( 'Copied to clipboard', 'ai-site-assistant-autopilot' ),
			'openDraft'           => __( 'Open the draft', 'ai-site-assistant-autopilot' ),
			'openForm'            => __( 'Open the form', 'ai-site-assistant-autopilot' ),
			'editInEfb'           => __( 'Edit in Easy Form Builder', 'ai-site-assistant-autopilot' ),
			'tryAgain'            => __( 'Try again', 'ai-site-assistant-autopilot' ),
			'buildAnother'        => __( 'Build another form', 'ai-site-assistant-autopilot' ),
			'viewInActivity'      => __( 'View in activity', 'ai-site-assistant-autopilot' ),
			'low'                 => __( 'Low', 'ai-site-assistant-autopilot' ),
			'medium'              => __( 'Medium', 'ai-site-assistant-autopilot' ),
			'high'                => __( 'High', 'ai-site-assistant-autopilot' ),
			'emailToAdmin'        => __( 'Email to site admin', 'ai-site-assistant-autopilot' ),
			'typeLabel'           => __( 'type', 'ai-site-assistant-autopilot' ),
			/* translators: %d: number of choices in a select or radio field. */
			'optionsCount'        => __( '%d options', 'ai-site-assistant-autopilot' ),
			'promptFirst'         => __( 'Type or speak a description of the form first.', 'ai-site-assistant-autopilot' ),
			'building'            => __( 'Building a reviewable plan…', 'ai-site-assistant-autopilot' ),
			'networkError'        => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'genericError'        => __( 'Could not generate a safe form.', 'ai-site-assistant-autopilot' ),
			'submitHint'          => __( 'Ctrl + Enter to submit', 'ai-site-assistant-autopilot' ),
			'recording'           => __( 'Recording — the transcript will land in the box, editable.', 'ai-site-assistant-autopilot' ),
			'stop'                => __( 'Stop', 'ai-site-assistant-autopilot' ),
			'speak'               => __( 'Speak', 'ai-site-assistant-autopilot' ),
			'transcribing'        => __( 'Transcribing your voice command…', 'ai-site-assistant-autopilot' ),
			'voiceAdded'          => __( 'Voice command added. Review the text, then generate the preview.', 'ai-site-assistant-autopilot' ),
			'voiceFailed'         => __( 'Voice transcription failed. You can type the command instead.', 'ai-site-assistant-autopilot' ),
			'noMic'               => __( 'This browser does not support voice recording. Please type the command.', 'ai-site-assistant-autopilot' ),
			'micDenied'           => __( 'Microphone access was not granted. Please type the command.', 'ai-site-assistant-autopilot' ),
			// The workspace and its chat (owner-approved redesign, 2026-10-28).
			'greet'               => __( 'Describe the form you need: who fills it in, and what do you need to know from them? Or pick an example; it lands here, ready to edit. I draft a plan, and nothing is created until you approve it.', 'ai-site-assistant-autopilot' ),
			'readyIntro'          => __( 'Almost ready. This is what I need before I can draft forms:', 'ai-site-assistant-autopilot' ),
			'studioOn'            => __( 'Form Studio is on', 'ai-site-assistant-autopilot' ),
			'studioOff'           => __( 'Turn on Form Studio', 'ai-site-assistant-autopilot' ),
			'studioOffNote'       => __( 'It is switched off in Integrations.', 'ai-site-assistant-autopilot' ),
			'aiOn'                => __( 'An AI service is connected', 'ai-site-assistant-autopilot' ),
			'aiOff'               => __( 'Connect an AI service', 'ai-site-assistant-autopilot' ),
			'aiOffNote'           => __( 'Your own API key from OpenAI, Claude, Gemini and others.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: email address. */
			'recipientOn'         => __( 'Answers go to %s', 'ai-site-assistant-autopilot' ),
			'recipientOff'        => __( 'Choose where answers are emailed', 'ai-site-assistant-autopilot' ),
			'recipientOffNote'    => __( 'Submissions are emailed to this address and not stored in WordPress.', 'ai-site-assistant-autopilot' ),
			'openIntegrations'    => __( 'Open Integrations', 'ai-site-assistant-autopilot' ),
			'setUp'               => __( 'Set up', 'ai-site-assistant-autopilot' ),
			'setEmail'            => __( 'Set email', 'ai-site-assistant-autopilot' ),
			'readyNow'            => __( 'All set. Describe the form you need, or pick an example.', 'ai-site-assistant-autopilot' ),
			'ctxNew'              => __( 'New native form', 'ai-site-assistant-autopilot' ),
			'ctxSetup'            => __( 'Setup needed', 'ai-site-assistant-autopilot' ),
			'ctxDrafting'         => __( 'Drafting a plan…', 'ai-site-assistant-autopilot' ),
			/* translators: %s: time left, such as 14:52. */
			'ctxPlan'             => __( 'Plan ready · valid for %s', 'ai-site-assistant-autopilot' ),
			'ctxExpired'          => __( 'Plan expired', 'ai-site-assistant-autopilot' ),
			'ctxCreated'          => __( 'Form created', 'ai-site-assistant-autopilot' ),
			'stepUnderstood'      => __( 'Understood the request', 'ai-site-assistant-autopilot' ),
			'stepChoosing'        => __( 'Choosing the fields…', 'ai-site-assistant-autopilot' ),
			'stepRules'           => __( 'Checking them against the native-form rules', 'ai-site-assistant-autopilot' ),
			'notBuilt'            => __( 'I could not build that yet', 'ai-site-assistant-autopilot' ),
			'tryExamples'         => __( 'Say who fills it in and what you need from them. For example:', 'ai-site-assistant-autopilot' ),
			'planReady'           => __( 'The plan is ready and shown on the canvas. Approve it, or ask me to change something.', 'ai-site-assistant-autopilot' ),
			'planRestored'        => __( 'Here is the plan you were reviewing before the page reloaded. It is still waiting for your approval.', 'ai-site-assistant-autopilot' ),
			'savedAsDraft'        => __( 'Saved as a draft', 'ai-site-assistant-autopilot' ),
			'notVisible'          => __( 'Not visible on your site', 'ai-site-assistant-autopilot' ),
			'publishedNote'       => __( 'Visible to visitors as soon as you confirm', 'ai-site-assistant-autopilot' ),
			/* translators: %s: masked email address. */
			'answersTo'           => __( 'Answers emailed to %s', 'ai-site-assistant-autopilot' ),
			/* translators: %s: Low, Medium or High. */
			'riskIs'              => __( 'Risk: %s', 'ai-site-assistant-autopilot' ),
			'neverIncluded'       => __( 'Native forms never include payments, passwords or sign-ups.', 'ai-site-assistant-autopilot' ),
			'reject'              => __( 'Reject', 'ai-site-assistant-autopilot' ),
			'planExpired'         => __( 'This plan expired. Ask again to get a fresh one.', 'ai-site-assistant-autopilot' ),
			'askAgain'            => __( 'Draft it again', 'ai-site-assistant-autopilot' ),
			/* translators: 1: the current plan in short, 2: the change the administrator asked for. */
			/* translators: %s: field label. */
			'nextOptional'        => __( 'Make “%s” optional', 'ai-site-assistant-autopilot' ),
			'nextPhone'           => __( 'Add a phone number field', 'ai-site-assistant-autopilot' ),
			'nextColumns'         => __( 'Put some fields side by side', 'ai-site-assistant-autopilot' ),
			'typeUrl'             => __( 'Web address', 'ai-site-assistant-autopilot' ),
			'typeNumber'          => __( 'Number', 'ai-site-assistant-autopilot' ),
			'typeDate'            => __( 'Date', 'ai-site-assistant-autopilot' ),
			'widthFull'           => __( 'Full width', 'ai-site-assistant-autopilot' ),
			'widthThreeQuarters'  => __( 'Three quarters', 'ai-site-assistant-autopilot' ),
			'widthTwoThirds'      => __( 'Two thirds', 'ai-site-assistant-autopilot' ),
			'widthHalf'           => __( 'Half', 'ai-site-assistant-autopilot' ),
			'widthThird'          => __( 'One third', 'ai-site-assistant-autopilot' ),
			'widthQuarter'        => __( 'One quarter', 'ai-site-assistant-autopilot' ),
			/* translators: %s: width of a form field, e.g. "Half". */
			'widthTitle'          => __( 'Width: %s', 'ai-site-assistant-autopilot' ),
			'width'               => __( 'Width', 'ai-site-assistant-autopilot' ),
			'pickHint'            => __( 'Click a field, here or in the preview, to change it or to ask the assistant about it.', 'ai-site-assistant-autopilot' ),
			'undo'                => __( 'Undo', 'ai-site-assistant-autopilot' ),
			'undoTitle'           => __( 'Take back the last change', 'ai-site-assistant-autopilot' ),
			'undone'              => __( 'Change taken back', 'ai-site-assistant-autopilot' ),
			'nothingToUndo'       => __( 'Nothing to take back, or it has expired.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selectField'         => __( 'Select the field “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'fieldSelected'       => __( 'Selected “%s”. Your next message is about this field.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'placeholderSelected' => __( 'Tell me what to change about “%s”…', 'ai-site-assistant-autopilot' ),
			'placeholderRevise'   => __( 'Ask for a change to the form…', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'ctxSelected'         => __( 'Editing “%s”', 'ai-site-assistant-autopilot' ),
			'clearSelection'      => __( 'Stop editing this field', 'ai-site-assistant-autopilot' ),
			'editingField'        => __( 'Editing the field', 'ai-site-assistant-autopilot' ),
			'editing'             => __( 'Editing', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of the form field a chat message is about. */
			'aboutField'          => __( 'About: %s', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selOptional'         => __( 'Make “%s” optional', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selRequired'         => __( 'Make “%s” required', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selHalf'             => __( 'Put “%s” next to the field after it', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selDropdown'         => __( 'Turn “%s” into a dropdown', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selOption'           => __( 'Add an option to “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: label of a form field. */
			'selRename'           => __( 'Rename “%s”', 'ai-site-assistant-autopilot' ),
			'moveUp'              => __( 'Move up', 'ai-site-assistant-autopilot' ),
			'moveDown'            => __( 'Move down', 'ai-site-assistant-autopilot' ),
			'removeField'         => __( 'Remove', 'ai-site-assistant-autopilot' ),
			'requiredField'       => __( 'Required', 'ai-site-assistant-autopilot' ),
			'fieldLabel'          => __( 'Label', 'ai-site-assistant-autopilot' ),
			'fieldPlaceholder'    => __( 'Placeholder', 'ai-site-assistant-autopilot' ),
			'fieldHelp'           => __( 'Hint under the field', 'ai-site-assistant-autopilot' ),
			'nextButtonText'      => __( 'Change the text of the send button', 'ai-site-assistant-autopilot' ),
			'fields'              => __( 'Fields', 'ai-site-assistant-autopilot' ),
			'typeText'            => __( 'Text', 'ai-site-assistant-autopilot' ),
			'typeEmail'           => __( 'Email', 'ai-site-assistant-autopilot' ),
			'typeTel'             => __( 'Phone', 'ai-site-assistant-autopilot' ),
			'typeTextarea'        => __( 'Long text', 'ai-site-assistant-autopilot' ),
			'typeSelect'          => __( 'Dropdown', 'ai-site-assistant-autopilot' ),
			'typeCheckbox'        => __( 'Checkboxes', 'ai-site-assistant-autopilot' ),
			'typeRadio'           => __( 'Choice', 'ai-site-assistant-autopilot' ),
			'placeholderReady'    => __( 'Describe the form: who fills it in and what you need to know…', 'ai-site-assistant-autopilot' ),
			'placeholderOff'      => __( 'Finish the setup to start…', 'ai-site-assistant-autopilot' ),
		);
	}
}
