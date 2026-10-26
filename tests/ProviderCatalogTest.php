<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\AnthropicProvider;
use AISAAutoPilot\Providers\OpenAiCompatibleProvider;
use AISAAutoPilot\Providers\ProviderException;
use PHPUnit\Framework\TestCase;

/**
 * The provider catalogue (T75) and the request quirks it relies on.
 */
final class ProviderCatalogTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['aisaautopilot_test_http_queue'], $GLOBALS['aisaautopilot_test_http_requests'] );
	}

	public function test_every_preset_is_well_formed(): void {
		foreach ( Settings::presets() as $key => $preset ) {
			$this->assertNotSame( '', (string) $preset['label'], $key );
			$this->assertContains( $preset['api'], array( 'openai', 'anthropic' ), $key );
			$this->assertContains( $preset['auth'], array( 'bearer', 'x-api-key', 'api-key' ), $key );
			$this->assertContains( $preset['group'], array( 'direct', 'gateway', 'custom' ), $key );

			if ( 'custom' !== $key ) {
				$this->assertStringStartsWith( 'https://', (string) $preset['base_url'], $key );
				$this->assertStringEndsNotWith( '/', (string) $preset['base_url'], $key );
			}
			if ( '' !== (string) $preset['key_url'] ) {
				$this->assertStringStartsWith( 'https://', (string) $preset['key_url'], $key );
			}

			// A default model must be one the dropdown actually offers.
			$models = (array) $preset['models'];
			if ( '' !== (string) $preset['model'] && array() !== $models ) {
				$this->assertArrayHasKey( (string) $preset['model'], $models, $key . ' default model is not in its list' );
			}
		}
	}

	public function test_xai_grok_is_offered(): void {
		$presets = Settings::presets();
		$this->assertArrayHasKey( 'xai', $presets );

		$xai = Settings::preset( 'xai' );
		$this->assertSame( 'https://api.x.ai/v1', $xai['base_url'] );
		$this->assertSame( 'openai', $xai['api'] );
		$this->assertSame( 'bearer', $xai['auth'] );
		$this->assertSame( 'grok-4.3', $xai['model'] );
		$this->assertArrayHasKey( 'grok-4.7', $xai['models'] );
		$this->assertArrayHasKey( 'grok-4.20-0309-non-reasoning', $xai['models'] );
		// Grok has no embeddings endpoint.
		$this->assertSame( array(), $xai['embeddings'] );

		foreach ( array( 'cohere', 'zai', 'moonshot', 'minimax', 'cerebras', 'sambanova', 'together', 'fireworks', 'huggingface', 'nvidia' ) as $added ) {
			$this->assertArrayHasKey( $added, $presets, $added );
		}
		// Owner decision 2026-10-26: Perplexity's Chat Completions API is gone
		// and Clusy.io has no public API.
		$this->assertArrayNotHasKey( 'perplexity', $presets );
		$this->assertArrayNotHasKey( 'clusy', $presets );
		$this->assertCount( 24, $presets, 'twenty-three presets plus Custom, as the readme says' );
	}

	/**
	 * WordPress.org rejects a plugin that sends data to a service its readme
	 * does not name, so every preset needs its terms and privacy links there.
	 */
	public function test_readme_discloses_every_preset_with_terms_and_privacy_links(): void {
		$readme = (string) file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$start  = strpos( $readme, '== External services ==' );
		$this->assertNotFalse( $start );
		$section = substr( $readme, (int) $start, (int) strpos( $readme, '**Telegram', (int) $start ) - (int) $start );

		foreach ( Settings::presets() as $key => $preset ) {
			if ( 'custom' === $key ) {
				continue;
			}
			// "xAI (Grok)" is listed as "xAI Grok (terms, privacy)".
			$name = trim( (string) preg_replace( '/\s*\(.*\)$/', '', (string) $preset['label'] ) );
			$this->assertMatchesRegularExpression(
				'/' . preg_quote( $name, '/' ) . '[^()\n]{0,20}\(https:\/\/\S+,\s+https:\/\/\S+\)/',
				$section,
				$key . ' is missing from readme.txt External services'
			);
		}
	}

	public function test_retired_and_wrong_model_ids_are_not_offered(): void {
		$retired = array(
			'deepseek'  => array( 'deepseek-chat', 'deepseek-reasoner', 'deepseek-v4-flash' ),
			'groq'      => array( 'llama-3.1-8b-instant', 'llama-3.3-70b-versatile', 'meta-llama/llama-4-scout-17b-16e-instruct' ),
			// Responses-API only, or shut down.
			'openai'    => array( 'gpt-5.5-pro', 'gpt-5.3-chat-latest', 'gpt-4.1-nano' ),
			'gemini'    => array( 'gemini-3.7-flash', 'gemini-3.5-flash', 'gemini-2.5-pro' ),
			'anthropic' => array( 'claude-sonnet-4-5', 'claude-opus-4-1' ),
		);
		foreach ( $retired as $provider => $ids ) {
			$models = (array) Settings::preset( $provider )['models'];
			foreach ( $ids as $id ) {
				$this->assertArrayNotHasKey( $id, $models, $provider . ' still offers ' . $id );
			}
		}

		// Mistral API names are "-latest" aliases or the 3-5 form, never the
		// model-card page slugs (mistral-medium-3-5-26-04) the list used to carry.
		foreach ( array_keys( (array) Settings::preset( 'mistral' )['models'] ) as $id ) {
			$this->assertDoesNotMatchRegularExpression( '/-\d{2}-\d{2}$/', (string) $id );
		}
	}

	public function test_default_settings_point_at_a_listed_model(): void {
		$defaults = Settings::defaults();
		$preset   = Settings::preset( (string) $defaults['provider'] );

		$this->assertSame( $preset['base_url'], $defaults['base_url'] );
		$this->assertArrayHasKey( (string) $defaults['model'], (array) $preset['models'] );
	}

	public function test_rejects_parameter_reads_openai_and_gateway_errors(): void {
		$openai = array(
			'error' => array(
				'message' => "Unsupported value: 'temperature' does not support 0.2 with this model. Only the default (1) value is supported.",
				'param'   => 'temperature',
				'code'    => 'unsupported_value',
			),
		);
		$this->assertTrue( OpenAiCompatibleProvider::rejects_parameter( $openai, 'temperature' ) );

		$gateway = array( 'error' => array( 'message' => 'temperature is not supported for this model' ) );
		$this->assertTrue( OpenAiCompatibleProvider::rejects_parameter( $gateway, 'temperature' ) );

		$flat = array( 'message' => "Parameter 'temperature' is unsupported" );
		$this->assertTrue( OpenAiCompatibleProvider::rejects_parameter( $flat, 'temperature' ) );

		// Mentioning the parameter is not a rejection of it.
		$range = array( 'error' => array( 'message' => 'temperature must be between 0 and 2' ) );
		$this->assertFalse( OpenAiCompatibleProvider::rejects_parameter( $range, 'temperature' ) );
		$other = array(
			'error' => array(
				'message' => 'Unsupported parameter: max_tokens',
				'param'   => 'max_tokens',
			),
		);
		$this->assertFalse( OpenAiCompatibleProvider::rejects_parameter( $other, 'temperature' ) );
		$this->assertFalse( OpenAiCompatibleProvider::rejects_parameter( array(), 'temperature' ) );
	}

	public function test_a_rejected_temperature_is_dropped_and_the_call_retried_once(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http(
				400,
				array(
					'error' => array(
						'message' => "Unsupported value: 'temperature' does not support 0.2 with this model. Only the default (1) value is supported.",
						'param'   => 'temperature',
					),
				)
			),
			self::http(
				200,
				array(
					'choices' => array( array( 'message' => array( 'content' => 'Hello' ) ) ),
					'usage'   => array(
						'prompt_tokens'     => 3,
						'completion_tokens' => 1,
						'total_tokens'      => 4,
					),
				)
			),
		);

		$provider = new OpenAiCompatibleProvider( 'https://api.openai.com/v1', 'gpt-6-luna', 'sk-test', 0.2 );
		$result   = $provider->generate_text( 'system', 'user' );

		$this->assertSame( 'Hello', $result['content'] );
		$requests = $GLOBALS['aisaautopilot_test_http_requests'];
		$this->assertCount( 2, $requests );
		$this->assertArrayHasKey( 'temperature', json_decode( $requests[0]['args']['body'], true ) );
		$this->assertArrayNotHasKey( 'temperature', json_decode( $requests[1]['args']['body'], true ) );
	}

	public function test_other_bad_requests_are_not_retried(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http( 400, array( 'error' => array( 'message' => 'messages must not be empty' ) ) ),
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'never reached' ) ) ) ) ),
		);

		$provider = new OpenAiCompatibleProvider( 'https://api.openai.com/v1', 'gpt-5.6-luna', 'sk-test', 0.2 );
		try {
			$provider->generate_text( 'system', 'user' );
			$this->fail( 'A plain 400 must surface as an error.' );
		} catch ( ProviderException $e ) {
			$this->assertSame( 'provider_error', $e->error_code() );
		}
		$this->assertCount( 1, $GLOBALS['aisaautopilot_test_http_requests'] );
	}

	/** Kimi fixes temperature at 1 and words its refusal differently from OpenAI. */
	public function test_a_fixed_temperature_refusal_from_kimi_is_retried_without_it(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http(
				400,
				array(
					'error' => array(
						'message' => 'invalid temperature: only 1 is allowed for this model',
						'type'    => 'invalid_request_error',
					),
				)
			),
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Hello' ) ) ) ) ),
		);

		$result = ( new OpenAiCompatibleProvider( 'https://api.moonshot.ai/v1', 'kimi-k3', 'sk-test', 0.2 ) )->generate_text( 'system', 'user' );

		$this->assertSame( 'Hello', $result['content'] );
		$requests = $GLOBALS['aisaautopilot_test_http_requests'];
		$this->assertCount( 2, $requests );
		$this->assertArrayNotHasKey( 'temperature', json_decode( $requests[1]['args']['body'], true ) );
	}

	/**
	 * MiniMax M3/M2.x and some open reasoning models put their thinking inside
	 * the reply as <think>…</think>; only the answer after it is the reply.
	 */
	public function test_inline_thinking_is_removed_from_the_reply(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => "<think>\nThe user wants a {\"form\"}.\n</think>\n\nHello" ) ) ) ) ),
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => "<think>Plan {a} then {b}.</think>\n{\"action\":\"reply\",\"reply\":\"Hi\"}" ) ) ) ) ),
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => "Thinking about it {x}.\n</think>\nDone" ) ) ) ) ),
		);

		$provider = new OpenAiCompatibleProvider( 'https://api.minimax.io/v1', 'MiniMax-M3', 'sk-test', 0.2 );

		$this->assertSame( 'Hello', $provider->generate_text( 'system', 'user' )['content'] );
		$this->assertSame(
			array(
				'action' => 'reply',
				'reply'  => 'Hi',
			),
			$provider->generate_structured_output( 'system', 'user' )
		);
		// A template that opens the block itself leaves only the closing tag.
		$this->assertSame( 'Done', $provider->generate_text( 'system', 'user' )['content'] );
	}

	public function test_a_reply_that_is_only_thinking_is_empty(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http( 200, array( 'choices' => array( array( 'message' => array( 'content' => '<think>Still thinking when the limit hit' ) ) ) ) ),
		);

		try {
			( new OpenAiCompatibleProvider( 'https://api.minimax.io/v1', 'MiniMax-M3', 'sk-test' ) )->generate_text( 'system', 'user' );
			$this->fail( 'Unfinished thinking is not an answer.' );
		} catch ( ProviderException $e ) {
			$this->assertSame( 'provider_empty_response', $e->error_code() );
		}
	}

	/**
	 * Test connection sends a tiny ping. A reasoning model can spend that whole
	 * allowance thinking and answer 200 with no text; the key, model and
	 * endpoint are still proven good, so that is a connection, not an error.
	 */
	public function test_health_check_counts_a_reply_spent_on_reasoning_as_connected(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http(
				200,
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => '' ),
							'finish_reason' => 'length',
						),
					),
				)
			),
		);

		$result = ( new OpenAiCompatibleProvider( 'https://api.x.ai/v1', 'grok-4.20-0309-reasoning', 'xai-test' ) )->health_check();

		$this->assertTrue( $result['ok'], (string) $result['message'] );
		$this->assertSame( 'connected', $result['code'] );
	}

	public function test_health_check_moves_to_max_completion_tokens_when_max_tokens_is_rejected(): void {
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http(
				400,
				array(
					'error' => array(
						'message' => "Unsupported parameter: 'max_tokens' is not supported with this model. Use 'max_completion_tokens' instead.",
						'param'   => 'max_tokens',
						'code'    => 'unsupported_parameter',
					),
				)
			),
			self::http(
				200,
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => '' ),
							'finish_reason' => 'length',
						),
					),
				)
			),
		);

		$result = ( new OpenAiCompatibleProvider( 'https://api.openai.com/v1', 'gpt-5.6-luna', 'sk-test', 0.2 ) )->health_check();

		$this->assertTrue( $result['ok'], (string) $result['message'] );
		$requests = $GLOBALS['aisaautopilot_test_http_requests'];
		$this->assertCount( 2, $requests );
		$first  = json_decode( $requests[0]['args']['body'], true );
		$second = json_decode( $requests[1]['args']['body'], true );
		$this->assertArrayHasKey( 'max_tokens', $first );
		$this->assertArrayNotHasKey( 'max_tokens', $second );
		$this->assertSame( $first['max_tokens'], $second['max_completion_tokens'] );
	}

	public function test_anthropic_health_check_counts_a_reply_spent_on_thinking_as_connected(): void {
		// Claude Opus 5 and Fable 5 always think; a 16-token ping ends at max_tokens with no text.
		$GLOBALS['aisaautopilot_test_http_queue'] = array(
			self::http(
				200,
				array(
					'content'     => array(
						array(
							'type'     => 'thinking',
							'thinking' => '',
						),
					),
					'stop_reason' => 'max_tokens',
					'usage'       => array(
						'input_tokens'  => 8,
						'output_tokens' => 16,
					),
				)
			),
		);

		$result = ( new AnthropicProvider( 'https://api.anthropic.com/v1', 'claude-opus-5', 'sk-ant-test' ) )->health_check();

		$this->assertTrue( $result['ok'], (string) $result['message'] );
		$this->assertSame( 'connected', $result['code'] );
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	private static function http( int $code, array $body ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => (string) json_encode( $body ),
		);
	}
}
