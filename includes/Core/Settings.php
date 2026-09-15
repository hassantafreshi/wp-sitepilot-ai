<?php
declare(strict_types=1);
namespace AISAAutoPilot\Core;
use AISAAutoPilot\Security\KeyVault;

final class Settings {
	public const OPTION                = 'aisaautopilot_settings';
	public const KEY_OPTION            = 'aisaautopilot_provider_key';
	public const EMBED_KEY_OPTION      = 'aisaautopilot_embedding_key';
	public const HANDOFF_SECRET_OPTION = 'aisaautopilot_handoff_secrets';

	/** @var string[] */
	private const HANDOFF_SECRET_KEYS = array(
		'handoff_telegram_token',
		'handoff_twilio_sid',
		'handoff_twilio_token',
	);

	/**
	 * Every field a preset may carry, with the value assumed when it is omitted.
	 *
	 * `api` picks the wire format (`openai` = Chat Completions, `anthropic` =
	 * Messages), `auth` picks the header the key travels in, `models` is the
	 * starting dropdown, and `embeddings` lists the models the same endpoint can
	 * vectorise with — empty means the provider has no /embeddings route.
	 *
	 * @var array<string, mixed>
	 */
	private const PRESET_DEFAULTS = array(
		'label'      => '',
		'base_url'   => '',
		'model'      => '',
		'key_url'    => '',
		'api'        => 'openai',
		'auth'       => 'bearer',
		'group'      => 'direct',
		'models'     => array(),
		'embeddings' => array(),
		'note'       => '',
	);

	/**
	 * The provider catalogue.
	 *
	 * Model lists are a starting point, not a hard-coded truth: every provider
	 * here also answers `GET {base_url}/models`, and the Settings screen refreshes
	 * the dropdown from that live list on request. Anything not listed can still
	 * be typed in by hand.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function presets(): array {
		$presets = array(
			'openai'     => array(
				'label'      => 'OpenAI',
				'base_url'   => 'https://api.openai.com/v1',
				'model'      => 'gpt-5.6-luna',
				'key_url'    => 'https://platform.openai.com/api-keys',
				'models'     => array(
					'gpt-5.6-sol'         => 'GPT-5.6 Sol (flagship)',
					'gpt-5.6-terra'       => 'GPT-5.6 Terra (balanced)',
					'gpt-5.6-luna'        => 'GPT-5.6 Luna (cost-optimised)',
					'gpt-5.5'             => 'GPT-5.5',
					'gpt-5.5-pro'         => 'GPT-5.5 Pro',
					'gpt-5.4'             => 'GPT-5.4',
					'gpt-5.4-mini'        => 'GPT-5.4 mini',
					'gpt-5.4-nano'        => 'GPT-5.4 nano',
					'gpt-5.3-chat-latest' => 'GPT-5.3 Chat (latest)',
					'gpt-5.2'             => 'GPT-5.2',
					'gpt-5.1'             => 'GPT-5.1',
					'gpt-4.1'             => 'GPT-4.1',
					'gpt-4.1-mini'        => 'GPT-4.1 mini',
					'gpt-4.1-nano'        => 'GPT-4.1 nano',
				),
				'embeddings' => array(
					'text-embedding-3-small' => 'text-embedding-3-small',
					'text-embedding-3-large' => 'text-embedding-3-large',
				),
			),
			'anthropic'  => array(
				'label'    => 'Anthropic (Claude)',
				'base_url' => 'https://api.anthropic.com/v1',
				'model'    => 'claude-opus-5',
				'key_url'  => 'https://console.anthropic.com/settings/keys',
				'api'      => 'anthropic',
				'auth'     => 'x-api-key',
				'models'   => array(
					'claude-opus-5'     => 'Claude Opus 5',
					'claude-opus-4-8'   => 'Claude Opus 4.8',
					'claude-opus-4-7'   => 'Claude Opus 4.7',
					'claude-opus-4-6'   => 'Claude Opus 4.6',
					'claude-sonnet-5'   => 'Claude Sonnet 5',
					'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
					'claude-haiku-4-5'  => 'Claude Haiku 4.5',
					'claude-fable-5'    => 'Claude Fable 5',
				),
				'note'     => __( 'Anthropic speaks its own Messages API, which AISAAutoPilot handles natively. It has no embeddings endpoint, so semantic search needs a separate embedding provider below.', 'ai-site-assistant-autopilot' ),
			),
			'gemini'     => array(
				'label'      => 'Google Gemini',
				'base_url'   => 'https://generativelanguage.googleapis.com/v1beta/openai',
				'model'      => 'gemini-3.6-flash',
				'key_url'    => 'https://aistudio.google.com/apikey',
				'models'     => array(
					'gemini-3.7-flash'       => 'Gemini 3.7 Flash',
					'gemini-3.6-flash'       => 'Gemini 3.6 Flash',
					'gemini-3.5-flash'       => 'Gemini 3.5 Flash',
					'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite',
					'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash-Lite',
					'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (preview)',
					'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
					'gemini-2.5-flash-lite'  => 'Gemini 2.5 Flash-Lite',
				),
				'embeddings' => array(
					'gemini-embedding-001' => 'gemini-embedding-001',
				),
				'note'       => __( 'Uses Google’s OpenAI-compatible endpoint, so the same key works for chat and embeddings.', 'ai-site-assistant-autopilot' ),
			),
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'base_url' => 'https://api.deepseek.com/v1',
				'model'    => 'deepseek-v4-flash',
				'key_url'  => 'https://platform.deepseek.com/api_keys',
				'models'   => array(
					'deepseek-v4-flash' => 'DeepSeek V4 Flash',
					'deepseek-v4-pro'   => 'DeepSeek V4 Pro',
					'deepseek-chat'     => 'deepseek-chat (alias)',
					'deepseek-reasoner' => 'deepseek-reasoner (alias)',
				),
			),
			'qwen'       => array(
				'label'      => 'Qwen (Alibaba Model Studio)',
				'base_url'   => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
				'model'      => 'qwen-plus',
				'key_url'    => 'https://bailian.console.alibabacloud.com/?tab=model#/api-key',
				'models'     => array(
					'qwen3-max'  => 'Qwen3-Max',
					'qwen-max'   => 'Qwen-Max',
					'qwen-plus'  => 'Qwen-Plus',
					'qwen-turbo' => 'Qwen-Turbo',
					'qwen-flash' => 'Qwen-Flash',
				),
				'embeddings' => array(
					'text-embedding-v4' => 'text-embedding-v4',
					'text-embedding-v3' => 'text-embedding-v3',
				),
				'note'       => __( 'Mainland-China accounts use https://dashscope.aliyuncs.com/compatible-mode/v1 instead.', 'ai-site-assistant-autopilot' ),
			),
			'mistral'    => array(
				'label'      => 'Mistral AI',
				'base_url'   => 'https://api.mistral.ai/v1',
				'model'      => 'mistral-medium-3-5-26-04',
				'key_url'    => 'https://console.mistral.ai/api-keys',
				'models'     => array(
					'mistral-large-3-25-12'    => 'Mistral Large 3',
					'mistral-medium-3-5-26-04' => 'Mistral Medium 3.5',
					'mistral-small-4-0-26-03'  => 'Mistral Small 4',
					'ministral-3-14b-25-12'    => 'Ministral 3 14B',
					'ministral-3-8b-25-12'     => 'Ministral 3 8B',
					'ministral-3-3b-25-12'     => 'Ministral 3 3B',
					'codestral-25-08'          => 'Codestral',
				),
				'embeddings' => array(
					'mistral-embed-23-12'   => 'Mistral Embed',
					'codestral-embed-25-05' => 'Codestral Embed',
				),
			),
			'groq'       => array(
				'label'    => 'Groq',
				'base_url' => 'https://api.groq.com/openai/v1',
				'model'    => 'llama-3.1-8b-instant',
				'key_url'  => 'https://console.groq.com/keys',
				'models'   => array(
					'meta-llama/llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B',
					'meta-llama/llama-4-maverick-17b-128e-instruct' => 'Llama 4 Maverick 17B',
					'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
					'llama-3.1-8b-instant'    => 'Llama 3.1 8B Instant',
					'openai/gpt-oss-120b'     => 'GPT-OSS 120B',
					'openai/gpt-oss-20b'      => 'GPT-OSS 20B',
					'groq/compound'           => 'Groq Compound',
					'groq/compound-mini'      => 'Groq Compound Mini',
				),
				'note'     => __( 'Groq is very fast but has no embeddings endpoint — pick a separate embedding provider for semantic search.', 'ai-site-assistant-autopilot' ),
			),
			'perplexity' => array(
				'label'    => 'Perplexity',
				'base_url' => 'https://api.perplexity.ai',
				'model'    => 'sonar-pro',
				'key_url'  => 'https://www.perplexity.ai/account/api/keys',
				'models'   => array(
					'sonar'               => 'Sonar',
					'sonar-pro'           => 'Sonar Pro',
					'sonar-reasoning-pro' => 'Sonar Reasoning Pro',
					'sonar-deep-research' => 'Sonar Deep Research',
				),
				'note'     => __( 'Perplexity answers from live web search. Its OpenAI-compatible Sonar endpoint is scheduled to retire on 27 September 2026 in favour of the Agent API, so treat this provider as short-term.', 'ai-site-assistant-autopilot' ),
			),
			'azure'      => array(
				'label'    => 'Azure OpenAI',
				'base_url' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1',
				'model'    => '',
				'key_url'  => 'https://portal.azure.com/',
				'auth'     => 'api-key',
				'note'     => __( 'Replace YOUR-RESOURCE with your Azure resource name. The model is your deployment name, so pick it from your Azure portal rather than a fixed list.', 'ai-site-assistant-autopilot' ),
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'openai/gpt-5.6-luna',
				'key_url'  => 'https://openrouter.ai/keys',
				'group'    => 'gateway',
				'models'   => array(
					'openai/gpt-5.6-luna'        => 'OpenAI GPT-5.6 Luna',
					'openai/gpt-5.6-sol'         => 'OpenAI GPT-5.6 Sol',
					'anthropic/claude-opus-5'    => 'Anthropic Claude Opus 5',
					'anthropic/claude-sonnet-5'  => 'Anthropic Claude Sonnet 5',
					'google/gemini-3.6-flash'    => 'Google Gemini 3.6 Flash',
					'deepseek/deepseek-v4-flash' => 'DeepSeek V4 Flash',
					'mistralai/mistral-large-3'  => 'Mistral Large 3',
				),
				'note'     => __( 'One key for many vendors. Use “Refresh from provider” to pull OpenRouter’s current catalogue.', 'ai-site-assistant-autopilot' ),
			),
			'aimlapi'    => array(
				'label'    => 'AI/ML API',
				'base_url' => 'https://api.aimlapi.com/v1',
				'model'    => '',
				'key_url'  => 'https://aimlapi.com/app/keys',
				'group'    => 'gateway',
				'note'     => __( 'Aggregator with 1000+ models behind one key. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'novita'     => array(
				'label'    => 'Novita AI',
				'base_url' => 'https://api.novita.ai/openai/v1',
				'model'    => '',
				'key_url'  => 'https://novita.ai/settings/key-management',
				'group'    => 'gateway',
				'note'     => __( 'Open-model inference cloud. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'edenai'     => array(
				'label'    => 'Eden AI',
				'base_url' => 'https://api.edenai.run/v3',
				'model'    => '',
				'key_url'  => 'https://app.edenai.run/admin/iam/api-keys',
				'group'    => 'gateway',
				'note'     => __( 'Multi-provider gateway. Model names are vendor-prefixed — use “Refresh from provider” to load them.', 'ai-site-assistant-autopilot' ),
			),
			'clusy'      => array(
				'label'    => 'Clusy.io',
				'base_url' => 'https://api.clusy.io/v1',
				'model'    => 'clusy-chat',
				'key_url'  => 'https://clusy.io/dashboard/api-keys',
				'group'    => 'gateway',
				'models'   => array( 'clusy-chat' => 'clusy-chat' ),
			),
			'custom'     => array(
				'label' => __( 'Custom (OpenAI-compatible)', 'ai-site-assistant-autopilot' ),
				'group' => 'custom',
				'note'  => __( 'Any endpoint that implements POST /chat/completions. Paste the base URL up to but not including /chat/completions.', 'ai-site-assistant-autopilot' ),
			),
		);

		foreach ( $presets as $key => $preset ) {
			$presets[ $key ] = array_merge( self::PRESET_DEFAULTS, $preset );
		}

		/**
		 * Filter the AI provider catalogue.
		 *
		 * Lets a site add a private endpoint without editing the plugin. Each
		 * entry accepts the keys listed in PRESET_DEFAULTS.
		 *
		 * @param array<string, array<string, mixed>> $presets
		 */
		$filtered = apply_filters( 'aisaautopilot_provider_presets', $presets );

		return is_array( $filtered ) && array() !== $filtered ? $filtered : $presets;
	}

	/**
	 * One preset with every key present, so callers never have to null-check.
	 *
	 * @return array<string, mixed>
	 */
	public static function preset( string $key ): array {
		$presets = self::presets();
		$preset  = isset( $presets[ $key ] ) && is_array( $presets[ $key ] ) ? $presets[ $key ] : array();

		return array_merge( self::PRESET_DEFAULTS, $preset );
	}

	/**
	 * The preset headings shown in the provider dropdown.
	 *
	 * @return array<string, string>
	 */
	public static function preset_groups(): array {
		return array(
			'direct'  => __( 'Direct providers', 'ai-site-assistant-autopilot' ),
			'gateway' => __( 'Gateways & aggregators', 'ai-site-assistant-autopilot' ),
			'custom'  => __( 'Custom endpoint', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * The saved provider's wire format: `openai` or `anthropic`.
	 */
	public static function provider_api(): string {
		return 'anthropic' === self::preset( (string) self::get( 'provider', '' ) )['api'] ? 'anthropic' : 'openai';
	}

	/**
	 * The header the saved provider's key travels in.
	 */
	public static function provider_auth(): string {
		$auth = (string) self::preset( (string) self::get( 'provider', '' ) )['auth'];

		return in_array( $auth, array( 'bearer', 'x-api-key', 'api-key' ), true ) ? $auth : 'bearer';
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
			'form_composer_enabled'    => true,
			'efb_copilot_enabled'      => true,
			'content_studio_enabled'   => true,
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

	/**
	 * The per-part colours the widget customiser can override.
	 *
	 * @var string[]
	 */
	public const WIDGET_COLOR_KEYS = array(
		'head_from',
		'head_to',
		'user',
		'ai_bg',
		'ai_text',
		'panel',
		'msgs',
		'border',
		'input',
		'send',
		'handoff',
		'bubble',
	);

	/** The four icon slots offered by the widget customiser. */
	public const WIDGET_ICON_KEYS = array( 'avatar', 'voice', 'handoff', 'float' );

	/**
	 * Colours derived from the brand colour and theme. These are the starting
	 * point the customiser opens with, and the values used when the customiser
	 * is off.
	 *
	 * @return array<string, string>
	 */
	public static function widget_derived_colors( string $brand, string $theme ): array {
		$dark = 'dark' === $theme;

		return array(
			'head_from' => $brand,
			'head_to'   => '#181f66',
			'user'      => $brand,
			'send'      => $brand,
			'bubble'    => $brand,
			'handoff'   => $brand,
			'panel'     => $dark ? '#1f2937' : '#ffffff',
			'msgs'      => $dark ? '#111827' : '#f9fafb',
			'ai_bg'     => $dark ? '#1f2937' : '#ffffff',
			'ai_text'   => $dark ? '#f3f4f6' : '#1f2937',
			'input'     => $dark ? '#111827' : '#f9fafb',
			'border'    => $dark ? '#374151' : '#e5e7eb',
		);
	}

	public static function widget_defaults(): array {
		return array(
			'enabled'                      => true,
			'position'                     => 'bottom-right',
			'theme'                        => 'light',
			'color'                        => '#202a8d',
			'bg'                           => '#ffffff',
			'title'                        => 'AI Site Assistant AutoPilot',
			'subtitle'                     => 'AI Assistant',
			'greeting'                     => 'Hi! How can I help you?',
			'suggestions'                  => array(),
			'panel_radius'                 => 16,
			'msg_radius'                   => 14,
			'bubble_radius'                => 30,
			'custom_colors'                => false,
			'colors'                       => array(),
			'icons'                        => array(),
			'auto_open'                    => 5,
			'voice_enabled'                => true,
			'voice_auto'                   => true,
			'conversation_logging_enabled' => false,
			'conversation_notice'          => 'This conversation may be recorded to help our support team.',
			'handoff_enabled'              => false,
			'handoff_email_enabled'        => true,
			'handoff_telegram_enabled'     => false,
			'handoff_twilio_enabled'       => false,
			'handoff_email_to'             => '',
			'handoff_telegram_chat'        => '',
			'handoff_twilio_from'          => '',
			'handoff_twilio_to'            => '',
			'exclude_pages'                => array(),
			'exclude_cats'                 => array(),
			'exclude_types'                => array(),
			'exclude_ids'                  => array(),
			'btn_size'                     => 56,
			'btn_shape'                    => 'circle',
			'btn_color'                    => '#202a8d',
			'btn_icon'                     => 'bubble',
			'btn_animation'                => 'pulse',
			'btn_offset_x'                 => 20,
			'btn_offset_y'                 => 20,
			'window_width'                 => 380,
			'window_height'                => 550,
			'window_border_radius'         => 12,
			'window_accent_color'          => '#202a8d',
			'window_header_color'          => '#202a8d',
			'window_font_size'             => 'medium',
			'window_shadow'                => 'medium',
			'widget_visibility'            => 'all',
		);
	}

	/**
	 * The Answer Desk has one public runtime contract.
	 *
	 * Its Integration switch deliberately controls whether the visitor-facing
	 * assistant exists at all, while the Widget switch controls whether that
	 * assistant is visible. Keeping the two checks together prevents the
	 * Conversations and Handoff admin surfaces from referring to a different
	 * renderer than the one visitors receive.
	 */
	public static function answer_desk_enabled(): bool {
		$settings = self::all();
		$widget   = isset( $settings['widget'] ) && is_array( $settings['widget'] )
			? $settings['widget']
			: array();

		return ! empty( $settings['public_assistant_enabled'] ) && ! empty( $widget['enabled'] );
	}

	/**
	 * A visitor may be offered handoff only when the Answer Desk is live and
	 * at least one enabled delivery channel has the fields it needs.
	 *
	 * This is intentionally shared by the renderer, chat engine and public
	 * endpoint. A checked-but-incomplete channel must never create a button
	 * which then fails after a visitor has pressed it.
	 */
	public static function answer_desk_handoff_available(): bool {
		if ( ! self::answer_desk_enabled() ) {
			return false;
		}

		$widget = (array) self::get( 'widget', array() );
		if ( empty( $widget['handoff_enabled'] ) ) {
			return false;
		}

		$email        = sanitize_email( (string) ( $widget['handoff_email_to'] ?? get_option( 'admin_email', '' ) ) );
		$has_email    = ! empty( $widget['handoff_email_enabled'] ) && '' !== $email && is_email( $email );
		$has_telegram = ! empty( $widget['handoff_telegram_enabled'] )
			&& self::has_handoff_secret( 'handoff_telegram_token' )
			&& '' !== trim( (string) ( $widget['handoff_telegram_chat'] ?? '' ) );
		$has_twilio   = ! empty( $widget['handoff_twilio_enabled'] )
			&& self::has_handoff_secret( 'handoff_twilio_sid' )
			&& self::has_handoff_secret( 'handoff_twilio_token' )
			&& '' !== trim( (string) ( $widget['handoff_twilio_from'] ?? '' ) )
			&& '' !== trim( (string) ( $widget['handoff_twilio_to'] ?? '' ) );

		return $has_email || $has_telegram || $has_twilio;
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

	public static function get( string $key, $fallback = null ) {
		$s = self::all();
		return array_key_exists( $key, $s ) ? $s[ $key ] : $fallback;
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
				$changed                                = true;
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
	 * The one approved recipient for new independent AISAAutoPilot Forms.
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
