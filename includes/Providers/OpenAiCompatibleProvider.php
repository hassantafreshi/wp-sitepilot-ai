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
 * Talks directly to any OpenAI-compatible Chat Completions API
 * (OpenAI, Gemini, DeepSeek, Qwen, Mistral, Groq, Perplexity, Azure OpenAI,
 * OpenRouter and the aggregator gateways, or a custom base URL).
 */
final class OpenAiCompatibleProvider implements ProviderInterface, ModelCatalogInterface {

	use SafeHttp;

	private string $base_url;
	private string $model;
	private string $api_key;
	private float $temperature;
	private int $timeout;
	private string $embedding_base_url;
	private string $embedding_model;
	private string $embedding_api_key;
	private string $auth;

	/**
	 * @param string $auth Which header carries the key: `bearer` (almost
	 *                     everyone), `api-key` (Azure OpenAI), or `x-api-key`.
	 */
	public function __construct(
		string $base_url,
		string $model,
		string $api_key,
		float $temperature = 0.2,
		int $timeout = 45,
		string $embedding_base_url = '',
		string $embedding_model = '',
		string $embedding_api_key = '',
		string $auth = 'bearer'
	) {
		$this->base_url    = rtrim( trim( $base_url ), '/' );
		$this->model       = trim( $model );
		$this->api_key     = trim( $api_key );
		$this->temperature = $temperature;
		$this->timeout     = $timeout;
		$this->auth        = in_array( $auth, array( 'bearer', 'api-key', 'x-api-key' ), true ) ? $auth : 'bearer';

		// Embeddings can point at a different endpoint/model/key; default to the chat provider's.
		$this->embedding_base_url = '' !== trim( $embedding_base_url ) ? rtrim( trim( $embedding_base_url ), '/' ) : $this->base_url;
		$this->embedding_model    = '' !== trim( $embedding_model ) ? trim( $embedding_model ) : 'text-embedding-3-small';
		$this->embedding_api_key  = '' !== trim( $embedding_api_key ) ? trim( $embedding_api_key ) : $this->api_key;
	}

	public function generate_structured_output( string $system_prompt, string $user_prompt ): array {
		$body = array(
			'model'           => $this->model,
			'temperature'     => $this->temperature,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		$result  = $this->request_chat( $body );
		$content = $result['content'];

		// Track usage.
		$this->track_usage( $result['usage'], 'structured_output' );

		return self::extract_json( $content );
	}

	public function generate_text( string $system_prompt, string $user_prompt ): array {
		$body = array(
			'model'       => $this->model,
			'temperature' => $this->temperature,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		$result = $this->request_chat( $body );

		// Track usage.
		$this->track_usage( $result['usage'], 'text_generation' );

		return $result;
	}

	public function generate_embedding( string $text ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			throw new ProviderException( 'empty_input', __( 'Cannot embed empty text.', 'ai-site-assistant-autopilot' ) );
		}

		$endpoint = self::join_endpoint( $this->embedding_base_url, '/embeddings' );
		$response = $this->post_json(
			$endpoint,
			$this->auth_headers( $this->embedding_api_key ),
			array(
				'model' => $this->embedding_model,
				'input' => $text,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			throw new ProviderException(
				'provider_unreachable',
				sprintf(
				/* translators: 1: error details */
					__( 'Could not reach the embedding endpoint (%1$s). Check the base URL and your connection.', 'ai-site-assistant-autopilot' ),
					$error_msg
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The embedding provider rejected your API key.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 404 === $code ) {
			throw new ProviderException( 'embeddings_unsupported', __( 'This provider does not offer an embeddings endpoint. Set an embedding provider that does (e.g. OpenAI).', 'ai-site-assistant-autopilot' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( 'provider_error', sprintf( /* translators: %d: HTTP status code. */ __( 'The embedding provider returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $code ) );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$vector  = $decoded['data'][0]['embedding'] ?? null;

		if ( ! is_array( $vector ) || empty( $vector ) ) {
			throw new ProviderException( 'provider_empty_response', __( 'The embedding provider returned no vector.', 'ai-site-assistant-autopilot' ) );
		}

		$vector = array_map( 'floatval', $vector );

		$usage = array(
			'prompt_tokens' => (int) ( $decoded['usage']['prompt_tokens'] ?? 0 ),
			'total_tokens'  => (int) ( $decoded['usage']['total_tokens'] ?? 0 ),
		);
		if ( 0 === $usage['total_tokens'] ) {
			$usage['prompt_tokens'] = (int) ceil( mb_strlen( $text ) / 4 );
			$usage['total_tokens']  = $usage['prompt_tokens'];
		}

		$this->track_usage(
			array(
				'prompt_tokens'     => $usage['prompt_tokens'],
				'completion_tokens' => 0,
				'total_tokens'      => $usage['total_tokens'],
			),
			'embedding'
		);

		return array(
			'embedding' => $vector,
			'usage'     => $usage,
		);
	}

	public function embedding_model(): string {
		return $this->embedding_model;
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
			$body = array(
				'model'      => $this->model,
				'max_tokens' => 5,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'ping',
					),
				),
			);
			$this->request_chat( $body );

			return array(
				'ok'      => true,
				'code'    => 'connected',
				'message' => __( 'Connection successful.', 'ai-site-assistant-autopilot' ),
			);
		} catch ( ProviderException $e ) {
			$message = $e->getMessage();
			$code    = $e->error_code();

			if ( 'provider_unreachable' === $code ) {
				$message = sprintf(
					/* translators: 1: original error message, 2: base URL */
					__( 'Could not reach the AI provider (%1$s). Check the base URL (%2$s) and your network connection.', 'ai-site-assistant-autopilot' ),
					$message,
					$this->base_url
				);
			} elseif ( 'model_not_found' === $code ) {
				$message = sprintf(
					/* translators: 1: provider error message, 2: model name */
					__( 'The model "%2$s" was not found or is not available on this provider: %1$s', 'ai-site-assistant-autopilot' ),
					$message,
					$this->model
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
	 * Ask the provider which models the key can actually use.
	 *
	 * Every OpenAI-compatible endpoint answers `GET {base}/models`, so the
	 * Settings screen can refresh its dropdown instead of relying on a list that
	 * was accurate the day the plugin shipped.
	 *
	 * @return string[] Model identifiers, sorted.
	 * @throws ProviderException
	 */
	public function list_models(): array {
		$response = $this->get_json( self::join_endpoint( $this->base_url, '/models' ) );

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

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The AI provider rejected your API key.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 404 === $code ) {
			throw new ProviderException( 'models_unsupported', __( 'This provider does not publish a model list. Type the model name instead.', 'ai-site-assistant-autopilot' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( 'provider_error', sprintf( /* translators: %d: HTTP status code. */ __( 'The AI provider returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $code ) );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$rows    = is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();

		return self::model_ids( $rows );
	}

	/**
	 * Pull usable identifiers out of a /models payload.
	 *
	 * Providers disagree on the field name (`id` everywhere, `name` on Google's
	 * compatibility layer) and some prefix the value with `models/`.
	 *
	 * @param array<int, mixed> $rows
	 * @return string[]
	 */
	public static function model_ids( array $rows ): array {
		$ids = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (string) ( $row['id'] ?? $row['name'] ?? '' );
			$id = trim( preg_replace( '#^models/#', '', $id ) ?? '' );
			if ( '' === $id || strlen( $id ) > 128 ) {
				continue;
			}
			$ids[ $id ] = true;
		}

		$ids = array_keys( $ids );
		sort( $ids, SORT_NATURAL | SORT_FLAG_CASE );

		return $ids;
	}

	/**
	 * The key header for this provider. Azure OpenAI expects `api-key`; a Bearer
	 * header there means an Entra ID token, not an API key.
	 *
	 * @return array<string, string>
	 */
	private function auth_headers( string $key ): array {
		$headers = array( 'Content-Type' => 'application/json' );

		if ( 'api-key' === $this->auth ) {
			$headers['api-key'] = $key;
		} elseif ( 'x-api-key' === $this->auth ) {
			$headers['x-api-key'] = $key;
		} else {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		return $headers;
	}

	/**
	 * Perform the HTTP call and return content + usage.
	 *
	 * @param array<string, mixed> $body
	 * @return array{content:string, usage:array{prompt_tokens:int, completion_tokens:int, total_tokens:int}}
	 * @throws ProviderException
	 */
	private function request_chat( array $body ): array {
		$endpoint = self::join_endpoint( $this->base_url, '/chat/completions' );

		$response = $this->post_json(
			$endpoint,
			$this->auth_headers( $this->api_key ),
			$body
		);

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
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		// Reasoning models (OpenAI GPT-5 and GPT-6, o-series and their gateway
		// copies) accept only their default temperature and answer 400 to any
		// other value. Drop it once and resend instead of failing every call.
		if ( 400 === $code && array_key_exists( 'temperature', $body ) && self::rejects_parameter( $decoded, 'temperature' ) ) {
			unset( $body['temperature'] );

			return $this->request_chat( $body );
		}

		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The AI provider rejected your API key.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 404 === $code ) {
			$provider_msg = $decoded['error']['message'] ?? '';
			$message      = '' !== $provider_msg
				? sprintf(
					/* translators: 1: provider error message */
					__( 'Model not found: %1$s', 'ai-site-assistant-autopilot' ),
					$provider_msg
				)
				: __( 'The selected model was not found. Check the model name or pick a different one.', 'ai-site-assistant-autopilot' );
			throw new ProviderException( 'model_not_found', $message );
		}
		if ( 402 === $code ) {
			throw new ProviderException( 'provider_payment_required', __( 'The AI provider requires an active billing account or available credit.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 429 === $code ) {
			throw new ProviderException( 'provider_rate_limited', __( 'The AI provider is rate-limiting requests. Please try again shortly.', 'ai-site-assistant-autopilot' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			$provider_msg = $decoded['error']['message'] ?? '';
			$message      = '' !== $provider_msg
				? sprintf(
					/* translators: 1: HTTP status code, 2: provider error message */
					__( 'The AI provider returned an error (HTTP %1$d): %2$s', 'ai-site-assistant-autopilot' ),
					$code,
					$provider_msg
				)
				: sprintf( /* translators: %d: HTTP status code. */ __( 'The AI provider returned an error (HTTP %d).', 'ai-site-assistant-autopilot' ), $code );
			throw new ProviderException( 'provider_error', $message );
		}

		$content = $decoded['choices'][0]['message']['content'] ?? null;

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException( 'provider_empty_response', __( 'The AI provider returned an empty response.', 'ai-site-assistant-autopilot' ) );
		}

		// Extract usage from the API response.
		$usage = array(
			'prompt_tokens'     => (int) ( $decoded['usage']['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $decoded['usage']['completion_tokens'] ?? 0 ),
			'total_tokens'      => (int) ( $decoded['usage']['total_tokens'] ?? 0 ),
		);

		// Fallback: estimate if the provider didn't return usage.
		if ( 0 === $usage['total_tokens'] ) {
			$usage['prompt_tokens']     = (int) ceil( mb_strlen( $body['messages'][1]['content'] ?? '' ) / 4 );
			$usage['completion_tokens'] = (int) ceil( mb_strlen( $content ) / 4 );
			$usage['total_tokens']      = $usage['prompt_tokens'] + $usage['completion_tokens'];
		}

		return array(
			'content' => $content,
			'usage'   => $usage,
		);
	}

	/**
	 * Record token usage via the UsageTracker.
	 *
	 * @param array{prompt_tokens:int, completion_tokens:int, total_tokens:int} $usage
	 */
	private function track_usage( array $usage, string $context ): void {
		try {
			$tracker = new UsageTracker();
			$tracker->record(
				$this->model,
				$usage['prompt_tokens'],
				$usage['completion_tokens'],
				$context
			);
		} catch ( \Throwable $e ) {
			// Usage tracking must never break the main flow.
		}
	}

	/**
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $body
	 * @return array|\WP_Error
	 */
	private function post_json( string $endpoint, array $headers, array $body ) {
		return $this->remote_json( 'POST', $endpoint, $headers, $body, $this->timeout );
	}

	/**
	 * Read-only /models call.
	 *
	 * @return array|\WP_Error
	 */
	private function get_json( string $endpoint ) {
		return $this->remote_json( 'GET', $endpoint, $this->auth_headers( $this->api_key ), null, min( 20, $this->timeout ) );
	}

	/**
	 * Whether a 400 body says the model refuses one request parameter.
	 *
	 * OpenAI names it in `error.param`; gateways often only say so in the
	 * message ("Unsupported value: 'temperature' does not support 0.2…"). A
	 * message that merely mentions the parameter is not enough: it must also
	 * say the value or parameter is unsupported.
	 *
	 * @param array<string, mixed> $decoded Decoded error body.
	 */
	public static function rejects_parameter( array $decoded, string $param ): bool {
		$error = $decoded['error'] ?? array();
		if ( is_array( $error ) && (string) ( $error['param'] ?? '' ) === $param ) {
			return true;
		}

		$message = is_array( $error ) ? (string) ( $error['message'] ?? '' ) : ( is_string( $error ) ? $error : '' );
		if ( '' === $message ) {
			$message = is_string( $decoded['message'] ?? null ) ? $decoded['message'] : '';
		}

		return '' !== $message
			&& false !== stripos( $message, $param )
			&& 1 === preg_match( '/unsupported|not supported|does not support|only the default/i', $message );
	}

	/**
	 * Extract a JSON object from raw model content, tolerating code fences and
	 * surrounding prose. Pure function — unit tested without WordPress.
	 *
	 * @return array<string, mixed>
	 * @throws ProviderException
	 */
	public static function extract_json( string $content ): array {
		$text = trim( $content );

		// Strip Markdown code fences if present.
		if ( str_starts_with( $text, '```' ) ) {
			$text = (string) preg_replace( '/^```[a-zA-Z]*\s*/', '', $text );
			$text = (string) preg_replace( '/\s*```$/', '', $text );
			$text = trim( $text );
		}

		$decoded = json_decode( $text, true );

		if ( ! is_array( $decoded ) ) {
			// Fallback: grab the outermost { ... } span.
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			}
		}

		if ( ! is_array( $decoded ) ) {
			throw new ProviderException( 'invalid_ai_output', __( 'The AI did not return a valid structured result. Please simplify your request and try again.', 'ai-site-assistant-autopilot' ) );
		}

		return $decoded;
	}
}
