<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Core\Settings;

/**
 * Read-only administrator view of conversations retained via explicit setting.
 */
final class AnswerConversationsPage {

	/** @var \Closure(): array<int,array<string,mixed>>|null */
	private ?\Closure $conversation_lister;

	/**
	 * @param \Closure(): array<int,array<string,mixed>>|null $conversation_lister
	 */
	public function __construct( ?\Closure $conversation_lister = null ) {
		$this->conversation_lister = $conversation_lister;
	}

	public function render(): void {
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : AnswerDeskNavigation::TAB_CONVERSATIONS; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		if ( AnswerDeskNavigation::TAB_WIDGET === $requested_tab ) {
			( new AnswerDeskWidgetPage() )->render();
			return;
		}
		if ( AnswerDeskNavigation::TAB_HANDOFF === $requested_tab ) {
			( new AnswerDeskHandoffPage() )->render();
			return;
		}
		if ( AnswerDeskNavigation::TAB_VOICE === $requested_tab ) {
			( new AnswerDeskVoicePage() )->render();
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}
		$widget = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		$items  = null === $this->conversation_lister
			? ( new VisitorConversationStore() )->list()
			: (array) call_user_func( $this->conversation_lister );
		$state  = $this->screen_state( $widget, $items );
		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-answer-desk data-sp-answer-tab="conversations" data-sp-answer-state="<?php echo esc_attr( $state ); ?>"><section class="sp-screen">
		<?php
		Ui::page_header(
			__( 'Answer Desk', 'ai-site-assistant-autopilot' ),
			__( 'The support chat on your site, and every conversation it handled.', 'ai-site-assistant-autopilot' ),
			array(
				'chip'    => ConnectionChip::derive(),
				'action'  => array(
					'label' => __( 'Widget settings', 'ai-site-assistant-autopilot' ),
					'url'   => AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ),
				),
				'subtabs' => AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_CONVERSATIONS ),
			)
		);
		?>
		<div class="sp-pagebody">
		<?php
		switch ( $state ) {
			case 'widget-hidden':
				$this->render_widget_hidden( $widget );
				break;
			case 'logging-off':
				$this->render_logging_off();
				break;
			case 'empty':
				$this->render_empty();
				break;
			default:
				$this->render_conversations( $items, $widget );
				break;
		}
		?>
		</div>
		</section><?php Ui::overlays(); ?></div>
		<?php
	}

	/**
	 * @param array<string,mixed>       $widget
	 * @param array<int,array<string,mixed>> $items
	 */
	private function screen_state( array $widget, array $items ): string {
		if ( ! Settings::answer_desk_enabled() ) {
			return 'widget-hidden';
		}
		if ( empty( $widget['conversation_logging_enabled'] ) ) {
			return 'logging-off';
		}

		return array() === $items ? 'empty' : 'ready';
	}

	/** @param array<string,mixed> $widget */
	private function render_widget_hidden( array $widget ): void {
		$integration_on = ! empty( Settings::get( 'public_assistant_enabled', false ) );
		$url            = $integration_on
			? AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET )
			: admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS );
		$label          = $integration_on
			? __( 'Open Widget settings', 'ai-site-assistant-autopilot' )
			: __( 'Open Integrations', 'ai-site-assistant-autopilot' );
		$message        = $integration_on
			? __( 'The Answer Desk integration is on, but its widget is hidden from visitors.', 'ai-site-assistant-autopilot' )
			: __( 'Answer Desk is turned off in Integrations, so no visitor conversation can be recorded.', 'ai-site-assistant-autopilot' );
		?>
		<?php Ui::alert( 'warn', 'warning', esc_html( $message ) ); ?>
		<section class="sp-card sp-answer-empty" aria-labelledby="sp-answer-hidden-title">
			<span class="sp-answer-empty__icon"><?php echo Ui::icon( 'chat', 22, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<h2 class="sp-answer-empty__title" id="sp-answer-hidden-title"><?php esc_html_e( 'No visitor widget is live yet', 'ai-site-assistant-autopilot' ); ?></h2>
			<p><?php esc_html_e( 'Turn on Answer Desk and its widget before expecting visitor conversations here. Recording remains an explicit privacy choice.', 'ai-site-assistant-autopilot' ); ?></p>
			<a class="sp-btn sp-btn--ghost sp-btn--r8" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		</section>
		<?php
	}

	private function render_logging_off(): void {
		$widget_url = AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET );
		?>
		<?php Ui::alert( 'info', 'shield', esc_html__( 'Conversation recording is off. Enable it only after reviewing your visitor notice and privacy obligations.', 'ai-site-assistant-autopilot' ) ); ?>
		<section class="sp-card sp-answer-empty" aria-labelledby="sp-answer-logging-title">
			<span class="sp-answer-empty__icon"><?php echo Ui::icon( 'shield', 22, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<h2 class="sp-answer-empty__title" id="sp-answer-logging-title"><?php esc_html_e( 'Conversation recording is optional', 'ai-site-assistant-autopilot' ); ?></h2>
			<p><?php esc_html_e( 'The widget can answer visitors without keeping their threads. Turn on recording if you need this private inbox, and make the notice visible to visitors.', 'ai-site-assistant-autopilot' ); ?></p>
			<a class="sp-btn sp-btn--ghost sp-btn--r8" href="<?php echo esc_url( $widget_url ); ?>"><?php esc_html_e( 'Review recording settings', 'ai-site-assistant-autopilot' ); ?></a>
		</section>
		<?php
	}

	private function render_empty(): void {
		?>
		<section class="sp-card sp-answer-empty" aria-labelledby="sp-answer-empty-title">
			<span class="sp-answer-empty__icon"><?php echo Ui::icon( 'chat', 22, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<h2 class="sp-answer-empty__title" id="sp-answer-empty-title"><?php esc_html_e( 'No conversations yet', 'ai-site-assistant-autopilot' ); ?></h2>
			<p><?php esc_html_e( 'The widget is ready to record conversations. As soon as a visitor asks a question, its private thread and any saved sources appear here.', 'ai-site-assistant-autopilot' ); ?></p>
		</section>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<string,mixed>             $widget
	 */
	private function render_conversations( array $items, array $widget ): void {
		$count_label = sprintf(
			/* translators: %s: number of recorded conversations. */
			_n( '%s recorded thread', '%s recorded threads', count( $items ), 'ai-site-assistant-autopilot' ),
			number_format_i18n( count( $items ) )
		);
		?>
		<div class="sp-answer-grid">
			<section class="sp-card sp-answer-conversation-list" data-sp-answer-conversation-list aria-labelledby="sp-answer-conversations-title">
				<header class="sp-answer-panel-head">
					<div>
						<h2 class="sp-h3 sp-mb0" id="sp-answer-conversations-title"><?php esc_html_e( 'Recorded conversations', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-sub sp-mb0"><?php esc_html_e( 'Latest visitor threads kept with their opted-in recording setting.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<span class="sp-answer-count" dir="ltr"><?php echo esc_html( $count_label ); ?></span>
				</header>
				<div class="sp-answer-conversation-list__items">
				<?php foreach ( $items as $item ) : ?>
					<?php $this->render_conversation( $item ); ?>
				<?php endforeach; ?>
				</div>
			</section>

			<aside class="sp-answer-side">
				<?php $this->render_widget_preview( $widget ); ?>
				<?php $this->render_cannot_answer(); ?>
			</aside>
		</div>
		<?php
	}

	/** @param array<string,mixed> $item */
	private function render_conversation( array $item ): void {
		$summary = $this->conversation_summary( $item );
		$title   = sprintf(
			/* translators: %d: Conversation ID. */
			__( 'Conversation #%d', 'ai-site-assistant-autopilot' ),
			(int) ( $item['id'] ?? 0 )
		);
		$updated = (string) ( $item['updated_at'] ?? '' );
		?>
		<article class="sp-answer-conversation" data-sp-answer-conversation>
			<div class="sp-answer-conversation__meta">
				<h3 class="sp-answer-conversation__title"><?php echo esc_html( $title ); ?></h3>
				<span class="sp-answer-conversation__time" data-sp-answer-time dir="ltr"><bdi title="<?php echo esc_attr( $updated ); ?>"><?php echo esc_html( $this->format_time( $updated ) ); ?></bdi></span>
			</div>
			<p class="sp-answer-conversation__question" dir="auto"><?php echo esc_html( $this->excerpt( $summary['question'], 240 ) ); ?></p>
			<?php if ( '' !== $summary['reply'] ) : ?>
				<p class="sp-answer-conversation__reply" dir="auto"><?php echo esc_html( $this->excerpt( $summary['reply'], 280 ) ); ?></p>
			<?php endif; ?>
			<?php $this->render_sources( $summary['sources'] ); ?>
			<?php $this->render_transcript( $summary['messages'] ); ?>
		</article>
		<?php
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{question:string,reply:string,sources:array<int,mixed>,messages:array<int,mixed>}
	 */
	private function conversation_summary( array $item ): array {
		$question = '';
		$reply    = '';
		$sources  = array();
		$messages = array_values( array_filter( (array) ( $item['messages'] ?? array() ), 'is_array' ) );

		foreach ( $messages as $message ) {
			$role    = (string) ( $message['role'] ?? '' );
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( 'user' === $role && '' !== $content ) {
				$question = $content;
			}
			if ( 'assistant' === $role && '' !== $content ) {
				$reply   = $content;
				$sources = (array) ( $message['sources'] ?? array() );
			}
		}

		if ( '' === $question ) {
			$question = __( 'No visitor message was retained for this thread.', 'ai-site-assistant-autopilot' );
		}

		return array(
			'question' => $question,
			'reply'    => $reply,
			'sources'  => $sources,
			'messages' => array_slice( $messages, -12 ),
		);
	}

	/** @param array<int,mixed> $sources */
	private function render_sources( array $sources ): void {
		$valid = array();
		foreach ( array_slice( $sources, 0, 6 ) as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$url   = esc_url( (string) ( $source['url'] ?? '' ) );
			$title = trim( (string) ( $source['title'] ?? '' ) );
			if ( '' !== $url && '' !== $title ) {
				$valid[] = array(
					'url'   => $url,
					'title' => $title,
				);
			}
		}
		if ( array() === $valid ) {
			return;
		}
		?>
		<div class="sp-answer-sources">
			<?php echo Ui::icon( 'book', 14, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
			<span><?php esc_html_e( 'Answered from', 'ai-site-assistant-autopilot' ); ?></span>
			<span class="sp-answer-sources__links">
			<?php foreach ( $valid as $source ) : ?>
				<a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" data-sp-answer-source><?php echo esc_html( $source['title'] ); ?></a>
			<?php endforeach; ?>
			</span>
		</div>
		<?php
	}

	/** @param array<int,mixed> $messages */
	private function render_transcript( array $messages ): void {
		if ( array() === $messages ) {
			return;
		}
		?>
		<details class="sp-answer-transcript">
			<summary><?php esc_html_e( 'View recorded thread', 'ai-site-assistant-autopilot' ); ?></summary>
			<div class="sp-answer-transcript__items">
			<?php foreach ( $messages as $message ) : ?>
				<?php
				$role    = 'assistant' === ( $message['role'] ?? '' ) ? 'assistant' : 'visitor';
				$content = trim( (string) ( $message['content'] ?? '' ) );
				if ( '' === $content ) {
					continue;
				}
				?>
				<div class="sp-answer-transcript__turn sp-answer-transcript__turn--<?php echo esc_attr( $role ); ?>">
					<strong><?php echo esc_html( 'assistant' === $role ? __( 'Assistant', 'ai-site-assistant-autopilot' ) : __( 'Visitor', 'ai-site-assistant-autopilot' ) ); ?></strong>
					<p dir="auto"><?php echo esc_html( $content ); ?></p>
				</div>
			<?php endforeach; ?>
			</div>
		</details>
		<?php
	}

	/** @param array<string,mixed> $widget */
	private function render_widget_preview( array $widget ): void {
		$brand       = sanitize_hex_color( (string) ( $widget['color'] ?? '' ) ) ?: '#202a8d';
		$copy        = Settings::widget_copy( $widget );
		$title       = $copy['title'];
		$subtitle    = $copy['subtitle'];
		$greeting    = $copy['greeting'];
		$suggestions = array_filter( (array) ( $widget['suggestions'] ?? array() ), 'is_string' );
		$preview_dir = is_rtl() ? 'rtl' : 'ltr';
		?>
		<section class="sp-card sp-answer-preview" data-sp-answer-preview aria-labelledby="sp-answer-preview-title">
			<div class="sp-answer-side__head">
				<h2 class="sp-h3 sp-mb0" id="sp-answer-preview-title"><?php esc_html_e( 'How it looks on your site', 'ai-site-assistant-autopilot' ); ?></h2>
				<a href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ) ); ?>"><?php esc_html_e( 'Settings', 'ai-site-assistant-autopilot' ); ?></a>
			</div>
			<div class="sp-answer-preview__widget" dir="<?php echo esc_attr( $preview_dir ); ?>" style="--sp-answer-accent:<?php echo esc_attr( $brand ); ?>">
				<?php /* F15-2 (T17): copy the owner typed may be in another script than the admin; dir="auto" keeps its punctuation in place, as the widget itself does. */ ?>
				<div class="sp-answer-preview__header">
					<strong dir="auto"><?php echo esc_html( '' !== $title ? $title : __( 'Answer Desk', 'ai-site-assistant-autopilot' ) ); ?></strong>
					<span dir="auto"><?php echo esc_html( $subtitle ); ?></span>
				</div>
				<div class="sp-answer-preview__body">
					<p class="sp-answer-preview__greeting" dir="auto"><?php echo esc_html( $greeting ); ?></p>
					<?php if ( array() !== $suggestions ) : ?>
						<span class="sp-answer-preview__suggestion" dir="auto"><?php echo esc_html( (string) reset( $suggestions ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="sp-answer-preview__input"><?php esc_html_e( 'Type your question…', 'ai-site-assistant-autopilot' ); ?></div>
			</div>
		</section>
		<?php
	}

	private function render_cannot_answer(): void {
		$handoff_ready = Settings::answer_desk_handoff_available();
		$handoff_url   = AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF );
		$gaps_url      = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		?>
		<section class="sp-card sp-answer-cannot-answer" aria-labelledby="sp-answer-cannot-title">
			<h2 class="sp-h3" id="sp-answer-cannot-title"><?php esc_html_e( 'When it cannot answer', 'ai-site-assistant-autopilot' ); ?></h2>
			<p>
			<?php
			echo esc_html(
				$handoff_ready
					? __( 'It does not guess. Unanswered questions become content gaps, and visitors can use a configured human handoff channel.', 'ai-site-assistant-autopilot' )
					: __( 'It does not guess. Unanswered questions become content gaps; connect a handoff channel if you want visitors to reach a person.', 'ai-site-assistant-autopilot' )
			);
			?>
			</p>
			<div class="sp-answer-cannot-answer__actions">
				<a href="<?php echo esc_url( $gaps_url ); ?>"><?php esc_html_e( 'View content gaps', 'ai-site-assistant-autopilot' ); ?></a>
				<?php if ( ! $handoff_ready ) : ?>
					<a href="<?php echo esc_url( $handoff_url ); ?>"><?php esc_html_e( 'Configure handoff', 'ai-site-assistant-autopilot' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	private function format_time( string $raw ): string {
		$timestamp = strtotime( $raw . ' UTC' );
		if ( false === $timestamp ) {
			return $raw;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	private function excerpt( string $text, int $limit ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '…';
	}
}
