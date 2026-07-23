<?php
declare(strict_types=1);
namespace SitePilot\Frontend;
use SitePilot\Core\Settings;

final class ChatWidgetSettings {
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No access' ); }
		$saved = $this->save();
		$s = Settings::all();
		$w = $s['widget'] ?? Settings::widget_defaults();
		?>
		<div class="wrap"><h1>🤖 SitePilot AI — Widget Settings</h1>
		<?php if ( $saved ) { echo '<div class="notice notice-success"><p>Settings saved.</p></div>'; } ?>
		<form method="post" class="sp-settings-form">
		<?php wp_nonce_field( 'sp_widget_save' ); ?>

		<div class="sp-settings-section">
		<h2>💬 General</h2>
		<p><label><input type="checkbox" name="widget[enabled]" value="1" <?php checked( $w['enabled'] ?? true ); ?> /> Enable Widget</label></p>
		<p><label>Title: <input type="text" name="widget[title]" value="<?php echo esc_attr( $w['title'] ?? 'SitePilot AI' ); ?>" class="regular-text" /></label></p>
		<p><label>Greeting: <input type="text" name="widget[greeting]" value="<?php echo esc_attr( $w['greeting'] ?? 'Hi! How can I help?' ); ?>" class="regular-text" /></label></p>
		<p><label>Position:</label>
			<select name="widget[position]">
				<?php foreach ( [ 'bottom-right'=>'Bottom Right', 'bottom-left'=>'Bottom Left', 'top-right'=>'Top Right', 'top-left'=>'Top Left' ] as $k => $v ) {
					echo '<option value="'.$k.'"'.selected($w['position']??'bottom-right',$k,false).'>'.$v.'</option>';
				} ?>
			</select></p>
		<p><label>Auto-open delay (seconds): <input type="number" name="widget[auto_open]" min="0" max="60" value="<?php echo (int)($w['auto_open']??5); ?>" /> (0=disabled)</label></p>
		</div>

		<div class="sp-settings-section">
		<h2>🎨 Theme</h2>
		<p><label><input type="radio" name="widget[theme]" value="light" <?php checked( $w['theme'] ?? 'light', 'light' ); ?> /> Light</label>
			<label><input type="radio" name="widget[theme]" value="dark" <?php checked( $w['theme'] ?? 'light', 'dark' ); ?> /> Dark</label></p>
		<p><label>Brand Color: <input type="color" name="widget[color]" value="<?php echo esc_attr( $w['color'] ?? '#4f46e5' ); ?>" /></label>
		<label> Background: <input type="color" name="widget[bg]" value="<?php echo esc_attr( $w['bg'] ?? '#ffffff' ); ?>" /></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>🎤 Voice</h2>
		<p><label><input type="checkbox" name="widget[voice_enabled]" value="1" <?php checked( $w['voice_enabled'] ?? true ); ?> /> Enable Voice</label></p>
		<p><label><input type="checkbox" name="widget[voice_auto]" value="1" <?php checked( $w['voice_auto'] ?? true ); ?> /> Auto-play responses</label></p>
		<p><label>Voice: <select name="widget[voice_name]">
			<?php foreach ( [ 'Google UK English Female'=>'Google UK Female','Google US English'=>'Google US','Microsoft Zira'=>'Zira','Microsoft David'=>'David' ] as $k=>$v ) {
				echo '<option value="'.$k.'"'.selected($w['voice_name']??'Google UK English Female',$k,false).'>'.$v.'</option>';
			} ?></select></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>👥 Human Handoff</h2>
		<p><label><input type="checkbox" name="widget[handoff_enabled]" value="1" <?php checked( $w['handoff_enabled'] ?? false ); ?> /> Enable Handoff</label></p>
		<p><label>Button text: <input type="text" name="widget[handoff_text]" value="<?php echo esc_attr( $w['handoff_text'] ?? 'Talk to human' ); ?>" /></label></p>
		<p><label>Hook message: <textarea name="widget[handoff_hook]" rows="2" class="regular-text"><?php echo esc_textarea( $w['handoff_hook'] ?? 'You are now connected to human support.' ); ?></textarea></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>📧 Email</h2>
		<p><label><input type="checkbox" name="widget[handoff_email_enabled]" value="1" <?php checked( $w['handoff_email_enabled'] ?? true ); ?> /> Enable Email</label></p>
		<p><label>Support Email: <input type="email" name="widget[handoff_email]" value="<?php echo esc_attr( $w['handoff_email'] ?? get_option('admin_email') ); ?>" class="regular-text" /></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>📱 Telegram</h2>
		<p><label><input type="checkbox" name="widget[handoff_telegram_enabled]" value="1" <?php checked( $w['handoff_telegram_enabled'] ?? false ); ?> /> Enable Telegram</label></p>
		<p><label>Bot Token: <input type="password" name="widget[handoff_telegram_token]" value="<?php echo esc_attr( $w['handoff_telegram_token'] ?? '' ); ?>" class="regular-text" /></label></p>
		<p><label>Chat ID: <input type="text" name="widget[handoff_telegram_chat]" value="<?php echo esc_attr( $w['handoff_telegram_chat'] ?? '' ); ?>" /></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>📞 Twilio WhatsApp</h2>
		<p><label><input type="checkbox" name="widget[handoff_twilio_enabled]" value="1" <?php checked( $w['handoff_twilio_enabled'] ?? false ); ?> /> Enable Twilio</label></p>
		<p><label>Account SID: <input type="text" name="widget[handoff_twilio_sid]" value="<?php echo esc_attr( $w['handoff_twilio_sid'] ?? '' ); ?>" class="regular-text" /></label></p>
		<p><label>Auth Token: <input type="password" name="widget[handoff_twilio_token]" value="<?php echo esc_attr( $w['handoff_twilio_token'] ?? '' ); ?>" class="regular-text" /></label></p>
		<p><label>From (WhatsApp): <input type="text" name="widget[handoff_twilio_from]" value="<?php echo esc_attr( $w['handoff_twilio_from'] ?? '' ); ?>" placeholder="whatsapp:+14155238886" /></label></p>
		<p><label>To (your number): <input type="text" name="widget[handoff_twilio_to]" value="<?php echo esc_attr( $w['handoff_twilio_to'] ?? '' ); ?>" placeholder="whatsapp:+1234567890" /></label></p>
		</div>

		<div class="sp-settings-section">
		<h2>🔗 Custom Webhook</h2>
		<p><label><input type="checkbox" name="widget[handoff_webhook_enabled]" value="1" <?php checked( $w['handoff_webhook_enabled'] ?? false ); ?> /> Enable Webhook</label></p>
		<p><label>Webhook URL: <input type="url" name="widget[handoff_webhook_url]" value="<?php echo esc_attr( $w['handoff_webhook_url'] ?? '' ); ?>" class="regular-text" /></label></p>
		</div>

		<p><button type="submit" class="button button-primary">Save Settings</button></p>
		</form>
		<style>.sp-settings-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin:16px 0;max-width:700px}
		.sp-settings-section h2{margin-top:0;font-size:16px}
		.sp-settings-section p{margin:8px 0}
		.sp-settings-section input[type="text"],.sp-settings-section input[type="email"],.sp-settings-section input[type="url"],.sp-settings-section input[type="password"],.sp-settings-section select,.sp-settings-section textarea{width:100%;max-width:400px}
		.sp-settings-section label{display:flex;align-items:center;gap:6px}
		</style></div>
		<?php
	}

	private function save(): bool {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sp_widget_save' ) ) return false;
		if ( ! current_user_can( 'manage_options' ) ) return false;
		$w = (array) ( $_POST['widget'] ?? array() );
		$parsed = array(
			'enabled' => ! empty( $w['enabled'] ),
			'position' => sanitize_text_field( $w['position'] ?? 'bottom-right' ),
			'theme' => sanitize_text_field( $w['theme'] ?? 'light' ),
			'color' => sanitize_hex_color( $w['color'] ?? '#4f46e5' ),
			'bg' => sanitize_hex_color( $w['bg'] ?? '#ffffff' ),
			'title' => sanitize_text_field( $w['title'] ?? 'SitePilot AI' ),
			'greeting' => sanitize_text_field( $w['greeting'] ?? 'Hi!' ),
			'auto_open' => absint( $w['auto_open'] ?? 5 ),
			'voice_enabled' => ! empty( $w['voice_enabled'] ),
			'voice_auto' => ! empty( $w['voice_auto'] ),
			'voice_name' => sanitize_text_field( $w['voice_name'] ?? 'Google UK English Female' ),
			'handoff_enabled' => ! empty( $w['handoff_enabled'] ),
			'handoff_text' => sanitize_text_field( $w['handoff_text'] ?? 'Talk to human' ),
			'handoff_hook' => sanitize_textarea_field( $w['handoff_hook'] ?? '' ),
			'handoff_email_enabled' => ! empty( $w['handoff_email_enabled'] ),
			'handoff_email' => sanitize_email( $w['handoff_email'] ?? get_option('admin_email') ),
			'handoff_telegram_enabled' => ! empty( $w['handoff_telegram_enabled'] ),
			'handoff_telegram_token' => sanitize_text_field( $w['handoff_telegram_token'] ?? '' ),
			'handoff_telegram_chat' => sanitize_text_field( $w['handoff_telegram_chat'] ?? '' ),
			'handoff_twilio_enabled' => ! empty( $w['handoff_twilio_enabled'] ),
			'handoff_twilio_sid' => sanitize_text_field( $w['handoff_twilio_sid'] ?? '' ),
			'handoff_twilio_token' => sanitize_text_field( $w['handoff_twilio_token'] ?? '' ),
			'handoff_twilio_from' => sanitize_text_field( $w['handoff_twilio_from'] ?? '' ),
			'handoff_twilio_to' => sanitize_text_field( $w['handoff_twilio_to'] ?? '' ),
			'handoff_webhook_enabled' => ! empty( $w['handoff_webhook_enabled'] ),
			'handoff_webhook_url' => esc_url_raw( $w['handoff_webhook_url'] ?? '' ),
		);
		$settings = Settings::all();
		$settings['widget'] = $parsed;
		update_option( Settings::OPTION, $settings );
		return true;
	}
}
