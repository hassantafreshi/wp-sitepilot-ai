<?php
/**
 * Local WordPress integration checks for the EFB Copilot REST contract.
 *
 * Covers the /efb/log callback (a wrong callback once turned every client log
 * into HTTP 500), the server-side resolution of `selected_field` against the
 * sanitized field catalogue, and the `efb_copilot_enabled` permission gate.
 *
 * No provider is contacted: every outbound HTTP request is pre-empted with
 * pre_http_request. The provider request body is read in memory only, to prove
 * what the real controller put into the prompt. The temporary provider settings
 * point at a reserved `.invalid` host with a non-secret placeholder key, and
 * every option this suite can change is snapshotted first and restored in
 * finally, then compared with its snapshot.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\EfbCopilotController;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;

const AISA_EFB_CONTRACT_HOST = 'efb-copilot-contract-test.invalid';

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
	'aisaautopilot_logs',
);
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

$original_user_id = get_current_user_id();
$subscriber_id    = 0;
$http_filter      = null;
$provider_prompts = array();
$other_requests   = 0;
$marker           = 't02contract' . strtolower( wp_generate_password( 8, false, false ) );

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

	// No autoload argument: keep whatever autoload flag the row already has.
	update_option( $option, $value );
};

$dispatch = static function ( int $user_id, string $route, array $params ): WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( 'POST', '/' . RestRoutes::NAMESPACE . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	return rest_do_request( $request );
};

$log_events = static function ( string $trace ): array {
	$events = array();
	$logs   = get_option( 'aisaautopilot_logs', array() );
	foreach ( is_array( $logs ) ? $logs : array() as $entry ) {
		if ( is_array( $entry ) && $trace === ( $entry['context']['trace_id'] ?? '' ) ) {
			$events[] = $entry;
		}
	}

	return $events;
};

// The live catalogue the browser would send. The label carries markup so the
// check can prove the prompt uses the sanitized catalogue value.
$fields = array(
	array(
		'id'    => 'work_email',
		'label' => 'Work <b>email</b>',
		'type'  => 'email',
	),
	array(
		'id'    => 'full_name',
		'label' => 'Full name',
		'type'  => 'text',
	),
);

$chat = static function ( string $trace, $selected, bool $has_form = true, array $extra = array() ) use ( $dispatch, $fields, &$provider_prompts ): array {
	$before   = count( $provider_prompts );
	$response = $dispatch(
		1,
		'/efb/chat',
		array_merge(
			array(
				'message'        => 'Make this field required.',
				'trace_id'       => $trace,
				'has_form'       => $has_form,
				'fields'         => $fields,
				'steps'          => array(),
				'history'        => array(),
				'selected_field' => $selected,
			),
			$extra
		)
	);
	$prompts  = array_slice( $provider_prompts, $before );

	return array(
		'response' => $response,
		'prompts'  => $prompts,
		'prompt'   => 1 === count( $prompts ) ? (string) $prompts[0] : '',
	);
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'provider'            => 'deepseek',
			'base_url'            => 'https://' . AISA_EFB_CONTRACT_HOST . '/v1',
			'model'               => 'efb-copilot-contract-model',
			'key_management'      => 'keyvault',
			'efb_copilot_enabled' => true,
		)
	);
	// Placeholder, not a credential: the host is reserved and transport is pre-empted.
	Settings::set_api_key( 'efb-copilot-contract-placeholder' );
	// An unlimited budget for this process only, so the capped provider reaches
	// transport; the real budget and usage are restored in finally.
	update_option( 'aisaautopilot_monthly_budget', 0 );

	$http_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_prompts, &$other_requests ) {
		unset( $preempt );
		if ( AISA_EFB_CONTRACT_HOST !== (string) wp_parse_url( $url, PHP_URL_HOST ) ) {
			++$other_requests;
			return new WP_Error( 'efb_contract_http_blocked', 'Outbound HTTP is blocked in this integration suite.' );
		}

		$body               = json_decode( (string) ( $args['body'] ?? '' ), true );
		$provider_prompts[] = (string) ( $body['messages'][1]['content'] ?? '' );

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => (string) wp_json_encode(
				array(
					'choices' => array(
						array(
							'message' => array(
								'content' => '{"action":"chat","reply":"Contract reply."}',
							),
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
	add_filter( 'pre_http_request', $http_filter, 10, 3 );

	do_action( 'rest_api_init' );
	$routes    = rest_get_server()->get_routes();
	$log_route = $routes[ '/' . RestRoutes::NAMESPACE . '/efb/log' ] ?? array();
	$handler   = is_array( $log_route[0] ?? null ) ? $log_route[0] : array();
	$callback  = $handler['callback'] ?? null;
	$check(
		array() !== $handler
		&& isset( $handler['methods']['POST'] )
		&& is_callable( $callback )
		&& is_array( $callback )
		&& $callback[0] instanceof EfbCopilotController
		&& 'client_log' === ( $callback[1] ?? '' )
		&& is_array( $handler['permission_callback'] ?? null )
		&& 'can_manage_efb_copilot' === ( $handler['permission_callback'][1] ?? '' ),
		'/efb/log is registered for POST with the callable client_log callback and the EFB Copilot permission'
	);

	$broken = array();
	foreach ( $routes as $path => $route_handlers ) {
		if ( ! str_starts_with( (string) $path, '/' . RestRoutes::NAMESPACE . '/efb/' ) ) {
			continue;
		}
		foreach ( (array) $route_handlers as $route_handler ) {
			if ( ! is_array( $route_handler ) || ! is_callable( $route_handler['callback'] ?? null ) || ! is_callable( $route_handler['permission_callback'] ?? null ) ) {
				$broken[] = (string) $path;
			}
		}
	}
	$check(
		array() === $broken && isset( $routes[ '/' . RestRoutes::NAMESPACE . '/efb/chat' ] ),
		'Every /efb/* route has a callable callback and permission callback'
	);

	$log_trace = $marker . '-log';
	$logged    = $dispatch(
		1,
		'/efb/log',
		array(
			'event'    => 'contract_check',
			'message'  => 'Applied <script>alert(1)</script> operations.',
			'trace_id' => $log_trace,
			'context'  => array(
				'applied' => array( 'set_field' ),
				'note'    => '<img src=x onerror=alert(1)>kept',
			),
		)
	);
	$log_data    = (array) $logged->get_data();
	$log_entries = $log_events( $log_trace );
	$log_entry   = $log_entries[0] ?? array();
	$check(
		200 === $logged->get_status()
		&& true === ( $log_data['ok'] ?? false )
		&& 1 === count( $log_entries )
		&& 'efb.client.contract_check' === ( $log_entry['event'] ?? '' )
		&& 'info' === ( $log_entry['level'] ?? '' )
		&& false === stripos( (string) ( $log_entry['message'] ?? '' ), '<script' )
		&& false === stripos( (string) wp_json_encode( $log_entry['context'] ?? array() ), '<img' ),
		'An administrator client log returns 200, lands once in the activity buffer and is stored sanitized'
	);

	$failed_trace = $marker . '-failed';
	$failed_log   = $dispatch(
		1,
		'/efb/log',
		array(
			'event'    => 'apply_report',
			'trace_id' => $failed_trace,
			'context'  => array( 'failed' => array( 'remove_field' ) ),
		)
	);
	$failed_rows  = $log_events( $failed_trace );
	$check(
		200 === $failed_log->get_status() && 1 === count( $failed_rows ) && 'error' === ( $failed_rows[0]['level'] ?? '' ),
		'A client report with failed operations is logged at error level'
	);

	$no_event = $dispatch( 1, '/efb/log', array( 'trace_id' => $marker . '-empty' ) );
	$check(
		400 === $no_event->get_status() && 'invalid_payload' === ( (array) $no_event->get_data() )['code'] && array() === $log_events( $marker . '-empty' ),
		'A client log without an event is rejected with 400 invalid_payload, not 500'
	);

	$subscriber_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot_efbcontract_' . wp_generate_password( 10, false, false ),
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'efb-contract-' . wp_generate_password( 10, false, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	$subscriber_id  = is_wp_error( $subscriber_id ) ? 0 : (int) $subscriber_id;
	$sub_trace      = $marker . '-subscriber';
	$prompts_before = count( $provider_prompts );
	$sub_log        = $dispatch(
		$subscriber_id,
		'/efb/log',
		array(
			'event'    => 'contract_check',
			'trace_id' => $sub_trace,
		)
	);
	$sub_chat       = $dispatch(
		$subscriber_id,
		'/efb/chat',
		array(
			'message'        => 'Make this field required.',
			'trace_id'       => $sub_trace,
			'has_form'       => true,
			'fields'         => $fields,
			'selected_field' => 'work_email',
		)
	);
	$check(
		$subscriber_id > 0
		&& in_array( $sub_log->get_status(), array( 401, 403 ), true )
		&& in_array( $sub_chat->get_status(), array( 401, 403 ), true )
		&& array() === $log_events( $sub_trace )
		&& count( $provider_prompts ) === $prompts_before,
		'A subscriber is refused on /efb/log and /efb/chat with no log entry and no provider request'
	);

	$valid = $chat(
		$marker . '-valid',
		'work_email',
		true,
		array(
			'selected_label' => 'Spoofed client label',
			'selected_type'  => 'html',
		)
	);
	$check(
		200 === $valid['response']->get_status()
		&& 1 === count( $valid['prompts'] )
		&& str_contains( $valid['prompt'], 'SELECTED_FIELD: id="work_email" label="Work email" type=email' )
		&& ! str_contains( $valid['prompt'], 'Spoofed client label' )
		&& ! str_contains( $valid['prompt'], '<b>' ),
		'A valid selected ID reaches the prompt with label and type from the sanitized catalogue, not from the client'
	);

	$received = array_values(
		array_filter(
			$log_events( $marker . '-valid' ),
			static fn( array $entry ): bool => 'efb.chat.received' === ( $entry['event'] ?? '' )
		)
	);
	$check(
		1 === count( $received ) && 'work_email' === ( $received[0]['context']['selected_id'] ?? '' ),
		'The chat trace records the resolved selected field ID'
	);

	$ghost = $chat( $marker . '-ghost', 'ghost_field' );
	$check(
		200 === $ghost['response']->get_status()
		&& 1 === count( $ghost['prompts'] )
		&& str_contains( $ghost['prompt'], 'CURRENT_FIELDS:' )
		&& ! str_contains( $ghost['prompt'], 'SELECTED_FIELD' )
		&& ! str_contains( $ghost['prompt'], 'ghost_field' ),
		'A selected ID that is not in the field catalogue never reaches the prompt'
	);

	$object = $chat(
		$marker . '-object',
		array(
			'id'    => 'work_email',
			'label' => 'Injected label',
			'type'  => 'html',
		)
	);
	$check(
		200 === $object['response']->get_status()
		&& 1 === count( $object['prompts'] )
		&& ! str_contains( $object['prompt'], 'SELECTED_FIELD' )
		&& ! str_contains( $object['prompt'], 'Injected label' ),
		'A client-built selection object is refused: only a scalar ID is accepted'
	);

	$closed = $chat( $marker . '-closed', 'work_email', false );
	$check(
		200 === $closed['response']->get_status()
		&& 1 === count( $closed['prompts'] )
		&& str_contains( $closed['prompt'], 'FORM_OPEN=no' )
		&& ! str_contains( $closed['prompt'], 'SELECTED_FIELD' ),
		'Selected field context is omitted when no form is open'
	);

	Settings::update( array( 'efb_copilot_enabled' => false ) );
	$gate_trace     = $marker . '-gate';
	$prompts_before = count( $provider_prompts );
	$gate_log       = $dispatch(
		1,
		'/efb/log',
		array(
			'event'    => 'contract_check',
			'trace_id' => $gate_trace,
		)
	);
	$gate_chat      = $dispatch(
		1,
		'/efb/chat',
		array(
			'message'        => 'Make this field required.',
			'trace_id'       => $gate_trace,
			'has_form'       => true,
			'fields'         => $fields,
			'selected_field' => 'work_email',
		)
	);
	$check(
		in_array( $gate_log->get_status(), array( 401, 403 ), true )
		&& in_array( $gate_chat->get_status(), array( 401, 403 ), true )
		&& array() === $log_events( $gate_trace )
		&& count( $provider_prompts ) === $prompts_before,
		'With efb_copilot_enabled=false even an administrator is refused, with no log entry and no provider request'
	);

	Settings::update( array( 'efb_copilot_enabled' => true ) );
	$reopened = $dispatch(
		1,
		'/efb/log',
		array(
			'event'    => 'contract_check',
			'trace_id' => $marker . '-reopened',
		)
	);
	$check(
		200 === $reopened->get_status(),
		'Re-enabling EFB Copilot reopens the route for the administrator'
	);

	$check(
		0 === $other_requests,
		'No outbound HTTP request other than the pre-empted placeholder provider was attempted'
	);
} catch ( Throwable $error ) {
	$check( false, 'EFB Copilot contract suite completes without a fatal error (' . get_class( $error ) . ')' );
} finally {
	if ( null !== $http_filter ) {
		remove_filter( 'pre_http_request', $http_filter, 10 );
	}
	if ( $subscriber_id > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $subscriber_id );
	}
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user_id );
}

$drifted = array();
foreach ( $options as $option ) {
	wp_cache_delete( $option, 'options' );
	// Loose comparison on purpose: arrays compare by value and the sentinel object matches itself.
	if ( get_option( $option, $missing ) != $originals[ $option ] ) {
		$drifted[] = $option;
	}
}
$check(
	array() === $drifted && ( 0 === $subscriber_id || false === get_userdata( $subscriber_id ) ),
	'Settings, key, budget, usage and activity options are restored and the temporary subscriber is removed'
		. ( array() === $drifted ? '' : ' (drifted: ' . implode( ', ', $drifted ) . ')' )
);

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
