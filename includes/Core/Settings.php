<?php
declare(strict_types=1);
namespace SitePilot\Core;
use SitePilot\Security\KeyVault;

final class Settings {
	public const OPTION           = 'sitepilot_ai_settings';
	public const KEY_OPTION       = 'sitepilot_ai_provider_key';
	public const EMBED_KEY_OPTION = 'sitepilot_ai_embedding_key';
	public const HANDOFF_SECRET_OPTION = 'sitepilot_ai_handoff_secrets';

	/** @var string[] */
	private const HANDOFF_SECRET_KEYS = array(
		'handoff_telegram_token',
		'handoff_twilio_sid',
		'handoff_twilio_token',
	);

	public static function presets(): array {
		return array(
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'base_url' => 'https://api.deepseek.com/v1',
				'model'    => 'deepseek-chat',
				'key_url'  => 'https://platform.deepseek.com/api_keys',
			),
			'openai'     => array(
				'label'    => 'OpenAI',
				'base_url' => 'https://api.openai.com/v1',
				'model'    => 'gpt-4o-mini',
				'key_url'  => 'https://platform.openai.com/api-keys',
			),
			'qwen'       => array(
				'label'    => 'Qwen (Alibaba)',
				'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
				'model'    => 'qwen-plus',
				'key_url'  => 'https://dashscope.console.aliyun.com/apiKey',
			),
			'clusy'      => array(
				'label'    => 'Clusy.io',
				'base_url' => 'https://api.clusy.io/v1',
				'model'    => 'clusy-chat',
				'key_url'  => 'https://clusy.io/dashboard/api-keys',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'deepseek/deepseek-chat',
				'key_url'  => 'https://openrouter.ai/keys',
			),
			'groq'       => array(
				'label'    => 'Groq',
				'base_url' => 'https://api.groq.com/openai/v1',
				'model'    => 'llama-3.3-70b-versatile',
				'key_url'  => 'https://console.groq.com/keys',
			),
			'custom'     => array(
				'label'    => 'Custom (OpenAI-compatible)',
				'base_url' => '',
				'model'    => '',
				'key_url'  => '',
			),
		);
	}

	public static function defaults(): array {
		$p = self::presets()['deepseek'];
		return array(
			'provider'                 => 'deepseek',
			'base_url'                 => $p['base_url'],
			'model'                    => $p['model'],
			'temperature'              => 0.2,
			'default_form_status'      => 'draft',
			'key_management'           => 'keyvault',
			'kb_post_types'            => array(),
			'kb_auto_index'            => true,
			'monthly_token_budget'     => 0,
			'widget'                   => self::widget_defaults(),
			'embeddings_enabled'       => false,
			'embedding_base_url'       => '',
			'embedding_model'          => 'text-embedding-3-small',
			'voice_model'              => 'whisper-1',
			'voice_responses_enabled'  => false,
			'public_assistant_enabled' => false,
			// Empty means the site's Administration Email Address is used. This
			// recipient is server-owned and is never accepted from an AI prompt.
			'form_notification_email'  => '',
			// Phase 1 WooCommerce contract: one reviewed simple-product draft.
			'woocommerce'              => array(
				'enabled'       => true,
				'auto_draft'    => true,
				'enable_search' => false,
				'enable_edit'   => false,
			),
		);
	}

	public static function widget_defaults(): array {
		return array(
			'enabled'                => true,
			'position'               => 'bottom-right',
			'theme'                  => 'light',
			'color'                  => '#4f46e5',
			'bg'                     => '#ffffff',
			'title'                  => 'SitePilot AI',
			'greeting'               => 'Hi! How can I help you?',
			'auto_open'              => 5,
			'voice_enabled'          => true,
			'voice_auto'             => true,
			'handoff_enabled'        => false,
			'handoff_email_enabled'  => true,
			'handoff_telegram_enabled' => false,
			'handoff_twilio_enabled' => false,
			'handoff_email_to'       => '',
			'handoff_telegram_chat'  => '',
			'handoff_twilio_from'    => '',
			'handoff_twilio_to'      => '',
			'exclude_pages'          => array(),
			'exclude_cats'           => array(),
			'exclude_types'          => array(),
			'exclude_ids'            => array(),
			'btn_size'               => 56,
			'btn_shape'              => 'circle',
			'btn_color'              => '#4f46e5',
			'btn_icon'               => 'bubble',
			'btn_animation'          => 'pulse',
			'btn_offset_x'           => 20,
			'btn_offset_y'           => 20,
			'window_width'           => 380,
			'window_height'          => 550,
			'window_border_radius'   => 12,
			'window_accent_color'    => '#4f46e5',
			'window_header_color'    => '#4f46e5',
			'window_font_size'       => 'medium',
			'window_shadow'          => 'medium',
			'widget_visibility'      => 'all',
		);
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return self::defaults();
		}

		// Credentials never travel with general settings, even internally.
		$widget = isset( $stored['widget'] ) && is_array( $stored['widget'] ) ? $stored['widget'] : array();
		if ( array() !== $widget ) {
			foreach ( self::HANDOFF_SECRET_KEYS as $key ) {
				unset( $widget[ $key ] );
			}
		}

		$settings           = array_merge( self::defaults(), $stored );
		$settings['widget'] = array_merge( self::widget_defaults(), $widget );

		return $settings;
	}

	public static function get( string $key, $default = null ) {
		$s = self::all();
		return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
	}

	public static function update( array $data ): void {
		$widget_update = array();
		if ( isset( $data['widget'] ) && is_array( $data['widget'] ) ) {
			$widget_update = $data['widget'];
			foreach ( self::HANDOFF_SECRET_KEYS as $key ) {
				if ( ! array_key_exists( $key, $widget_update ) ) {
					continue;
				}
				$value = $widget_update[ $key ];
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					self::set_handoff_secret( $key, trim( $value ) );
				}
				unset( $widget_update[ $key ] );
			}
			unset( $data['widget'] );
		}
		$existing = self::all();
		$updated  = array_merge( $existing, $data );
		if ( array() !== $widget_update ) {
			$updated['widget'] = array_merge( (array) $existing['widget'], $widget_update );
		}
		update_option( self::OPTION, $updated, false );
	}

	public static function set_api_key( string $plaintext ): void {
		if ( '' === $plaintext ) {
			delete_option( self::KEY_OPTION );
			return;
		}
		$vault     = KeyVault::fromWordPress();
		$encrypted = $vault->encrypt( $plaintext );
		update_option( self::KEY_OPTION, $encrypted, false );
	}

	public static function get_api_key(): string {
		$stored = get_option( self::KEY_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return ''; }
		try {
			$vault = KeyVault::fromWordPress();
			return $vault->decrypt( $stored );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	public static function has_api_key(): bool {
		$stored = get_option( self::KEY_OPTION, '' );
		return is_string( $stored ) && '' !== $stored;
	}

	public static function delete_api_key(): void {
		delete_option( self::KEY_OPTION );
	}

	// ── Embedding API key (optional, separate provider for /embeddings) ──

	public static function set_embedding_key( string $plaintext ): void {
		if ( '' === $plaintext ) {
			delete_option( self::EMBED_KEY_OPTION );
			return;
		}
		$vault     = KeyVault::fromWordPress();
		$encrypted = $vault->encrypt( $plaintext );
		update_option( self::EMBED_KEY_OPTION, $encrypted, false );
	}

	/**
	 * The embedding key, or the main provider key when no separate one is set.
	 */
	public static function get_embedding_key(): string {
		$stored = get_option( self::EMBED_KEY_OPTION, '' );
		if ( is_string( $stored ) && '' !== $stored ) {
			try {
				$vault = KeyVault::fromWordPress();
				return $vault->decrypt( $stored );
			} catch ( \Throwable $e ) {
				return self::get_api_key();
			}
		}

		return self::get_api_key();
	}

	public static function has_embedding_key(): bool {

		$stored = get_option( self::EMBED_KEY_OPTION, '' );
		return is_string( $stored ) && '' !== $stored;
	}

	/**
	 * Store delivery-provider credentials separately from regular settings.
	 * Empty values deliberately delete a credential rather than storing a blank
	 * encrypted token.
	 */
	public static function set_handoff_secret( string $key, string $plaintext ): bool {

		if ( ! in_array( $key, self::HANDOFF_SECRET_KEYS, true ) ) {
			return false;
		}

		$secrets = self::handoff_secrets();
		if ( '' === $plaintext ) {
			unset( $secrets[ $key ] );
			update_option( self::HANDOFF_SECRET_OPTION, $secrets, false );
			return true;
		}

		try {
			$secrets[ $key ] = KeyVault::fromWordPress()->encrypt( $plaintext );
			update_option( self::HANDOFF_SECRET_OPTION, $secrets, false );
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public static function get_handoff_secret( string $key ): string {

		if ( ! in_array( $key, self::HANDOFF_SECRET_KEYS, true ) ) {
			return '';
		}

		$stored = self::handoff_secrets()[ $key ] ?? '';
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		try {
			return KeyVault::fromWordPress()->decrypt( $stored );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	public static function has_handoff_secret( string $key ): bool {
		return '' !== self::get_handoff_secret( $key );
	}

	/**
	 * One-time migration for installations that stored handoff credentials in
	 * the general settings option before encrypted storage existed.
	 */
	public static function migrate_legacy_handoff_secrets(): void {

		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || empty( $settings['widget'] ) || ! is_array( $settings['widget'] ) ) {
			return;
		}

		$changed = false;
		if ( empty( $settings['widget']['handoff_email_to'] ) && ! empty( $settings['widget']['handoff_email'] ) ) {
			$email = sanitize_email( (string) $settings['widget']['handoff_email'] );
			if ( '' !== $email ) {
				$settings['widget']['handoff_email_to'] = $email;
				$changed = true;
			}
		}
		if ( array_key_exists( 'handoff_email', $settings['widget'] ) ) {
			unset( $settings['widget']['handoff_email'] );
			$changed = true;
		}
		foreach ( self::HANDOFF_SECRET_KEYS as $key ) {
			$value = $settings['widget'][ $key ] ?? '';
			if ( is_string( $value ) && '' !== $value ) {
				if ( ! self::set_handoff_secret( $key, $value ) ) {
					continue;
				}
			}
			if ( array_key_exists( $key, $settings['widget'] ) ) {
				unset( $settings['widget'][ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $settings, false );
		}
	}

	/**
	 * The one approved recipient for new independent SitePilot Forms.
	 *
	 * Form instructions cannot set or override this value. A form stores the
	 * resolved address at approval time so later settings changes do not reroute
	 * an already-approved form unexpectedly.
	 */
	public static function form_notification_email(): string {

		$configured = self::valid_email( (string) self::get( 'form_notification_email', '' ) );
		if ( '' !== $configured ) {
			return $configured;
		}

		return self::valid_email( (string) get_option( 'admin_email', '' ) );
	}

	private static function valid_email( string $email ): string {

		$email = sanitize_email( trim( $email ) );
		if ( '' === $email ) {
			return '';
		}
		if ( function_exists( 'is_email' ) ) {
			return is_email( $email ) ? $email : '';
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}

	/** @return array<string,string> */
	private static function handoff_secrets(): array {

		$stored = get_option( self::HANDOFF_SECRET_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}
}
