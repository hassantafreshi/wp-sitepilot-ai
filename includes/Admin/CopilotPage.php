<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Providers\ProviderManager;

/**
 * The Admin Copilot screen. Static markup; behavior lives in copilot.js.
 */
final class CopilotPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$configured = ( new ProviderManager() )->is_configured();
		$examples   = array(
			__( 'Create a contact form with name, email, phone, and message.', 'sitepilot-ai' ),
			__( 'Build a job application form with full name, email, phone, and a cover letter.', 'sitepilot-ai' ),
			__( 'Make a newsletter signup with first name and email.', 'sitepilot-ai' ),
			__( 'Support request form with name, email, priority (low, medium, high) and details.', 'sitepilot-ai' ),
		);
		?>
		<div class="wrap sitepilot-wrap">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-superhero"></span>
				<?php esc_html_e( 'Copilot', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</h1>
			<p class="sitepilot-subtitle">
				<?php esc_html_e( 'Describe the form you need. SitePilot drafts it, you review and approve, then it is created. Nothing is created without your approval.', 'sitepilot-ai' ); ?>
			</p>

			<?php if ( ! $configured ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn">
					<span class="dashicons dashicons-warning"></span>
					<span>
						<?php esc_html_e( 'No AI provider is connected yet.', 'sitepilot-ai' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>">
							<?php esc_html_e( 'Add your API key in Settings', 'sitepilot-ai' ); ?>
						</a>.
					</span>
				</div>
			<?php endif; ?>

			<div class="sitepilot-card sitepilot-composer">
				<label for="sitepilot-prompt" class="sitepilot-label">
					<?php esc_html_e( 'What form do you want to create?', 'sitepilot-ai' ); ?>
				</label>
				<textarea id="sitepilot-prompt" class="sitepilot-textarea" rows="3"
					placeholder="<?php esc_attr_e( 'e.g. Create a contact form with name, email, phone and message', 'sitepilot-ai' ); ?>"
					<?php disabled( ! $configured ); ?>></textarea>

				<div class="sitepilot-examples">
					<span class="sitepilot-examples__label"><?php esc_html_e( 'Try:', 'sitepilot-ai' ); ?></span>
					<?php foreach ( $examples as $example ) : ?>
						<button type="button" class="sitepilot-chip" data-example="<?php echo esc_attr( $example ); ?>">
							<?php echo esc_html( $example ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="sitepilot-composer__actions">
					<button type="button" id="sitepilot-generate" class="button button-primary button-hero" <?php disabled( ! $configured ); ?>>
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'Generate preview', 'sitepilot-ai' ); ?>
					</button>
					<span id="sitepilot-status" class="sitepilot-status" role="status" aria-live="polite"></span>
				</div>
			</div>

			<div id="sitepilot-preview" class="sitepilot-card sitepilot-preview" hidden></div>
			<div id="sitepilot-result" class="sitepilot-card sitepilot-result" hidden></div>
		</div>
		<?php
	}
}
