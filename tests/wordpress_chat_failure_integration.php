<?php
/**
 * Local WordPress integration checks for chat failures.
 *
 * A failure is not an answer: when the provider fails, or none is set up, the visitor widget,
 * the public chat endpoint and the administrator console refuse with ok:false and an HTTP
 * error, nothing is stored as a conversation turn, and a visitor never sees the provider's or
 * the setup wording. The provider is an inert endpoint intercepted by pre_http_request. Every
 * fixture and option is restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ChatController;
use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Api\PublicChatController;
use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\KnowledgeIndex;
use AISAAutoPilot\Core\Settings;

global $wpdb;

$missing = new stdClass();
$checks  = array();
$options = array(
	Settings::OPTION,
	Settings::KEY_OPTION,
	'aisaautopilot_monthly_budget',
	'aisaautopilot_usage_totals',
	'aisaautopilot_usage_period',
	'aisaautopilot_usage_log',
	'aisaautopilot_usage_period_contexts',
	'aisaautopilot_usage_daily',
	'aisaautopilot_kb_answer_cache',
	'aisaautopilot_kb_version',
	'aisaautopilot_kb_gaps',
	'aisaautopilot_logs',
);
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

$test_ip        = '203.0.113.93';
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
$provider_calls   = 0;
$provider_filter  = null;
$post_id          = 0;
$conversation_ids = array();
$widget_table     = $wpdb->prefix . 'aisaautopilot_widget_chats';
$kb               = new KnowledgeBase();
$index            = new KnowledgeIndex();
$manager          = new ConversationManager();

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

$widget_count = static function () use ( $wpdb, $widget_table ): int {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$widget_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

$ask_widget = static function ( string $question ): \WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/ask' );
	$request->set_param( 'question', $question );
	return ( new ChatWidgetController() )->ask( $request );
};

$ask_public = static function ( string $question ): \WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/public/chat' );
	$request->set_param( 'question', $question );
	return ( new PublicChatController() )->ask( $request );
};

$ask_admin = static function ( string $question, int $conversation_id ): \WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/chat/ask' );
	$request->set_param( 'question', $question );
	$request->set_param( 'conversation_id', $conversation_id );
	return ( new ChatController() )->ask( $request );
};

$is_refusal = static function ( \WP_REST_Response $response, int $status, string $code ): bool {
	$data = (array) $response->get_data();
	return $status === $response->get_status()
		&& false === ( $data['ok'] ?? true )
		&& $code === ( $data['code'] ?? '' )
		&& ! array_key_exists( 'answer', $data )
		&& '' !== trim( (string) ( $data['message'] ?? '' ) );
};

try {
	wp_set_current_user( 1 );
	$_SERVER['REMOTE_ADDR'] = $test_ip;
	$kb->create_table();
	$index->create_tables();
	Settings::update(
		array(
			'provider'                 => 'deepseek',
			'base_url'                 => 'https://chat-failure-test.invalid/v1',
			'model'                    => 'chat-failure-contract-model',
			'key_management'           => 'keyvault',
			'embeddings_enabled'       => false,
			'public_assistant_enabled' => true,
			'widget'                   => array(
				'enabled'                      => true,
				'conversation_logging_enabled' => true,
				'handoff_enabled'              => false,
			),
		)
	);
	Settings::set_api_key( 'chat-failure-test-key' );
	update_option( 'aisaautopilot_monthly_budget', 0, false );
	update_option( 'aisaautopilot_kb_answer_cache', array(), false );

	// A page the question matches, so every path reaches the provider.
	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Failure fixture opening hours',
			'post_content' => '<p>The failure fixture shop opens at 9:30 on Saturdays.</p>',
		),
		true
	);
	wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );
	( new ContentIndexer() )->index_single( $post_id );

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_calls ) {
		if ( false === strpos( $url, 'chat-failure-test.invalid' ) ) {
			return $preempt;
		}
		++$provider_calls;
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Upstream exploded: internal detail' ) ) ),
			'response' => array(
				'code'    => 500,
				'message' => 'Internal Server Error',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$widget_before = $widget_count();
	$widget        = $ask_widget( 'When does the failure fixture shop open on Saturday?' );
	$public        = $ask_public( 'When does the failure fixture shop open on Saturday?' );
	$check(
		$provider_calls > 0
		&& $is_refusal( $widget, 503, 'assistant_unavailable' )
		&& $is_refusal( $public, 503, 'assistant_unavailable' )
		&& false === strpos( (string) wp_json_encode( $widget->get_data() ), 'internal detail' ),
		'A provider failure reaches the visitor as a 503 refusal with a neutral message, never as an answer'
	);
	$check(
		$widget_before === $widget_count(),
		'With conversation logging on, a provider failure stores no visitor turn'
	);

	$conversation_ids[] = $manager->create( 'Failure fixture' );
	$admin              = $ask_admin( 'When does the failure fixture shop open on Saturday?', $conversation_ids[0] );
	$admin_row          = $manager->get( $conversation_ids[0] );
	$check(
		$is_refusal( $admin, 503, 'assistant_unavailable' )
		&& 0 === (int) ( $admin_row['message_count'] ?? -1 ),
		'The administrator console refuses a provider failure with 503 and records neither the question nor an answer'
	);

	// No provider at all.
	update_option( Settings::KEY_OPTION, '', false );
	Settings::update( array( 'key_management' => 'keyvault' ) );
	$calls_before       = $provider_calls;
	$widget_none        = $ask_widget( 'When does the failure fixture shop open on Saturday?' );
	$conversation_ids[] = $manager->create( 'No provider fixture' );
	$admin_none         = $ask_admin( 'When does the failure fixture shop open on Saturday?', $conversation_ids[1] );
	$none_row           = $manager->get( $conversation_ids[1] );
	$widget_none_text   = (string) wp_json_encode( $widget_none->get_data() );
	$check(
		$is_refusal( $widget_none, 503, 'assistant_not_configured' )
		&& false === stripos( $widget_none_text, 'API key' )
		&& false === stripos( $widget_none_text, 'Settings' )
		&& $calls_before === $provider_calls,
		'Without a provider, the widget refuses without retry and without telling a visitor about API keys or settings'
	);
	$check(
		$is_refusal( $admin_none, 400, 'not_configured' )
		&& false !== stripos( (string) ( $admin_none->get_data()['message'] ?? '' ), 'not configured' )
		&& 0 === (int) ( $none_row['message_count'] ?? -1 ),
		'Without a provider, the administrator is told it is not configured and nothing is recorded'
	);
} catch ( Throwable $error ) {
	$check( false, 'Chat failure contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	foreach ( $conversation_ids as $conversation_id ) {
		$manager->delete( (int) $conversation_id );
	}
	if ( $post_id > 0 ) {
		$kb->delete_by_post_id( $post_id );
		$index->delete_post( $post_id );
		wp_delete_post( $post_id, true );
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
