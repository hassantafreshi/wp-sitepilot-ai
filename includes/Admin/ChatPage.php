<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Providers\ProviderManager;

/**
 * The RAG Chat page — ask questions about the site content.
 */
final class ChatPage {

	public const SLUG = 'sitepilot-ai-chat';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$configured    = ( new ProviderManager() )->is_configured();
		$kb_exists     = ( new \SitePilot\Content\KnowledgeBase() )->table_exists();
		$kb_stats      = $kb_exists ? ( new \SitePilot\Content\KnowledgeBase() )->get_stats() : array( 'total' => 0 );
		$has_content   = $kb_stats['total'] > 0;
		?>
		<div class="wrap sitepilot-wrap sitepilot-chat-wrap">
			<div class="sitepilot-chat-layout">
				<!-- Sidebar: Conversations -->
				<aside class="sitepilot-chat-sidebar" id="sp-chat-sidebar">
					<div class="sitepilot-chat-sidebar__header">
						<h3 class="sitepilot-chat-sidebar__title">
							<span class="dashicons dashicons-format-chat"></span>
							<?php esc_html_e( 'Conversations', 'sitepilot-ai' ); ?>
						</h3>
						<button type="button" id="sp-chat-new" class="button button-small" title="<?php esc_attr_e( 'New conversation', 'sitepilot-ai' ); ?>">
							<span class="dashicons dashicons-plus"></span>
						</button>
					</div>
					<div id="sp-chat-list" class="sitepilot-chat-list">
						<p class="description"><?php esc_html_e( 'Loading…', 'sitepilot-ai' ); ?></p>
					</div>
				</aside>

				<!-- Main: Chat Area -->
				<main class="sitepilot-chat-main">
					<div class="sitepilot-chat-header">
						<h1 class="sitepilot-title">
							<?php esc_html_e( 'SitePilot AI Chat', 'sitepilot-ai' ); ?>
							<span class="sp-ai-badge">AI</span>
						</h1>
						<p class="sitepilot-subtitle">
							<?php esc_html_e( 'Ask questions about your website content. SitePilot searches your knowledge base and provides accurate answers.', 'sitepilot-ai' ); ?>
						</p>
					</div>

					<?php if ( ! $configured ) : ?>
						<div class="sitepilot-banner sitepilot-banner--warn">
							<span class="dashicons dashicons-warning"></span>
							<span>
								<?php esc_html_e( 'No AI provider is connected.', 'sitepilot-ai' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>">
									<?php esc_html_e( 'Add your API key in Settings', 'sitepilot-ai' ); ?>
								</a>.
							</span>
						</div>
					<?php endif; ?>

					<?php if ( ! $has_content ) : ?>
						<div class="sitepilot-banner sitepilot-banner--info">
							<span class="dashicons dashicons-info"></span>
							<span>
								<?php esc_html_e( 'Your knowledge base is empty.', 'sitepilot-ai' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KnowledgeBasePage::SLUG ) ); ?>">
									<?php esc_html_e( 'Index your content first', 'sitepilot-ai' ); ?>
								</a>.
							</span>
						</div>
					<?php endif; ?>

					<!-- Messages -->
					<div id="sp-chat-messages" class="sitepilot-chat-messages">
						<div class="sitepilot-chat-welcome">
							<div class="sitepilot-chat-welcome__icon">🤖</div>
							<h2><?php esc_html_e( 'Hello! Ask me anything about your website.', 'sitepilot-ai' ); ?></h2>
							<p><?php esc_html_e( 'For example:', 'sitepilot-ai' ); ?></p>
							<div class="sitepilot-chat-suggestions">
								<button class="sitepilot-chip" data-question="What is this website about?">
									📄 <?php esc_html_e( 'What is this website about?', 'sitepilot-ai' ); ?>
								</button>
								<button class="sitepilot-chip" data-question="Summarize my latest posts">
									📝 <?php esc_html_e( 'Summarize my latest posts', 'sitepilot-ai' ); ?>
								</button>
								<button class="sitepilot-chip" data-question="How many pages do I have?">
									📊 <?php esc_html_e( 'How many pages do I have?', 'sitepilot-ai' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Input -->
					<div class="sitepilot-chat-input-wrap">
						<textarea id="sp-chat-input" class="sitepilot-chat-input" rows="2"
							placeholder="<?php esc_attr_e( 'Ask anything about your site…', 'sitepilot-ai' ); ?>"
							<?php disabled( ! $configured || ! $has_content ); ?>></textarea>
						<div class="sitepilot-chat-input__actions">
							<button type="button" id="sp-chat-send" class="button button-primary"
								<?php disabled( ! $configured || ! $has_content ); ?>>
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</button>
							<span id="sp-chat-status" class="sitepilot-status" role="status" aria-live="polite"></span>
						</div>
					</div>
				</main>
			</div>
		</div>
		<?php
	}
}
