<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Api\HandoffInboxController;
use AISAAutoPilot\Support\HandoffInbox;

/**
 * Administrator-only inbox for conversations a visitor explicitly handed to a person.
 *
 * {@see render_inbox()} carries no page wrapper, so the same inbox renders on this page and
 * inside the legacy Widget settings handoff tab. Status changes go through the protected
 * `handoffs/{id}/status` route and archiving through `handoffs/{id}/archive`; visitors never
 * see either.
 */
final class HandoffInboxPage {

	/** Messages shown per thread, newest last. */
	private const THREAD_MESSAGES = 12;

	/** Threads loaded into the inbox, and separately into the archive, most recent activity first. */
	private const THREAD_LIMIT = 100;

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}
		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-handoff-inbox>
			<section class="sp-screen">
			<?php
			// An Answer Desk sub-surface: same header and tabs as the Human handoff tab.
			Ui::page_header(
				__( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				__( 'The support chat on your site, and every conversation it handled.', 'ai-site-assistant-autopilot' ),
				array(
					'chip'    => ConnectionChip::derive(),
					'action'  => array(
						'label' => __( 'Configure handoff channels', 'ai-site-assistant-autopilot' ),
						'url'   => AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF ),
					),
					'subtabs' => AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_HANDOFF ),
				)
			);
			?>
				<div class="sp-pagebody">
					<?php $this->render_inbox(); ?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * The inbox itself: filters, thread rows, delivery channels and the thread drawer.
	 */
	public function render_inbox(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$labels  = $this->status_labels();
		$counts  = array_fill_keys( array_merge( array( 'all' ), HandoffInbox::STATUSES, array( 'archived' ) ), 0 );
		$threads = array();
		$inbox   = new HandoffInbox();
		foreach ( $inbox->list( self::THREAD_LIMIT ) as $item ) {
			$thread = $this->thread( $item );
			++$counts['all'];
			++$counts[ $thread['status'] ];
			$threads[] = $thread;
		}
		foreach ( $inbox->list( self::THREAD_LIMIT, 0, true ) as $item ) {
			$threads[] = $this->thread( $item );
			++$counts['archived'];
		}
		$has_threads = array() !== $threads;
		$filters     = array( 'all' => __( 'All', 'ai-site-assistant-autopilot' ) ) + $labels + array( 'archived' => __( 'Archived', 'ai-site-assistant-autopilot' ) );
		$close_icon  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>';
		?>
		<div class="sp-hoi" data-sp-handoff-inbox-list>
			<section class="sp-hoi__card" aria-labelledby="sp-hoi-title">
				<div class="sp-hoi__head">
					<div class="sp-hoi__intro">
						<div class="sp-hoi__title-row">
							<h2 class="sp-hoi__title" id="sp-hoi-title"><?php esc_html_e( 'Waiting for a person', 'ai-site-assistant-autopilot' ); ?></h2>
							<span class="sp-hoi__badge" data-sp-handoff-new-badge<?php echo $counts['new'] > 0 ? '' : ' hidden'; ?>>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of threads nobody has picked up yet. */
									__( '%s new', 'ai-site-assistant-autopilot' ),
									number_format_i18n( $counts['new'] )
								)
							);
							?>
							</span>
						</div>
						<p class="sp-hoi__text"><?php esc_html_e( 'Visitors who asked for a person. The status is for your team only — visitors never see it.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<div class="sp-hoi__filters" role="group" aria-label="<?php esc_attr_e( 'Filter threads by status', 'ai-site-assistant-autopilot' ); ?>"<?php echo $has_threads ? '' : ' hidden'; ?>>
						<?php foreach ( $filters as $key => $label ) : ?>
							<button type="button" class="sp-hoi__filter<?php echo 'all' === $key ? ' is-on' : ''; ?><?php echo 'archived' === $key ? ' sp-hoi__filter--archived' : ''; ?>" data-sp-handoff-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'all' === $key ? 'true' : 'false'; ?>">
								<span><?php echo esc_html( $label ); ?></span>
								<span class="sp-hoi__count" data-sp-handoff-count="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="sp-hoi__empty" data-sp-handoff-empty<?php echo $counts['all'] > 0 ? ' hidden' : ''; ?>>
					<span class="sp-hoi__empty-icon" aria-hidden="true"><?php echo Ui::icon( 'headset', 21, '#202a8d', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
					<h3 class="sp-hoi__empty-title" data-sp-handoff-empty-title><?php echo esc_html( $has_threads ? __( 'Nothing waiting in the inbox', 'ai-site-assistant-autopilot' ) : __( 'No one has asked for a person yet', 'ai-site-assistant-autopilot' ) ); ?></h3>
					<p class="sp-hoi__empty-text" data-sp-handoff-empty-text><?php echo esc_html( $has_threads ? __( 'Every thread is archived. Open Archived to see them or move one back.', 'ai-site-assistant-autopilot' ) : __( 'When a visitor asks for a person, the conversation lands here and in your ready channels.', 'ai-site-assistant-autopilot' ) ); ?></p>
				</div>

				<?php if ( $has_threads ) : ?>
					<ul class="sp-hoi__rows" data-sp-handoff-rows>
						<?php foreach ( $threads as $thread ) : ?>
							<li class="sp-hoi__item" data-sp-handoff-item="<?php echo esc_attr( (string) $thread['id'] ); ?>" data-status="<?php echo esc_attr( $thread['status'] ); ?>" data-archived="<?php echo $thread['archived'] ? '1' : '0'; ?>"<?php echo $thread['archived'] ? ' hidden' : ''; ?>>
								<button type="button" class="sp-hoi__row" data-sp-handoff-open="<?php echo esc_attr( (string) $thread['id'] ); ?>" aria-haspopup="dialog">
									<span class="sp-hoi__who">
										<span class="sp-hoi__dot" aria-hidden="true"></span>
										<span class="sp-hoi__who-text">
											<span class="sp-hoi__name" dir="auto"><?php echo esc_html( $thread['name'] ); ?></span>
											<?php if ( '' !== $thread['contact'] ) : ?>
												<span class="sp-hoi__contact" dir="ltr"><?php echo esc_html( $thread['contact'] ); ?></span>
											<?php else : ?>
												<span class="sp-hoi__contact"><?php esc_html_e( 'No contact details left', 'ai-site-assistant-autopilot' ); ?></span>
											<?php endif; ?>
										</span>
									</span>
									<span class="sp-hoi__question" dir="auto"><?php echo esc_html( '' !== $thread['question'] ? $thread['question'] : '—' ); ?></span>
									<span class="sp-hoi__when"><?php echo esc_html( $thread['when'] ); ?></span>
									<span class="sp-hoi__status" data-sp-handoff-status-label><?php echo esc_html( $labels[ $thread['status'] ] ); ?></span>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="sp-hoi__foot">
					<p class="sp-hoi__foot-text">
						<?php echo Ui::icon( 'info', 14, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?>
						<span><?php esc_html_e( 'Each request is also sent to the channels that are ready. A thread shows the last 12 messages the visitor shared.', 'ai-site-assistant-autopilot' ); ?></span>
					</p>
					<button type="button" class="sp-hoi__archive-all" data-sp-handoff-archive-resolved<?php echo $counts['resolved'] > 0 ? '' : ' hidden'; ?>>
						<?php esc_html_e( 'Archive resolved', 'ai-site-assistant-autopilot' ); ?>
						<span class="sp-hoi__count" data-sp-handoff-archive-resolved-count><?php echo esc_html( number_format_i18n( $counts['resolved'] ) ); ?></span>
					</button>
				</div>
			</section>

			<section class="sp-hoi__channels" aria-labelledby="sp-hoi-channels-title">
				<div class="sp-hoi__channels-body">
					<h3 class="sp-hoi__channels-title" id="sp-hoi-channels-title"><?php esc_html_e( 'Where requests are sent', 'ai-site-assistant-autopilot' ); ?></h3>
					<ul class="sp-hoi__chips">
						<?php foreach ( AnswerDeskHandoffPage::channel_states() as $channel ) : ?>
							<li class="sp-hoi__chip is-<?php echo esc_attr( $channel['state'] ); ?>" data-sp-handoff-channel-state="<?php echo esc_attr( $channel['id'] ); ?>">
								<span class="sp-hoi__chip-dot" aria-hidden="true"></span>
								<span><?php echo esc_html( $channel['label'] . ' · ' . $channel['state_label'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<a class="sp-hoi__configure" href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF ) ); ?>"><?php esc_html_e( 'Configure channels', 'ai-site-assistant-autopilot' ); ?></a>
			</section>

			<div class="sp-hoi-drawer" data-sp-handoff-drawer hidden>
				<div class="sp-hoi-drawer__backdrop" data-sp-handoff-close></div>
				<div class="sp-hoi-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="sp-hoi-drawer-name">
					<div class="sp-hoi-drawer__head">
						<div class="sp-hoi-drawer__who">
							<h2 class="sp-hoi-drawer__name" id="sp-hoi-drawer-name" dir="auto" data-sp-handoff-d="name"></h2>
							<ul class="sp-hoi-drawer__contact" data-sp-handoff-d="contact"></ul>
							<p class="sp-hoi-drawer__dates" data-sp-handoff-d="dates"></p>
						</div>
						<button type="button" class="sp-hoi-drawer__close" data-sp-handoff-close aria-label="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>">
							<?php echo $close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?>
						</button>
					</div>
					<div class="sp-hoi-drawer__section">
						<h3 class="sp-hoi-drawer__label" id="sp-hoi-status-label"><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></h3>
						<div class="sp-hoi-drawer__options" role="group" aria-labelledby="sp-hoi-status-label">
							<?php foreach ( $labels as $status => $label ) : ?>
								<button type="button" class="sp-hoi-drawer__option" aria-pressed="false" data-sp-handoff-set="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
						<p class="sp-hoi-drawer__failed" data-sp-handoff-d="failed" hidden><?php esc_html_e( 'No channel accepted this request when it was sent. Reply to the visitor directly, then mark the thread.', 'ai-site-assistant-autopilot' ); ?></p>
						<p class="sp-hoi-drawer__archived" data-sp-handoff-d="archived" hidden></p>
						<p class="sp-hoi-drawer__saving" data-sp-handoff-d="saving"></p>
					</div>
					<div class="sp-hoi-drawer__thread">
						<h3 class="sp-hoi-drawer__label"><?php esc_html_e( 'Conversation', 'ai-site-assistant-autopilot' ); ?></h3>
						<div class="sp-hoi-drawer__messages" data-sp-handoff-d="messages"></div>
					</div>
					<div class="sp-hoi-drawer__foot">
						<a class="sp-btn sp-btn--r8" data-sp-handoff-d="reply" hidden><?php esc_html_e( 'Reply by email', 'ai-site-assistant-autopilot' ); ?></a>
						<button type="button" class="sp-btn sp-btn--ghost sp-btn--r8" data-sp-handoff-archive><?php esc_html_e( 'Archive', 'ai-site-assistant-autopilot' ); ?></button>
						<button type="button" class="sp-btn sp-btn--ghost sp-btn--r8" data-sp-handoff-close><?php esc_html_e( 'Close', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
				</div>
			</div>
			<?php
			Ui::json_script(
				'sp-handoff-inbox-data',
				array(
					'threads' => $threads,
					'labels'  => $labels,
					'i18n'    => $this->strings(),
				)
			);
			?>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private function status_labels(): array {
		return array(
			'new'         => __( 'New', 'ai-site-assistant-autopilot' ),
			'in_progress' => __( 'In progress', 'ai-site-assistant-autopilot' ),
			'resolved'    => __( 'Resolved', 'ai-site-assistant-autopilot' ),
			'failed'      => __( 'Delivery failed', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * One thread as the inbox shows it: contact details, the first visitor question, the last messages and dates.
	 *
	 * @param array<string,mixed> $item Normalised inbox row.
	 * @return array{id:int,status:string,archived:bool,archived_on:string,name:string,email:string,phone:string,website:string,contact:string,question:string,when:string,asked:string,updated:string,messages:array<int,array{role:string,content:string}>}
	 */
	private function thread( array $item ): array {
		$status = in_array( (string) ( $item['status'] ?? '' ), HandoffInbox::STATUSES, true ) ? (string) $item['status'] : 'new';

		$messages = array();
		foreach ( (array) ( $item['messages'] ?? array() ) as $message ) {
			$content = is_array( $message ) ? trim( (string) ( $message['content'] ?? '' ) ) : '';
			if ( '' === $content ) {
				continue;
			}
			$messages[] = array(
				'role'    => 'assistant' === ( $message['role'] ?? '' ) ? 'assistant' : 'visitor',
				'content' => $content,
			);
		}

		$question = '';
		foreach ( $messages as $message ) {
			if ( 'visitor' === $message['role'] ) {
				$question = $message['content'];
				break;
			}
		}

		$name    = trim( (string) ( $item['visitor_name'] ?? '' ) );
		$email   = (string) ( $item['visitor_email'] ?? '' );
		$email   = is_email( $email ) ? $email : '';
		$phone   = trim( (string) ( $item['visitor_phone'] ?? '' ) );
		$website = (string) ( $item['visitor_website'] ?? '' );
		// Only a web address is ever turned into a link in the drawer.
		$website = 1 === preg_match( '#^https?://#i', $website ) ? esc_url_raw( $website, array( 'http', 'https' ) ) : '';
		$created = (int) strtotime( (string) ( $item['created_at'] ?? '' ) . ' UTC' );
		$updated = (int) strtotime( (string) ( $item['updated_at'] ?? '' ) . ' UTC' );
		$contact = '' !== $email ? $email : ( '' !== $phone ? $phone : (string) preg_replace( '#^https?://#i', '', $website ) );

		return array(
			'id'          => (int) ( $item['id'] ?? 0 ),
			'status'      => $status,
			'archived'    => null !== ( $item['archived_at'] ?? null ),
			'archived_on' => HandoffInboxController::moment( (string) ( $item['archived_at'] ?? '' ) ),
			'name'        => '' !== $name ? $name : __( 'Visitor', 'ai-site-assistant-autopilot' ),
			'email'       => $email,
			'phone'       => $phone,
			'website'     => $website,
			'contact'     => $contact,
			'question'    => mb_substr( $question, 0, 160 ),
			'when'        => $this->when( $created ),
			'asked'       => $this->moment( $created ),
			'updated'     => $this->moment( $updated > 0 ? $updated : $created ),
			'messages'    => array_slice( $messages, -self::THREAD_MESSAGES ),
		);
	}

	private function when( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}
		if ( abs( time() - $timestamp ) < DAY_IN_SECONDS ) {
			return sprintf(
				/* translators: %s: human-readable time difference, such as "8 mins". */
				__( '%s ago', 'ai-site-assistant-autopilot' ),
				human_time_diff( $timestamp )
			);
		}
		return (string) wp_date( (string) get_option( 'date_format' ), $timestamp );
	}

	private function moment( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Copy for handoff-inbox.js.
	 *
	 * @return array<string,string>
	 */
	private function strings(): array {
		return array(
			/* translators: %s: number of threads nobody has picked up yet. */
			'newBadge'             => __( '%s new', 'ai-site-assistant-autopilot' ),
			'noContact'            => __( 'No contact details left — reply in your usual channel', 'ai-site-assistant-autopilot' ),
			'email'                => __( 'Email', 'ai-site-assistant-autopilot' ),
			'phone'                => __( 'Mobile', 'ai-site-assistant-autopilot' ),
			'website'              => __( 'Website', 'ai-site-assistant-autopilot' ),
			/* translators: 1: when the visitor asked, 2: when the thread last changed. */
			'dates'                => __( 'Asked %1$s · updated %2$s', 'ai-site-assistant-autopilot' ),
			'assistant'            => __( 'Assistant', 'ai-site-assistant-autopilot' ),
			'noMessages'           => __( 'The visitor did not share any messages.', 'ai-site-assistant-autopilot' ),
			'saving'               => __( 'Saving…', 'ai-site-assistant-autopilot' ),
			/* translators: %s: thread status, such as "Resolved". */
			'marked'               => __( 'Marked as %s. Visitors do not see this.', 'ai-site-assistant-autopilot' ),
			'saveFailed'           => __( 'Could not update the status. Please try again.', 'ai-site-assistant-autopilot' ),
			'emptyTitle'           => __( 'No one has asked for a person yet', 'ai-site-assistant-autopilot' ),
			'emptyText'            => __( 'When a visitor asks for a person, the conversation lands here and in your ready channels.', 'ai-site-assistant-autopilot' ),
			'allArchivedTitle'     => __( 'Nothing waiting in the inbox', 'ai-site-assistant-autopilot' ),
			'allArchivedText'      => __( 'Every thread is archived. Open Archived to see them or move one back.', 'ai-site-assistant-autopilot' ),
			'filteredTitle'        => __( 'No threads with this status', 'ai-site-assistant-autopilot' ),
			'filteredText'         => __( 'Pick another filter to see the rest.', 'ai-site-assistant-autopilot' ),
			'archivedEmptyTitle'   => __( 'No archived threads', 'ai-site-assistant-autopilot' ),
			'archivedEmptyText'    => __( 'Archive a thread from its panel, or archive every resolved thread at once. Nothing is deleted, and any thread can be moved back.', 'ai-site-assistant-autopilot' ),
			'archive'              => __( 'Archive', 'ai-site-assistant-autopilot' ),
			'restore'              => __( 'Move back to inbox', 'ai-site-assistant-autopilot' ),
			/* translators: %s: date and time the thread was archived. */
			'archivedNote'         => __( 'Archived %s. It no longer counts as waiting and keeps its status.', 'ai-site-assistant-autopilot' ),
			'archivedToast'        => __( 'Archived. It no longer counts as waiting.', 'ai-site-assistant-autopilot' ),
			'restoredToast'        => __( 'Moved back to the inbox.', 'ai-site-assistant-autopilot' ),
			'archiveFailed'        => __( 'Could not archive this thread. Please try again.', 'ai-site-assistant-autopilot' ),
			'archiveResolvedTitle' => __( 'Archive every resolved thread?', 'ai-site-assistant-autopilot' ),
			'archiveResolvedBody'  => __( 'Resolved threads leave the inbox list and stay under Archived, where any of them can be moved back. Nothing is deleted.', 'ai-site-assistant-autopilot' ),
			'archiveResolvedCta'   => __( 'Archive resolved', 'ai-site-assistant-autopilot' ),
			/* translators: %s: number of resolved threads that were archived. */
			'archivedOne'          => _n( '%s resolved thread archived.', '%s resolved threads archived.', 1, 'ai-site-assistant-autopilot' ),
			/* translators: %s: number of resolved threads that were archived. */
			'archivedMany'         => _n( '%s resolved thread archived.', '%s resolved threads archived.', 2, 'ai-site-assistant-autopilot' ),
			'replySubject'         => sprintf(
				/* translators: %s: site name. */
				__( 'Your question on %s', 'ai-site-assistant-autopilot' ),
				wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
			),
			/* translators: %s: number of visitors whose handoff has not been picked up yet. */
			'waitingOne'           => _n( '%s visitor waiting for a person', '%s visitors waiting for a person', 1, 'ai-site-assistant-autopilot' ),
			/* translators: %s: number of visitors whose handoff has not been picked up yet. */
			'waitingMany'          => _n( '%s visitor waiting for a person', '%s visitors waiting for a person', 2, 'ai-site-assistant-autopilot' ),
		);
	}
}
