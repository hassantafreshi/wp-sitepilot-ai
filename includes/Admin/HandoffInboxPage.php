<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Support\HandoffInbox;

/**
 * Administrator-only inbox for conversations explicitly handed to a human.
 *
 * The list itself is exposed via {@see render_inbox()} so the exact same surface
 * can be embedded inline in the Answer Desk "Human handoff" tab instead of
 * feeling like a separate page.
 */
final class HandoffInboxPage {


	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}
		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen">
		<?php Ui::title( __( 'Handoff inbox', 'ai-site-assistant-autopilot' ), 'headset', true ); ?>
				<p class="sp-lead"><?php esc_html_e( 'Configure human support handoff and follow the visitor threads that were explicitly escalated.', 'ai-site-assistant-autopilot' ); ?></p>
		<?php AnswerDeskNavigation::render( AnswerDeskNavigation::TAB_HANDOFF ); ?>
				<div class="sp-row-wrap" style="margin:0 0 16px">
					<a class="sp-btn sp-btn--ghost sp-btn--r8" href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF ) ); ?>"><?php esc_html_e( 'Configure handoff channels', 'ai-site-assistant-autopilot' ); ?></a>
				</div>
		<?php $this->render_inbox(); ?>
			</section>
		<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * The escalated-conversation inbox: the status alert (after an update) plus
	 * the list of handoff threads. Deliberately free of any page wrapper so it can
	 * render both on this page and inline inside the Answer Desk handoff tab.
	 */
	public function render_inbox(): void {
		$inbox   = new HandoffInbox();
		$updated = false;
		if ( current_user_can( 'manage_options' )
			&& isset( $_POST['aisaautopilot_handoff_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aisaautopilot_handoff_nonce'] ) ), 'aisaautopilot_handoff_status' ) ) {
			$updated = $inbox->set_status( absint( $_POST['handoff_id'] ?? 0 ), sanitize_key( (string) wp_unslash( $_POST['handoff_status'] ?? '' ) ) );
		}
		$items = $inbox->list();

		if ( $updated ) {
			Ui::alert( 'ok', 'check-circle', esc_html__( 'Handoff status updated.', 'ai-site-assistant-autopilot' ) );
		}

		if ( empty( $items ) ) {
			?>
			<div class="sp-card sp-empty"><span class="sp-empty__icon">💬</span><?php esc_html_e( 'No visitor has requested a human handoff yet.', 'ai-site-assistant-autopilot' ); ?></div>
			<?php
			return;
		}
		?>
		<div class="sp-stack sp-stack--md">
		<?php foreach ( $items as $item ) : ?>
			<article class="sp-card sp-card--24">
				<div class="sp-row" style="align-items:flex-start;gap:14px">
					<div class="sp-grow">
						<h2 class="sp-h3" style="margin-bottom:4px !important"><?php echo esc_html( (string) ( $item['visitor_name'] ?: __( 'Visitor', 'ai-site-assistant-autopilot' ) ) ); ?></h2>
			<?php if ( '' !== (string) $item['visitor_email'] ) : ?>
						<p class="sp-sub sp-mb0"><?php echo esc_html( (string) $item['visitor_email'] ); ?></p>
			<?php endif; ?>
					</div>
			<?php echo Ui::pill( $this->status_label( (string) $item['status'] ), $this->status_tone( (string) $item['status'] ), false, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
				</div>
				<div class="sp-stack" style="margin:14px 0">
			<?php foreach ( array_slice( (array) $item['messages'], -12 ) as $message ) : ?>
				<?php if ( is_array( $message ) ) : ?>
						<p class="sp-sub sp-mb0"><strong><?php echo esc_html( 'assistant' === ( $message['role'] ?? '' ) ? __( 'Assistant', 'ai-site-assistant-autopilot' ) : __( 'Visitor', 'ai-site-assistant-autopilot' ) ); ?>:</strong> <?php echo esc_html( (string) ( $message['content'] ?? '' ) ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>
				</div>
				<form method="post" class="sp-row" style="gap:10px">
			<?php wp_nonce_field( 'aisaautopilot_handoff_status', 'aisaautopilot_handoff_nonce' ); ?>
					<input type="hidden" name="handoff_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
					<select class="sp-select" name="handoff_status" aria-label="<?php esc_attr_e( 'Handoff status', 'ai-site-assistant-autopilot' ); ?>">
			<?php foreach ( array( 'new', 'in_progress', 'resolved', 'failed' ) as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $item['status'], $status ); ?>><?php echo esc_html( $this->status_label( $status ) ); ?></option>
			<?php endforeach; ?>
					</select>
					<button class="sp-btn sp-btn--ghost sp-btn--r8" type="submit"><?php esc_html_e( 'Update status', 'ai-site-assistant-autopilot' ); ?></button>
					<span class="sp-sub"><?php echo esc_html( (string) $item['created_at'] ); ?></span>
				</form>
			</article>
		<?php endforeach; ?>
		</div>
		<?php
	}

	private function status_label( string $status ): string {
		return array(
			'new'         => __( 'New', 'ai-site-assistant-autopilot' ),
			'in_progress' => __( 'In progress', 'ai-site-assistant-autopilot' ),
			'resolved'    => __( 'Resolved', 'ai-site-assistant-autopilot' ),
			'failed'      => __( 'Delivery failed', 'ai-site-assistant-autopilot' ),
		)[ $status ] ?? __( 'New', 'ai-site-assistant-autopilot' );
	}

	private function status_tone( string $status ): string {
		return 'resolved' === $status ? 'ok' : ( 'failed' === $status ? 'err' : ( 'in_progress' === $status ? 'info' : 'warn' ) );
	}
}
