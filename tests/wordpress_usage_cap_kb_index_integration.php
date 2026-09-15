<?php
/**
 * Local WordPress integration checks for a usage-cap pause during KB indexing.
 *
 * The test uses an inert OpenAI-compatible endpoint intercepted by
 * pre_http_request. It proves that a cap reached after one successful item
 * preserves the cursor, returns the actionable 429 envelope, does not clear
 * existing knowledge on a full-reindex request, and resumes that exact cursor
 * once the cap is removed. Every fixture and option is restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\KnowledgeBaseController;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Usage\UsageTracker;

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
	'aisaautopilot_usage_historical_totals',
	'aisaautopilot_usage_historical_log',
	'aisaautopilot_usage_period_archive',
	'aisaautopilot_kb_version',
	'aisaautopilot_kb_answer_cache',
	'aisaautopilot_logs',
);
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

$original_user_id  = get_current_user_id();
$provider_attempts = 0;
$provider_filter   = null;
$fixture_type      = 'aisa_cap_fixture';
$registered_type   = false;
$post_ids          = array();
$kb                = new KnowledgeBase();

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

$provider_response = static function (): array {
	$summary = wp_json_encode(
		array(
			'title'        => 'Temporary cap fixture summary',
			'summary'      => 'A deterministic local response used only by the usage-cap indexing test.',
			'keywords'     => 'usage, cap, fixture',
			'category'     => 'Testing',
			'content_type' => 'article',
			'token_count'  => 1,
		)
	);

	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'choices' => array(
					array(
						'message' => array( 'content' => $summary ),
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 1,
					'completion_tokens' => 0,
					'total_tokens'      => 1,
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

try {
	wp_set_current_user( 1 );
	if ( ! post_type_exists( $fixture_type ) ) {
		register_post_type(
			$fixture_type,
			array(
				'public'       => true,
				'show_ui'      => false,
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'query_var'    => false,
				'show_in_rest' => false,
			)
		);
		$registered_type = true;
	}

	$kb->create_table();
	Settings::update(
		array(
			'provider'           => 'deepseek',
			'base_url'           => 'https://usage-cap-kb-index-test.invalid/v1',
			'model'              => 'usage-cap-kb-index-contract-model',
			'key_management'     => 'keyvault',
			'embeddings_enabled' => false,
			'kb_post_types'      => array( $fixture_type ),
		)
	);
	Settings::set_api_key( 'usage-cap-kb-index-contract-key' );
	update_option( 'aisaautopilot_monthly_budget', 1, false );
	update_option( 'aisaautopilot_usage_period', wp_date( 'Y-m' ), false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'request_count'     => 0,
			'last_used'         => '',
		),
		false
	);
	update_option( 'aisaautopilot_usage_log', array(), false );
	update_option( 'aisaautopilot_usage_period_contexts', array(), false );
	update_option( 'aisaautopilot_usage_daily', array(), false );

	foreach ( array( 'First', 'Second' ) as $label ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $fixture_type,
				'post_status'  => 'publish',
				'post_title'   => 'Usage cap KB ' . $label . ' fixture',
				'post_content' => 'Temporary local post for the cursor-preservation contract.',
			),
			true
		);
		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			throw new RuntimeException( 'Could not create the temporary KB cap fixture.' );
		}
		$post_ids[] = (int) $post_id;
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( (int) $post_id ) );
	}

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_attempts, $provider_response ) {
		if ( false === strpos( $url, 'usage-cap-kb-index-test.invalid' ) ) {
			return $preempt;
		}

		++$provider_attempts;
		return $provider_response();
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$controller = new KnowledgeBaseController();
	$first_call = new WP_REST_Request( 'POST', '/aisaautopilot/v1/kb/index/batch' );
	$first_call->set_param( 'offset', 0 );
	$first_call->set_param( 'post_types', array( $fixture_type ) );
	$first_response = $controller->index_batch( $first_call );
	$first_data     = (array) $first_response->get_data();
	$first_row      = $kb->get_by_post_id( $post_ids[0] );
	$second_row     = $kb->get_by_post_id( $post_ids[1] );
	$check(
		429 === $first_response->get_status()
		&& false === ( $first_data['ok'] ?? true )
		&& 'usage_cap_reached' === ( $first_data['code'] ?? '' )
		&& true === ( $first_data['paused'] ?? false )
		&& false === ( $first_data['done'] ?? true )
		&& 1 === (int) ( $first_data['processed'] ?? -1 )
		&& 1 === (int) ( $first_data['offset'] ?? -1 )
		&& 1 === count( (array) ( $first_data['items'] ?? array() ) )
		&& is_array( $first_row )
		&& null === $second_row
		&& 1 === $provider_attempts
		&& ( new UsageTracker() )->is_exhausted(),
		'A mid-batch cap returns 429 with the unadvanced cursor and no skipped second post'
	);

	$truncate_response = $controller->truncate( new WP_REST_Request( 'POST', '/aisaautopilot/v1/kb/truncate' ) );
	$truncate_data     = (array) $truncate_response->get_data();
	$check(
		429 === $truncate_response->get_status()
		&& false === ( $truncate_data['ok'] ?? true )
		&& 'usage_cap_reached' === ( $truncate_data['code'] ?? '' )
		&& is_array( $kb->get_by_post_id( $post_ids[0] ) ),
		'Full re-index refuses to clear existing knowledge while the cap is exhausted'
	);

	update_option( 'aisaautopilot_monthly_budget', 0, false );
	$resume_call = new WP_REST_Request( 'POST', '/aisaautopilot/v1/kb/index/batch' );
	$resume_call->set_param( 'offset', (int) $first_data['offset'] );
	$resume_call->set_param( 'post_types', array( $fixture_type ) );
	$resume_response = $controller->index_batch( $resume_call );
	$resume_data     = (array) $resume_response->get_data();
	$check(
		200 === $resume_response->get_status()
		&& true === ( $resume_data['ok'] ?? false )
		&& true === ( $resume_data['done'] ?? false )
		&& 1 === (int) ( $resume_data['processed'] ?? -1 )
		&& 2 === (int) ( $resume_data['offset'] ?? -1 )
		&& is_array( $kb->get_by_post_id( $post_ids[1] ) )
		&& 2 === $provider_attempts,
		'After the cap is removed, the saved cursor indexes only the remaining post'
	);
} catch ( Throwable $error ) {
	$check( false, 'Usage-cap KB indexing contract completes without a fatal error' );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	foreach ( $post_ids as $post_id ) {
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );
		$kb->delete_by_post_id( $post_id );
		wp_delete_post( $post_id, true );
	}
	if ( $registered_type && function_exists( 'unregister_post_type' ) ) {
		unregister_post_type( $fixture_type );
	}
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
