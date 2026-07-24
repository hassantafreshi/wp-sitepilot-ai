<?php
declare(strict_types=1);
namespace SitePilot\Frontend;
use SitePilot\Core\Settings;

final class ChatWidgetSettings {
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) ); }
		$saved = $this->save();
		$s = Settings::all();
		$w = $s['widget'] ?? Settings::widget_defaults();

		$positions = array(
			'bottom-right' => __( 'Bottom Right', 'sitepilot-ai' ),
			'bottom-left'  => __( 'Bottom Left', 'sitepilot-ai' ),
			'top-right'    => __( 'Top Right', 'sitepilot-ai' ),
			'top-left'     => __( 'Top Left', 'sitepilot-ai' ),
		);
		$voices = array(
			'Google UK English Female' => 'Google UK Female',
			'Google US English'        => 'Google US',
			'Microsoft Zira'           => 'Zira',
			'Microsoft David'          => 'David',
		);
		?>
		<div class="wrap sitepilot-wrap sitepilot-wrap--narrow">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'Chat Widget', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</h1>
			<p class="sitepilot-subtitle">
				<?php esc_html_e( 'Customize the front-end chat widget: appearance, voice, and human handoff channels.', 'sitepilot-ai' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sitepilot-ai' ); ?></p></div>
			<?php endif; ?>

			<div class="sitepilot-widget-layout">
			<form method="post" class="sitepilot-widget-form">
				<?php wp_nonce_field( 'sp_widget_save' ); ?>

				<!-- General -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">💬</span> <?php esc_html_e( 'General', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[enabled]" value="1" <?php checked( $w['enabled'] ?? true ); ?> />
						<?php esc_html_e( 'Enable the widget on the site', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-row">
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-title"><?php esc_html_e( 'Title', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-title" type="text" name="widget[title]" value="<?php echo esc_attr( $w['title'] ?? 'SitePilot AI' ); ?>" />
						</div>
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-greeting"><?php esc_html_e( 'Greeting', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-greeting" type="text" name="widget[greeting]" value="<?php echo esc_attr( $w['greeting'] ?? 'Hi! How can I help?' ); ?>" />
						</div>
					</div>
					<div class="sp-w-row">
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-position"><?php esc_html_e( 'Position', 'sitepilot-ai' ); ?></label>
							<select id="sp-w-position" name="widget[position]">
								<?php foreach ( $positions as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $w['position'] ?? 'bottom-right', $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-autoopen"><?php esc_html_e( 'Auto-open delay (seconds)', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-autoopen" type="number" name="widget[auto_open]" min="0" max="60" value="<?php echo (int) ( $w['auto_open'] ?? 5 ); ?>" />
							<span class="sp-w-hint"><?php esc_html_e( 'Set to 0 to disable auto-open.', 'sitepilot-ai' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Theme -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">🎨</span> <?php esc_html_e( 'Theme', 'sitepilot-ai' ); ?></h2>
					<div class="sp-w-field">
						<span class="sp-w-label"><?php esc_html_e( 'Color scheme', 'sitepilot-ai' ); ?></span>
						<div class="sp-w-radios">
							<label class="sp-w-radio">
								<input type="radio" name="widget[theme]" value="light" <?php checked( $w['theme'] ?? 'light', 'light' ); ?> />
								<?php esc_html_e( 'Light', 'sitepilot-ai' ); ?>
							</label>
							<label class="sp-w-radio">
								<input type="radio" name="widget[theme]" value="dark" <?php checked( $w['theme'] ?? 'light', 'dark' ); ?> />
								<?php esc_html_e( 'Dark', 'sitepilot-ai' ); ?>
							</label>
						</div>
					</div>
					<div class="sp-w-field">
						<span class="sp-w-label"><?php esc_html_e( 'Colors', 'sitepilot-ai' ); ?></span>
						<div class="sp-w-colors">
							<label class="sp-w-color">
								<input type="color" name="widget[color]" value="<?php echo esc_attr( $w['color'] ?? '#4f46e5' ); ?>" />
								<?php esc_html_e( 'Brand', 'sitepilot-ai' ); ?>
							</label>
							<label class="sp-w-color">
								<input type="color" name="widget[bg]" value="<?php echo esc_attr( $w['bg'] ?? '#ffffff' ); ?>" />
								<?php esc_html_e( 'Background', 'sitepilot-ai' ); ?>
							</label>
						</div>
					</div>
				</div>

				<!-- Voice -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">🎤</span> <?php esc_html_e( 'Voice', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[voice_enabled]" value="1" <?php checked( $w['voice_enabled'] ?? true ); ?> />
						<?php esc_html_e( 'Enable voice input & playback', 'sitepilot-ai' ); ?>
					</label>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[voice_auto]" value="1" <?php checked( $w['voice_auto'] ?? true ); ?> />
						<?php esc_html_e( 'Auto-play responses', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-field">
						<label class="sp-w-label" for="sp-w-voice"><?php esc_html_e( 'Voice', 'sitepilot-ai' ); ?></label>
						<select id="sp-w-voice" name="widget[voice_name]">
							<?php foreach ( $voices as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $w['voice_name'] ?? 'Google UK English Female', $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Human Handoff -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">👥</span> <?php esc_html_e( 'Human Handoff', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[handoff_enabled]" value="1" <?php checked( $w['handoff_enabled'] ?? false ); ?> />
						<?php esc_html_e( 'Enable handoff to a human', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-field">
						<label class="sp-w-label" for="sp-w-handoff-text"><?php esc_html_e( 'Button text', 'sitepilot-ai' ); ?></label>
						<input id="sp-w-handoff-text" type="text" name="widget[handoff_text]" value="<?php echo esc_attr( $w['handoff_text'] ?? 'Talk to human' ); ?>" />
					</div>
					<div class="sp-w-field">
						<label class="sp-w-label" for="sp-w-handoff-hook"><?php esc_html_e( 'Hook message', 'sitepilot-ai' ); ?></label>
						<textarea id="sp-w-handoff-hook" name="widget[handoff_hook]" rows="2"><?php echo esc_textarea( $w['handoff_hook'] ?? 'You are now connected to human support.' ); ?></textarea>
					</div>
				</div>

				<!-- Email -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">📧</span> <?php esc_html_e( 'Email', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[handoff_email_enabled]" value="1" <?php checked( $w['handoff_email_enabled'] ?? true ); ?> />
						<?php esc_html_e( 'Enable email notifications', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-field">
						<label class="sp-w-label" for="sp-w-email"><?php esc_html_e( 'Support email', 'sitepilot-ai' ); ?></label>
						<input id="sp-w-email" type="email" name="widget[handoff_email]" value="<?php echo esc_attr( $w['handoff_email'] ?? get_option( 'admin_email' ) ); ?>" />
					</div>
				</div>

				<!-- Telegram -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">📱</span> <?php esc_html_e( 'Telegram', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[handoff_telegram_enabled]" value="1" <?php checked( $w['handoff_telegram_enabled'] ?? false ); ?> />
						<?php esc_html_e( 'Enable Telegram notifications', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-row">
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tg-token"><?php esc_html_e( 'Bot token', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tg-token" type="password" autocomplete="off" name="widget[handoff_telegram_token]" value="<?php echo esc_attr( $w['handoff_telegram_token'] ?? '' ); ?>" />
						</div>
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tg-chat"><?php esc_html_e( 'Chat ID', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tg-chat" type="text" name="widget[handoff_telegram_chat]" value="<?php echo esc_attr( $w['handoff_telegram_chat'] ?? '' ); ?>" />
						</div>
					</div>
				</div>

				<!-- Twilio WhatsApp -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">📞</span> <?php esc_html_e( 'Twilio WhatsApp', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[handoff_twilio_enabled]" value="1" <?php checked( $w['handoff_twilio_enabled'] ?? false ); ?> />
						<?php esc_html_e( 'Enable Twilio WhatsApp notifications', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-row">
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tw-sid"><?php esc_html_e( 'Account SID', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tw-sid" type="text" name="widget[handoff_twilio_sid]" value="<?php echo esc_attr( $w['handoff_twilio_sid'] ?? '' ); ?>" />
						</div>
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tw-token"><?php esc_html_e( 'Auth token', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tw-token" type="password" autocomplete="off" name="widget[handoff_twilio_token]" value="<?php echo esc_attr( $w['handoff_twilio_token'] ?? '' ); ?>" />
						</div>
					</div>
					<div class="sp-w-row">
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tw-from"><?php esc_html_e( 'From (WhatsApp)', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tw-from" type="text" name="widget[handoff_twilio_from]" value="<?php echo esc_attr( $w['handoff_twilio_from'] ?? '' ); ?>" placeholder="whatsapp:+14155238886" />
						</div>
						<div class="sp-w-field">
							<label class="sp-w-label" for="sp-w-tw-to"><?php esc_html_e( 'To (your number)', 'sitepilot-ai' ); ?></label>
							<input id="sp-w-tw-to" type="text" name="widget[handoff_twilio_to]" value="<?php echo esc_attr( $w['handoff_twilio_to'] ?? '' ); ?>" placeholder="whatsapp:+1234567890" />
						</div>
					</div>
				</div>

				<!-- Custom Webhook -->
				<div class="sitepilot-card">
					<h2 class="sp-w-head"><span class="sp-w-head__icon">🔗</span> <?php esc_html_e( 'Custom Webhook', 'sitepilot-ai' ); ?></h2>
					<label class="sp-w-check">
						<input type="checkbox" name="widget[handoff_webhook_enabled]" value="1" <?php checked( $w['handoff_webhook_enabled'] ?? false ); ?> />
						<?php esc_html_e( 'Enable custom webhook', 'sitepilot-ai' ); ?>
					</label>
					<div class="sp-w-field">
						<label class="sp-w-label" for="sp-w-webhook"><?php esc_html_e( 'Webhook URL', 'sitepilot-ai' ); ?></label>
						<input id="sp-w-webhook" type="url" name="widget[handoff_webhook_url]" value="<?php echo esc_attr( $w['handoff_webhook_url'] ?? '' ); ?>" />
					</div>
				</div>

				<div class="sitepilot-widget-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'sitepilot-ai' ); ?></button>
				</div>
			</form>

			<!-- Live preview -->
			<aside class="sp-wp" aria-hidden="true">
				<div class="sp-wp__label"><?php esc_html_e( 'Live preview', 'sitepilot-ai' ); ?></div>
				<div class="sp-wp__stage" id="sp-wp-stage" data-position="<?php echo esc_attr( $w['position'] ?? 'bottom-right' ); ?>">
					<div class="sp-wp__window" id="sp-wp-window">
						<div class="sp-wp__header" id="sp-wp-header" style="background: <?php echo esc_attr( $w['color'] ?? '#4f46e5' ); ?>;">
							<span class="sp-wp__title" id="sp-wp-title"><?php echo esc_html( $w['title'] ?? 'SitePilot AI' ); ?></span>
							<span class="sp-wp__dot"></span>
						</div>
						<div class="sp-wp__body" id="sp-wp-body" style="background: <?php echo esc_attr( $w['bg'] ?? '#ffffff' ); ?>;">
							<div class="sp-wp__msg" id="sp-wp-greeting"><?php echo esc_html( $w['greeting'] ?? 'Hi! How can I help?' ); ?></div>
						</div>
						<div class="sp-wp__input"><span><?php esc_html_e( 'Type a message…', 'sitepilot-ai' ); ?></span></div>
					</div>
					<button type="button" class="sp-wp__launcher" id="sp-wp-launcher" tabindex="-1" style="background: <?php echo esc_attr( $w['color'] ?? '#4f46e5' ); ?>;">💬</button>
				</div>
			</aside>
			</div>
		</div>
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
