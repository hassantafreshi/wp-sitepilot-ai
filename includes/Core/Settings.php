<?php
declare(strict_types=1);
namespace SitePilot\Core;
use SitePilot\Security\KeyVault;

final class Settings {
	public const OPTION = 'sitepilot_ai_settings';
	public const KEY_OPTION = 'sitepilot_ai_provider_key';

	public static function presets(): array {
		return array(
			'deepseek' => array('label'=>'DeepSeek','base_url'=>'https://api.deepseek.com/v1','model'=>'deepseek-chat','key_url'=>'https://platform.deepseek.com/api_keys'),
			'openai' => array('label'=>'OpenAI','base_url'=>'https://api.openai.com/v1','model'=>'gpt-4o-mini','key_url'=>'https://platform.openai.com/api-keys'),
			'qwen' => array('label'=>'Qwen (Alibaba)','base_url'=>'https://dashscope.aliyuncs.com/compatible-mode/v1','model'=>'qwen-plus','key_url'=>'https://dashscope.console.aliyun.com/apiKey'),
			'clusy' => array('label'=>'Clusy.io','base_url'=>'https://api.clusy.io/v1','model'=>'clusy-chat','key_url'=>'https://clusy.io/dashboard/api-keys'),
			'openrouter' => array('label'=>'OpenRouter','base_url'=>'https://openrouter.ai/api/v1','model'=>'deepseek/deepseek-chat','key_url'=>'https://openrouter.ai/keys'),
			'groq' => array('label'=>'Groq','base_url'=>'https://api.groq.com/openai/v1','model'=>'llama-3.3-70b-versatile','key_url'=>'https://console.groq.com/keys'),
			'custom' => array('label'=>'Custom (OpenAI-compatible)','base_url'=>'','model'=>'','key_url'=>''),
		);
	}

	public static function defaults(): array {
		$p = self::presets()['deepseek'];
		return array(
			'provider' => 'deepseek', 'base_url' => $p['base_url'], 'model' => $p['model'],
			'temperature' => 0.2, 'default_form_status' => 'draft', 'prefer_efb' => true,
			'key_management' => 'keyvault', 'kb_post_types' => array(), 'kb_auto_index' => true,
			'monthly_token_budget' => 0, 'widget' => self::widget_defaults(),
		);
	}

	public static function widget_defaults(): array {
		return array(
			'enabled' => true, 'position' => 'bottom-right', 'theme' => 'light',
			'color' => '#4f46e5', 'bg' => '#ffffff', 'title' => 'SitePilot AI',
			'greeting' => 'Hi! How can I help you?', 'auto_open' => 5,
			'voice_enabled' => true, 'voice_auto' => true, 'voice_name' => 'Google UK English Female',
			'handoff_enabled' => false, 'handoff_text' => 'Talk to human', 'handoff_hook' => '',
			'handoff_email_enabled' => true, 'handoff_email' => '',
			'handoff_telegram_enabled' => false, 'handoff_telegram_token' => '', 'handoff_telegram_chat' => '',
			'handoff_twilio_enabled' => false, 'handoff_twilio_sid' => '', 'handoff_twilio_token' => '', 'handoff_twilio_from' => '', 'handoff_twilio_to' => '',
			'handoff_webhook_enabled' => false, 'handoff_webhook_url' => '',
		);
	}

	public static function all(): array {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? array_merge( self::defaults(), $s ) : self::defaults();
	}

	public static function get( string $key, $default = null ) {
		$s = self::all();
		return array_key_exists( $key, $s ) ? $s[$key] : $default;
	}

	public static function update( array $data ): void {
		$s = self::all();
		foreach ( $data as $k => $v ) { $s[$k] = $v; }
		update_option( self::OPTION, $s );
	}

	public static function set_api_key( string $plaintext ): void {
		if ( '' === $plaintext ) { delete_option( self::KEY_OPTION ); return; }
		$vault = KeyVault::fromWordPress();
		update_option( self::KEY_OPTION, $vault->encrypt( $plaintext ) );
	}

	public static function get_api_key(): string {
		$stored = get_option( self::KEY_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) { return ''; }
		$vault = KeyVault::fromWordPress();
		return $vault->decrypt( $stored );
	}

	public static function has_api_key(): bool {
		$stored = get_option( self::KEY_OPTION, '' );
		return is_string( $stored ) && '' !== $stored;
	}

	public static function delete_api_key(): void { delete_option( self::KEY_OPTION ); }
}
