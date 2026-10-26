<?php
/**
 * Runs the real EFB Copilot server code for browser checks, with no WordPress,
 * no database and no network.
 *
 * Mode `chat` answers one /efb/chat request with EfbCopilotController::chat()
 * itself. Only the AI provider is scripted: the caller supplies the JSON the
 * model would have returned, and it is handed back through the PHPUnit
 * bootstrap's HTTP queue. Field cleaning, operation validation, rule building,
 * plan gates, field templates, review findings and the reply envelope are all
 * the plugin's own code, so a browser check that mocks /efb/chat with this
 * output exercises exactly what the server sends.
 *
 * Mode `page` returns the copilot markup and browser configuration exactly as
 * EfbBuilderButton renders them, plus an EFB form structure built by
 * EfbFormConverter, so a stub editor page can host the real efb-copilot.js.
 *
 * stdin:  {"mode":"chat","request":{...the /efb/chat body...},"ai":{...},"efb_plan":1|2|3,
 *          "privacy_url":"","privacy_title":""}
 *         {"mode":"page","efb_plan":1|2|3,"plan":{...a form plan...}}
 * stdout: chat: {"status":200,"body":{...},"provider_calls":1,"prompt":"..."}
 *         page: {"markup":"...","config":{...},"structure":[...]}
 *
 * Options live in memory for this one process and are gone when it exits.
 */

declare(strict_types=1);

$aisa_offline_input = json_decode( (string) stream_get_contents( STDIN ), true );
if ( ! is_array( $aisa_offline_input ) ) {
	fwrite( STDERR, "Expected a JSON object on stdin.\n" );
	exit( 64 );
}

$GLOBALS['aisa_offline_options'] = array(
	// EFB stores its plan here: 1 Pro, 3 Free Plus, anything else Free.
	'emsfb_pro'                  => (int) ( $aisa_offline_input['efb_plan'] ?? 2 ),
	'wp_page_for_privacy_policy' => '' !== (string) ( $aisa_offline_input['privacy_url'] ?? '' ) ? 3 : 0,
);
$GLOBALS['aisa_offline_privacy'] = array(
	'url'   => (string) ( $aisa_offline_input['privacy_url'] ?? '' ),
	'title' => (string) ( $aisa_offline_input['privacy_title'] ?? '' ),
);
$GLOBALS['aisa_offline_script'] = array();

// Defined before the bootstrap, whose stubs only stand in for missing functions.
function get_option( string $option, $default = false ) {
	return $GLOBALS['aisa_offline_options'][ $option ] ?? $default;
}
function update_option( string $option, $value, $autoload = null ): bool {
	$GLOBALS['aisa_offline_options'][ $option ] = $value;
	return true;
}
function delete_option( string $option ): bool {
	unset( $GLOBALS['aisa_offline_options'][ $option ] );
	return true;
}
function get_privacy_policy_url(): string {
	return $GLOBALS['aisa_offline_privacy']['url'];
}
function get_the_title( $post = 0 ): string {
	return $GLOBALS['aisa_offline_privacy']['title'];
}
function wp_generate_uuid4(): string {
	return bin2hex( random_bytes( 16 ) );
}
function is_admin(): bool {
	return true;
}
function is_rtl(): bool {
	return false;
}
function wp_create_nonce( $action = -1 ): string {
	return 'offline-nonce';
}
function wp_localize_script( string $handle, string $object_name, array $l10n ): void {
	$GLOBALS['aisa_offline_script'][ $object_name ] = $l10n;
}
function esc_attr_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
function esc_html_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params;

		/** @param array<string, mixed> $params */
		public function __construct( array $params ) {
			$this->params = $params;
		}

		/** @return mixed */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;
		private int $status;

		/** @param mixed $data */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/** @return mixed */
		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

require __DIR__ . '/bootstrap.php';

use AISAAutoPilot\Api\EfbCopilotController;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Efb\EfbBuilderButton;
use AISAAutoPilot\Efb\EfbFormConverter;

$aisa_offline_mode = (string) ( $aisa_offline_input['mode'] ?? 'chat' );

if ( 'page' === $aisa_offline_mode ) {
	$_GET['page'] = 'Emsfb';
	$button       = new EfbBuilderButton();
	$button->enqueue( 'toplevel_page_Emsfb' );
	ob_start();
	$button->render();
	$markup = (string) ob_get_clean();

	echo wp_json_encode(
		array(
			'markup'    => $markup,
			'config'    => $GLOBALS['aisa_offline_script']['AISAAutoPilotEFB'] ?? null,
			'structure' => ( new EfbFormConverter() )->convert( (array) ( $aisa_offline_input['plan'] ?? array() ) ),
		)
	) . PHP_EOL;
	exit( 0 );
}

// A configured provider on a reserved host; the request never leaves this
// process because the bootstrap's wp_remote_post() serves the queue instead.
Settings::update(
	array(
		'provider'            => 'openai',
		'base_url'            => 'https://scripted-provider.invalid/v1',
		'model'               => 'scripted-model',
		'efb_copilot_enabled' => true,
	)
);
Settings::set_api_key( 'scripted-placeholder-key' );

$GLOBALS['aisaautopilot_test_http_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body'     => (string) wp_json_encode(
			array(
				'choices' => array(
					array(
						'message'       => array(
							'role'    => 'assistant',
							'content' => (string) wp_json_encode( $aisa_offline_input['ai'] ?? array() ),
						),
						'finish_reason' => 'stop',
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 1,
					'completion_tokens' => 1,
					'total_tokens'      => 2,
				),
			)
		),
	),
);
$GLOBALS['aisaautopilot_test_http_requests'] = array();

$aisa_offline_response = ( new EfbCopilotController() )->chat( new WP_REST_Request( (array) ( $aisa_offline_input['request'] ?? array() ) ) );

$aisa_offline_prompt = '';
foreach ( $GLOBALS['aisaautopilot_test_http_requests'] as $aisa_offline_call ) {
	$aisa_offline_body = json_decode( (string) ( $aisa_offline_call['args']['body'] ?? '' ), true );
	foreach ( (array) ( $aisa_offline_body['messages'] ?? array() ) as $aisa_offline_message ) {
		if ( 'user' === ( $aisa_offline_message['role'] ?? '' ) ) {
			$aisa_offline_prompt = (string) ( $aisa_offline_message['content'] ?? '' );
		}
	}
}

echo wp_json_encode(
	array(
		'status'         => $aisa_offline_response->get_status(),
		'body'           => $aisa_offline_response->get_data(),
		'provider_calls' => count( $GLOBALS['aisaautopilot_test_http_requests'] ),
		'prompt'         => $aisa_offline_prompt,
	)
) . PHP_EOL;
