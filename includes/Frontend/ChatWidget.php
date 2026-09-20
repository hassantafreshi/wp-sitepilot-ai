<?php
declare(strict_types=1);
namespace AISAAutoPilot\Frontend;
use AISAAutoPilot\Core\Settings;

final class ChatWidget {

	/* Inline line-icons (trusted static markup) — currentColor so they inherit their button color. */
	private const ICON_CHAT   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.4 8.7 8.7 0 0 1-3.9-.9L3 20.5l1.6-4.6a8.4 8.4 0 0 1-1-4A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z"/></svg>';
	private const ICON_CLOSE  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
	private const ICON_SHIELD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 7.5v5.2c0 4.4 3.3 7.9 8 8.8 4.7-.9 8-4.4 8-8.8V7.5L12 3Z"/><path d="m9 12 2.2 2.2L15 10.5"/></svg>';
	private const ICON_SEND   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>';
	private const ICON_MIC    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>';
	private const ICON_PERSON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>';
	private const ICON_EXPAND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';
	private const ICON_SHRINK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 9H3V3M15 15h6v6M9 9 3 3M15 15l6 6"/></svg>';
	private const ICON_MINUS  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg>';
	private const ICON_STOP   = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/></svg>';
	private const ICON_DOWN   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>';

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
		$copy         = Settings::widget_copy( $w );
		$title        = $copy['title'];
		$greet        = $copy['greeting'];
		$subtitle     = $copy['subtitle'];
		$handoff_text = (string) ( $w['handoff_text'] ?? __( 'Talk to a human', 'ai-site-assistant-autopilot' ) );
		$handoff_hook = (string) ( $w['handoff_hook'] ?? '' );
		$voice        = ! empty( $w['voice_enabled'] );
		$handoff      = Settings::answer_desk_handoff_available();
		$handoff_asks = $handoff ? $this->handoff_asks() : array();
		$recording    = ! empty( $w['conversation_logging_enabled'] );
		$notice       = sanitize_text_field( $copy['conversation_notice'] );
		$suggestions  = $this->suggestions( $w );
		$auto_open    = max( 0, (int) ( $w['auto_open'] ?? 0 ) );
		if ( '' === trim( $handoff_text ) ) {
			$handoff_text = __( 'Talk to a human', 'ai-site-assistant-autopilot' );
		}

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
			data-spw-auto-open="<?php echo esc_attr( (string) $auto_open ); ?>"
			style="<?php echo esc_attr( $style ); ?>">

			<div class="spw-nudge" id="spw-nudge" hidden>
				<button type="button" class="spw-nudge__body" id="spw-nudge-open">
					<span class="spw-nudge__title"><?php esc_html_e( 'Need a hand?', 'ai-site-assistant-autopilot' ); ?></span>
					<?php /* dir="auto": copy the site owner typed can be in a different script from the page. */ ?>
					<span class="spw-nudge__text" dir="auto"><?php echo esc_html( $greet ); ?></span>
				</button>
				<button type="button" class="spw-nudge__close" id="spw-nudge-close" aria-label="<?php esc_attr_e( 'Dismiss', 'ai-site-assistant-autopilot' ); ?>">
					<?php echo self::ICON_CLOSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
				</button>
			</div>

			<button type="button" id="spw-toggle" class="spw-toggle" aria-label="<?php esc_attr_e( 'Open chat', 'ai-site-assistant-autopilot' ); ?>">
				<span class="spw-toggle__ring" aria-hidden="true"></span>
				<span class="spw-toggle__ico"><?php echo $this->icon_or( $icons, 'float', self::ICON_CHAT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
			</button>

			<div class="spw-scrim" id="spw-scrim" hidden></div>

			<div id="spw-panel" class="spw-panel" role="dialog" aria-modal="false"
				aria-labelledby="spw-title" tabindex="-1">

				<div class="spw-main">
					<div class="spw-header">
						<?php /* No "online" dot: nothing measures whether the assistant can answer right now. */ ?>
						<span class="spw-avatar">
							<span class="spw-avatar__icon"><?php echo $this->icon_or( $icons, 'avatar', self::ICON_SHIELD ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
						</span>
						<div class="spw-header__meta">
							<div class="spw-header__title" id="spw-title" dir="auto"><?php echo esc_html( $title ); ?></div>
							<div class="spw-header__sub" dir="auto"><?php echo esc_html( $subtitle ); ?></div>
						</div>
						<?php if ( $voice ) : ?>
							<button type="button" id="spw-voice" class="spw-hbtn spw-hbtn--voice"
								aria-label="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>"
								title="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>">
								<?php /* While recording, both mic buttons show a stop square: pressing again stops. */ ?>
								<span class="spw-mic__ico"><?php echo self::ICON_MIC; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
								<span class="spw-mic__stop"><?php echo self::ICON_STOP; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
								<span class="spw-hbtn__label"><?php esc_html_e( 'Voice', 'ai-site-assistant-autopilot' ); ?></span>
							</button>
						<?php endif; ?>
						<button type="button" id="spw-expand" class="spw-hbtn spw-hbtn--expand"
							aria-label="<?php esc_attr_e( 'Full screen', 'ai-site-assistant-autopilot' ); ?>"
							title="<?php esc_attr_e( 'Full screen', 'ai-site-assistant-autopilot' ); ?>">
							<span class="spw-hbtn__on"><?php echo self::ICON_EXPAND; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
							<span class="spw-hbtn__off"><?php echo self::ICON_SHRINK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
						</button>
						<button type="button" id="spw-close" class="spw-hbtn spw-hbtn--close" aria-label="<?php esc_attr_e( 'Minimise chat', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Minimise chat', 'ai-site-assistant-autopilot' ); ?>">
							<?php echo self::ICON_MINUS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
						</button>
					</div>

					<div class="spw-body">
						<?php /* aria-atomic=false so assistive tech announces only new turns, not the whole log. */ ?>
						<div id="spw-msgs" class="spw-messages" role="log" aria-live="polite" aria-atomic="false"
							aria-label="<?php esc_attr_e( 'Conversation', 'ai-site-assistant-autopilot' ); ?>">
							<div class="spw-day" aria-hidden="true"><?php esc_html_e( 'Today', 'ai-site-assistant-autopilot' ); ?></div>
							<div id="spw-welcome" class="spw-row spw-row--ai">
								<div class="spw-row__stack"><div class="spw-msg spw-msg--ai spw-msg--greeting" dir="auto"><?php echo esc_html( $greet ); ?></div></div>
							</div>
							<?php if ( array() !== $suggestions ) : ?>
								<div class="spw-chips" id="spw-chips" role="group" aria-label="<?php esc_attr_e( 'Suggested questions', 'ai-site-assistant-autopilot' ); ?>">
									<?php foreach ( $suggestions as $suggestion ) : ?>
										<button type="button" class="spw-chip" dir="auto" data-spw-ask="<?php echo esc_attr( $suggestion ); ?>"><?php echo esc_html( $suggestion ); ?></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<button type="button" id="spw-jump" class="spw-jump" hidden
							aria-label="<?php esc_attr_e( 'Scroll to latest message', 'ai-site-assistant-autopilot' ); ?>">
							<?php echo self::ICON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?>
						</button>
					</div>

					<div class="spw-composer">
						<div class="spw-composer__box">
							<?php if ( $voice ) : ?>
								<?php /* Shown only while recording: a live dot and the elapsed time. The status region below announces the phase. */ ?>
								<span class="spw-rec" id="spw-rec" aria-hidden="true" hidden><span class="spw-rec__dot"></span><span class="spw-rec__time" id="spw-rec-time" dir="ltr">0:00</span></span>
							<?php endif; ?>
							<textarea class="spw-input" id="spw-input" rows="1"
								placeholder="<?php esc_attr_e( 'Write a message…', 'ai-site-assistant-autopilot' ); ?>"
								aria-label="<?php esc_attr_e( 'Type your message', 'ai-site-assistant-autopilot' ); ?>"></textarea>
							<?php if ( $voice ) : ?>
								<button type="button" id="spw-mic" class="spw-mic" aria-pressed="false" aria-label="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Voice input', 'ai-site-assistant-autopilot' ); ?>">
									<span class="spw-mic__ico"><?php echo $this->icon_or( $icons, 'voice', self::ICON_MIC ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?></span>
									<span class="spw-mic__stop"><?php echo self::ICON_STOP; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
								</button>
							<?php endif; ?>
							<button type="button" id="spw-send" class="spw-send" aria-label="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>" title="<?php esc_attr_e( 'Send', 'ai-site-assistant-autopilot' ); ?>">
								<span class="spw-send__ico"><?php echo self::ICON_SEND; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
								<span class="spw-send__stop"><?php echo self::ICON_STOP; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG. ?></span>
							</button>
						</div>
						<p class="spw-footnote">
							<span><?php esc_html_e( 'Answers come from this site and can be wrong.', 'ai-site-assistant-autopilot' ); ?></span>
							<?php if ( $handoff ) : ?>
								<button type="button" class="spw-link" data-spw-handoff><?php echo esc_html( $handoff_text ); ?></button>
							<?php endif; ?>
						</p>
						<?php if ( $recording && '' !== $notice ) : ?>
							<p class="spw-recording-notice"><?php echo esc_html( $notice ); ?></p>
						<?php endif; ?>
						<?php
						/*
						 * Voice is transcript-first, so the three phases a screen reader cannot
						 * otherwise notice — recording, transcribing, and a transcript waiting in
						 * the message box — are announced here. The recording dot and timer, the
						 * typing dots and the filled box carry the same news visually. Nothing in
						 * this flow sends on its own.
						 */
						?>
						<?php if ( $voice ) : ?>
							<p class="spw-live" id="spw-voice-status" role="status" aria-live="polite" data-spw-voice-status></p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( array() !== $suggestions || $handoff ) : ?>
					<aside class="spw-side" aria-label="<?php esc_attr_e( 'Popular questions', 'ai-site-assistant-autopilot' ); ?>">
						<?php if ( array() !== $suggestions ) : ?>
							<div class="spw-side__label"><?php esc_html_e( 'Popular questions', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="spw-side__list">
								<?php foreach ( $suggestions as $suggestion ) : ?>
									<button type="button" class="spw-side__q" dir="auto" data-spw-ask="<?php echo esc_attr( $suggestion ); ?>"><?php echo esc_html( $suggestion ); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $handoff ) : ?>
							<div class="spw-side__human">
								<div class="spw-side__human-title"><?php esc_html_e( 'Can’t find it?', 'ai-site-assistant-autopilot' ); ?></div>
								<p><?php esc_html_e( 'A person from our team can take over at any time.', 'ai-site-assistant-autopilot' ); ?></p>
								<button type="button" class="spw-side__btn" data-spw-handoff><?php echo esc_html( $handoff_text ); ?></button>
							</div>
						<?php endif; ?>
					</aside>
				<?php endif; ?>
			</div>

			<?php if ( $handoff ) : ?>
				<template id="spw-handoff-template">
					<div class="spw-handoff" role="group" aria-label="<?php esc_attr_e( 'Talk to our team', 'ai-site-assistant-autopilot' ); ?>">
						<div class="spw-handoff__title">
							<?php echo $this->icon_or( $icons, 'handoff', self::ICON_PERSON ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in icon_or(). ?>
							<span><?php esc_html_e( 'I’ll bring in a person', 'ai-site-assistant-autopilot' ); ?></span>
						</div>
						<p class="spw-handoff__text">
							<?php
							$default_hook = array() !== $handoff_asks
								? __( 'That one is better answered by our team. Leave your details, then send them this conversation.', 'ai-site-assistant-autopilot' )
								: __( 'That one is better answered by our team. Add your email or phone in a message first, then send them this conversation.', 'ai-site-assistant-autopilot' );
							echo esc_html( '' !== $handoff_hook ? $handoff_hook : $default_hook );
							?>
						</p>
						<?php if ( array() !== $handoff_asks ) : ?>
							<div class="spw-handoff__fields">
								<?php foreach ( $handoff_asks as $key => $ask ) : ?>
									<label class="spw-handoff__field">
										<span class="spw-handoff__label">
											<?php echo esc_html( $ask['label'] ); ?>
											<?php if ( 'optional' === $ask['mode'] ) : ?>
												<em><?php esc_html_e( '(optional)', 'ai-site-assistant-autopilot' ); ?></em>
											<?php endif; ?>
										</span>
										<input class="spw-handoff__input" data-spw-handoff-field="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $ask['type'] ); ?>" autocomplete="<?php echo esc_attr( $ask['autocomplete'] ); ?>" inputmode="<?php echo esc_attr( $ask['inputmode'] ); ?>" maxlength="<?php echo esc_attr( (string) $ask['max'] ); ?>" dir="<?php echo 'name' === $key ? 'auto' : 'ltr'; ?>"<?php echo 'required' === $ask['mode'] ? ' required aria-required="true"' : ''; ?><?php echo '' !== $ask['placeholder'] ? ' placeholder="' . esc_attr( $ask['placeholder'] ) . '"' : ''; ?> />
										<span class="spw-handoff__error" data-spw-handoff-error hidden></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="spw-handoff__actions">
							<button type="button" class="spw-handoff__send" data-spw-handoff-send><?php esc_html_e( 'Send this chat to our team', 'ai-site-assistant-autopilot' ); ?></button>
							<button type="button" class="spw-handoff__later" data-spw-handoff-dismiss><?php esc_html_e( 'Not now', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
						<p class="spw-handoff__status" role="status" aria-live="polite"></p>
					</div>
				</template>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The contact details the handoff card asks for, in order, skipping the ones the site owner switched off.
	 *
	 * @return array<string,array{mode:string,label:string,type:string,autocomplete:string,inputmode:string,max:int,placeholder:string}>
	 */
	private function handoff_asks(): array {
		$inputs = array(
			'name'    => array( __( 'Your name', 'ai-site-assistant-autopilot' ), 'text', 'name', 'text', 120, '' ),
			'email'   => array( __( 'Email address', 'ai-site-assistant-autopilot' ), 'email', 'email', 'email', 190, '' ),
			'phone'   => array( __( 'Mobile number', 'ai-site-assistant-autopilot' ), 'tel', 'tel', 'tel', 40, '' ),
			'website' => array( __( 'Website address', 'ai-site-assistant-autopilot' ), 'text', 'url', 'url', 255, 'example.com' ),
		);
		$asks   = array();
		foreach ( Settings::handoff_fields() as $key => $mode ) {
			if ( 'off' === $mode || ! isset( $inputs[ $key ] ) ) {
				continue;
			}
			$asks[ $key ] = array(
				'mode'         => $mode,
				'label'        => $inputs[ $key ][0],
				'type'         => $inputs[ $key ][1],
				'autocomplete' => $inputs[ $key ][2],
				'inputmode'    => $inputs[ $key ][3],
				'max'          => $inputs[ $key ][4],
				'placeholder'  => $inputs[ $key ][5],
			);
		}
		return $asks;
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

			'--spw-radius'       => (int) ( $w['panel_radius'] ?? 20 ) . 'px',
			'--spw-msg-radius'   => (int) ( $w['msg_radius'] ?? 14 ) . 'px',
			'--spw-float-radius' => (int) ( $w['bubble_radius'] ?? 30 ) . 'px',

			// Panel size was configurable but never reached the stylesheet.
			'--spw-w'            => max( 320, min( 560, (int) ( $w['window_width'] ?? 392 ) ) ) . 'px',
			'--spw-h'            => max( 380, min( 820, (int) ( $w['window_height'] ?? 624 ) ) ) . 'px',
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
		if ( 'exclude' !== ( $w['widget_visibility'] ?? 'all' ) ) {
			return true;
		}

		return ! self::is_hidden_here( Settings::widget_exclusions( (array) $w ) );
	}

	/**
	 * @param array{ids: array<int, int>, types: array<int, string>, cats: array<int, int>} $rules
	 */
	private static function is_hidden_here( array $rules ): bool {
		// Only a post object is compared with entry IDs; a term archive's ID is a different number space.
		$queried = get_queried_object();
		$post_id = $queried instanceof \WP_Post ? (int) $queried->ID : 0;

		if ( $post_id > 0 && in_array( $post_id, $rules['ids'], true ) ) {
			return true;
		}
		if ( array() !== $rules['types'] && ( is_singular( $rules['types'] ) || is_post_type_archive( $rules['types'] ) ) ) {
			return true;
		}
		if ( array() !== $rules['cats'] ) {
			if ( is_category( $rules['cats'] ) ) {
				return true;
			}
			if ( $post_id > 0 && is_singular( 'post' ) && has_category( $rules['cats'], $post_id ) ) {
				return true;
			}
		}

		return false;
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
