<?php
declare(strict_types=1);
namespace AISAAutoPilot\Core;
use AISAAutoPilot\Security\KeyVault;
use AISAAutoPilot\Support\Retention;

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
		'handoff_whatsapp_token',
		'handoff_slack_webhook',
		'handoff_signal_token',
	);

	/** Every handoff delivery channel, in the order the settings page shows them. */
	public const HANDOFF_CHANNELS = array( 'email', 'telegram', 'whatsapp', 'twilio', 'slack', 'signal' );

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
		// phpcs:disable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Bring-your-own-key presets: each base URL only pre-fills the endpoint the administrator chooses, and no request is made until they save a key. The WordPress AI Client exists only from WordPress 7.0 while this plugin supports 6.2, and keys can already come from WordPress Connectors (see WpConnectors).
		$presets = array(
			'openai'      => array(
				'label'      => 'OpenAI',
				'base_url'   => 'https://api.openai.com/v1',
				'model'      => 'gpt-5.6-luna',
				'key_url'    => 'https://platform.openai.com/api-keys',
				'models'     => array(
					'gpt-6-astra'   => 'GPT-6 Astra (flagship)',
					'gpt-6.1-sol'   => 'GPT-6.1 Sol',
					'gpt-6.1-terra' => 'GPT-6.1 Terra',
					'gpt-6-luna'    => 'GPT-6 Luna (cost-optimised)',
					'gpt-5.6-sol'   => 'GPT-5.6 Sol',
					'gpt-5.6-terra' => 'GPT-5.6 Terra (balanced)',
					'gpt-5.6-luna'  => 'GPT-5.6 Luna (cost-optimised)',
					'gpt-5.5'       => 'GPT-5.5',
					'gpt-5.4'       => 'GPT-5.4',
					'gpt-5.4-mini'  => 'GPT-5.4 mini',
				),
				'embeddings' => array(
					'text-embedding-3-small' => 'text-embedding-3-small',
					'text-embedding-3-large' => 'text-embedding-3-large',
				),
			),
			'anthropic'   => array(
				'label'    => 'Anthropic (Claude)',
				'base_url' => 'https://api.anthropic.com/v1',
				'model'    => 'claude-sonnet-5-5',
				'key_url'  => 'https://console.anthropic.com/settings/keys',
				'api'      => 'anthropic',
				'auth'     => 'x-api-key',
				'models'   => array(
					'claude-opus-5-5'   => 'Claude Opus 5.5',
					'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
					'claude-haiku-5-5'  => 'Claude Haiku 5.5',
					'claude-fable-5-1'  => 'Claude Fable 5.1',
					'claude-opus-5'     => 'Claude Opus 5',
					'claude-sonnet-5'   => 'Claude Sonnet 5',
					'claude-fable-5'    => 'Claude Fable 5',
					'claude-opus-4-8'   => 'Claude Opus 4.8',
					'claude-opus-4-7'   => 'Claude Opus 4.7',
					'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
					'claude-haiku-4-5'  => 'Claude Haiku 4.5',
				),
				'note'     => __( 'Anthropic speaks its own Messages API, which AISAAutoPilot handles natively. It has no embeddings endpoint, so semantic search needs a separate embedding provider below.', 'ai-site-assistant-autopilot' ),
			),
			'gemini'      => array(
				'label'      => 'Google Gemini',
				'base_url'   => 'https://generativelanguage.googleapis.com/v1beta/openai',
				'model'      => 'gemini-3.8-flash',
				'key_url'    => 'https://aistudio.google.com/apikey',
				'models'     => array(
					'gemini-4-argon'         => 'Gemini 4 Argon',
					'gemini-3.8-flash'       => 'Gemini 3.8 Flash',
					'gemini-3.6-flash'       => 'Gemini 3.6 Flash',
					'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite',
					'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash-Lite',
					'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (preview)',
				),
				'embeddings' => array(
					'gemini-embedding-001'       => 'gemini-embedding-001',
					'gemini-embedding-2-preview' => 'gemini-embedding-2-preview',
				),
				'note'       => __( 'Uses Google’s OpenAI-compatible endpoint, so the same key works for chat and embeddings.', 'ai-site-assistant-autopilot' ),
			),
			'xai'         => array(
				'label'    => 'xAI (Grok)',
				'base_url' => 'https://api.x.ai/v1',
				'model'    => 'grok-4.3',
				'key_url'  => 'https://console.x.ai',
				'models'   => array(
					'grok-4.7'                     => 'Grok 4.7 (flagship)',
					'grok-4.6'                     => 'Grok 4.6',
					'grok-4.5'                     => 'Grok 4.5',
					'grok-4.3'                     => 'Grok 4.3 (fast, low cost)',
					'grok-4.20-0309-non-reasoning' => 'Grok 4.20 (non-reasoning)',
					'grok-4.20-0309-reasoning'     => 'Grok 4.20 (reasoning)',
				),
				'note'     => __( 'xAI has no embeddings endpoint, so semantic search needs a separate embedding provider. Use https://us.api.x.ai/v1 to keep inference in the United States.', 'ai-site-assistant-autopilot' ),
			),
			'deepseek'    => array(
				'label'    => 'DeepSeek',
				'base_url' => 'https://api.deepseek.com/v1',
				'model'    => 'deepseek-flash',
				'key_url'  => 'https://platform.deepseek.com/api_keys',
				'models'   => array(
					'deepseek-flash'  => 'DeepSeek Flash',
					'deepseek-v4-pro' => 'DeepSeek V4 Pro',
				),
				'note'     => __( 'deepseek-chat and deepseek-reasoner were retired; deepseek-flash replaces them.', 'ai-site-assistant-autopilot' ),
			),
			'qwen'        => array(
				'label'      => 'Qwen (Alibaba Model Studio)',
				'base_url'   => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
				'model'      => 'qwen3.8-flash',
				'key_url'    => 'https://bailian.console.alibabacloud.com/?tab=model#/api-key',
				'models'     => array(
					'qwen3.8-max'   => 'Qwen3.8-Max',
					'qwen3.8-flash' => 'Qwen3.8-Flash',
					'qwen3.7-plus'  => 'Qwen3.7-Plus',
					'qwen3.7-flash' => 'Qwen3.7-Flash',
					'qwen3-max'     => 'Qwen3-Max',
					'qwen-plus'     => 'Qwen-Plus (legacy)',
					'qwen-flash'    => 'Qwen-Flash (legacy)',
				),
				'embeddings' => array(
					'text-embedding-v4'      => 'text-embedding-v4',
					'text-embedding-v3'      => 'text-embedding-v3',
					'qwen3.7-text-embedding' => 'qwen3.7-text-embedding',
				),
				'note'       => __( 'A key only works in its own region: mainland-China accounts use https://dashscope.aliyuncs.com/compatible-mode/v1 and US accounts https://dashscope-us.aliyuncs.com/compatible-mode/v1.', 'ai-site-assistant-autopilot' ),
			),
			'mistral'     => array(
				'label'      => 'Mistral AI',
				'base_url'   => 'https://api.mistral.ai/v1',
				'model'      => 'mistral-medium-3-5',
				'key_url'    => 'https://console.mistral.ai/api-keys',
				'models'     => array(
					'mistral-large-latest'   => 'Mistral Large (latest)',
					'mistral-medium-3-5'     => 'Mistral Medium 3.5',
					'mistral-medium-latest'  => 'Mistral Medium (latest)',
					'mistral-small-latest'   => 'Mistral Small (latest)',
					'magistral-small-latest' => 'Magistral Small (latest)',
					'ministral-14b-latest'   => 'Ministral 14B',
					'ministral-8b-latest'    => 'Ministral 8B',
					'ministral-3b-latest'    => 'Ministral 3B',
				),
				'embeddings' => array(
					'mistral-embed'   => 'Mistral Embed',
					'codestral-embed' => 'Codestral Embed',
				),
				'note'       => __( 'The -latest names move to newer models over time; pick a dated name when behaviour must stay fixed.', 'ai-site-assistant-autopilot' ),
			),
			'cohere'      => array(
				'label'      => 'Cohere',
				'base_url'   => 'https://api.cohere.ai/compatibility/v1',
				'model'      => 'command-a-plus-05-2026',
				'key_url'    => 'https://dashboard.cohere.com/api-keys',
				'models'     => array(
					'command-a-plus-05-2026'      => 'Command A+',
					'command-a-reasoning-08-2025' => 'Command A Reasoning',
					'command-a-translate-08-2025' => 'Command A Translate',
					'command-a-03-2025'           => 'Command A',
					'command-r7b-12-2024'         => 'Command R7B',
					'command-r-plus-08-2024'      => 'Command R+',
				),
				'embeddings' => array(
					'embed-v5.0-pro'  => 'embed-v5.0-pro',
					'embed-v5.0-fast' => 'embed-v5.0-fast',
					'embed-v4.0'      => 'embed-v4.0',
				),
				'note'       => __( 'Uses Cohere’s OpenAI Compatibility API. It may not answer the model-list request, so pick or type the model name.', 'ai-site-assistant-autopilot' ),
			),
			'zai'         => array(
				'label'    => 'Z.ai (GLM)',
				'base_url' => 'https://api.z.ai/api/paas/v4',
				'model'    => 'glm-5.2',
				'key_url'  => 'https://z.ai/manage-apikey/apikey-list',
				'models'   => array(
					'glm-5.3'        => 'GLM-5.3',
					'glm-5.3-flash'  => 'GLM-5.3-Flash',
					'glm-5.3-flashx' => 'GLM-5.3-FlashX',
					'glm-5.2'        => 'GLM-5.2',
					'glm-5.1'        => 'GLM-5.1',
				),
				'note'     => __( 'Mainland-China accounts (Zhipu BigModel) use https://open.bigmodel.cn/api/paas/v4 instead.', 'ai-site-assistant-autopilot' ),
			),
			'moonshot'    => array(
				'label'    => 'Moonshot AI (Kimi)',
				'base_url' => 'https://api.moonshot.ai/v1',
				'model'    => 'kimi-k3',
				'key_url'  => 'https://platform.kimi.ai/console/api-keys',
				'models'   => array(
					'kimi-k3.1-pro'            => 'Kimi K3.1 Pro',
					'kimi-k3.1-pro-highspeed'  => 'Kimi K3.1 Pro (high speed)',
					'kimi-k3'                  => 'Kimi K3',
					'kimi-k2.7-code'           => 'Kimi K2.7 Code',
					'kimi-k2.7-code-highspeed' => 'Kimi K2.7 Code (high speed)',
					'kimi-k2.6'                => 'Kimi K2.6',
				),
				'note'     => __( 'Kimi models use a fixed temperature, so AISAAutoPilot drops its own setting for them. Mainland-China accounts use https://api.moonshot.cn/v1. No embeddings endpoint.', 'ai-site-assistant-autopilot' ),
			),
			'minimax'     => array(
				'label'    => 'MiniMax',
				'base_url' => 'https://api.minimax.io/v1',
				'model'    => 'MiniMax-M3.1-Flash',
				'key_url'  => 'https://platform.minimax.io/user-center/basic-information/interface-key',
				'models'   => array(
					'MiniMax-M3.1-Flash'     => 'MiniMax-M3.1-Flash',
					'MiniMax-M3'             => 'MiniMax-M3',
					'MiniMax-M2.7'           => 'MiniMax-M2.7',
					'MiniMax-M2.7-highspeed' => 'MiniMax-M2.7 (high speed)',
					'MiniMax-M2.5'           => 'MiniMax-M2.5',
					'MiniMax-M2.5-highspeed' => 'MiniMax-M2.5 (high speed)',
				),
				'note'     => __( 'Mainland-China accounts use https://api.minimaxi.com/v1. No embeddings endpoint on this API.', 'ai-site-assistant-autopilot' ),
			),
			'groq'        => array(
				'label'    => 'Groq',
				'base_url' => 'https://api.groq.com/openai/v1',
				'model'    => 'openai/gpt-oss-120b',
				'key_url'  => 'https://console.groq.com/keys',
				'models'   => array(
					'openai/gpt-oss-120b' => 'GPT-OSS 120B',
					'openai/gpt-oss-20b'  => 'GPT-OSS 20B',
					'qwen/qwen3.8-27b'    => 'Qwen3.8 27B (preview)',
				),
				'note'     => __( 'Groq is very fast but has no embeddings endpoint — pick a separate embedding provider for semantic search. Its Llama models now need an Enterprise plan.', 'ai-site-assistant-autopilot' ),
			),
			'cerebras'    => array(
				'label'    => 'Cerebras',
				'base_url' => 'https://api.cerebras.ai/v1',
				'model'    => 'gpt-oss-120b',
				'key_url'  => 'https://cloud.cerebras.ai/',
				'models'   => array(
					'gpt-oss-120b' => 'GPT-OSS 120B',
				),
				'note'     => __( 'Very fast open-model inference. Use “Refresh from provider” to load every model your key can use. No embeddings endpoint.', 'ai-site-assistant-autopilot' ),
			),
			'sambanova'   => array(
				'label'    => 'SambaNova Cloud',
				'base_url' => 'https://api.sambanova.ai/v1',
				'model'    => 'gpt-oss-120b',
				'key_url'  => 'https://cloud.sambanova.ai/',
				'models'   => array(
					'gpt-oss-120b' => 'GPT-OSS 120B',
				),
				'note'     => __( 'Open-model inference. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'azure'       => array(
				'label'    => 'Azure OpenAI',
				'base_url' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1',
				'model'    => '',
				'key_url'  => 'https://portal.azure.com/',
				'auth'     => 'api-key',
				'note'     => __( 'Replace YOUR-RESOURCE with your Azure resource name. The model is your deployment name, so pick it from your Azure portal rather than a fixed list.', 'ai-site-assistant-autopilot' ),
			),
			'openrouter'  => array(
				'label'    => 'OpenRouter',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'openai/gpt-6-luna',
				'key_url'  => 'https://openrouter.ai/keys',
				'group'    => 'gateway',
				'models'   => array(
					'openai/gpt-6-luna'            => 'OpenAI GPT-6 Luna',
					'anthropic/claude-sonnet-5.5'  => 'Anthropic Claude Sonnet 5.5',
					'google/gemini-3.8-flash'      => 'Google Gemini 3.8 Flash',
					'x-ai/grok-4.7'                => 'xAI Grok 4.7',
					'deepseek/deepseek-v4.1-flash' => 'DeepSeek V4.1 Flash',
					'mistralai/mistral-medium-3-5' => 'Mistral Medium 3.5',
				),
				'note'     => __( 'One key for many vendors. Use “Refresh from provider” to pull OpenRouter’s current catalogue.', 'ai-site-assistant-autopilot' ),
			),
			'together'    => array(
				'label'    => 'Together AI',
				'base_url' => 'https://api.together.ai/v1',
				'model'    => '',
				'key_url'  => 'https://api.together.ai/settings/profile',
				'group'    => 'gateway',
				'note'     => __( 'Open-model cloud with chat, embeddings and speech. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'fireworks'   => array(
				'label'    => 'Fireworks AI',
				'base_url' => 'https://api.fireworks.ai/inference/v1',
				'model'    => '',
				'key_url'  => 'https://fireworks.ai/account/api-keys',
				'group'    => 'gateway',
				'note'     => __( 'Model names look like accounts/fireworks/models/<name>. Use “Refresh from provider” to load them.', 'ai-site-assistant-autopilot' ),
			),
			'huggingface' => array(
				'label'    => 'Hugging Face Inference Providers',
				'base_url' => 'https://router.huggingface.co/v1',
				'model'    => 'openai/gpt-oss-120b',
				'key_url'  => 'https://huggingface.co/settings/tokens',
				'group'    => 'gateway',
				'models'   => array(
					'openai/gpt-oss-120b' => 'GPT-OSS 120B',
				),
				'note'     => __( 'Needs a fine-grained token allowed to “Make calls to Inference Providers”. Chat only — no embeddings on this endpoint.', 'ai-site-assistant-autopilot' ),
			),
			'nvidia'      => array(
				'label'    => 'NVIDIA NIM',
				'base_url' => 'https://integrate.api.nvidia.com/v1',
				'model'    => '',
				'key_url'  => 'https://build.nvidia.com/',
				'group'    => 'gateway',
				'note'     => __( 'Hosted NVIDIA NIM endpoints. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'aimlapi'     => array(
				'label'    => 'AI/ML API',
				'base_url' => 'https://api.aimlapi.com/v1',
				'model'    => '',
				'key_url'  => 'https://aimlapi.com/app/keys',
				'group'    => 'gateway',
				'note'     => __( 'Aggregator with 1000+ models behind one key. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'novita'      => array(
				'label'    => 'Novita AI',
				'base_url' => 'https://api.novita.ai/openai/v1',
				'model'    => '',
				'key_url'  => 'https://novita.ai/settings/key-management',
				'group'    => 'gateway',
				'note'     => __( 'Open-model inference cloud. Use “Refresh from provider” to load the current list.', 'ai-site-assistant-autopilot' ),
			),
			'edenai'      => array(
				'label'    => 'Eden AI',
				'base_url' => 'https://api.edenai.run/v3',
				'model'    => '',
				'key_url'  => 'https://app.edenai.run/admin/iam/api-keys',
				'group'    => 'gateway',
				'note'     => __( 'Multi-provider gateway. Model names are vendor-prefixed — use “Refresh from provider” to load them. EU-only routing: https://api.eu.edenai.run/v3.', 'ai-site-assistant-autopilot' ),
			),
			'custom'      => array(
				'label' => __( 'Custom (OpenAI-compatible)', 'ai-site-assistant-autopilot' ),
				'group' => 'custom',
				'note'  => __( 'Any endpoint that implements POST /chat/completions. Paste the base URL up to but not including /chat/completions.', 'ai-site-assistant-autopilot' ),
			),
		);
		// phpcs:enable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration

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
			// Keep a submission whose email could not be sent (Forms\FormOutbox). Off: nothing is stored.
			'forms_keep_undelivered'   => false,
			// Days visitor data is kept (0 = until deleted by hand); see Support\Retention.
			'retention'                => Retention::DEFAULTS,
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

	/** Contact details the handoff card can ask a visitor for, in display order. */
	public const HANDOFF_FIELD_KEYS = array( 'name', 'email', 'phone', 'website' );

	/** Per detail: not asked, asked but optional, or required before the conversation can be sent. */
	public const HANDOFF_FIELD_MODES = array( 'off', 'optional', 'required' );

	/**
	 * Revision of the shipped widget defaults, stored as widget.defaults_revision.
	 *
	 * Revision 2 (S3.3.1, owner decision D-25): the chat no longer opens on its own, replies are
	 * not read aloud unless the site owner turns it on, and the panel uses the mockup's 392×624.
	 */
	public const WIDGET_DEFAULTS_REVISION = 2;

	/**
	 * Legacy values that no admin screen ever wrote, mapped to their revision-2 defaults.
	 *
	 * Settings::update() stores the whole widget_defaults() on every save, so these values sit in
	 * the option of any site that saved anything before revision 2 without anyone choosing them.
	 *
	 * @var array<string, array{0:int, 1:int}>
	 */
	private const WIDGET_LEGACY_DEFAULTS = array(
		'auto_open'     => array( 5, 0 ),
		'window_width'  => array( 380, 392 ),
		'window_height' => array( 550, 624 ),
	);

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
			'panel_radius'                 => 20,
			'msg_radius'                   => 14,
			'bubble_radius'                => 30,
			'custom_colors'                => false,
			'colors'                       => array(),
			'icons'                        => array(),
			// Seconds before the panel opens by itself; 0 keeps it closed and shows the nudge instead.
			'auto_open'                    => 0,
			'voice_enabled'                => true,
			'voice_auto'                   => false,
			'conversation_logging_enabled' => false,
			'conversation_notice'          => 'This conversation may be recorded to help our support team.',
			'handoff_enabled'              => false,
			'handoff_email_enabled'        => true,
			'handoff_telegram_enabled'     => false,
			'handoff_twilio_enabled'       => false,
			'handoff_whatsapp_enabled'     => false,
			'handoff_slack_enabled'        => false,
			'handoff_signal_enabled'       => false,
			'handoff_email_to'             => '',
			'handoff_telegram_chat'        => '',
			'handoff_twilio_from'          => '',
			'handoff_twilio_to'            => '',
			// WhatsApp Cloud API: the business number's ID, the team member's number, and an optional approved template.
			'handoff_whatsapp_phone_id'    => '',
			'handoff_whatsapp_to'          => '',
			'handoff_whatsapp_template'    => '',
			'handoff_whatsapp_language'    => 'en_US',
			// Signal through the owner's signal-cli REST API server.
			'handoff_signal_url'           => '',
			'handoff_signal_from'          => '',
			'handoff_signal_to'            => '',
			'handoff_fields'               => array_fill_keys( self::HANDOFF_FIELD_KEYS, 'off' ),
			// When the assistant finds no answer: 'message' (the text below, or the built-in one) or 'handoff'.
			'not_found_action'             => 'message',
			'not_found_message'            => '',
			// 'all' shows the widget everywhere; 'exclude' hides it where widget_exclusions() matches.
			'widget_visibility'            => 'all',
			'exclude_types'                => array(),
			'exclude_cats'                 => array(),
			'exclude_ids'                  => array(),
			// Read with exclude_ids so nothing saved is lost; the page picker writes exclude_ids only.
			'exclude_pages'                => array(),
			'window_width'                 => 392,
			'window_height'                => 624,
			'defaults_revision'            => self::WIDGET_DEFAULTS_REVISION,
		) + self::WIDGET_RETIRED_DEFAULTS;
	}

	/**
	 * Keys from the first widget design. Nothing reads or writes them; they stay only because
	 * Settings::update() has already stored them on every site that saved. The live setting that
	 * does each job is listed in docs/technical/DATA_MODEL_FA.md.
	 *
	 * @var array<string, int|string>
	 */
	private const WIDGET_RETIRED_DEFAULTS = array(
		'btn_size'             => 56,
		'btn_shape'            => 'circle',
		'btn_color'            => '#202a8d',
		'btn_icon'             => 'bubble',
		'btn_animation'        => 'pulse',
		'btn_offset_x'         => 20,
		'btn_offset_y'         => 20,
		'window_border_radius' => 12,
		'window_accent_color'  => '#202a8d',
		'window_header_color'  => '#202a8d',
		'window_font_size'     => 'medium',
		'window_shadow'        => 'medium',
	);

	/** The most single entries the widget can be hidden on. */
	public const WIDGET_EXCLUDE_IDS_MAX = 200;

	/**
	 * Where the widget is hidden while widget_visibility is 'exclude', normalised.
	 *
	 * Entry IDs are merged from exclude_ids, exclude_pages and widget_exclude_ids (the key an
	 * earlier renderer read), so a choice stored under any of them still applies.
	 *
	 * @param array<string, mixed> $widget
	 * @return array{ids: array<int, int>, types: array<int, string>, cats: array<int, int>}
	 */
	public static function widget_exclusions( array $widget ): array {
		$ids = array();
		foreach ( array( 'exclude_ids', 'exclude_pages', 'widget_exclude_ids' ) as $key ) {
			foreach ( (array) ( $widget[ $key ] ?? array() ) as $id ) {
				$id = self::positive_id( $id );
				if ( $id > 0 ) {
					$ids[ $id ] = $id;
				}
			}
		}

		$types = array();
		foreach ( (array) ( $widget['exclude_types'] ?? array() ) as $type ) {
			$type = is_scalar( $type ) ? sanitize_key( (string) $type ) : '';
			if ( '' !== $type ) {
				$types[ $type ] = $type;
			}
		}

		$cats = array();
		foreach ( (array) ( $widget['exclude_cats'] ?? array() ) as $cat ) {
			$cat = self::positive_id( $cat );
			if ( $cat > 0 ) {
				$cats[ $cat ] = $cat;
			}
		}

		return array(
			'ids'   => array_slice( array_values( $ids ), 0, self::WIDGET_EXCLUDE_IDS_MAX ),
			'types' => array_values( $types ),
			'cats'  => array_values( $cats ),
		);
	}

	/**
	 * A stored or posted ID as a positive integer, or 0. Unlike absint(), -3 stays invalid
	 * instead of becoming 3, and an array never casts to 1.
	 *
	 * @param mixed $value
	 */
	public static function positive_id( $value ): int {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return 0;
		}
		return max( 0, (int) $value );
	}

	/**
	 * The visitor-facing default copy, in the current language.
	 *
	 * Keys match widget_defaults(), which keeps these strings in English.
	 *
	 * @return array{title:string, subtitle:string, greeting:string, conversation_notice:string}
	 */
	public static function widget_default_copy(): array {
		return array(
			'title'               => __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
			'subtitle'            => __( 'AI Assistant', 'ai-site-assistant-autopilot' ),
			'greeting'            => __( 'Hi! How can I help you?', 'ai-site-assistant-autopilot' ),
			'conversation_notice' => __( 'This conversation may be recorded to help our support team.', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * The widget's header, greeting and recording notice as visitors should read them.
	 *
	 * The shipped defaults are stored in English, because Settings::update() writes them on every
	 * save. A stored value equal to its default was therefore never written by anyone, so it is
	 * shown in the current language. Anything the site owner typed comes back exactly as saved.
	 *
	 * @param array<string, mixed> $widget
	 * @return array{title:string, subtitle:string, greeting:string, conversation_notice:string}
	 */
	public static function widget_copy( array $widget ): array {
		$defaults = self::widget_defaults();
		$copy     = self::widget_default_copy();
		foreach ( $copy as $key => $translated ) {
			$stored = $widget[ $key ] ?? $defaults[ $key ];
			$stored = is_scalar( $stored ) ? (string) $stored : '';
			if ( $stored !== $defaults[ $key ] ) {
				$copy[ $key ] = $stored;
			}
		}
		return $copy;
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

		foreach ( self::HANDOFF_CHANNELS as $channel ) {
			if ( ! empty( $widget[ 'handoff_' . $channel . '_enabled' ] ) && self::handoff_channel_ready( $channel, $widget ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a handoff channel has everything it needs to deliver, switched on or not.
	 * The one rule the widget, the settings page and its REST endpoint all use.
	 *
	 * @param array<string, mixed> $widget Widget settings.
	 */
	public static function handoff_channel_ready( string $channel, array $widget ): bool {
		$value = static fn( string $key ): string => trim( (string) ( $widget[ $key ] ?? '' ) );
		switch ( $channel ) {
			case 'email':
				$email = sanitize_email( (string) ( $widget['handoff_email_to'] ?? '' ) );
				$email = '' !== $email ? $email : sanitize_email( (string) get_option( 'admin_email', '' ) );
				return '' !== $email && (bool) is_email( $email );
			case 'telegram':
				return self::has_handoff_secret( 'handoff_telegram_token' ) && '' !== $value( 'handoff_telegram_chat' );
			case 'twilio':
				return self::has_handoff_secret( 'handoff_twilio_sid' )
					&& self::has_handoff_secret( 'handoff_twilio_token' )
					&& '' !== $value( 'handoff_twilio_from' )
					&& '' !== $value( 'handoff_twilio_to' );
			case 'whatsapp':
				return self::has_handoff_secret( 'handoff_whatsapp_token' )
					&& '' !== $value( 'handoff_whatsapp_phone_id' )
					&& '' !== $value( 'handoff_whatsapp_to' );
			case 'slack':
				return \AISAAutoPilot\Support\HandoffSlack::is_webhook_url( self::get_handoff_secret( 'handoff_slack_webhook' ) );
			case 'signal':
				return \AISAAutoPilot\Support\HandoffSignal::is_server_url( $value( 'handoff_signal_url' ) )
					&& \AISAAutoPilot\Support\HandoffSignal::is_number( $value( 'handoff_signal_from' ) )
					&& \AISAAutoPilot\Support\HandoffSignal::is_recipient( $value( 'handoff_signal_to' ) );
		}

		return false;
	}

	/**
	 * The contact details the handoff card asks for, each 'off', 'optional' or 'required'. A missing or
	 * unknown value is 'off', so nothing is collected unless the site owner chose it.
	 *
	 * @return array<string,string>
	 */
	public static function handoff_fields(): array {
		$widget = (array) self::get( 'widget', array() );
		$stored = isset( $widget['handoff_fields'] ) && is_array( $widget['handoff_fields'] ) ? $widget['handoff_fields'] : array();
		$fields = array();
		foreach ( self::HANDOFF_FIELD_KEYS as $key ) {
			$mode           = (string) ( $stored[ $key ] ?? 'off' );
			$fields[ $key ] = in_array( $mode, self::HANDOFF_FIELD_MODES, true ) ? $mode : 'off';
		}
		return $fields;
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
	 * One-time move of stored widget values off defaults that nobody chose.
	 *
	 * Only the exact legacy values in WIDGET_LEGACY_DEFAULTS move, only for keys that had no admin
	 * control before revision 2, and only while the stored widget carries no revision marker.
	 * Values a site owner can set on a screen, such as voice_auto and panel_radius, are never
	 * touched. Sites that never saved settings read the new defaults directly.
	 */
	public static function migrate_widget_defaults(): void {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || empty( $settings['widget'] ) || ! is_array( $settings['widget'] ) ) {
			return;
		}

		$widget   = $settings['widget'];
		$revision = $widget['defaults_revision'] ?? 0;
		if ( is_numeric( $revision ) && (int) $revision >= self::WIDGET_DEFAULTS_REVISION ) {
			return;
		}

		foreach ( self::WIDGET_LEGACY_DEFAULTS as $key => $move ) {
			if ( isset( $widget[ $key ] ) && is_numeric( $widget[ $key ] ) && (int) $widget[ $key ] === $move[0] ) {
				$widget[ $key ] = $move[1];
			}
		}
		$widget['defaults_revision'] = self::WIDGET_DEFAULTS_REVISION;
		$settings['widget']          = $widget;
		update_option( self::OPTION, $settings, false );
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
