<?php
declare(strict_types=1);
namespace SitePilot\Frontend;
use SitePilot\Core\Settings;

final class ChatWidget {

	/* Inline line-icons (trusted static markup) — currentColor so they inherit their button color. */
	private const ICON_CHAT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
	private const ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
	private const ICON_ASSISTANT = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.6 4.3a3 3 0 0 0 1.8 1.8L19.5 10l-4.1 1.4a3 3 0 0 0-1.8 1.8L12 17.5l-1.6-4.3a3 3 0 0 0-1.8-1.8L4.5 10l4.1-1.4a3 3 0 0 0 1.8-1.8L12 2.5z"/><path d="M18.5 14l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7.7-1.9z"/></svg>';
	private const ICON_SEND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
	private const ICON_MIC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
	private const ICON_SUPPORT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>';

	public function render(): string {
		$s = Settings::all();
		$w = $s['widget'] ?? Settings::widget_defaults();
		if ( empty( $w['enabled'] ) ) { return ''; }

		$theme     = ( ( $w['theme'] ?? 'light' ) === 'dark' ) ? 'dark' : 'light';
		$allowed   = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
		$pos       = in_array( $w['position'] ?? '', $allowed, true ) ? $w['position'] : 'bottom-right';
		$color     = sanitize_hex_color( $w['color'] ?? '' ) ?: '#4f46e5';
		$bg        = sanitize_hex_color( $w['bg'] ?? '' ) ?: ( 'dark' === $theme ? '#1f2937' : '#ffffff' );
		$title     = $w['title'] ?? 'SitePilot AI';
		$greet     = $w['greeting'] ?? __( 'Hi! How can I help?', 'sitepilot-ai' );
		$subtitle  = __( 'AI Assistant', 'sitepilot-ai' );
		$voice     = ! empty( $w['voice_enabled'] );
		$handoff   = ! empty( $w['handoff_enabled'] );

		ob_start();
		?>
		<div class="spw-root spw--<?php echo esc_attr( $theme ); ?> spw--<?php echo esc_attr( $pos ); ?>"
			style="--spw-primary: <?php echo esc_attr( $color ); ?>; --spw-bg: <?php echo esc_attr( $bg ); ?>;">

			<button type="button" id="spw-toggle" class="spw-toggle" aria-label="<?php esc_attr_e( 'Open chat', 'sitepilot-ai' ); ?>">
				<span class="spw-toggle__ico spw-toggle__ico--open"><?php echo self::ICON_CHAT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
				<span class="spw-toggle__ico spw-toggle__ico--close"><?php echo self::ICON_CLOSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
			</button>

			<div id="spw-panel" class="spw-panel" role="dialog" aria-label="<?php esc_attr_e( 'Chat', 'sitepilot-ai' ); ?>">
				<div class="spw-header">
					<span class="spw-avatar"><?php echo self::ICON_ASSISTANT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
					<div class="spw-header__meta">
						<div class="spw-header__title"><?php echo esc_html( $title ); ?></div>
						<div class="spw-header__sub"><span class="spw-status-dot"></span><?php echo esc_html( $subtitle ); ?></div>
					</div>
					<button type="button" id="spw-close" class="spw-close" aria-label="<?php esc_attr_e( 'Close', 'sitepilot-ai' ); ?>">
						<?php echo self::ICON_CLOSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
					</button>
				</div>

				<div id="spw-msgs" class="spw-messages" role="log" aria-live="polite">
					<div class="spw-msg spw-msg--ai spw-msg--greeting"><?php echo esc_html( $greet ); ?></div>
				</div>

				<?php if ( $handoff ) : ?>
					<div class="spw-handoff">
						<button type="button" id="spw-handoff-btn" class="spw-handoff-btn">
							<?php echo self::ICON_SUPPORT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
							<span><?php esc_html_e( 'Talk to a human', 'sitepilot-ai' ); ?></span>
						</button>
					</div>
				<?php endif; ?>

				<div class="spw-input-wrap">
					<?php if ( $voice ) : ?>
						<button type="button" id="spw-mic" class="spw-mic" aria-label="<?php esc_attr_e( 'Voice input', 'sitepilot-ai' ); ?>">
							<?php echo self::ICON_MIC; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
						</button>
					<?php endif; ?>
					<textarea class="spw-input" rows="1" placeholder="<?php esc_attr_e( 'Type your message…', 'sitepilot-ai' ); ?>" aria-label="<?php esc_attr_e( 'Type your message', 'sitepilot-ai' ); ?>"></textarea>
					<button type="button" id="spw-send" class="spw-send" aria-label="<?php esc_attr_e( 'Send', 'sitepilot-ai' ); ?>">
						<?php echo self::ICON_SEND; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
	public function should_render_widget(): bool {
		$s = Settings::all();
		$w = $s['widget'] ?? Settings::widget_defaults();
		if ( empty( $w['enabled'] ) ) { return false; }
		$visibility = $w['widget_visibility'] ?? 'all';
		if ( 'all' === $visibility ) { return true; }

		$ids = (array) ( $w['widget_exclude_ids'] ?? array() );
		$pages = (array) ( $w['exclude_pages'] ?? array() );
		$cats = (array) ( $w['exclude_cats'] ?? array() );
		$types = (array) ( $w['exclude_types'] ?? array() );

		if ( 'exclude' === $visibility ) {
			$current_id = (int) ( get_queried_object_id() );
			$current_type = get_post_type();
			if ( in_array( $current_id, $ids, true ) ) { return false; }
			if ( in_array( $current_type, $types, true ) ) { return false; }
			if ( is_singular() && has_category( $cats ) ) { return false; }
		}
		return true;
	}

	public static function render_standalone(): void {
		$widget = new self();
		if ( ! $widget->should_render_widget() ) { return; }
		echo '<div class="sp-widget-standalone">' . $widget->render() . '</div>';
	}
}
