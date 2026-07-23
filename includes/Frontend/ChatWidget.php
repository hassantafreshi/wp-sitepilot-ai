<?php
declare(strict_types=1);
namespace SitePilot\Frontend;
use SitePilot\Core\Settings;

final class ChatWidget {
	public function render(): string {
		$s=Settings::all();
		$w=$s['widget']??Settings::widget_defaults();
		if(empty($w['enabled'])){return'';}
		$theme=$w['theme']??'light';$pos=$w['position']??'bottom-right';$color=$w['color']??'#4f46e5';
		$bg=($w['bg_color']??'')?:($theme==='dark'?'#1f2937':'#ffffff');
		$title=esc_html($w['title']??'SitePilot AI');$greet=esc_html($w['greeting']??'Hi! How can I help?');
		$voice=!empty($w['voice_enabled']);$auto=(int)($w['auto_open']??5);
		$handoff=!empty($w['handoff_enabled']);

		$state_loading='<div class="sp-state-loading"><div class="sp-spinner"><div></div><div></div><div></div></div><p>'.__('Connecting to AI...','sitepilot-ai').'</p></div>';
		$state_error='<div class="sp-state-error"><span class="sp-state-icon">⚠️</span><p>'.__('Something went wrong. Please try again.','sitepilot-ai').'</p><button class="sp-retry-btn">'.__('Retry','sitepilot-ai').'</button></div>';
		$state_empty='<div class="sp-state-empty"><span class="sp-state-icon">💬</span><p>'.__('No messages yet. Start a conversation!','sitepilot-ai').'</p></div>';
		$state_recording='<div class="sp-state-recording"><div class="sp-wave"><span></span><span></span><span></span><span></span><span></span></div><p>'.__('Listening...','sitepilot-ai').'</p></div>';
		$state_processing='<div class="sp-state-processing"><span class="sp-state-icon">🤖</span><p>'.__('AI is thinking...','sitepilot-ai').'</p></div>';
		$state_handoff='<div class="sp-state-handoff"><span class="sp-state-icon">🧑‍💼</span><p>'.__('Connecting you to support...','sitepilot-ai').'</p></div>';

		return sprintf(
			'<div id="sp-widget" class="sp-widget sp-widget--%s sp-widget--%s"
			      data-color="%s" data-bg="%s" data-theme="%s" data-voice="%d"
			      data-auto="%d" data-handoff="%d"
			      data-loading="%s" data-error="%s" data-empty="%s"
			      data-recording="%s" data-processing="%s" data-handoffs="%s">
				<div class="sp-widget-bubble" role="button" aria-label="Open chat">💬</div>
				<div class="sp-widget-panel" role="dialog" aria-label="Chat">
					<div class="sp-widget-header" style="background:%s;">
						<span class="sp-widget-indicator"></span>
						<strong>%s</strong>
						<span class="sp-widget-close" role="button" aria-label="Close">&times;</span>
					</div>
					<div class="sp-widget-status"></div>
					<div class="sp-widget-msgs" role="log" aria-live="polite"></div>
					<div class="sp-widget-input">
						<textarea placeholder="%s" rows="1" aria-label="Type your message"></textarea>
						%s
						<button class="sp-widget-btn sp-widget-send" aria-label="Send">➤</button>
						<button class="sp-widget-btn sp-widget-handoff" aria-label="Talk to human" style="display:%s;">👤</button>
					</div>
				</div>
			</div>',
			$theme,$pos,esc_attr($color),esc_attr($bg),$theme,$voice?1:0,$auto,$handoff?1:0,
			esc_attr($state_loading),esc_attr($state_error),esc_attr($state_empty),
			esc_attr($state_recording),esc_attr($state_processing),esc_attr($state_handoff),
			esc_attr($color),$title,esc_attr__('Type your message...','sitepilot-ai'),
			$voice?'<button class="sp-widget-btn sp-widget-mic" aria-label="Voice input">🎤</button>':'',
			$handoff?'inline-block':'none'
		);
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
