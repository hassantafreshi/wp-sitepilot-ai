<?php
/**
 * Local WordPress integration checks for the truthful Activity Log timeline.
 *
 * It owns one generated draft and a bounded fixture log. Both the existing log
 * option and timezone settings are restored in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Activity\ActivityFeed;
use AISAAutoPilot\Activity\ActivityTimeline;
use AISAAutoPilot\Logging\Logger;

$original_user_id  = get_current_user_id();
$original_logs     = get_option( 'aisaautopilot_logs', null );
$original_timezone = get_option( 'timezone_string', null );
$original_offset   = get_option( 'gmt_offset', null );
$checks            = array();
$post_id           = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$find_group = static function ( array $groups, string $label ): array {
	foreach ( $groups as $group ) {
		if ( $label === ( $group['label'] ?? '' ) ) {
			return $group;
		}
	}

	return array();
};

$find_row = static function ( array $groups, string $summary ): array {
	foreach ( $groups as $group ) {
		foreach ( (array) ( $group['rows'] ?? array() ) as $row ) {
			if ( $summary === ( $row['summary'] ?? '' ) ) {
				return $row;
			}
		}
	}

	return array();
};

try {
	wp_set_current_user( 1 );
	update_option( 'timezone_string', 'UTC', false );
	update_option( 'gmt_offset', 0, false );

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'AISAAutoPilot Activity Timeline fixture',
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) || ! is_numeric( $post_id ) ) {
		$post_id = 0;
	} else {
		$post_id = (int) $post_id;
		update_post_meta( $post_id, '_aisaautopilot_generated', '1' );
	}

	$now = (int) strtotime( '2026-09-06T18:00:00+00:00' );
	update_option(
		'aisaautopilot_logs',
		array(
			array(
				'time'    => gmdate( 'c', $now - ( 2 * DAY_IN_SECONDS ) ),
				'level'   => 'error',
				'event'   => 'kb_index',
				'message' => 'Timeline old index issue',
				'context' => array(),
			),
			array(
				'time'    => gmdate( 'c', $now - DAY_IN_SECONDS - 1200 ),
				'level'   => 'warning',
				'event'   => 'handoff_email',
				'message' => 'Timeline handoff review',
				'context' => array(),
			),
			array(
				'time'    => gmdate( 'c', $now - 300 ),
				'level'   => 'info',
				'event'   => 'content.draft_created',
				'message' => 'Timeline content draft',
				'context' => array( 'post_id' => $post_id ),
			),
			array(
				'time'    => gmdate( 'c', $now - 120 ),
				'level'   => 'info',
				'event'   => 'woo.draft.created',
				'message' => 'Timeline missing product',
				'context' => array( 'product_id' => 999999 ),
			),
			array(
				'time'    => 'not-a-date',
				'level'   => 'info',
				'event'   => 'activity.unknown',
				'message' => 'Timeline invalid date',
				'context' => array(),
			),
		),
		false
	);

	$feed   = new ActivityFeed( new Logger() );
	$items  = $feed->recent( 100 );
	$groups = ( new ActivityTimeline( $feed ) )->groups( 100, $now );

	$content_item = array();
	foreach ( $items as $item ) {
		if ( 'content.draft_created' === ( $item['event'] ?? '' ) ) {
			$content_item = $item;
			break;
		}
	}
	$check(
		$post_id > 0
		&& $post_id === (int) ( $content_item['post_id'] ?? 0 )
		&& 0 === (int) ( $content_item['product_id'] ?? 0 )
		&& 0 === (int) ( $content_item['form_id'] ?? 0 ),
		'Activity feed keeps only the non-sensitive resource identifiers needed for a truthful row action'
	);

	$today       = $find_group( $groups, __( 'Today', 'ai-site-assistant-autopilot' ) );
	$content_row = $find_row( $groups, 'Timeline content draft' );
	$store_row   = $find_row( $groups, 'Timeline missing product' );
	$check(
		2 === count( (array) ( $today['rows'] ?? array() ) )
		&& 'Timeline missing product' === ( $today['rows'][0]['summary'] ?? '' )
		&& __( 'Content Studio', 'ai-site-assistant-autopilot' ) === ( $content_row['feature'] ?? '' )
		&& __( 'Recorded', 'ai-site-assistant-autopilot' ) === ( $content_row['state'] ?? '' )
		&& 'off' === ( $content_row['tone'] ?? '' )
		&& __( 'Open draft', 'ai-site-assistant-autopilot' ) === ( $content_row['action']['label'] ?? '' )
		&& false !== strpos( (string) ( $content_row['action']['url'] ?? '' ), 'post=' . $post_id ),
		'Today groups newest-first and exposes Open draft only for the generated draft that still exists'
	);
	$check(
		__( 'Store Copilot', 'ai-site-assistant-autopilot' ) === ( $store_row['feature'] ?? '' )
		&& array_key_exists( 'action', $store_row )
		&& null === $store_row['action']
		&& __( 'Answer Desk', 'ai-site-assistant-autopilot' ) === ( $find_row( $groups, 'Timeline handoff review' )['feature'] ?? '' )
		&& __( 'Review', 'ai-site-assistant-autopilot' ) === ( $find_row( $groups, 'Timeline handoff review' )['state'] ?? '' )
		&& __( 'Site Knowledge', 'ai-site-assistant-autopilot' ) === ( $find_row( $groups, 'Timeline old index issue' )['feature'] ?? '' )
		&& __( 'Needs attention', 'ai-site-assistant-autopilot' ) === ( $find_row( $groups, 'Timeline old index issue' )['state'] ?? '' ),
		'Feature and status labels are derived from actual event namespaces and levels without guessed actors or rollback controls'
	);
	$invalid_row = $find_row( $groups, 'Timeline invalid date' );
	$check(
		__( 'Date unavailable', 'ai-site-assistant-autopilot' ) === ( $groups[0]['label'] ?? '' )
		&& '—' === ( $invalid_row['when'] ?? '' )
		&& '' === ( $invalid_row['time_iso'] ?? '' ),
		'Invalid timestamps remain explicit and never receive a false Today label'
	);

	$seed = static function ( array $entries ): void {
		update_option( 'aisaautopilot_logs', $entries, false );
	};
	$entry = array( 'time' => gmdate( 'c', $now ), 'level' => 'info', 'event' => 'content.draft_created', 'message' => 'Guarded draft', 'context' => array( 'post_id' => $post_id ) );
	foreach ( array( -$post_id, (string) $post_id . 'invalid', (float) $post_id, array( $post_id ), true ) as $invalid_id ) {
		$entry['context']['post_id'] = $invalid_id;
		$seed( array( $entry ) );
		$check( null === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['action'], 'Malformed resource ID cannot become an edit link: ' . gettype( $invalid_id ) );
	}
	$entry['context']['post_id'] = $post_id;
	$seed( array( $entry ) );
	wp_set_current_user( 0 );
	$check( null === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['action'], 'A user without edit permission gets no resource action' );
	wp_set_current_user( 1 );
	delete_post_meta( $post_id, '_aisaautopilot_generated' );
	$check( null === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['action'], 'An unmarked resource gets no generated-draft action' );
	update_post_meta( $post_id, '_aisaautopilot_generated', '1' );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'trash' ) );
	$check( null === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['action'], 'A trashed resource gets no edit action' );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	$entry['event'] = 'woo.draft.created';
	$entry['context'] = array( 'product_id' => $post_id );
	$seed( array( $entry ) );
	$check( null === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['action'], 'A post cannot be mistaken for a WooCommerce product' );

	$entry['event'] = 'action.executed';
	foreach ( array( 'content_draft' => 'Content Studio', 'woocommerce_product_draft' => 'Store Copilot', 'native_form' => 'Form Studio', 'easy_form_builder' => 'Form Studio', 'unknown' => 'AutoPilot' ) as $connector => $feature ) {
		$entry['context'] = array( 'connector_id' => $connector );
		$seed( array( $entry ) );
		$check( $feature === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['feature'], 'Generic action uses the actual connector: ' . $connector );
	}
	// T16: the copilot inside the Easy Form Builder editor writes efb.*; Form Studio does not.
	foreach ( array( 'efb.client.chat', 'efb.customize', 'efb.save', 'efb.apply', 'efb.plan.provider_error' ) as $efb_event ) {
		$entry['event']   = $efb_event;
		$entry['context'] = array();
		$seed( array( $entry ) );
		$check( __( 'Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ) === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['rows'][0]['feature'], 'EFB copilot event is labelled Easy Form Builder Copilot, not Form Studio: ' . $efb_event );
	}
	$entry['event'] = 'action.executed';
	foreach ( array( 'tomorrow', '2026-09-06 18:00:00', '2026-02-30T18:00:00Z', '2026-09-06T25:00:00Z' ) as $invalid_time ) {
		$entry['time'] = $invalid_time;
		$seed( array( $entry ) );
		$check( 'unavailable' === ( new ActivityTimeline( $feed ) )->groups( 100, $now )[0]['day_key'], 'Invalid or relative date fails closed: ' . $invalid_time );
	}
	update_option( 'timezone_string', 'Asia/Tehran', false );
	$entry['time'] = '2026-09-06T21:00:00Z';
	$seed( array( $entry ) );
	$local_groups = ( new ActivityTimeline( $feed ) )->groups( 100, (int) strtotime( '2026-09-06T21:30:00Z' ) );
	$check( '2026-09-07' === $local_groups[0]['day_key'] && __( 'Today', 'ai-site-assistant-autopilot' ) === $local_groups[0]['label'] && '00:30' === $local_groups[0]['rows'][0]['when'], 'UTC midnight boundary uses the WordPress timezone and accepts ISO Z timestamps' );
	$seed( array( null, 'malformed', array( 'time' => array(), 'level' => array(), 'event' => array(), 'message' => '<b>Bearer fixture-token</b>', 'context' => array( 'trace_id' => array() ) ) ) );
	$items = $feed->recent( 100 );
	$check( 1 === count( $items ) && '[REDACTED]' === $items[0]['message'] && '' === $items[0]['trace_id'] && '' === $items[0]['time'], 'Malformed stored rows are safe and legacy messages are redacted on read' );
	$seed( array_fill( 0, 110, $entry ) );
	$check( 100 === count( $feed->recent( 999 ) ) && 1 === count( $feed->recent( 0 ) ) && array() === $feed->recent( 100, 'error' ), 'Legacy feed limit and level filtering remain bounded and compatible' );
} finally {
	if ( $post_id > 0 ) {
		wp_delete_post( $post_id, true );
	}
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
	if ( null === $original_timezone ) {
		delete_option( 'timezone_string' );
	} else {
		update_option( 'timezone_string', $original_timezone, false );
	}
	if ( null === $original_offset ) {
		delete_option( 'gmt_offset' );
	} else {
		update_option( 'gmt_offset', $original_offset, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
