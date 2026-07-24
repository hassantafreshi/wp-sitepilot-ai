<?php
declare(strict_types=1);

namespace SitePilot\Providers;

use SitePilot\Usage\UsageTracker;

/**
 * Talks directly to any OpenAI-compatible Chat Completions API
 * (DeepSeek, OpenAI, OpenRouter, Groq, Qwen, clusy.io, custom base URL).
 */
final class OpenAiCompatibleProvider implements ProviderInterface {

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
			throw new ProviderException( 'empty_input', __( 'Cannot embed empty text.', 'sitepilot-ai' ) );
		}

		$endpoint = $this->embedding_base_url . '/embeddings';
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->embedding_api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model' => $this->embedding_model,
					'input' => $text,
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'provider_unreachable', __( 'Could not reach the embedding endpoint. Check the base URL and your connection.', 'sitepilot-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The embedding provider rejected your API key.', 'sitepilot-ai' ) );
		}
		if ( 404 === $code ) {
			throw new ProviderException( 'embeddings_unsupported', __( 'This provider does not offer an embeddings endpoint. Set an embedding provider that does (e.g. OpenAI).', 'sitepilot-ai' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( 'provider_error', sprintf( /* translators: %d: HTTP status code. */ __( 'The embedding provider returned an error (HTTP %d).', 'sitepilot-ai' ), $code ) );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$vector  = $decoded['data'][0]['embedding'] ?? null;

		if ( ! is_array( $vector ) || empty( $vector ) ) {
			throw new ProviderException( 'provider_empty_response', __( 'The embedding provider returned no vector.', 'sitepilot-ai' ) );
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
				'message' => __( 'No API key is set.', 'sitepilot-ai' ),
			);
		}
		if ( '' === $this->base_url || '' === $this->model ) {
			return array(
				'ok'      => false,
				'message' => __( 'Base URL and model are required.', 'sitepilot-ai' ),
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
				'message' => __( 'Connection successful.', 'sitepilot-ai' ),
			);
		} catch ( ProviderException $e ) {
			return array(
				'ok'      => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Perform the HTTP call and return content + usage.
	 *
	 * @param array<string, mixed> $body
	 * @return array{content:string, usage:array{prompt_tokens:int, completion_tokens:int, total_tokens:int}}
	 * @throws ProviderException
	 */
	private function request_chat( array $body ): array {
		$endpoint = $this->base_url . '/chat/completions';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'provider_unreachable', __( 'Could not reach the AI provider. Check the base URL and your connection.', 'sitepilot-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( 401 === $code || 403 === $code ) {
			throw new ProviderException( 'provider_unauthorized', __( 'The AI provider rejected your API key.', 'sitepilot-ai' ) );
		}
		if ( 429 === $code ) {
			throw new ProviderException( 'provider_rate_limited', __( 'The AI provider is rate-limiting requests. Please try again shortly.', 'sitepilot-ai' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( 'provider_error', sprintf( /* translators: %d: HTTP status code. */ __( 'The AI provider returned an error (HTTP %d).', 'sitepilot-ai' ), $code ) );
		}

		$decoded = json_decode( $raw, true );
		$content = $decoded['choices'][0]['message']['content'] ?? null;

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException( 'provider_empty_response', __( 'The AI provider returned an empty response.', 'sitepilot-ai' ) );
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
			throw new ProviderException( 'invalid_ai_output', __( 'The AI did not return a valid structured result. Please simplify your request and try again.', 'sitepilot-ai' ) );
		}

		return $decoded;
	}
}
