<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * AI Chat — ask questions about the site content, grounded in the knowledge base.
 */
final class ChatPage {

	public const SLUG = 'aisaautopilot-chat';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$configured = ( new ProviderManager() )->is_configured();
		$kb         = new KnowledgeBase();
		$kb_total   = $kb->table_exists() ? (int) ( $kb->get_stats()['total'] ?? 0 ) : 0;
		$has_kb     = $kb_total > 0;
		$ready      = $configured && $has_kb;

		$kb_url       = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );

		$samples = array(
			__( 'What is this website about?', 'ai-site-assistant-autopilot' ),
			__( 'Summarize my latest posts', 'ai-site-assistant-autopilot' ),
			__( 'How many pages do I have?', 'ai-site-assistant-autopilot' ),
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-chat>
			<section class="sp-screen">
				<h1 class="sp-h1">
					<span><?php esc_html_e( 'AI Site Assistant AutoPilot Chat', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-ai-badge">AI</span>
				</h1>
				<p class="sp-lead sp-mb0"><?php esc_html_e( 'Ask questions about your website content. AISAAutoPilot searches your knowledge base and provides accurate answers.', 'ai-site-assistant-autopilot' ); ?></p>

				<div class="sp-chat">
					<aside class="sp-chat__aside">
						<div class="sp-chat__aside-head">
							<h3 class="sp-chat__aside-title">
								<?php echo Ui::icon( 'chat', 17, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span><?php esc_html_e( 'Conversations', 'ai-site-assistant-autopilot' ); ?></span>
							</h3>
							<button type="button" id="sp-chat-new" class="sp-btn--icon-sm" title="<?php esc_attr_e( 'New conversation', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'New conversation', 'ai-site-assistant-autopilot' ); ?>">
								<?php echo Ui::icon( 'plus', 16, '#1d2327', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
						</div>
						<div id="sp-chat-list" class="sp-chat__list">
							<p class="sp-help" style="padding:8px"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</aside>

					<main class="sp-chat__main">
						<?php if ( ! $configured ) : ?>
							<div class="sp-alert sp-alert--warn" style="margin:14px 20px 0">
								<?php echo Ui::icon( 'warning', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span>
									<?php esc_html_e( 'No AI provider is connected.', 'ai-site-assistant-autopilot' ); ?>
									<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Add your API key in Settings', 'ai-site-assistant-autopilot' ); ?></a>.
								</span>
							</div>
						<?php elseif ( ! $has_kb ) : ?>
							<div class="sp-alert sp-alert--info" style="margin:14px 20px 0">
								<?php echo Ui::icon( 'info', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span>
									<?php esc_html_e( 'Your knowledge base is empty.', 'ai-site-assistant-autopilot' ); ?>
									<a href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Index your content first', 'ai-site-assistant-autopilot' ); ?></a>.
								</span>
							</div>
						<?php endif; ?>

						<div id="sp-chat-messages" class="sp-chat__msgs">
							<div class="sp-chat__welcome">
								<span class="sp-chat__welcome-icon"><?php echo Ui::solid( 'sparkle', 26, 'grad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<h2><?php esc_html_e( 'Hello! Ask me anything about your website.', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'For example:', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-chat__welcome-chips">
									<?php foreach ( $samples as $sample ) : ?>
										<button type="button" class="sp-chip" data-question="<?php echo esc_attr( $sample ); ?>"><?php echo esc_html( $sample ); ?></button>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="sp-chat__composer">
							<label class="sp-sr" for="sp-chat-input"><?php esc_html_e( 'Your question', 'ai-site-assistant-autopilot' ); ?></label>
							<textarea id="sp-chat-input" rows="2" placeholder="<?php esc_attr_e( 'Ask anything about your site…', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>></textarea>
							<button type="button" id="sp-mic-btn" class="sp-mic" aria-label="<?php esc_attr_e( 'Start voice input', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Start voice input', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>>
								<?php echo Ui::icon( 'mic', 18, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
							<button type="button" id="sp-chat-send" class="sp-send" aria-label="<?php esc_attr_e( 'Send message', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>>
								<?php echo Ui::icon( 'send', 18, '#fff', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
							<span id="sp-chat-status" class="sp-sr" role="status" aria-live="polite"></span>
						</div>
					</main>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}
}
