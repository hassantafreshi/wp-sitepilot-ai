<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Rendering primitives shared by every AISAAutoPilot admin screen.
 *
 * The icon set and the component markup are a direct transcription of
 * "UI wireframe/AI Site Assistant AutoPilot.dc.html" so the plugin and the wireframe stay
 * visually identical. Nothing here queries WordPress; callers pass data in.
 */
final class Ui {

	/**
	 * Inline SVG bodies keyed by name. Every path is copied from the wireframe.
	 *
	 * @var array<string, string>
	 */
	private const PATHS = array(
		'spark'        => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" stroke-linejoin="round"/>',
		'spark-plus'   => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" stroke-linejoin="round"/><path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z" stroke-linejoin="round"/>',
		'shield'       => '<path d="M12 3l7.5 3v6c0 4.2-3.1 7.8-7.5 9-4.4-1.2-7.5-4.8-7.5-9V6z" stroke-linejoin="round"/>',
		'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8" stroke-linecap="round"/>',
		'database'     => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
		'chat'         => '<path d="M20 15a2 2 0 01-2 2H8l-4 3V6a2 2 0 012-2h12a2 2 0 012 2z" stroke-linejoin="round"/>',
		'bars'         => '<path d="M4 19V10M10 19V5M16 19v-7M22 19H2" stroke-linecap="round"/>',
		'globe'        => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.2 2.4 3.3 5.4 3.3 8.5S14.2 18.1 12 20.5c-2.2-2.4-3.3-5.4-3.3-8.5S9.8 5.9 12 3.5z"/>',
		'cart'         => '<path d="M4 5h2l2.2 9.5a2 2 0 002 1.5h6.6a2 2 0 002-1.6L20.5 8H7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
		'plug'         => '<path d="M9 3v6M15 3v6M7 9h10v4a5 5 0 01-10 0z" stroke-linejoin="round"/><path d="M12 18v3" stroke-linecap="round"/>',
		'gear'         => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v2.6M12 18.9v2.6M21.5 12h-2.6M5.1 12H2.5M18.7 5.3l-1.8 1.8M7.1 16.9l-1.8 1.8M18.7 18.7l-1.8-1.8M7.1 7.1L5.3 5.3" stroke-linecap="round"/>',
		'pulse'        => '<path d="M3 12h4l2.5-6 4 12 2.5-6h5" stroke-linecap="round" stroke-linejoin="round"/>',
		'form'         => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4" stroke-linecap="round"/>',
		'list'         => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h6M7 13h10M7 17h4" stroke-linecap="round"/>',
		'lock'         => '<path d="M6 11V8a6 6 0 1112 0v3"/><rect x="4" y="11" width="16" height="9" rx="2"/>',
		'search'       => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5" stroke-linecap="round"/>',
		'refresh'      => '<path d="M20 11a8 8 0 10-2.3 5.7" stroke-linecap="round"/><path d="M20 5v6h-6" stroke-linecap="round" stroke-linejoin="round"/>',
		'warning'      => '<path d="M12 4.5l8.5 15h-17z" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke-linecap="round"/>',
		'check'        => '<path d="M5 12.5l4.5 4.5L19 7" stroke-linecap="round" stroke-linejoin="round"/>',
		'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round"/>',
		'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/>',
		'alert-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>',
		'mic'          => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0013 0M12 18v3" stroke-linecap="round"/>',
		'mic-full'     => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
		'copy'         => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a1 1 0 011-1h9"/>',
		'send'         => '<line x1="22" y1="2" x2="11" y2="13" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke-linejoin="round"/>',
		'book'         => '<path d="M5 5.5A1.5 1.5 0 016.5 4H19v16H6.5A1.5 1.5 0 015 18.5z"/><path d="M9 4v16"/>',
		'doc'          => '<path d="M5 4h9l5 5v11H5z" stroke-linejoin="round"/><path d="M9 13h6M9 17h4" stroke-linecap="round"/>',
		'mail'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5L12 13l8.5-6.5"/>',
		'phone'        => '<path d="M5 3.5h3l1.5 4-2 1.5a11 11 0 005.5 5.5l1.5-2 4 1.5v3a2 2 0 01-2 2A15.5 15.5 0 013.5 5.5a2 2 0 012-2z" stroke-linejoin="round"/>',
		'headset'      => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
		'palette'      => '<path d="M12 3a9 9 0 100 18h1.5a2 2 0 001.4-3.4 2 2 0 011.4-3.4H18a3 3 0 003-3A8.6 8.6 0 0012 3z" stroke-linejoin="round"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16" cy="10" r="1"/>',
		'plus'         => '<path d="M12 5v14M5 12h14" stroke-linecap="round"/>',
		'close'        => '<line x1="18" y1="6" x2="6" y2="18" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke-linecap="round"/>',
		'minimize'     => '<line x1="5" y1="18" x2="19" y2="18" stroke-linecap="round"/>',
		'expand'       => '<path d="M15 3h6v6M9 21H3v-6" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 3l-7 7M3 21l7-7" stroke-linecap="round" stroke-linejoin="round"/>',
		'shrink'       => '<path d="M9 3v6H3M15 21v-6h6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 9l6-6M21 15l-6 6" stroke-linecap="round" stroke-linejoin="round"/>',
		'home'         => '<path d="M4 11l8-6 8 6v8a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1z" stroke-linejoin="round"/>',
		'money'        => '<path d="M12 3v18M16.5 7.5c0-1.7-2-2.5-4.5-2.5s-4.5.8-4.5 2.5S9 10.4 12 11s4.5 1.8 4.5 3.5S14.5 17.5 12 17.5s-4.5-.8-4.5-2.5" stroke-linecap="round"/>',
		'bag'          => '<path d="M5 7h14l-1.2 11a2 2 0 01-2 1.8H8.2a2 2 0 01-2-1.8z" stroke-linejoin="round"/><path d="M9 7V5.5a3 3 0 016 0V7"/>',
		'box'          => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z" stroke-linejoin="round"/><path d="M4 8l8 4 8-4M12 12v8"/>',
		'card'         => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.4"/>',
		'pie'          => '<path d="M4 12a8 8 0 018-8v8z"/><circle cx="12" cy="12" r="8"/>',
		'chart'        => '<path d="M4 18V9M10 18V5M16 18v-6M22 18H2" stroke-linecap="round"/>',
		'chevron'      => '<path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>',
		'image'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-4 4 3 3-2 6 5"/>',
		// Integration marks copied from the mockup's INTEGRATIONS table.
		'book-open'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5ZM4 5.5v15M9 8h6M9 12h4" stroke-linecap="round" stroke-linejoin="round"/>',
		'bubble'       => '<path d="M21 12a8 8 0 0 1-8 8H8l-4 3v-6.5A8 8 0 0 1 12 4h1a8 8 0 0 1 8 8Z" stroke-linecap="round" stroke-linejoin="round"/>',
		'form-lines'   => '<path d="M4 5h16v14H4ZM8 9h8M8 13h5M8 17h3" stroke-linecap="round" stroke-linejoin="round"/>',
		'layers'       => '<path d="M12 3 3 8l9 5 9-5-9-5ZM3 14l9 5 9-5M3 11l9 5 9-5" stroke-linecap="round" stroke-linejoin="round"/>',
		'pencil'       => '<path d="M17 3l4 4L8 20H4v-4L17 3ZM14 6l4 4" stroke-linecap="round" stroke-linejoin="round"/>',
		'cart-mock'    => '<path d="M6 6h15l-1.5 8.5H7.5L6 6ZM6 6 5 3H2M9 20a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm9 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" stroke-linecap="round" stroke-linejoin="round"/>',
		// Setup wizard marks from the mockup.
		'shield-check' => '<path d="M12 3 4 7.5v5.2c0 4.4 3.3 7.9 8 8.8 4.7-.9 8-4.4 8-8.8V7.5L12 3Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2.2 2.2L15 10.5" stroke-linecap="round" stroke-linejoin="round"/>',
		'shield-key'   => '<path d="M12 3 4 7.5v5.2c0 4.4 3.3 7.9 8 8.8 4.7-.9 8-4.4 8-8.8V7.5L12 3Zm0 6v3m0 3h.01" stroke-linecap="round" stroke-linejoin="round"/>',
		'connector'    => '<path d="M7 12h10M6 9.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Zm12 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z" stroke-linecap="round" stroke-linejoin="round"/>',
		'tick'         => '<path d="m5 13 4.5 4.5L19 7" stroke-linecap="round" stroke-linejoin="round"/>',
		'dash'         => '<path d="M7 12h10" stroke-linecap="round"/>',
		'triangle'     => '<path d="M12 8v5M12 17h.01M10.3 3.9 2.5 17.4a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>',
		'chev-left'    => '<path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>',
		'chev-right'   => '<path d="m10 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>',
		'chev-down'    => '<path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>',
		'external'     => '<path d="M14 5h5v5M19 5 10 14M17 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h4" stroke-linecap="round" stroke-linejoin="round"/>',
		'sliders'      => '<path d="M4 8h11M19 8h1M4 16h4M12 16h8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="17" cy="8" r="2"/><circle cx="10" cy="16" r="2"/>',
		'cog'          => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.4-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.4l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 11a2 2 0 1 1 0 4Z" stroke-linecap="round" stroke-linejoin="round"/>',
		'magnify'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>',
	);

	/** Solid (filled) marks used inside gradient avatars. */
	private const SOLID = array(
		'sparkle'  => '<path d="M12 2.5l1.6 4.3a3 3 0 0 0 1.8 1.8L19.5 10l-4.1 1.4a3 3 0 0 0-1.8 1.8L12 17.5l-1.6-4.3a3 3 0 0 0-1.8-1.8L4.5 10l4.1-1.4a3 3 0 0 0 1.8-1.8L12 2.5z"/>',
		'sparkle2' => '<path d="M12 2.5l1.6 4.3a3 3 0 0 0 1.8 1.8L19.5 10l-4.1 1.4a3 3 0 0 0-1.8 1.8L12 17.5l-1.6-4.3a3 3 0 0 0-1.8-1.8L4.5 10l4.1-1.4a3 3 0 0 0 1.8-1.8L12 2.5z"/><path d="M18.5 14l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7.7-1.9z"/>',
	);

	/**
	 * A stroked outline icon.
	 *
	 * @param string $name   One of self::PATHS.
	 * @param int    $size   Pixel size for width and height.
	 * @param string $stroke CSS colour or "grad" for the brand gradient.
	 * @param float  $width  Stroke width.
	 */
	public static function icon( string $name, int $size = 16, string $stroke = 'currentColor', float $width = 1.7 ): string {
		$body = self::PATHS[ $name ] ?? '';
		if ( '' === $body ) {
			return '';
		}
		if ( 'grad' === $stroke ) {
			$stroke = 'url(#sp-grad)';
		}

		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="%2$s" stroke-width="%3$s" aria-hidden="true" focusable="false">%4$s</svg>',
			$size,
			esc_attr( $stroke ),
			esc_attr( (string) $width ),
			$body
		);
	}

	/** A filled icon (used for gradient sparkles). */
	public static function solid( string $name, int $size = 22, string $fill = 'currentColor' ): string {
		$body = self::SOLID[ $name ] ?? '';
		if ( '' === $body ) {
			return '';
		}
		if ( 'grad' === $fill ) {
			$fill = 'url(#sp-grad)';
		}

		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="%2$s" aria-hidden="true" focusable="false">%3$s</svg>',
			$size,
			esc_attr( $fill ),
			$body
		);
	}

	/** The stable SVG id remains for existing callers; the brand is now flat navy. */
	public static function grad_defs(): void {
		echo '<svg class="sp-grad-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><linearGradient id="sp-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#202a8d"/><stop offset="1" stop-color="#202a8d"/></linearGradient></defs></svg>';
	}

	/**
	 * Page title: gradient icon + text + optional AI badge.
	 */
	public static function title( string $text, string $icon = '', bool $ai_badge = false ): void {
		echo '<h1 class="sp-h1">';
		if ( '' !== $icon ) {
			echo self::icon( $icon, 26, 'grad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		}
		echo '<span>' . esc_html( $text ) . '</span>';
		if ( $ai_badge ) {
			echo '<span class="sp-ai-badge">AI</span>';
		}
		echo '</h1>';
	}

	/** Status pill with a leading dot. Tone: ok | warn | err | info | off | blue. */
	public static function pill( string $label, string $tone = 'off', bool $dot = true, string $extra = '' ): string {
		return sprintf(
			'<span class="sp-pill sp-pill--%1$s %4$s">%2$s%3$s</span>',
			esc_attr( $tone ),
			$dot ? '<span class="sp-dot"></span>' : '',
			esc_html( $label ),
			esc_attr( $extra )
		);
	}

	/**
	 * One dependency row on the Connections screen.
	 *
	 * @param array{tone:string,badge:string,note:string} $state Health state.
	 */
	public static function health_row( string $icon, string $title, array $state ): void {
		$tone = (string) ( $state['tone'] ?? 'off' );
		?>
		<div class="sp-health">
			<span class="sp-health__icon sp-health__icon--<?php echo esc_attr( $tone ); ?>"><?php echo self::icon( $icon, 19 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<span class="sp-health__body">
				<span class="sp-health__title"><?php echo esc_html( $title ); ?></span>
				<span class="sp-health__note"><?php echo esc_html( (string) ( $state['note'] ?? '' ) ); ?></span>
			</span>
			<?php echo self::pill( (string) ( $state['badge'] ?? '' ), $tone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
		</div>
		<?php
	}

	/**
	 * Build a health state array.
	 *
	 * @return array{tone:string,badge:string,note:string}
	 */
	public static function health( string $tone, string $badge, string $note ): array {
		return array(
			'tone'  => $tone,
			'badge' => $badge,
			'note'  => $note,
		);
	}

	/** Big number tile (Usage / WooCommerce dashboards). */
	public static function stat( string $icon, string $value, string $label, string $tone = '' ): void {
		?>
		<div class="sp-stat">
			<span class="sp-stat__icon<?php echo '' !== $tone ? ' sp-stat__icon--' . esc_attr( $tone ) : ''; ?>"><?php echo self::icon( $icon, 24, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<span class="sp-stat__body">
				<span class="sp-stat__num<?php echo '' !== $tone && in_array( $tone, array( 'ok', 'err' ), true ) ? ' sp-stat__num--' . esc_attr( $tone ) : ''; ?>"><?php echo esc_html( $value ); ?></span>
				<span class="sp-stat__label"><?php echo esc_html( $label ); ?></span>
			</span>
		</div>
		<?php
	}

	/** Centred gradient metric tile (Knowledge Base). */
	public static function metric( string $icon, string $value, string $label, bool $as_text = false ): void {
		?>
		<div class="sp-card sp-metric">
			<span class="sp-metric__icon"><?php echo self::icon( $icon, 22, 'grad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<span class="sp-metric__num<?php echo $as_text ? ' sp-metric__num--text' : ''; ?>"><?php echo esc_html( $value ); ?></span>
			<span class="sp-metric__label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/** Key/value line inside a side card. */
	public static function kv( string $key, string $value, string $value_class = '' ): void {
		?>
		<div class="sp-kv__row">
			<span class="sp-kv__k"><?php echo esc_html( $key ); ?></span>
			<span class="sp-kv__v <?php echo esc_attr( $value_class ); ?>"><?php echo esc_html( $value ); ?></span>
		</div>
		<?php
	}

	/** Small fact card used on the result screen. */
	public static function fact( string $key, string $value, bool $mono = false ): void {
		?>
		<div class="sp-fact">
			<div class="sp-fact__k"><?php echo esc_html( $key ); ?></div>
			<div class="sp-fact__v<?php echo $mono ? ' sp-mono' : ''; ?>"><?php echo esc_html( $value ); ?></div>
		</div>
		<?php
	}

	/** A full-width alert strip. Tone: ok | warn | err | info | soft | busy. */
	public static function alert( string $tone, string $icon, string $html ): void {
		?>
		<div class="sp-alert sp-alert--<?php echo esc_attr( $tone ); ?>">
			<?php echo self::icon( $icon, 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
			<span><?php echo wp_kses_post( $html ); ?></span>
		</div>
		<?php
	}

	/** Breadcrumb trail. Items are [label, url|null]. */
	public static function crumbs( array $items ): void {
		echo '<nav class="sp-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ai-site-assistant-autopilot' ) . '">';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			list($label, $url) = array_pad( (array) $item, 2, null );
			if ( $url ) {
				echo '<a href="' . esc_url( (string) $url ) . '">' . esc_html( (string) $label ) . '</a>';
			} else {
				echo '<span>' . esc_html( (string) $label ) . '</span>';
			}
			if ( $i !== $last ) {
				echo '<span aria-hidden="true">/</span>';
			}
		}
		echo '</nav>';
	}

	/** Progress bar. */
	public static function bar( float $percent, string $modifier = '', string $fill = '' ): void {
		$percent = max( 0.0, min( 100.0, $percent ) );
		printf(
			'<div class="sp-bar %1$s"><div class="sp-bar__fill %2$s" style="width:%3$s%%"></div></div>',
			esc_attr( $modifier ),
			esc_attr( $fill ),
			esc_attr( (string) round( $percent, 2 ) )
		);
	}

	/**
	 * Emit a payload for JavaScript as an application/json script block.
	 *
	 * The HEX flags matter: without them an encoded value containing "</script>"
	 * would close the block early and turn data into markup. JSON.parse decodes
	 * the escapes transparently, so the reading side is unchanged.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function json_script( string $id, array $data ): void {
		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			$json = '{}';
		}

		printf(
			'<script type="application/json" id="%s">%s</script>',
			esc_attr( $id ),
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, with tag/quote characters hex-escaped above.
		);
	}

	/** Toast + blocking-overlay hosts, rendered once per screen that needs them. */
	public static function overlays(): void {
		?>
		<div id="sp-toast" class="sp-toast" role="status" aria-live="polite" hidden></div>
		<div id="sp-busy" class="sp-busy" hidden>
			<span class="sp-spinner sp-spinner--lg"></span>
			<span id="sp-busy-label"><?php esc_html_e( 'Working…', 'ai-site-assistant-autopilot' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Shared page header bar — the mockup's white app header.
	 *
	 * Title + subtitle on the left; connection chip, "Connection" shortcut and
	 * an optional primary action on the right; optional sub-tab row inside the
	 * same bar. Every screen renders this first so the IA matches the mockup.
	 *
	 * @param array{
	 *     chip?: array{tone?:string,label?:string},
	 *     action?: array{label?:string,url?:string,id?:string,attrs?:string,type?:string,class?:string},
	 *     subtabs?: array<int, array{id?:string,label?:string,url?:string,active?:bool}>,
	 *     connection_url?: string
	 * } $args
	 */
	public static function page_header( string $title, string $sub, array $args = array() ): void {
		$chip      = (array) ( $args['chip'] ?? array() );
		$action    = (array) ( $args['action'] ?? array() );
		$subtabs   = array_values( (array) ( $args['subtabs'] ?? array() ) );
		$chip_tone = (string) ( $chip['tone'] ?? 'ok' );
		if ( ! in_array( $chip_tone, array( 'ok', 'warn', 'err' ), true ) ) {
			$chip_tone = 'ok';
		}
		$chip_label = (string) ( $chip['label'] ?? '' );
		$conn_url   = (string) ( $args['connection_url'] ?? admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP ) );
		?>
		<div class="sp-pagebar">
			<div class="sp-pagebar__row">
				<div class="sp-pagebar__titles">
					<h1 class="sp-pagebar__title" dir="auto"><?php echo esc_html( $title ); ?></h1>
					<?php if ( '' !== $sub ) : ?>
						<p class="sp-pagebar__sub" dir="auto"><?php echo esc_html( $sub ); ?></p>
					<?php endif; ?>
				</div>
				<div class="sp-pagebar__side">
					<?php if ( '' !== $chip_label ) : ?>
						<div class="sp-pagebar__chip sp-pagebar__chip--<?php echo esc_attr( $chip_tone ); ?>">
							<span class="sp-pagebar__dot" aria-hidden="true"></span>
							<span><?php echo esc_html( $chip_label ); ?></span>
						</div>
					<?php endif; ?>
					<a class="sp-pagebar__btn" href="<?php echo esc_url( $conn_url ); ?>"><?php esc_html_e( 'Connection', 'ai-site-assistant-autopilot' ); ?></a>
					<?php if ( ! empty( $action['label'] ) ) : ?>
						<?php if ( 'button' === (string) ( $action['type'] ?? 'link' ) ) : ?>
							<button
								type="button"
								class="sp-pagebar__action<?php echo isset( $action['class'] ) ? ' ' . esc_attr( (string) $action['class'] ) : ''; ?>"
								<?php echo isset( $action['id'] ) ? 'id="' . esc_attr( (string) $action['id'] ) . '"' : ''; ?>
							><span><?php echo esc_html( (string) $action['label'] ); ?></span></button>
						<?php else : ?>
							<a
								class="sp-pagebar__action<?php echo isset( $action['class'] ) ? ' ' . esc_attr( (string) $action['class'] ) : ''; ?>"
								href="<?php echo esc_url( (string) ( $action['url'] ?? '#' ) ); ?>"
								<?php echo isset( $action['id'] ) ? 'id="' . esc_attr( (string) $action['id'] ) . '"' : ''; ?>
								<?php echo isset( $action['attrs'] ) ? esc_attr( (string) $action['attrs'] ) : ''; ?>
							><?php echo esc_html( (string) $action['label'] ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( array() !== $subtabs ) : ?>
				<nav class="sp-subtabs" aria-label="<?php esc_attr_e( 'Page sections', 'ai-site-assistant-autopilot' ); ?>">
					<?php foreach ( $subtabs as $tab ) : ?>
						<?php $tab = (array) $tab; ?>
						<a
							class="sp-subtabs__tab<?php echo ! empty( $tab['active'] ) ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( (string) ( $tab['url'] ?? '#' ) ); ?>"
							<?php echo isset( $tab['id'] ) ? 'data-sp-subtab="' . esc_attr( (string) $tab['id'] ) . '"' : ''; ?>
							<?php echo ! empty( $tab['active'] ) ? 'aria-current="page"' : ''; ?>
						><?php echo esc_html( (string) ( $tab['label'] ?? '' ) ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
		<?php /* WordPress moves admin notices below this marker instead of into the title row. */ ?>
		<hr class="wp-header-end" />
		<?php
	}

	/**
	 * Dismissible scenario banner (mockup `BANNERS`). Tone: bad | warn.
	 *
	 * Dismissal is client-side (sessionStorage, keyed by `$id`) — the banner is
	 * derived server-side on every load, so dismissing only hides it for the
	 * session; the underlying condition still renders it next session.
	 */
	public static function banner( string $id, string $tone, string $title, string $body, string $cta_label = '', string $cta_url = '', string $cta_id = '' ): void {
		if ( ! in_array( $tone, array( 'bad', 'warn' ), true ) ) {
			$tone = 'warn';
		}
		?>
		<div class="sp-banner sp-banner--<?php echo esc_attr( $tone ); ?>" data-sp-banner="<?php echo esc_attr( $id ); ?>">
			<span class="sp-banner__icon" aria-hidden="true"><?php echo self::icon( 'warning', 17, 'currentColor', 2.1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<div class="sp-banner__body">
				<div class="sp-banner__title"><?php echo esc_html( $title ); ?></div>
				<p class="sp-banner__text"><?php echo esc_html( $body ); ?></p>
			</div>
			<?php if ( '' !== $cta_label ) : ?>
				<a class="sp-banner__cta" href="<?php echo esc_url( $cta_url ); ?>" <?php echo '' !== $cta_id ? 'id="' . esc_attr( $cta_id ) . '"' : ''; ?>><?php echo esc_html( $cta_label ); ?></a>
			<?php endif; ?>
			<button type="button" class="sp-banner__dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'ai-site-assistant-autopilot' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * The running-job bar host. Hidden until `AISAAutoPilotShell.job.start()`.
	 * Label, ETA and percent are filled by JS; pause/stop dispatch events the
	 * running batch loop listens to.
	 */
	public static function jobbar(): void {
		?>
		<div class="sp-jobbar" id="sp-jobbar" hidden>
			<span class="sp-jobbar__spin" aria-hidden="true"><?php echo self::icon( 'refresh', 19, '#202a8d', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<div class="sp-jobbar__body">
				<div class="sp-jobbar__label" id="sp-jobbar-label"></div>
				<div class="sp-jobbar__eta" id="sp-jobbar-eta"></div>
				<span class="sp-jobbar__track" aria-hidden="true"><span class="sp-jobbar__fill" id="sp-jobbar-fill"></span></span>
			</div>
			<div class="sp-jobbar__btns">
				<button type="button" class="sp-jobbar__btn" id="sp-jobbar-pause"><?php esc_html_e( 'Pause', 'ai-site-assistant-autopilot' ); ?></button>
				<button type="button" class="sp-jobbar__btn" id="sp-jobbar-stop"><?php esc_html_e( 'Stop', 'ai-site-assistant-autopilot' ); ?></button>
			</div>
			<span class="sp-jobbar__pct" id="sp-jobbar-pct">0%</span>
		</div>
		<?php
	}

	/** An image drop slot used by the widget customiser. */
	public static function slot( string $id, string $title, string $note, string $value = '' ): void {
		$filled = '' !== $value;
		?>
		<div class="sp-stack--sm" style="display:flex;flex-direction:column;gap:8px">
			<span>
				<span class="sp-label sp-mb0"><?php echo esc_html( $title ); ?></span>
				<span class="sp-help"><?php echo esc_html( $note ); ?></span>
			</span>
			<div class="sp-slot<?php echo $filled ? ' is-filled' : ''; ?>" data-sp-slot="<?php echo esc_attr( $id ); ?>">
				<input type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" aria-label="<?php echo esc_attr( $title ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" data-sp-slot-value />
				<span class="sp-slot__ph"><?php echo self::icon( 'image', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Drop a PNG', 'ai-site-assistant-autopilot' ); ?></span></span>
				<img src="<?php echo esc_attr( $value ); ?>" alt="" <?php echo $filled ? '' : 'hidden'; ?> data-sp-slot-img />
				<button type="button" class="sp-slot__clear" aria-label="<?php esc_attr_e( 'Remove image', 'ai-site-assistant-autopilot' ); ?>">&times;</button>
			</div>
		</div>
		<?php
	}
}
