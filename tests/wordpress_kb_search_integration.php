<?php
/**
 * Local WordPress integration checks for the passage index and what the site assistant
 * does with it.
 *
 * Two temporary pages are indexed: a public one with a price under a heading, and a
 * password-protected one with a staff code. The AI endpoint is an inert
 * OpenAI-compatible URL intercepted by pre_http_request, which records what the model
 * would have been sent. The checks prove that:
 *  - a protected page never enters the index or the model's context;
 *  - a question nothing on the site relates to is answered "not found" with no request,
 *    in the site owner's chosen way (built-in text, their own text, or a handoff offer);
 *  - a related question sends the matching passage and cites its page;
 *  - a follow-up keeps its subject through the widget's history;
 *  - a visitor writing in another script gets the built-in text in their language.
 * Every fixture and option is restored in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\KnowledgeIndex;
use AISAAutoPilot\Core\Settings;

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

$test_ip        = '203.0.113.91';
$rate_keys      = array( 'aisaautopilot_rate_widget_text_' . hash_hmac( 'sha256', 'ip:' . $test_ip, wp_salt( 'auth' ) ) );
$original_rates = array();
foreach ( $rate_keys as $key ) {
	$original_rates[ $key ] = get_transient( $key );
}

$original_user_id = get_current_user_id();
$had_remote_addr  = array_key_exists( 'REMOTE_ADDR', $_SERVER );
$original_remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$requests         = array();
$provider_filter  = null;
$post_ids         = array();
$kb               = new KnowledgeBase();
$index            = new KnowledgeIndex();

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

// Every model reply is the same decision: answered from passage 1.
$provider_response = static function (): array {
	$decision = wp_json_encode(
		array(
			'status'          => 'answered',
			'answer'          => 'A 250 g bag costs 14.50 euros.',
			'clarify_options' => array(),
			'sources'         => array( 1 ),
		)
	);

	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'choices' => array(
					array(
						'message' => array( 'content' => $decision ),
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 1,
					'completion_tokens' => 1,
					'total_tokens'      => 2,
				),
			)
		),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};

$ask = static function ( string $question, array $history = array() ): array {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/ask' );
	$request->set_param( 'question', $question );
	if ( array() !== $history ) {
		$request->set_param( 'history', $history );
	}

	return (array) ( new ChatWidgetController() )->ask( $request )->get_data();
};

$widget = static function ( array $values ): void {
	Settings::update( array( 'widget' => $values ) );
};

try {
	wp_set_current_user( 1 );
	$_SERVER['REMOTE_ADDR'] = $test_ip;
	$kb->create_table();
	$index->create_tables();

	Settings::update(
		array(
			'provider'                 => 'deepseek',
			'base_url'                 => 'https://kb-search-test.invalid/v1',
			'model'                    => 'kb-search-contract-model',
			'key_management'           => 'keyvault',
			'embeddings_enabled'       => false,
			'public_assistant_enabled' => true,
			'widget'                   => array(
				'enabled'                      => true,
				'conversation_logging_enabled' => false,
				'handoff_enabled'              => false,
				'not_found_action'             => 'message',
				'not_found_message'            => '',
			),
		)
	);
	Settings::set_api_key( 'kb-search-test-key' );
	update_option( 'aisaautopilot_monthly_budget', 0, false );
	update_option( 'aisaautopilot_kb_answer_cache', array(), false );

	$fixtures = array(
		'public'    => array(
			'post_title'   => 'Yirgacheffe test lot',
			'post_content' => '<p>Our Yirgacheffe test lot comes from a washing station in southern Ethiopia.</p>'
				. '<h2>Price and size</h2><p>A 250 g bag of the Yirgacheffe test lot costs 14.50 euros.</p>',
		),
		'protected' => array(
			'post_title'    => 'Staff room test notes',
			'post_content'  => '<p>The staff discount code for the Yirgacheffe test lot is ZETA-4471.</p>',
			'post_password' => 'kb-search-test',
		),
	);
	foreach ( $fixtures as $key => $fixture ) {
		$post_id = wp_insert_post(
			$fixture + array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			throw new RuntimeException( 'Could not create the temporary KB search fixture.' );
		}
		$post_ids[ $key ] = (int) $post_id;
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( (int) $post_id ) );
	}

	$indexer   = new ContentIndexer();
	$public    = $indexer->index_single( $post_ids['public'] );
	$protected = $indexer->index_single( $post_ids['protected'] );
	$check(
		'indexed' === ( $public['status'] ?? '' )
		&& $index->count_for_post( $post_ids['public'] ) > 0
		&& 'skipped' === ( $protected['status'] ?? '' )
		&& 0 === $index->count_for_post( $post_ids['protected'] )
		&& null === $kb->get_by_post_id( $post_ids['protected'] ),
		'A public page is indexed as passages and a password-protected page is not indexed at all'
	);

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$requests, $provider_response ) {
		if ( false === strpos( $url, 'kb-search-test.invalid' ) ) {
			return $preempt;
		}
		$requests[] = (string) ( $args['body'] ?? '' );
		return $provider_response();
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$unrelated = $ask( 'Do you rent submarines?' );
	$check(
		true === ( $unrelated['ok'] ?? false )
		&& 'not_found' === ( $unrelated['status'] ?? '' )
		&& ChatEngine::default_not_found_message( false ) === ( $unrelated['answer'] ?? '' )
		&& false === ( $unrelated['handoff_offer'] ?? true )
		&& array() === $requests,
		'A question nothing on the site relates to gets the built-in "not found" text with no AI request'
	);

	$widget( array( 'not_found_message' => 'We do not cover that here. Write to hello@example.test.' ) );
	$custom = $ask( 'Do you rent bicycles?' );
	$check(
		'We do not cover that here. Write to hello@example.test.' === ( $custom['answer'] ?? '' ) && array() === $requests,
		'The site owner\'s own "not found" text replaces the built-in one'
	);

	$widget(
		array(
			'not_found_message'     => '',
			'not_found_action'      => 'handoff',
			'handoff_enabled'       => true,
			'handoff_email_enabled' => true,
			'handoff_email_to'      => 'support@example.test',
		)
	);
	$handoff = $ask( 'Do you sell pizza?' );
	$check(
		true === ( $handoff['handoff_offer'] ?? false )
		&& ChatEngine::default_not_found_message( true ) === ( $handoff['answer'] ?? '' )
		&& array() === $requests,
		'With "Offer to connect to a person" and a ready channel, "not found" offers the handoff'
	);

	$widget( array( 'handoff_email_enabled' => false ) );
	$no_channel = $ask( 'Do you sell tickets?' );
	$check(
		false === ( $no_channel['handoff_offer'] ?? true ) && ChatEngine::default_not_found_message( false ) === ( $no_channel['answer'] ?? '' ),
		'Without a ready handoff channel, the offer falls back to the message'
	);
	$widget(
		array(
			'not_found_action' => 'message',
			'handoff_enabled'  => false,
		)
	);

	$persian = $ask( 'آیا دوچرخه اجاره می‌دهید؟' );
	switch_to_locale( 'fa_IR' );
	$persian_text = ChatEngine::default_not_found_message( false );
	restore_previous_locale();
	$check(
		in_array( 'fa_IR', get_available_languages(), true )
			? ( $persian_text === ( $persian['answer'] ?? '' ) && ChatEngine::default_not_found_message( false ) !== $persian_text )
			: ChatEngine::default_not_found_message( false ) === ( $persian['answer'] ?? '' ),
		'A Persian question on an English site gets the built-in text in Persian when WordPress has Persian installed'
	);

	$answered = $ask( 'How much is the Yirgacheffe test lot?' );
	$sent     = (string) ( $requests[0] ?? '' );
	$sources  = (array) ( $answered['sources'] ?? array() );
	$check(
		'answered' === ( $answered['status'] ?? '' )
		&& 1 === count( $requests )
		&& false !== strpos( $sent, '14.50 euros' )
		&& false !== strpos( $sent, 'Price and size' )
		&& false === strpos( $sent, 'ZETA-4471' )
		&& (int) ( $sources[0]['post_id'] ?? 0 ) === $post_ids['public'],
		'A related question sends the matching passage (never the protected page) and cites its page'
	);

	$requests = array();
	$lonely   = $ask( 'and how much is it?' );
	$check(
		'not_found' === ( $lonely['status'] ?? '' ) && array() === $requests,
		'A follow-up with no history has nothing to search for and makes no AI request'
	);

	$follow = $ask(
		'and how much is it?',
		array(
			array(
				'role' => 'user',
				'text' => 'Tell me about the Yirgacheffe test lot',
			),
			array(
				'role' => 'assistant',
				'text' => 'It comes from southern Ethiopia.',
			),
		)
	);
	$check(
		'answered' === ( $follow['status'] ?? '' )
		&& 1 === count( $requests )
		&& false !== strpos( (string) $requests[0], '14.50 euros' )
		&& false !== strpos( (string) $requests[0], 'untrusted reference text' ),
		'Through the widget\'s history, a follow-up keeps its subject and finds the price'
	);
} catch ( Throwable $error ) {
	$check( false, 'KB search contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	foreach ( $post_ids as $post_id ) {
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );
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
