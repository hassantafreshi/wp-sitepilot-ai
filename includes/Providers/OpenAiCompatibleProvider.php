<?php
declare(strict_types=1);

namespace SitePilot\Providers;

/**
 * Talks directly to any OpenAI-compatible Chat Completions API
 * (DeepSeek, OpenAI, OpenRouter, Groq, cloud.ai, custom base URL).
 */
final class OpenAiCompatibleProvider implements ProviderInterface {

	private string $base_url;
	private string $model;
	private string $api_key;
	private float $temperature;
	private int $timeout;

	public function __construct( string $base_url, string $model, string $api_key, float $temperature = 0.2, int $timeout = 45 ) {
		$this->base_url    = rtrim( trim( $base_url ), '/' );
		$this->model       = trim( $model );
		$this->api_key     = trim( $api_key );
		$this->temperature = $temperature;
		$this->timeout     = $timeout;
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

		$content = $this->request_chat( $body );

		return self::extract_json( $content );
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
	 * Perform the HTTP call and return the assistant message content string.
	 *
	 * @param array<string, mixed> $body
	 * @throws ProviderException
	 */
	private function request_chat( array $body ): string {
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

		return $content;
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
