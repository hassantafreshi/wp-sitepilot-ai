<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Ask your knowledge base — the administrator console for testing grounded answers.
 *
 * Conversations belong to the signed-in administrator (ConversationManager scopes every
 * read and delete to the current user) and are never shown to visitors. Asking stays locked,
 * with the reason on the page, until an AI service is connected and content is indexed.
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
		$chip       = ConnectionChip::derive();
		// What keeps asking locked: the AI service first, then indexed content.
		$lock = $ready ? '' : ( $configured ? 'knowledge' : 'provider' );

		$kb_url      = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$entries_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB . '&tab=entries' );
		$setup_url   = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );

		$samples = array(
			__( 'What is this website about?', 'ai-site-assistant-autopilot' ),
			__( 'How can I get in touch?', 'ai-site-assistant-autopilot' ),
			__( 'What do you offer?', 'ai-site-assistant-autopilot' ),
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-chat data-sp-chat-ready="<?php echo esc_attr( $ready ? '1' : '0' ); ?>">
			<section class="sp-screen">
				<?php
				// A retained Site Knowledge sub-surface (the "Ask" console), so it uses the shared header.
				Ui::page_header(
					__( 'Ask your knowledge base', 'ai-site-assistant-autopilot' ),
					__( 'Test what AutoPilot answers from your indexed content. Visitors never see these conversations.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => $chip,
						'action' => array(
							'label' => __( 'Back to Site Knowledge', 'ai-site-assistant-autopilot' ),
							'url'   => $kb_url,
						),
					)
				);
				?>

				<div class="sp-chat" data-sp-chat-console>
					<aside class="sp-chat__aside" aria-labelledby="sp-chat-list-title">
						<div class="sp-chat__aside-head">
							<h2 class="sp-chat__aside-title" id="sp-chat-list-title"><?php esc_html_e( 'Conversations', 'ai-site-assistant-autopilot' ); ?></h2>
							<button type="button" id="sp-chat-new" class="sp-chat__new" title="<?php esc_attr_e( 'New conversation', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'New conversation', 'ai-site-assistant-autopilot' ); ?>">
								<?php echo Ui::icon( 'plus', 14, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
						</div>
						<div id="sp-chat-list" class="sp-chat__list" aria-busy="true">
							<p class="sp-chat__list-note"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
						<p class="sp-chat__aside-foot"><?php esc_html_e( 'Only administrators see this console. Visitors use the widget.', 'ai-site-assistant-autopilot' ); ?></p>
					</aside>

					<section class="sp-chat__main" aria-label="<?php esc_attr_e( 'Conversation', 'ai-site-assistant-autopilot' ); ?>">
						<div class="sp-chat__meta" data-sp-chat-meta>
							<?php echo Ui::icon( 'book', 14, '#202a8d', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php echo esc_html( $this->meta_line( $configured, $kb_total, $chip ) ); ?></span>
						</div>

						<?php if ( '' !== $lock ) : ?>
							<div class="sp-chat__notice" data-sp-chat-notice="<?php echo esc_attr( $lock ); ?>">
								<span class="sp-chat__notice-icon" aria-hidden="true"><?php echo Ui::icon( 'lock', 16, 'currentColor', 2.1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<div class="sp-chat__notice-body">
									<strong class="sp-chat__notice-title"><?php esc_html_e( 'Asking is locked', 'ai-site-assistant-autopilot' ); ?></strong>
									<p class="sp-chat__notice-text"><?php echo esc_html( $this->lock_reason( $lock, $kb_total ) ); ?></p>
								</div>
								<?php if ( 'provider' === $lock ) : ?>
									<a class="sp-chat__notice-btn" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Open Setup & Connection', 'ai-site-assistant-autopilot' ); ?></a>
								<?php else : ?>
									<a class="sp-chat__notice-btn" href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Open Site Knowledge', 'ai-site-assistant-autopilot' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div id="sp-chat-messages" class="sp-chat__msgs" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Messages', 'ai-site-assistant-autopilot' ); ?>">
							<div class="sp-chat__welcome<?php echo '' !== $lock ? ' is-locked' : ''; ?>" data-sp-chat-welcome>
								<span class="sp-chat__welcome-icon" aria-hidden="true">
									<?php echo Ui::icon( '' !== $lock ? 'lock' : 'chat', 22, '' !== $lock ? 'currentColor' : '#202a8d', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								</span>
								<h2 class="sp-chat__welcome-title"><?php echo esc_html( $this->welcome_title( $lock ) ); ?></h2>
								<p class="sp-chat__welcome-text"><?php echo esc_html( $this->welcome_text( $lock ) ); ?></p>
								<?php if ( 'provider' === $lock ) : ?>
									<div class="sp-chat__welcome-actions" data-sp-chat-lock-actions>
										<a class="sp-btn sp-btn--r8" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Open Setup & Connection', 'ai-site-assistant-autopilot' ); ?></a>
										<?php if ( $has_kb ) : ?>
											<a class="sp-btn sp-btn--ghost sp-btn--r8" href="<?php echo esc_url( $entries_url ); ?>"><?php esc_html_e( 'See what is indexed', 'ai-site-assistant-autopilot' ); ?></a>
										<?php endif; ?>
									</div>
								<?php elseif ( 'knowledge' === $lock ) : ?>
									<div class="sp-chat__welcome-actions" data-sp-chat-lock-actions>
										<a class="sp-btn sp-btn--r8" href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Open Site Knowledge', 'ai-site-assistant-autopilot' ); ?></a>
									</div>
								<?php endif; ?>
								<div class="sp-chat__chips">
									<?php foreach ( $samples as $sample ) : ?>
										<button type="button" class="sp-chat__chip" data-question="<?php echo esc_attr( $sample ); ?>" <?php disabled( ! $ready ); ?>>
											<?php
											if ( ! $ready ) {
												echo Ui::icon( 'lock', 12, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
											}
											echo esc_html( $sample );
											?>
										</button>
									<?php endforeach; ?>
								</div>
								<?php if ( '' !== $lock ) : ?>
									<p class="sp-chat__chips-note">
									<?php
									echo esc_html(
										'provider' === $lock
											? __( 'Suggestions stay locked until a service is connected.', 'ai-site-assistant-autopilot' )
											: __( 'Suggestions stay locked until content is indexed.', 'ai-site-assistant-autopilot' )
									);
									?>
									</p>
								<?php endif; ?>
							</div>
						</div>

						<div class="sp-chat__composer">
							<p id="sp-chat-status" class="sp-chat__status" role="status" aria-live="polite"></p>
							<div class="sp-chat__field<?php echo $ready ? '' : ' is-disabled'; ?>">
								<label class="sp-sr" for="sp-chat-input"><?php esc_html_e( 'Your question', 'ai-site-assistant-autopilot' ); ?></label>
								<textarea id="sp-chat-input" rows="1" maxlength="2000" placeholder="<?php echo esc_attr( $this->placeholder( $lock ) ); ?>" aria-describedby="sp-chat-foot" <?php disabled( ! $ready ); ?>></textarea>
								<button type="button" id="sp-mic-btn" class="sp-mic" aria-pressed="false" aria-label="<?php esc_attr_e( 'Record a question', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Record a question', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>>
									<?php echo Ui::icon( 'mic', 16, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								</button>
								<button type="button" id="sp-chat-send" class="sp-send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>" disabled>
									<?php echo Ui::icon( 'send', 16, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								</button>
							</div>
							<p id="sp-chat-foot" class="sp-chat__foot">
							<?php
							echo esc_html(
								'' !== $lock
									? __( 'Nothing is sent while this is locked.', 'ai-site-assistant-autopilot' )
									: __( 'Each question uses tokens from your monthly cap.', 'ai-site-assistant-autopilot' )
							);
							?>
							</p>
						</div>
					</section>
				</div>
				<?php
				Ui::json_script(
					'sp-chat-config',
					array(
						'ready'     => $ready,
						'gapsUrl'   => $kb_url . '#sp-kb-gaps',
						'healthUrl' => admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS ),
						'usageUrl'  => admin_url( 'admin.php?page=' . AdminMenu::SLUG_USAGE ),
					)
				);
				Ui::json_script( 'sp-chat-i18n', $this->strings() );
				?>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * One truthful line about what answers are drawn from right now.
	 *
	 * @param array{tone:string,label:string} $chip Connection chip state.
	 */
	private function meta_line( bool $configured, int $kb_total, array $chip ): string {
		if ( ! $configured ) {
			return __( 'Not connected — answers are paused', 'ai-site-assistant-autopilot' );
		}
		if ( 'warn' === $chip['tone'] ) {
			return $chip['label'];
		}
		if ( $kb_total <= 0 ) {
			return __( 'No indexed content yet — answers need something to read', 'ai-site-assistant-autopilot' );
		}

		return sprintf(
			/* translators: 1: number of indexed items, 2: AI service and model. */
			_n( 'Answers from %1$s indexed item · %2$s', 'Answers from %1$s indexed items · %2$s', $kb_total, 'ai-site-assistant-autopilot' ),
			number_format_i18n( $kb_total ),
			$chip['label']
		);
	}

	/**
	 * Why asking is locked, with the real indexed count when content is already there.
	 */
	private function lock_reason( string $lock, int $kb_total ): string {
		if ( 'knowledge' === $lock ) {
			return __( 'Nothing has been indexed yet, so there is nothing to answer from. Index your content in Site Knowledge first.', 'ai-site-assistant-autopilot' );
		}
		if ( $kb_total > 0 ) {
			return sprintf(
				/* translators: %s: number of indexed items. */
				_n( 'No AI service is connected, so nothing can answer yet. Your content is ready — %s item is indexed.', 'No AI service is connected, so nothing can answer yet. Your content is ready — %s items are indexed.', $kb_total, 'ai-site-assistant-autopilot' ),
				number_format_i18n( $kb_total )
			);
		}

		return __( 'No AI service is connected and nothing has been indexed yet, so nothing can answer.', 'ai-site-assistant-autopilot' );
	}

	private function welcome_title( string $lock ): string {
		if ( 'provider' === $lock ) {
			return __( 'Asking is locked until a service is connected', 'ai-site-assistant-autopilot' );
		}
		if ( 'knowledge' === $lock ) {
			return __( 'Asking is locked until content is indexed', 'ai-site-assistant-autopilot' );
		}

		return __( 'Ask what your visitors would ask', 'ai-site-assistant-autopilot' );
	}

	private function welcome_text( string $lock ): string {
		if ( 'provider' === $lock ) {
			return __( 'The message box, the microphone and the suggestions below stay locked. Connect a service in Setup & Connection, then come back here.', 'ai-site-assistant-autopilot' );
		}
		if ( 'knowledge' === $lock ) {
			return __( 'The message box, the microphone and the suggestions below stay locked. Index your content in Site Knowledge, then come back here.', 'ai-site-assistant-autopilot' );
		}

		return __( 'Answers come only from indexed content, with the pages they used. It is the quickest way to spot a missing page.', 'ai-site-assistant-autopilot' );
	}

	private function placeholder( string $lock ): string {
		if ( 'provider' === $lock ) {
			return __( 'Locked — connect an AI service to ask a question', 'ai-site-assistant-autopilot' );
		}
		if ( 'knowledge' === $lock ) {
			return __( 'Locked — index your content to ask a question', 'ai-site-assistant-autopilot' );
		}

		return __( 'Ask anything about your site…', 'ai-site-assistant-autopilot' );
	}

	/**
	 * Copy for chat.js and voice.js, so both scripts read translated text.
	 *
	 * @return array<string,string>
	 */
	private function strings(): array {
		return array(
			'noConversations'  => __( 'No conversations yet. Your questions appear here.', 'ai-site-assistant-autopilot' ),
			'listFailed'       => __( 'Could not load conversations.', 'ai-site-assistant-autopilot' ),
			'untitled'         => __( 'New conversation', 'ai-site-assistant-autopilot' ),
			'today'            => __( 'Today', 'ai-site-assistant-autopilot' ),
			'yesterday'        => __( 'Yesterday', 'ai-site-assistant-autopilot' ),
			/* translators: %s: conversation title. */
			'deleteLabel'      => __( 'Delete “%s”', 'ai-site-assistant-autopilot' ),
			'deleteTitle'      => __( 'Delete this conversation?', 'ai-site-assistant-autopilot' ),
			'deleteBody'       => __( 'It is removed from this console for good. Nothing on your site changes.', 'ai-site-assistant-autopilot' ),
			'deleteCta'        => __( 'Delete conversation', 'ai-site-assistant-autopilot' ),
			'deleted'          => __( 'Conversation deleted.', 'ai-site-assistant-autopilot' ),
			'deleteFailed'     => __( 'Could not delete the conversation. Please try again.', 'ai-site-assistant-autopilot' ),
			'openFailed'       => __( 'Could not open this conversation. Please try again.', 'ai-site-assistant-autopilot' ),
			'sources'          => __( 'From:', 'ai-site-assistant-autopilot' ),
			'notFound'         => __( 'Not in your content', 'ai-site-assistant-autopilot' ),
			'seeGaps'          => __( 'See questions your site could not answer', 'ai-site-assistant-autopilot' ),
			'handoffNote'      => __( 'Visitors are offered a person at this point.', 'ai-site-assistant-autopilot' ),
			'thinking'         => __( 'Finding an answer…', 'ai-site-assistant-autopilot' ),
			'answerFailed'     => __( 'Could not get an answer. Please try again.', 'ai-site-assistant-autopilot' ),
			'networkError'     => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'errorTitle'       => __( 'No answer came back', 'ai-site-assistant-autopilot' ),
			'errorProvider'    => __( 'The AI service did not return an answer. Ask again, or check the connection in Health.', 'ai-site-assistant-autopilot' ),
			'askAgain'         => __( 'Ask again', 'ai-site-assistant-autopilot' ),
			'openHealth'       => __( 'Open Health', 'ai-site-assistant-autopilot' ),
			'openUsage'        => __( 'Open Usage & Cost', 'ai-site-assistant-autopilot' ),
			'listen'           => __( 'Listen', 'ai-site-assistant-autopilot' ),
			'stopAudio'        => __( 'Stop audio', 'ai-site-assistant-autopilot' ),
			'recordStart'      => __( 'Record a question', 'ai-site-assistant-autopilot' ),
			'recordStop'       => __( 'Stop recording', 'ai-site-assistant-autopilot' ),
			'listening'        => __( 'Listening… Select the microphone again when you are finished.', 'ai-site-assistant-autopilot' ),
			'transcribing'     => __( 'Transcribing your question…', 'ai-site-assistant-autopilot' ),
			'transcriptReady'  => __( 'Transcript ready — check it, then send. Nothing is sent on its own.', 'ai-site-assistant-autopilot' ),
			'transcribeFailed' => __( 'Voice transcription failed. Please try again.', 'ai-site-assistant-autopilot' ),
			'noAudio'          => __( 'No audio was captured. Please try again.', 'ai-site-assistant-autopilot' ),
			'maxDuration'      => __( 'Maximum recording time reached. Transcribing…', 'ai-site-assistant-autopilot' ),
			'micUnsupported'   => __( 'Voice recording is not supported in this browser.', 'ai-site-assistant-autopilot' ),
			'micDenied'        => __( 'Microphone access was denied. Allow it in your browser, then try again.', 'ai-site-assistant-autopilot' ),
			'recorderFailed'   => __( 'This browser cannot create a supported voice recording.', 'ai-site-assistant-autopilot' ),
		);
	}
}
