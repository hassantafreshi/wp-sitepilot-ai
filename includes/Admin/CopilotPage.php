<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Core\Settings;
use SitePilot\Providers\ProviderManager;

/**
 * Three exclusive screens for the safe form-creation flow in the WP-02,
 * WP-03 and WP-04 wireframes. JavaScript moves the administrator between
 * them; it never appends a second interface below the current one.
 */
final class CopilotPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$configured   = ( new ProviderManager() )->is_configured();
		$recipient    = Settings::form_notification_email();
		$ready        = $configured && '' !== $recipient;
		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$home_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME );
		$forms_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORMS );
		$examples     = array(
			__( 'Create a contact form with name, email, phone, and message.', 'sitepilot-ai' ),
			__( 'Build a job application form with full name, email, phone, and a cover letter.', 'sitepilot-ai' ),
			__( 'Make a newsletter signup with first name and email.', 'sitepilot-ai' ),
			__( 'Support request form with name, email, priority (low, medium, high) and details.', 'sitepilot-ai' ),
		);
		?>
		<div class="wrap sitepilot-wrap sitepilot-flow" data-sitepilot-copilot dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<section id="sitepilot-compose-screen" class="sitepilot-flow-screen" aria-labelledby="sitepilot-compose-title">
				<nav class="sitepilot-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'sitepilot-ai' ); ?>">
					<a href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'Home', 'sitepilot-ai' ); ?></a>
					<span aria-hidden="true">/</span>
					<span><?php esc_html_e( 'Create with AI', 'sitepilot-ai' ); ?></span>
				</nav>

				<header class="sitepilot-flow-heading">
					<div>
						<p class="sitepilot-eyebrow"><?php esc_html_e( 'Create with AI', 'sitepilot-ai' ); ?></p>
						<h1 id="sitepilot-compose-title" class="sitepilot-title"><?php esc_html_e( 'Copilot', 'sitepilot-ai' ); ?> <span class="sp-ai-badge">AI</span></h1>
						<p class="sitepilot-subtitle"><?php esc_html_e( 'Describe the form you need. Nothing is created until you review and explicitly approve the plan.', 'sitepilot-ai' ); ?></p>
					</div>
				</header>

				<div class="sitepilot-flow-layout">
					<div>
						<section class="sitepilot-flow-card sitepilot-composer-card" aria-label="<?php esc_attr_e( 'Create a form request', 'sitepilot-ai' ); ?>">
							<div class="sitepilot-flow-tabs" role="tablist" aria-label="<?php esc_attr_e( 'AI task type', 'sitepilot-ai' ); ?>">
								<button type="button" class="is-active" role="tab" aria-selected="true"><?php esc_html_e( 'Create a form', 'sitepilot-ai' ); ?></button>
								<button type="button" role="tab" aria-selected="false" disabled title="<?php esc_attr_e( 'WooCommerce is required for product drafts.', 'sitepilot-ai' ); ?>"><?php esc_html_e( 'Product draft — needs WooCommerce', 'sitepilot-ai' ); ?></button>
							</div>

							<label for="sitepilot-prompt" class="sp-sr-only"><?php esc_html_e( 'Describe the form you want to create', 'sitepilot-ai' ); ?></label>
							<textarea id="sitepilot-prompt" class="sitepilot-textarea sitepilot-composer-card__prompt" rows="7" placeholder="<?php esc_attr_e( 'For example: Create a contact form with name, email, phone and message.', 'sitepilot-ai' ); ?>" <?php disabled( ! $ready ); ?>></textarea>

							<div class="sitepilot-composer-card__toolbar">
								<button type="button" id="sitepilot-voice-toggle" class="sitepilot-icon-button" aria-pressed="false" aria-label="<?php esc_attr_e( 'Start voice command', 'sitepilot-ai' ); ?>" title="<?php esc_attr_e( 'Start voice command', 'sitepilot-ai' ); ?>" <?php disabled( ! $ready ); ?>>
									<span class="dashicons dashicons-microphone" aria-hidden="true"></span>
									<span class="sp-sr-only sitepilot-voice-button__label"><?php esc_html_e( 'Speak', 'sitepilot-ai' ); ?></span>
								</button>
								<span id="sitepilot-status" class="sitepilot-status" role="status" aria-live="polite"><?php esc_html_e( 'Type or speak your request.', 'sitepilot-ai' ); ?></span>
								<button type="button" id="sitepilot-generate" class="button button-primary button-hero sitepilot-generate-button" <?php disabled( ! $ready ); ?>>
									<span class="dashicons dashicons-sparkles" aria-hidden="true"></span>
									<?php esc_html_e( 'Generate preview', 'sitepilot-ai' ); ?>
								</button>
							</div>

							<?php if ( ! $ready ) : ?>
								<div class="sitepilot-flow-alert sitepilot-flow-alert--warning" role="alert">
									<span class="dashicons dashicons-lock" aria-hidden="true"></span>
									<div>
										<strong><?php esc_html_e( 'This action needs setup', 'sitepilot-ai' ); ?></strong>
										<p>
											<?php if ( ! $configured && '' === $recipient ) : ?>
												<?php esc_html_e( 'Connect an AI provider and set the form notification email before generating a plan.', 'sitepilot-ai' ); ?>
											<?php elseif ( ! $configured ) : ?>
												<?php esc_html_e( 'Connect an AI provider before generating a plan.', 'sitepilot-ai' ); ?>
											<?php else : ?>
												<?php esc_html_e( 'Set the notification email before generating a plan.', 'sitepilot-ai' ); ?>
											<?php endif; ?>
											<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open settings', 'sitepilot-ai' ); ?></a>
										</p>
									</div>
								</div>
							<?php endif; ?>

							<div id="sitepilot-generation-progress" class="sitepilot-generation-progress" hidden aria-live="polite">
								<span class="sitepilot-spinner" aria-hidden="true"></span>
								<div>
									<strong><?php esc_html_e( 'Preparing a reviewable plan', 'sitepilot-ai' ); ?></strong>
									<ol><li><?php esc_html_e( 'Analyze request', 'sitepilot-ai' ); ?></li><li><?php esc_html_e( 'Run safety checks', 'sitepilot-ai' ); ?></li><li><?php esc_html_e( 'Build preview', 'sitepilot-ai' ); ?></li></ol>
								</div>
							</div>
						</section>

						<div class="sitepilot-examples sitepilot-examples--flow">
							<span class="sitepilot-examples__label"><?php esc_html_e( 'Try an example:', 'sitepilot-ai' ); ?></span>
							<?php foreach ( $examples as $example ) : ?>
								<button type="button" class="sitepilot-chip" data-example="<?php echo esc_attr( $example ); ?>"><?php echo esc_html( $example ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>

					<aside class="sitepilot-flow-context" aria-label="<?php esc_attr_e( 'Context of this request', 'sitepilot-ai' ); ?>">
						<div class="sitepilot-flow-card">
							<h2><?php esc_html_e( 'Context of this request', 'sitepilot-ai' ); ?></h2>
							<dl class="sitepilot-context-list">
								<div><dt><?php esc_html_e( 'Connector', 'sitepilot-ai' ); ?></dt><dd><?php esc_html_e( 'SitePilot Forms', 'sitepilot-ai' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Result state', 'sitepilot-ai' ); ?></dt><dd><?php esc_html_e( 'Draft', 'sitepilot-ai' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Site language', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( get_locale() ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Knowledge base', 'sitepilot-ai' ); ?></dt><dd><?php esc_html_e( 'Not used', 'sitepilot-ai' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Estimated usage', 'sitepilot-ai' ); ?></dt><dd><?php esc_html_e( 'Shown before approval', 'sitepilot-ai' ); ?></dd></div>
							</dl>
						</div>
						<div class="sitepilot-flow-note sitepilot-flow-note--security"><span class="dashicons dashicons-shield" aria-hidden="true"></span><p><?php esc_html_e( 'Nothing runs automatically. The final form is created only after your approval.', 'sitepilot-ai' ); ?></p></div>
						<div class="sitepilot-flow-note"><span class="dashicons dashicons-microphone" aria-hidden="true"></span><p><?php esc_html_e( 'Voice input is transcribed into editable text before any AI request is sent.', 'sitepilot-ai' ); ?></p></div>
					</aside>
				</div>
			</section>

			<section id="sitepilot-review-screen" class="sitepilot-flow-screen" hidden aria-labelledby="sitepilot-review-title">
				<div id="sitepilot-review-content"></div>
			</section>

			<section id="sitepilot-result-screen" class="sitepilot-flow-screen" hidden aria-labelledby="sitepilot-result-title">
				<div id="sitepilot-result-content"></div>
				<p class="sitepilot-flow-return"><a class="button button-secondary" href="<?php echo esc_url( $forms_url ); ?>"><?php esc_html_e( 'View all SitePilot Forms', 'sitepilot-ai' ); ?></a></p>
			</section>
		</div>
		<?php
	}
}
