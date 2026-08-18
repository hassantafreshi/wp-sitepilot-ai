<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;

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

		$configured = ( new ProviderManager() )->is_configured();
		$recipient  = Settings::form_notification_email();
		$efb_ready  = ( new EasyFormBuilderConnector() )->is_available();
		$woo_ready  = class_exists( 'WooCommerce' );
		$ready      = $configured && '' !== $recipient;

		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$home_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME );
		$conn_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS );

		// A prompt handed over from the Home quick-start box.
		$seed = isset( $_GET['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_GET['prompt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of a textarea.

		$examples = array(
			__( 'Create a contact form with name, email, phone, and message.', 'ai-site-assistant-autopilot' ),
			__( 'Build a job application form with full name, email, phone, and a cover letter.', 'ai-site-assistant-autopilot' ),
			__( 'Make a newsletter signup with first name and email.', 'ai-site-assistant-autopilot' ),
			__( 'Support request form with name, email, priority (low, medium, high) and details.', 'ai-site-assistant-autopilot' ),
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-aisaautopilot-copilot>

			<!-- ── WP-02 compose ─────────────────────────────────── -->
			<section id="aisaautopilot-compose-screen" class="sp-screen">
				<?php
				Ui::crumbs(
					array(
						array( __( 'Home', 'ai-site-assistant-autopilot' ), $home_url ),
						array( __( 'Create with AI', 'ai-site-assistant-autopilot' ), null ),
					)
				);
				?>
				<h1 class="sp-h1">
					<?php echo Ui::icon( 'spark-plus', 26, 'grad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
					<span><?php esc_html_e( 'Copilot', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-ai-badge">AI</span>
				</h1>
				<p class="sp-lead"><?php esc_html_e( 'Describe the form you need. AISAAutoPilot drafts it, you review and approve, then it is created. Nothing is created without your approval.', 'ai-site-assistant-autopilot' ); ?></p>

				<div class="sp-cols">
					<div class="sp-col">
						<div class="sp-card">
							<div class="sp-label sp-label--14"><?php esc_html_e( 'What form do you want to create?', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-tabs sp-tabs--boxed" style="margin-bottom:16px" role="tablist">
								<button type="button" class="sp-tabs__btn is-on" role="tab" aria-selected="true"><?php esc_html_e( 'Create a form', 'ai-site-assistant-autopilot' ); ?></button>
								<button type="button" class="sp-tabs__btn" role="tab" aria-selected="false" disabled title="<?php esc_attr_e( 'WooCommerce is required for product drafts.', 'ai-site-assistant-autopilot' ); ?>">
									<?php echo esc_html( $woo_ready ? __( 'Product draft', 'ai-site-assistant-autopilot' ) : __( 'Product draft — needs WooCommerce', 'ai-site-assistant-autopilot' ) ); ?>
								</button>
							</div>

							<div class="sp-composer">
								<label class="sp-sr" for="aisaautopilot-prompt"><?php esc_html_e( 'Describe the form you want to create', 'ai-site-assistant-autopilot' ); ?></label>
								<textarea id="aisaautopilot-prompt" placeholder="<?php esc_attr_e( 'Describe the form you need — fields, purpose and where it will be used.', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>><?php echo esc_textarea( $seed ); ?></textarea>
								<div class="sp-composer__bar">
									<button type="button" id="aisaautopilot-voice-toggle" class="sp-btn--icon" aria-pressed="false" aria-label="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Start voice command', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>>
										<?php echo Ui::icon( 'mic', 16, '#1d2327' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<span class="sp-sr aisaautopilot-voice-button__label"><?php esc_html_e( 'Speak', 'ai-site-assistant-autopilot' ); ?></span>
									</button>
									<span id="aisaautopilot-status" class="sp-composer__hint" role="status" aria-live="polite"><?php esc_html_e( 'Ctrl + Enter to submit', 'ai-site-assistant-autopilot' ); ?></span>
									<button type="button" id="aisaautopilot-generate" class="sp-btn" style="padding:10px 20px" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Generate preview', 'ai-site-assistant-autopilot' ); ?></button>
								</div>
							</div>

							<?php if ( ! $ready ) : ?>
								<div class="sp-notice sp-notice--err" style="margin-top:11px">
									<?php echo Ui::icon( 'alert-circle', 16, '#dc2626', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<div class="sp-notice__body">
										<div class="sp-notice__title" style="color:#b91c1c">
											<?php echo esc_html( $configured ? __( 'Form delivery is not configured', 'ai-site-assistant-autopilot' ) : __( 'AI provider unavailable', 'ai-site-assistant-autopilot' ) ); ?>
										</div>
										<div class="sp-notice__text">
											<?php echo esc_html( $configured ? __( 'Set the notification email before a plan can be built.', 'ai-site-assistant-autopilot' ) : __( 'Connect an AI provider before a preview can be built.', 'ai-site-assistant-autopilot' ) ); ?>
										</div>
									</div>
									<a class="sp-btn sp-btn--danger sp-btn--xs" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Check connection', 'ai-site-assistant-autopilot' ); ?></a>
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

						<div class="sp-chips" id="aisaautopilot-examples">
							<span class="sp-chips__label"><?php esc_html_e( 'Try:', 'ai-site-assistant-autopilot' ); ?></span>
							<?php foreach ( $examples as $example ) : ?>
								<button type="button" class="sp-chip" data-example="<?php echo esc_attr( $example ); ?>"><?php echo esc_html( $example ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>

					<aside class="sp-side">
						<div class="sp-card sp-card--16 sp-card--flat">
							<div class="sp-h3" style="margin-bottom:12px"><?php esc_html_e( 'Context of this request', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-kv">
								<?php
								Ui::kv( __( 'Connector', 'ai-site-assistant-autopilot' ), 'AISAAutoPilot Forms' );
								Ui::kv( __( 'Result state', 'ai-site-assistant-autopilot' ), 'publish' === Settings::get( 'default_form_status', 'draft' ) ? __( 'Published', 'ai-site-assistant-autopilot' ) : __( 'Draft', 'ai-site-assistant-autopilot' ) );
								Ui::kv( __( 'Site language', 'ai-site-assistant-autopilot' ), get_locale() . ( is_rtl() ? ' (RTL)' : '' ) );
								Ui::kv( __( 'Knowledge base', 'ai-site-assistant-autopilot' ), __( 'Not used for forms', 'ai-site-assistant-autopilot' ) );
								?>
								<div class="sp-hr"></div>
								<?php Ui::kv( __( 'Estimated usage', 'ai-site-assistant-autopilot' ), __( '~1,200 tokens', 'ai-site-assistant-autopilot' ) ); ?>
							</div>
						</div>
						<div class="sp-card sp-card--accent">
							<div class="sp-row-wrap sp-row-wrap--sm" style="margin-bottom:6px">
								<?php echo Ui::icon( 'shield', 16, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span style="font-size:13.5px;font-weight:700"><?php esc_html_e( 'Nothing runs automatically', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<p class="sp-help sp-help--12"><?php esc_html_e( 'We only build a plan. You see every field and the destination before anything is created.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
						<div class="sp-card sp-card--dashed" style="background:#fff">
							<div class="sp-h3" style="font-size:13px"><?php esc_html_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?></div>
							<p class="sp-help sp-help--12"><?php esc_html_e( 'Recording becomes an editable transcript first. You always send it yourself.', 'ai-site-assistant-autopilot' ); ?></p>
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
				'homeUrl'      => $home_url,
				'historyUrl'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ),
				'connUrl'      => $conn_url,
				'formsUrl'     => admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORMS ),
				'efbReady'     => $efb_ready,
				'defaultDraft' => 'publish' !== Settings::get( 'default_form_status', 'draft' ),
				'host'         => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'i18n'         => self::strings(),
			)
		);
	}

	/**
	 * Every string the review/result renderer needs, translated server-side.
	 *
	 * @return array<string, string>
	 */
	private static function strings(): array {
		return array(
			'home'            => __( 'Home', 'ai-site-assistant-autopilot' ),
			'createWithAi'    => __( 'Create with AI', 'ai-site-assistant-autopilot' ),
			'reviewAction'    => __( 'Review action', 'ai-site-assistant-autopilot' ),
			'actionHistory'   => __( 'Action history', 'ai-site-assistant-autopilot' ),
			'result'          => __( 'Result', 'ai-site-assistant-autopilot' ),
			'waiting'         => __( 'Waiting for your approval', 'ai-site-assistant-autopilot' ),
			'expired'         => __( 'Expired', 'ai-site-assistant-autopilot' ),
			'createdNow'      => __( 'Created just now', 'ai-site-assistant-autopilot' ),
			/* translators: %s: human-readable time remaining, e.g. "14 minutes". */
			'validFor'        => __( 'Valid for %s more', 'ai-site-assistant-autopilot' ),
			'proposalExpired' => __( 'This proposal has expired', 'ai-site-assistant-autopilot' ),
			'tlRequest'       => __( 'Request', 'ai-site-assistant-autopilot' ),
			'tlPlan'          => __( 'AI plan', 'ai-site-assistant-autopilot' ),
			'tlSafety'        => __( 'Safety check', 'ai-site-assistant-autopilot' ),
			'tlApproval'      => __( 'Your approval', 'ai-site-assistant-autopilot' ),
			'tlExecution'     => __( 'Execution', 'ai-site-assistant-autopilot' ),
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
			'estimatedUsage'  => __( 'Estimated usage', 'ai-site-assistant-autopilot' ),
			'estimate'        => __( '~1,200 tokens', 'ai-site-assistant-autopilot' ),
			'approveDraft'    => __( 'Approve & save as draft', 'ai-site-assistant-autopilot' ),
			'approvePublish'  => __( 'Approve & publish', 'ai-site-assistant-autopilot' ),
			'cannotApprove'   => __( 'Expired', 'ai-site-assistant-autopilot' ),
			'backAndEdit'     => __( 'Back and edit', 'ai-site-assistant-autopilot' ),
			'rejectProposal'  => __( 'Reject this proposal', 'ai-site-assistant-autopilot' ),
			'approveNote'     => __( 'Single use · reversible by deleting the draft', 'ai-site-assistant-autopilot' ),
			'expiredNote'     => __( 'Rebuild the request to continue.', 'ai-site-assistant-autopilot' ),
			'rejected'        => __( 'Proposal rejected. Nothing was created.', 'ai-site-assistant-autopilot' ),
			'creating'        => __( 'Creating the form in WordPress', 'ai-site-assistant-autopilot' ),
			'okTitle'         => __( 'The form was created as a draft.', 'ai-site-assistant-autopilot' ),
			'okTitlePub'      => __( 'The form was created and published.', 'ai-site-assistant-autopilot' ),
			/* translators: %s: the title of the form that was created. */
			'okBody'          => __( '“%s” was created. Place the shortcode on a page to show it to visitors.', 'ai-site-assistant-autopilot' ),
			'failTitle'       => __( 'The form was not created', 'ai-site-assistant-autopilot' ),
			'shortcode'       => __( 'Shortcode', 'ai-site-assistant-autopilot' ),
			'copy'            => __( 'Copy', 'ai-site-assistant-autopilot' ),
			'copied'          => __( 'Copied to clipboard', 'ai-site-assistant-autopilot' ),
			'openForm'        => __( 'Open the form', 'ai-site-assistant-autopilot' ),
			'editInEfb'       => __( 'Edit in Easy Form Builder', 'ai-site-assistant-autopilot' ),
			'allForms'        => __( 'View all AISAAutoPilot Forms', 'ai-site-assistant-autopilot' ),
			'tryAgain'        => __( 'Try again', 'ai-site-assistant-autopilot' ),
			'time'            => __( 'Time', 'ai-site-assistant-autopilot' ),
			'actionId'        => __( 'Action ID', 'ai-site-assistant-autopilot' ),
			'recordedUsage'   => __( 'Recorded usage', 'ai-site-assistant-autopilot' ),
			'notRecorded'     => __( 'Not recorded', 'ai-site-assistant-autopilot' ),
			'nextStep'        => __( 'Next step', 'ai-site-assistant-autopilot' ),
			'placeOnPage'     => __( 'Place this form on a page', 'ai-site-assistant-autopilot' ),
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
