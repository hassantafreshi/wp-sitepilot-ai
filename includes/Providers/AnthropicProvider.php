<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

/*
 * Exception messages here are never echoed: they travel back through the REST
 * layer as JSON, or are rendered by a caller with esc_html(). Escaping them at
 * the throw site would put HTML entities into the JSON payload, so the sniff is
 * disabled for this file rather than silenced line by line.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use AISAAutoPilot\Usage\UsageTracker;

/**
 * Talks to Anthropic's native Messages API.
 *
 * Anthropic does not offer an OpenAI-compatible Chat Completions endpoint, so
 * Claude gets its own transport rather than being forced through a shim: the
 * key travels in `x-api-key`, the system prompt is a top-level field, and
 * `max_tokens` is mandatory.
 */
final class AnthropicProvider implements ProviderInterface, ModelCatalogInterface {

	use SafeHttp;

	/** The dated API contract this class is written against. */
	private const API_VERSION = '2023-06-01';

	/** Anthropic requires an explicit output ceiling on every request. */
	private const MAX_TOKENS = 8192;

	/**
	 * Model families that still accept sampling parameters.
	 *
	 * Claude Opus 5.5, Opus 5, Opus 4.8, Opus 4.7, Sonnet 5 and Fable 5/5.1
	 * reject `temperature` with a 400, and Sonnet 5.5 and Haiku 5.5 reject any
	 * non-default value, so it is omitted unless the chosen model is
	 * known to take it. Omitting it is always valid, which makes that the safe
	 * default for a model name this list has never seen.
	 *
	 * @var string[]
	 */
	private const ACCEPTS_TEMPERATURE = array(
		'claude-opus-4-6',
		'claude-opus-4-5',
		'claude-sonnet-4-6',
		'claude-sonnet-4-5',
		'claude-haiku-4-5',
		'claude-haiku-3',
		'claude-3',
	);

	private string $base_url;
	private string $model;
	private string $api_key;
	private float $temperature;
	private int $timeout;
	private string $embedding_base_url;
	private string $embedding_model;
	private string $embedding_api_key;

	public function __construct(
		string $base_url,
		string $model,
		string $api_key,
		float $temperature = 0.2,
		int $timeout = 45,
		string $embedding_base_url = '',
		string $embedding_model = '',
		string $embedding_api_key = ''
	) {
		$this->base_url    = rtrim( trim( $base_url ), '/' );
		$this->model       = trim( $model );
		$this->api_key     = trim( $api_key );
		$this->temperature = $temperature;
		$this->timeout     = $timeout;

		// Claude has no /embeddings route, so semantic search has to point at a
		// second, OpenAI-compatible provider. Nothing is defaulted from the
		// Anthropic endpoint here — it would only produce a confusing 404.
		$this->embedding_base_url = rtrim( trim( $embedding_base_url ), '/' );
		$this->embedding_model    = trim( $embedding_model );
		$this->embedding_api_key  = trim( $embedding_api_key );
	}

	public function generate_structured_output( string $system_prompt, string $user_prompt ): array {
		// The Messages API has no `response_format`, so the JSON-only contract is
		// stated in the system prompt and enforced by the shared parser. Kept in
		// English and out of the translation catalogue, like the rest of the
		// plugin's prompts: this is model input, not user-facing copy.
		$system = $system_prompt . "\n\nReply with a single raw JSON object and nothing else. Do not wrap it in Markdown code fences and do not add commentary.";

		$result = $this->request_messages( $system, $user_prompt );
		$this->track_usage( $result['usage'], 'structured_output' );

		return OpenAiCompatibleProvider::extract_json( $result['content'] );
	}

	public function generate_text( string $system_prompt, string $user_prompt ): array {
		$result = $this->request_messages( $system_prompt, $user_prompt );
		$this->track_usage( $result['usage'], 'text_generation' );

		return $result;
	}

	public function generate_embedding( string $text ): array {
		if ( '' === $this->embedding_base_url || '' === $this->embedding_api_key ) {
			throw new ProviderException(
				'embeddings_unsupported',
				__( 'Anthropic does not provide embeddings. Set an embedding base URL, model and key (for example OpenAI) in Settings to use semantic search.', 'ai-site-assistant-autopilot' )
			);
		}

		return $this->embedding_provider()->generate_embedding( $text );
	}

	public function embedding_model(): string {
		return '' !== $this->embedding_model ? $this->embedding_model : 'text-embedding-3-small';
	}

	public function health_check(): array {
		if ( '' === $this->api_key ) {
			return array(
				'ok'      => false,
				'code'    => 'no_api_key',
				'message' => __( 'No API key is set.', 'ai-site-assistant-autopilot' ),
			);
		}
		if ( '' === $this->base_url || '' === $this->model ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_config',
				'message' => __( 'Base URL and model are required.', 'ai-site-assistant-autopilot' ),
			);
		}

		try {
			$this->request_messages( '', 'ping', 16 );

			return OpenAiCompatibleProvider::connected();
		} catch ( ProviderException $e ) {
			$message = $e->getMessage();
			$code    = $e->error_code();

			// Claude Opus 5 and Fable 5 always think, so a 16-token ping can end at
			// max_tokens with no text. A 200 still proves the key and the model.
			if ( 'provider_empty_response' === $code ) {
				return OpenAiCompatibleProvider::connected();
			}

			if ( 'provider_unreachable' === $code ) {
				$message = sprintf(
					/* translators: 1: original error message, 2: base URL */
					__( 'Could not reach the AI provider (%1$s). Check the base URL (%2$s) and your network connection.', 'ai-site-assistant-autopilot' ),
					$message,
					$this->base_url
				);
			}

			return array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			);
		}
	}

	/**
	 * The models this key can use, newest first as Anthropic returns them.
	 *
	 * @return string[]
	 * @throws ProviderException
	 */
	public function list_models(): array {
		$response = $this->remote_json( 'GET', self::join_endpoint( $this->base_url, '/models' ), $this->headers(), null, min( 20, $this->timeout ) );

		$decoded = $this->decode( $response );
		$rows    = isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();

		return OpenAiCompatibleProvider::model_ids( $rows );
	}

	// ── Transport ───────────────────────────────────────────────

	/**
	 * One Messages API round trip.
	 *
	 * @return array{content:string, usage:array{prompt_tokens:int, completion_tokens:int, total_tokens:int}}
	 * @throws ProviderException
	 */
	private function request_messages( string $system_prompt, string $user_prompt, ?int $max_tokens = null ): array {
		$body = array(
			'model'      => $this->model,
			'max_tokens' => null === $max_tokens ? self::MAX_TOKENS : $max_tokens,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		if ( '' !== trim( $system_prompt ) ) {
			$body['system'] = $system_prompt;
		}
		if ( $this->accepts_temperature() ) {
			$body['temperature'] = $this->temperature;
		}

		$endpoint = self::join_endpoint( $this->base_url, '/messages' );
		$response = $this->remote_json( 'POST', $endpoint, $this->headers(), $body, $this->timeout );
		$decoded  = $this->decode( $response );

		// A safety classifier can decline a request with HTTP 200 and no content,
		// so stop_reason has to be read before the content blocks.
		if ( 'refusal' === ( $decoded['stop_reason'] ?? '' ) ) {
			throw new ProviderException( 'provider_refused', __( 'Anthropic declined this request. Please rephrase it and try again.', 'ai-site-assistant-autopilot' ) );
		}

		$content = self::text_from_blocks( $decoded['content'] ?? array() );
		if ( '' === $content ) {
			throw new ProviderException( 'provider_empty_response', __( 'The AI provider returned an empty response.', 'ai-site-assistant-autopilot' ) );
		}

		$prompt_tokens     = (int) ( $decoded['usage']['input_tokens'] ?? 0 );
		$completion_tokens = (int) ( $decoded['usage']['output_tokens'] ?? 0 );
		if ( 0 === $prompt_tokens && 0 === $completion_tokens ) {
			$prompt_tokens     = (int) ceil( mb_strlen( $user_prompt ) / 4 );
			$completion_tokens = (int) ceil( mb_strlen( $content ) / 4 );
		}

		return array(
			'content' => $content,
			'usage'   => array(
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens'      => $prompt_tokens + $completion_tokens,
			),
		);
	}

	/**
	 * Turn a transport result into a decoded body, or throw the right error.
	 *
	 * @param array<string, mixed>|\WP_Error $response
	 * @return array<string, mixed>
	 * @throws ProviderException
	 */
	private function decode( $response ): array {
		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			throw new ProviderException(
				'provider_unreachable',
				sprintf(
				/* translators: 1: error details */
					__( 'Could not reach the AI provider (%1$s). Check the base URL and your connection.', 'ai-site-assistant-autopilot' ),
					$error_msg
				)
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The AI provider rejected your API key.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 402 === $code ) {
			throw new ProviderException( 'provider_payment_required', __( 'The AI provider requires an active billing account or available credit.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 404 === $code ) {
			throw new ProviderException( 'model_not_found', __( 'The selected endpoint or model was not found. Check the base URL and model name.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 429 === $code ) {
			throw new ProviderException( 'provider_rate_limited', __( 'The AI provider is rate-limiting requests. Please try again shortly.', 'ai-site-assistant-autopilot' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( 'provider_error', sprintf( /* translators: %d: HTTP status code. */ __( 'The AI provider returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $code ) );
		}

		return $decoded;
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array(
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::API_VERSION,
			'Content-Type'      => 'application/json',
		);
	}

	/**
	 * Concatenate the text blocks of a Messages response, skipping thinking and
	 * tool blocks that carry no user-visible text.
	 *
	 * @param mixed $blocks
	 */
	private static function text_from_blocks( $blocks ): string {
		if ( ! is_array( $blocks ) ) {
			return '';
		}

		$parts = array();
		foreach ( $blocks as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && is_string( $block['text'] ?? null ) ) {
				$parts[] = $block['text'];
			}
		}

		return trim( implode( '', $parts ) );
	}

	private function accepts_temperature(): bool {
		foreach ( self::ACCEPTS_TEMPERATURE as $family ) {
			if ( str_starts_with( $this->model, $family ) ) {
				return true;
			}
		}

		return false;
	}

	private function embedding_provider(): OpenAiCompatibleProvider {
		return new OpenAiCompatibleProvider(
			$this->embedding_base_url,
			'',
			$this->embedding_api_key,
			$this->temperature,
			$this->timeout,
			$this->embedding_base_url,
			$this->embedding_model,
			$this->embedding_api_key
		);
	}

	/**
	 * @param array{prompt_tokens:int, completion_tokens:int, total_tokens:int} $usage
	 */
	private function track_usage( array $usage, string $context ): void {
		try {
			( new UsageTracker() )->record( $this->model, $usage['prompt_tokens'], $usage['completion_tokens'], $context );
		} catch ( \Throwable $e ) {
			// Usage tracking must never break the main flow.
		}
	}
}
