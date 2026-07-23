<?php
declare(strict_types=1);
namespace SitePilot\Frontend;
use SitePilot\Core\Settings;

final class ChatWidget {
	public function render(): string {
		$s = Settings::all();
		if ( empty( $s['widget_enabled'] ) ) { return ''; }
		$theme = $s['widget_theme'] ?? 'light';
		$pos   = $s['widget_position'] ?? 'bottom-right';
		$color = $s['widget_color'] ?? '#4f46e5';
		$title = esc_html( $s['widget_title'] ?? 'SitePilot AI' );
		$greet = esc_html( $s['widget_greeting'] ?? 'Hi! How can I help you?' );
		$voice = ! empty( $s['widget_voice_enabled'] );
		$handoff = ! empty( $s['widget_handoff_enabled'] );
		$auto_open = (int) ( $s['widget_auto_open'] ?? 0 );

		return sprintf(
			'<div id="sp-widget" class="sp-widget sp-widget--%s sp-widget--%s" 
			      data-color="%s" data-theme="%s" data-voice="%d" 
			      data-handoff="%d" data-auto-open="%d">
				<div class="sp-widget-bubble">💬</div>
				<div class="sp-widget-panel">
					<div class="sp-widget-header">
						<strong>%s</strong>
						<span class="sp-widget-close">✕</span>
					</div>
					<div class="sp-widget-msgs"></div>
					<div class="sp-widget-input">
						<textarea placeholder="%s" rows="1"></textarea>
						%s
						<button class="sp-widget-send">➤</button>
					</div>
				</div>
			</div>',
			$theme, $pos, esc_attr( $color ), $theme,
			$voice ? 1 : 0, $handoff ? 1 : 0, $auto_open,
			$title, esc_attr( $greet ),
			$voice ? '<button class="sp-widget-mic">🎤</button>' : ''
		);
	}

	public static function render_standalone(): void {
		echo '<div class="sp-widget-standalone">';
		( new self() )->render();
		echo '</div>';
	}
}
