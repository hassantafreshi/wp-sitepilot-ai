<?php
/**
 * Local WordPress integration checks for the safe Activity Log CSV export.
 *
 * The endpoint exits after streaming, so this exercises its reusable URL,
 * records and serializer. The pre-existing local log option is restored in
 * finally; no user activity is cleared or retained by the fixture.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\LogExport;
use AISAAutoPilot\Logging\Logger;

$original_user_id = get_current_user_id();
$original_logs    = get_option( 'aisaautopilot_logs', null );
$original_request = $_REQUEST;
$die_handler = static function (): Closure {
	return static function (): void { throw new RuntimeException( 'Export denied by WordPress' ); };
};
$checks           = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

try {
	wp_set_current_user( 1 );
	$token = 'AISAAutoPilot activity CSV ' . wp_generate_password( 8, false, false );
	( new Logger() )->info(
		'activity.export_fixture',
		' =1+1 ' . $token . "\r\nsecond line",
		array(
			'trace_id'  => '+trace-' . $token,
			'action_id' => '-action-' . $token,
		)
	);

	$export    = new LogExport();
	$url       = LogExport::url();
	$url_query = array();
	parse_str( (string) wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_QUERY ), $url_query );
	$records = $export->records();
	$stream  = fopen( 'php://temp', 'w+' );
	$csv     = '';
	if ( false !== $stream ) {
		$export->write_csv( $stream );
		rewind( $stream );
		$csv = (string) stream_get_contents( $stream );
		fclose( $stream );
	}
	$csv_lines  = preg_split( '/\r\n|\r|\n/', trim( $csv ) ) ?: array();
	$csv_header = isset( $csv_lines[0] ) ? str_getcsv( $csv_lines[0] ) : array();
	$csv_row    = isset( $csv_lines[1] ) ? str_getcsv( $csv_lines[1] ) : array();

	$check(
		'aisaautopilot_export_logs' === ( $url_query['action'] ?? '' )
		&& '' !== ( $url_query['_wpnonce'] ?? '' ),
		'Activity Log exposes a dedicated nonce-protected admin export URL'
	);
	$check(
		isset( $records[0] )
		&& 'activity.export_fixture' === ( $records[0]['event'] ?? '' )
		&& false !== strpos( (string) ( $records[0]['message'] ?? '' ), $token )
		&& false !== strpos( (string) ( $records[0]['trace_id'] ?? '' ), '+trace-' . $token )
		&& false !== strpos( (string) ( $records[0]['action_id'] ?? '' ), '-action-' . $token ),
		'Activity Log export reads the newest redacted activity record without changing its data contract'
	);
	$check(
		array( 'time', 'level', 'event', 'trace_id', 'action_id', 'connector_id', 'message' ) === $csv_header
		&& "'+trace-" . $token === ( $csv_row[3] ?? '' )
		&& "'-action-" . $token === ( $csv_row[4] ?? '' )
		&& "'=1+1 " . $token . ' second line' === ( $csv_row[6] ?? '' )
		&& false === strpos( $csv, $token . "\r\nsecond line" )
		&& "'=one" === LogExport::csv_cell( '=one' )
		&& "'+two" === LogExport::csv_cell( '+two' )
		&& "'-three" === LogExport::csv_cell( '-three' )
		&& "'@four" === LogExport::csv_cell( '@four' )
		&& 'plain line  next line' === LogExport::csv_cell( "plain line\r\nnext line" ),
		'Activity Log CSV uses an explicit schema, neutralizes spreadsheet formulas and removes embedded line breaks'
	);
	add_filter( 'wp_die_handler', $die_handler );
	foreach ( array( 0, 1 ) as $user_id ) {
		wp_set_current_user( $user_id );
		$_REQUEST['_wpnonce'] = 'invalid-activity-export-nonce';
		$denied = false;
		try {
			$export->handle_export();
		} catch ( RuntimeException $error ) {
			$denied = 'Export denied by WordPress' === $error->getMessage();
		}
		$check( $denied, 0 === $user_id ? 'Export rejects a user without administrative capability' : 'Export rejects an administrator with an invalid nonce' );
	}
} finally {
	remove_filter( 'wp_die_handler', $die_handler );
	$_REQUEST = $original_request;
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
