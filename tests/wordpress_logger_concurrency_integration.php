<?php
/**
 * Local WordPress integration checks for concurrent Activity Log writes (D-09).
 *
 * Logger keeps its ring buffer in a single option. Two requests that append at
 * the same time each read the option, add their entry and write the whole array
 * back, so whichever writes last silently drops the other entry. This suite
 * reproduces both shapes of that race against the real database:
 *
 * - a stale in-request copy: this process logs, another process appends, and
 *   this process logs again;
 * - real PHP processes appending in parallel.
 *
 * The log option is snapshotted first and restored in finally. Every entry the
 * suite writes carries a random marker in its event name, so the checks never
 * count entries written by the site itself.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';

// Worker mode is started by the parent run below; it is never run by hand.
if ( 'worker' === ( $argv[1] ?? '' ) ) {
	require $wp_root . '/wp-load.php';

	$event    = (string) ( $argv[2] ?? '' );
	$count    = max( 1, (int) ( $argv[3] ?? 1 ) );
	$start_at = (float) ( $argv[4] ?? 0 );
	$worker   = (int) ( $argv[5] ?? 0 );
	if ( 0 !== strpos( $event, 'test.d09.' ) ) {
		exit( 2 );
	}

	// Bootstrapping takes a different time in every process; start together.
	while ( microtime( true ) < $start_at ) {
		usleep( 2000 );
	}

	$logger = new \AISAAutoPilot\Logging\Logger();
	for ( $i = 0; $i < $count; $i++ ) {
		$logger->info( $event, sprintf( 'Worker %d entry %d', $worker, $i ) );
	}
	exit( 0 );
}

require $wp_root . '/wp-load.php';

use AISAAutoPilot\Logging\Logger;

const AISA_D09_OPTION  = 'aisaautopilot_logs';
const AISA_D09_WORKERS = 6;
const AISA_D09_ENTRIES = 15;

$missing  = new stdClass();
$original = get_option( AISA_D09_OPTION, $missing );
$checks   = array();
$token    = strtolower( wp_generate_password( 8, false, false ) );
$event_a  = 'test.d09.' . $token . '.request';
$event_b  = 'test.d09.' . $token . '.parallel';

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

// Read the row itself: the object cache of this process is exactly what the
// race is about, so it cannot be the witness.
$read_raw = static function (): array {
	global $wpdb;
	$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", AISA_D09_OPTION ) );
	$value = null === $raw ? array() : maybe_unserialize( $raw );

	return is_array( $value ) ? $value : array();
};

$count_event = static function ( string $event ) use ( $read_raw ): int {
	return count(
		array_filter(
			$read_raw(),
			static fn ( $entry ): bool => is_array( $entry ) && $event === ( $entry['event'] ?? '' )
		)
	);
};

// What another PHP process does: read the current row, append, write it back,
// without touching this process's cached copy.
$append_from_elsewhere = static function ( string $event ) use ( $read_raw ): void {
	global $wpdb;
	$logs   = $read_raw();
	$logs[] = array(
		'time'    => gmdate( 'c' ),
		'level'   => 'info',
		'event'   => $event,
		'message' => 'Appended by another process',
		'user_id' => 0,
		'context' => array(),
	);
	$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $logs ) ), array( 'option_name' => AISA_D09_OPTION ) );
};

try {
	$logger = new Logger();

	// 1. Stale in-request copy.
	$logger->info( $event_a, 'First entry of this request' );
	$append_from_elsewhere( $event_a );
	$logger->info( $event_a, 'Second entry of this request' );
	$request_count = $count_event( $event_a );
	echo 'REQUEST_ENTRIES ' . $request_count . PHP_EOL;
	$check( 3 === $request_count, 'an entry another process appends between two writes of one request survives the second write' );

	// 2. Parallel processes.
	$start_at = microtime( true ) + 8;
	$procs    = array();
	for ( $worker = 0; $worker < AISA_D09_WORKERS; $worker++ ) {
		$pipes   = array();
		$process = proc_open(
			array( PHP_BINARY, __FILE__, 'worker', $event_b, (string) AISA_D09_ENTRIES, sprintf( '%.4F', $start_at ), (string) $worker ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			null,
			array( 'AISAAUTOPILOT_WP_ROOT' => $wp_root ) + getenv()
		);
		if ( ! is_resource( $process ) ) {
			$check( false, 'worker ' . $worker . ' starts' );
			continue;
		}
		fclose( $pipes[0] );
		$procs[] = array(
			'process' => $process,
			'pipes'   => $pipes,
		);
	}

	$exit_codes = array();
	foreach ( $procs as $proc ) {
		$output = stream_get_contents( $proc['pipes'][1] ) . stream_get_contents( $proc['pipes'][2] );
		fclose( $proc['pipes'][1] );
		fclose( $proc['pipes'][2] );
		$exit_codes[] = proc_close( $proc['process'] );
		if ( '' !== trim( (string) $output ) ) {
			echo 'WORKER_OUTPUT ' . substr( trim( (string) $output ), 0, 500 ) . PHP_EOL;
		}
	}
	$check( AISA_D09_WORKERS === count( $exit_codes ) && array( 0 ) === array_values( array_unique( $exit_codes ) ), AISA_D09_WORKERS . ' worker processes finish with exit code 0' );

	$expected       = AISA_D09_WORKERS * AISA_D09_ENTRIES;
	$parallel_count = $count_event( $event_b );
	echo 'PARALLEL_ENTRIES ' . $parallel_count . ' of ' . $expected . PHP_EOL;
	$check( $expected === $parallel_count, AISA_D09_WORKERS . ' processes appending ' . AISA_D09_ENTRIES . ' entries each at the same moment keep all ' . $expected . ' entries' );

	// 3. The ring buffer keeps its bound and its order.
	$check( count( $read_raw() ) <= 200, 'the log buffer stays within its 200-entry bound' );
	$newest = $logger->recent( 1 );
	$check( isset( $newest[0]['event'] ) && 0 === strpos( (string) $newest[0]['event'], 'test.d09.' . $token ), 'recent() still returns the newest entry first' );
} catch ( Throwable $error ) {
	$check( false, 'suite completed without exception: ' . $error->getMessage() );
} finally {
	if ( $original === $missing ) {
		delete_option( AISA_D09_OPTION );
	} else {
		update_option( AISA_D09_OPTION, $original, false );
	}
}

$leftover = $count_event( $event_a ) + $count_event( $event_b );
$check( 0 === $leftover, 'restore leaves no suite entries in the log option' );

$failed = 0;
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
	if ( ! $item['ok'] ) {
		++$failed;
	}
}
echo count( $checks ) . ' checks, ' . $failed . ' failed' . PHP_EOL;
exit( $failed > 0 ? 1 : 0 );
