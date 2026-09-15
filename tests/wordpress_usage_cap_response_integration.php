<?php
/**
 * Local WordPress integration checks for user-facing usage-cap responses.
 *
 * A temporary post and KB row make each chat path reach the provider boundary.
 * The HTTP pre-flight filter makes any outbound request observable. Every
 * option, transient, post, KB row and conversation owned by this script is
 * removed or restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ChatController;
use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Api\CopilotController;
use AISAAutoPilot\Api\KnowledgeBaseController;
use AISAAutoPilot\Api\PublicChatController;
use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;

$missing = new stdClass();
$checks  = array();
$options = array(
	Settings::OPTION,
	Settings::KEY_OPTION,
	'aisaautopilot_monthly_budget',
	'aisaautopilot_usage_totals',
	'aisaautopilot_usage_period',
	'aisaautopilot_kb_answer_cache',
	'aisaautopilot_kb_version',
);
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

$test_ip       = '203.0.113.77';
$rate_keys      = array(
	'aisaautopilot_rate_widget_text_' . hash_hmac( 'sha256', 'ip:' . $test_ip, wp_salt( 'auth' ) ),
	'aisaautopilot_rate_public_text_' . hash_hmac( 'sha256', 'ip:' . $test_ip, wp_salt( 'auth' ) ),
);
$original_rates = array();
foreach ( $rate_keys as $key ) {
	$original_rates[ $key ] = get_transient( $key );
}

$original_user_id = get_current_user_id();
$had_remote_addr  = array_key_exists( 'REMOTE_ADDR', $_SERVER );
$original_remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$provider_attempts = 0;
$provider_filter   = null;
$fixture_post_id   = 0;
$conversation_id   = 0;
$kb                = new KnowledgeBase();
$conversation      = new ConversationManager();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}

	update_option( $option, $value, false );
};

$is_cap_response = static function ( \WP_REST_Response $response ): bool {
	$data = (array) $response->get_data();

	return 429 === $response->get_status()
		&& false === ( $data['ok'] ?? true )
		&& 'usage_cap_reached' === ( $data['code'] ?? '' )
		&& '' !== trim( (string) ( $data['message'] ?? '' ) );
};

try {
	if ( ! $kb->table_exists() ) {
		throw new RuntimeException( 'Knowledge Base table is not available for the local response contract.' );
	}

	wp_set_current_user( 1 );
	$_SERVER['REMOTE_ADDR'] = $test_ip;
	Settings::update(
		array(
			'provider'                 => 'deepseek',
			'base_url'                 => 'https://usage-cap-response-test.invalid/v1',
			'model'                    => 'usage-cap-response-contract-model',
			'key_management'           => 'keyvault',
			'embeddings_enabled'       => false,
			'form_composer_enabled'    => true,
			'public_assistant_enabled' => true,
			'widget'                   => array(
				'enabled'                      => true,
				'conversation_logging_enabled' => false,
			),
		)
	);
	Settings::set_api_key( 'usage-cap-response-test-key' );
	update_option( 'aisaautopilot_monthly_budget', 1, false );
	update_option( 'aisaautopilot_usage_period', wp_date( 'Y-m' ), false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 1,
			'completion_tokens' => 0,
			'total_tokens'      => 1,
			'request_count'     => 1,
			'last_used'         => gmdate( 'c' ),
		),
		false
	);

	$fixture_post_id = wp_insert_post(
		array(
			'post_title'   => 'Usage cap response fixture',
			'post_content' => 'A temporary knowledge record for the cap response test.',
			'post_status'  => 'draft',
		),
		true
	);
	if ( is_wp_error( $fixture_post_id ) || $fixture_post_id <= 0 ) {
		throw new RuntimeException( 'Could not create the local usage-cap fixture post.' );
	}
	if ( ! $kb->upsert(
		array(
			'post_id'      => $fixture_post_id,
			'post_type'    => 'post',
			'title'        => 'Usage cap response fixture',
			'summary'      => 'This record exists only to give the chat engine context during the usage-cap test.',
			'keywords'     => 'usage cap fixture',
			'category'     => 'test',
			'content_type' => 'article',
			'content_hash' => hash( 'sha256', 'usage-cap-response-' . $fixture_post_id ),
			'token_count'  => 20,
		)
	) ) {
		throw new RuntimeException( 'Could not create the local usage-cap KB fixture.' );
	}

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_attempts ) {
		++$provider_attempts;
		return new WP_Error( 'usage_cap_provider_called', 'Provider transport must not run while the cap is exhausted.' );
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$form_request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/form/plan' );
	$form_request->set_param( 'prompt', 'Create a small contact form.' );
	$form_response = ( new CopilotController() )->plan( $form_request );
	$check( $is_cap_response( $form_response ), 'Form Composer returns an actionable 429 when the shared cap is reached' );

	$kb_request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/kb/chat' );
	$kb_request->set_param( 'question', 'What is the usage cap response fixture?' );
	$kb_response = ( new KnowledgeBaseController() )->chat( $kb_request );
	$check( $is_cap_response( $kb_response ), 'Knowledge Base chat returns an actionable 429 when the shared cap is reached' );

	$conversation_id = $conversation->create( 'Usage cap response fixture' );
	$chat_request    = new WP_REST_Request( 'POST', '/aisaautopilot/v1/chat/ask' );
	$chat_request->set_param( 'conversation_id', $conversation_id );
	$chat_request->set_param( 'question', 'Explain the usage cap response fixture.' );
	$chat_response = ( new ChatController() )->ask( $chat_request );
	$chat_row      = $conversation->get( $conversation_id );
	$check(
		$is_cap_response( $chat_response ) && 0 === (int) ( $chat_row['message_count'] ?? -1 ),
		'Admin chat returns 429 without recording a fabricated assistant response'
	);

	$widget_request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/ask' );
	$widget_request->set_param( 'question', 'Tell me about the usage cap response fixture.' );
	$widget_response = ( new ChatWidgetController() )->ask( $widget_request );
	$check( $is_cap_response( $widget_response ), 'Widget chat returns an actionable 429 when the shared cap is reached' );

	$public_request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/public/chat' );
	$public_request->set_param( 'question', 'Can you describe the usage cap response fixture?' );
	$public_response = ( new PublicChatController() )->ask( $public_request );
	$check( $is_cap_response( $public_response ), 'Public chat returns an actionable 429 when the shared cap is reached' );

	$check( 0 === $provider_attempts, 'No capped REST path attempts provider transport' );
} catch ( Throwable $error ) {
	$check( false, 'Usage-cap REST response contract completes without a fatal error' );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	if ( $conversation_id > 0 ) {
		$conversation->delete( $conversation_id );
	}
	if ( $fixture_post_id > 0 ) {
		$kb->delete_by_post_id( $fixture_post_id );
		wp_delete_post( $fixture_post_id, true );
	}
	foreach ( $rate_keys as $key ) {
		if ( false === $original_rates[ $key ] ) {
			delete_transient( $key );
		} else {
			set_transient( $key, $original_rates[ $key ], HOUR_IN_SECONDS );
		}
	}
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	if ( $had_remote_addr ) {
		$_SERVER['REMOTE_ADDR'] = $original_remote;
	} else {
		unset( $_SERVER['REMOTE_ADDR'] );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
