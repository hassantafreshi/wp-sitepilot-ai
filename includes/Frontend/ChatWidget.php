<?php
declare(strict_types=1);
namespace AISAAutoPilot\Frontend;
use AISAAutoPilot\Core\Settings;

final class ChatWidget {

	/* Inline line-icons (trusted static markup) — currentColor so they inherit their button color. */
	private const ICON_CHAT      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
	private const ICON_CLOSE     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
	private const ICON_ASSISTANT = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.6 4.3a3 3 0 0 0 1.8 1.8L19.5 10l-4.1 1.4a3 3 0 0 0-1.8 1.8L12 17.5l-1.6-4.3a3 3 0 0 0-1.8-1.8L4.5 10l4.1-1.4a3 3 0 0 0 1.8-1.8L12 2.5z"/><path d="M18.5 14l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7.7-1.9z"/></svg>';
	private const ICON_SEND      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
	private const ICON_MIC       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
	private const ICON_SUPPORT   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>';
	private const ICON_EXPAND    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6"/><path d="M21 3l-7 7M3 21l7-7"/></svg>';
	private const ICON_SHRINK    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3v6H3M15 21v-6h6"/><path d="M3 9l6-6M21 15l-6 6"/></svg>';
	private const ICON_STOP      = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/></svg>';
	private const ICON_DOWN      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>';

	public function render(): string {
		$w = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		if ( ! $this->should_render_widget() ) {
			return ''; }

		$theme   = ( ( $w['theme'] ?? 'light' ) === 'dark' ) ? 'dark' : 'light';
		$allowed = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
		$pos     = in_array( $w['position'] ?? '', $allowed, true ) ? $w['position'] : 'bottom-right';
		$color   = sanitize_hex_color( $w['color'] ?? '' ) ?: '#202a8d';

		// The customiser's per-part palette, falling back to the brand-derived one.
		$derived = Settings::widget_derived_colors( $color, $theme );
		$colors  = ! empty( $w['custom_colors'] )
			? array_merge( $derived, array_filter( (array) ( $w['colors'] ?? array() ) ) )
			: $derived;

		$icons        = (array) ( $w['icons'] ?? array() );
		$title        = (string) ( $w['title'] ?? 'AI Site Assistant AutoPilot' );
		$greet        = (string) ( $w['greeting'] ?? __( 'Hi! How can I help?', 'ai-site-assistant-autopilot' ) );
		$subtitle     = (string) ( $w['subtitle'] ?? __( 'AI Assistant', 'ai-site-assistant-autopilot' ) );
		$handoff_text = (string) ( $w['handoff_text'] ?? __( 'Talk to a human', 'ai-site-assistant-autopilot' ) );
		$handoff_hook = (string) ( $w['handoff_hook'] ?? '' );
		$voice        = ! empty( $w['voice_enabled'] );
		$handoff      = Settings::answer_desk_handoff_available();
		$recording    = ! empty( $w['conversation_logging_enabled'] );
		$notice       = sanitize_text_field( (string) ( $w['conversation_notice'] ?? '' ) );
		$suggestions  = $this->suggestions( $w );

		$style = $this->style_vars( $colors, $w, $theme );

		ob_start();
		?>
		<?php
		/*
		 * The widget carries its own direction rather than inheriting it. Themes
		 * are inconsistent about setting dir on <html>, and the panel is a
		 * self-contained surface: its text has to read correctly even inside a
		 * theme that never declares one. Placement stays physical (see
		 * widget.css) — "bottom right" means the bottom right corner on an RTL
		 * site too.
		 */
		?>
		<div class="spw-root spw--<?php echo esc_attr( $theme ); ?> spw--<?php echo esc_attr( $pos ); ?>"
			dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
			style="<?php echo esc_attr( $style ); ?>">

			<button type="button" id="spw-toggle" class="spw-toggle" aria-label="<?php esc_attr_e( 'Open chat', 'ai-site-assistant-autopilot' ); ?>">
				<span class="spw-toggle__ico spw-toggle__ico--open"><?php echo $this->icon_or( $icons, 'float', self::ICON_CHAT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
				<span class="spw-toggle__ico spw-toggle__ico--close"><?php echo self::ICON_CLOSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
			</button>

			<div id="spw-panel" class="spw-panel" role="dialog" aria-modal="false"
				aria-labelledby="spw-title" tabindex="-1">

				<div class="spw-header">
					<span class="spw-avatar"><?php echo $this->icon_or( $icons, 'avatar', self::ICON_ASSISTANT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
					<div class="spw-header__meta">
						<div class="spw-header__title" id="spw-title"><?php echo esc_html( $title ); ?></div>
						<div class="spw-header__sub"><span class="spw-status-dot"></span><?php echo esc_html( $subtitle ); ?></div>
					</div>
					<button type="button" id="spw-expand" class="spw-hbtn spw-hbtn--expand"
						aria-label="<?php esc_attr_e( 'Full screen', 'ai-site-assistant-autopilot' ); ?>"
						title="<?php esc_attr_e( 'Full screen', 'ai-site-assistant-autopilot' ); ?>">
						<span class="spw-hbtn__on"><?php echo self::ICON_EXPAND; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
						<span class="spw-hbtn__off"><?php echo self::ICON_SHRINK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
					</button>
					<button type="button" id="spw-close" class="spw-hbtn" aria-label="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Close', 'ai-site-assistant-autopilot' ); ?>">
						<?php echo self::ICON_CLOSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
					</button>
				</div>

				<div class="spw-body">
					<?php /* aria-atomic=false so assistive tech announces only new turns, not the whole log. */ ?>
					<div id="spw-msgs" class="spw-messages" role="log" aria-live="polite" aria-atomic="false"
						aria-label="<?php esc_attr_e( 'Conversation', 'ai-site-assistant-autopilot' ); ?>">

						<div id="spw-welcome" class="spw-welcome">
							<span class="spw-welcome__icon"><?php echo $this->icon_or( $icons, 'avatar', self::ICON_ASSISTANT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
							<h3 class="spw-welcome__title"><?php echo esc_html( $greet ); ?></h3>
							<?php if ( array() !== $suggestions ) : ?>
								<p class="spw-welcome__hint"><?php esc_html_e( 'Pick a question, or type your own.', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="spw-suggest" role="list">
									<?php foreach ( $suggestions as $suggestion ) : ?>
										<button type="button" class="spw-suggest__chip" role="listitem" data-spw-ask="<?php echo esc_attr( $suggestion ); ?>"><?php echo esc_html( $suggestion ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<button type="button" id="spw-jump" class="spw-jump" hidden
						aria-label="<?php esc_attr_e( 'Scroll to latest message', 'ai-site-assistant-autopilot' ); ?>">
						<?php echo self::ICON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
					</button>
				</div>

				<?php if ( $handoff ) : ?>
					<div class="spw-handoff">
						<?php if ( '' !== $handoff_hook ) : ?>
							<p><?php echo esc_html( $handoff_hook ); ?></p>
						<?php endif; ?>
						<button type="button" id="spw-handoff-btn" class="spw-handoff-btn">
							<?php echo $this->icon_or( $icons, 'handoff', self::ICON_SUPPORT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?>
							<span><?php echo esc_html( $handoff_text ); ?></span>
						</button>
					</div>
				<?php endif; ?>

				<div class="spw-composer">
					<div class="spw-composer__row">
						<?php if ( $voice ) : ?>
							<button type="button" id="spw-mic" class="spw-mic" aria-label="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>">
								<?php echo $this->icon_or( $icons, 'voice', self::ICON_MIC ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?>
							</button>
						<?php endif; ?>
						<textarea class="spw-input" rows="1"
							placeholder="<?php esc_attr_e( 'Type your message…', 'ai-site-assistant-autopilot' ); ?>"
							aria-label="<?php esc_attr_e( 'Type your message', 'ai-site-assistant-autopilot' ); ?>"></textarea>
						<button type="button" id="spw-send" class="spw-send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>">
							<span class="spw-send__ico"><?php echo self::ICON_SEND; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
							<span class="spw-send__stop"><?php echo self::ICON_STOP; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
						</button>
					</div>
					<p class="spw-disclaimer"><?php esc_html_e( 'AI answers can be wrong. Check anything important.', 'ai-site-assistant-autopilot' ); ?></p>
					<?php
					if ( $recording && '' !== $notice ) :
						?>
						<p class="spw-recording-notice"><?php echo esc_html( $notice ); ?></p><?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build the inline custom-property block the stylesheet reads.
	 *
	 * @param array<string, string> $colors
	 * @param array<string, mixed>  $w
	 */
	private function style_vars( array $colors, array $w, string $theme ): string {
		$map = array(
			'--spw-primary'      => $colors['head_from'] ?? '#202a8d',
			'--spw-head-from'    => $colors['head_from'] ?? '#202a8d',
			'--spw-head-to'      => $colors['head_to'] ?? '#181f66',
			'--spw-user'         => $colors['user'] ?? '#202a8d',
			'--spw-ai-bg'        => $colors['ai_bg'] ?? '#ffffff',
			'--spw-ai-text'      => $colors['ai_text'] ?? '#1f2937',
			'--spw-bg'           => $colors['panel'] ?? '#ffffff',
			'--spw-msgs'         => $colors['msgs'] ?? '#f9fafb',
			'--spw-border'       => $colors['border'] ?? '#e5e7eb',
			'--spw-input-bg'     => $colors['input'] ?? '#f9fafb',
			'--spw-send'         => $colors['send'] ?? '#202a8d',
			'--spw-handoff'      => $colors['handoff'] ?? '#202a8d',
			'--spw-float'        => $colors['bubble'] ?? '#202a8d',
			'--spw-text'         => 'dark' === $theme ? '#f3f4f6' : '#1f2937',

			'--spw-radius'       => (int) ( $w['panel_radius'] ?? 16 ) . 'px',
			'--spw-msg-radius'   => (int) ( $w['msg_radius'] ?? 14 ) . 'px',
			'--spw-float-radius' => (int) ( $w['bubble_radius'] ?? 30 ) . 'px',

			// Panel size was configurable but never reached the stylesheet.
			'--spw-w'            => max( 320, min( 560, (int) ( $w['window_width'] ?? 400 ) ) ) . 'px',
			'--spw-h'            => max( 380, min( 820, (int) ( $w['window_height'] ?? 620 ) ) ) . 'px',
		);

		$out = '';
		foreach ( $map as $property => $value ) {
			$value = (string) $value;
			// Only hex colours and "<n>px" ever reach the attribute.
			if ( ! preg_match( '/^(#[0-9a-fA-F]{3,8}|\d{1,3}px)$/', $value ) ) {
				continue;
			}
			$out .= $property . ':' . $value . ';';
		}

		return $out;
	}

	/**
	 * Opening suggestions for the empty state.
	 *
	 * Offering a few concrete questions before the free-text field is the
	 * pattern that converts best: it tells the visitor what the assistant can
	 * actually answer instead of leaving them to guess.
	 *
	 * @param array<string, mixed> $w
	 * @return array<int, string>
	 */
	private function suggestions( array $w ): array {
		$raw = $w['suggestions'] ?? null;

		if ( ! is_array( $raw ) ) {
			$raw = array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) );
		}

		$out = array();
		foreach ( $raw as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );
			if ( '' !== $line ) {
				$out[] = mb_substr( $line, 0, 90 );
			}
		}

		return array_slice( $out, 0, 4 );
	}

	/**
	 * A saved icon upload, or the bundled SVG when none is set.
	 *
	 * @param array<string, mixed> $icons
	 */
	private function icon_or( array $icons, string $key, string $fallback ): string {
		$src = trim( (string) ( $icons[ $key ] ?? '' ) );
		if ( '' === $src ) {
			return $fallback;
		}

		// Data URIs are stored already validated; anything else must be a URL.
		$safe = 0 === strpos( $src, 'data:image/' ) ? $src : esc_url( $src );
		if ( '' === $safe ) {
			return $fallback;
		}

		return '<img src="' . esc_attr( $safe ) . '" alt="" />';
	}

	public function should_render_widget(): bool {
		$s = Settings::all();
		$w = $s['widget'] ?? Settings::widget_defaults();
		if ( ! Settings::answer_desk_enabled() || empty( $w['enabled'] ) ) {
			return false; }
		$visibility = $w['widget_visibility'] ?? 'all';
		if ( 'all' === $visibility ) {
			return true; }

		$ids   = (array) ( $w['widget_exclude_ids'] ?? array() );
		$pages = (array) ( $w['exclude_pages'] ?? array() );
		$cats  = (array) ( $w['exclude_cats'] ?? array() );
		$types = (array) ( $w['exclude_types'] ?? array() );

		if ( 'exclude' === $visibility ) {
			$current_id   = (int) ( get_queried_object_id() );
			$current_type = get_post_type();
			if ( in_array( $current_id, $ids, true ) ) {
				return false; }
			if ( in_array( $current_type, $types, true ) ) {
				return false; }
			if ( is_singular() && has_category( $cats ) ) {
				return false; }
		}
		return true;
	}

	/**
	 * Shortcode callback for the configurable public widget.
	 *
	 * @param array<string,mixed> $attributes
	 */
	public static function render_standalone( array $attributes = array(), ?string $content = null, string $tag = '' ): string {

		unset( $attributes, $content, $tag );
		$widget = new self();
		if ( ! $widget->should_render_widget() ) {
			return '';
		}
		return '<div class="sp-widget-standalone">' . $widget->render() . '</div>';
	}
}
