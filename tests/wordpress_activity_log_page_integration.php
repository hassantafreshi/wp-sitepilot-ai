<?php
/**
 * Local WordPress render checks for the canonical mockup-shaped Activity Log.
 *
 * A generated draft and the previous activity option are restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\LogExport;
use AISAAutoPilot\Admin\LogsPage;

$original_user_id = get_current_user_id();
$original_logs    = get_option( 'aisaautopilot_logs', null );
$checks           = array();
$post_id          = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function (): string {
	ob_start();
	( new LogsPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'AISAAutoPilot Activity Log page fixture',
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

	update_option(
		'aisaautopilot_logs',
		array(
			array(
				'time'    => gmdate( 'c', time() - 60 ),
				'level'   => 'error',
				'event'   => 'kb_index',
				'message' => 'Activity Log page error fixture',
				'context' => array(),
			),
			array(
				'time'    => gmdate( 'c', time() - 30 ),
				'level'   => 'info',
				'event'   => 'content.draft_created',
				'message' => 'Activity Log page draft fixture',
				'context' => array( 'post_id' => $post_id ),
			),
		),
		false
	);
	$html = $render();
	$check(
		1 === substr_count( $html, 'data-sp-activity-timeline' )
		&& 1 === substr_count( $html, 'data-sp-activity-group' )
		&& 2 === substr_count( $html, 'data-sp-activity-row' )
		&& false !== strpos( $html, 'class="sp-activity-card"' )
		&& false !== strpos( $html, 'Recorded AutoPilot activity, with a real draft link when one is available.' )
		&& false === strpos( $html, 'with a way back.' )
		&& false !== strpos( $html, esc_url( LogExport::url() ) ),
		'Activity Log renders the mockup card, grouped rows, truthful header copy and real CSV header action'
	);
	$check(
		false !== strpos( $html, 'sp-activity-row__when' )
		&& false !== strpos( $html, 'sp-activity-row__feature' )
		&& false !== strpos( $html, 'sp-activity-row__summary' )
		&& false !== strpos( $html, 'sp-activity-row__state--bad' )
		&& false !== strpos( $html, 'Open draft' )
		&& false === strpos( $html, 'sp-logs-search' )
		&& false === strpos( $html, 'sp-logs-level' )
		&& false === strpos( $html, 'aisaautopilot_logs_nonce' )
		&& false === strpos( $html, 'Clear log' )
		&& false === strpos( $html, 'sp-tr--head' ),
		'Activity Log keeps the five-part truthful timeline row and removes legacy filters, raw table and destructive clear surface'
	);

	update_option( 'aisaautopilot_logs', array(), false );
	$empty_html = $render();
	$check(
		1 === substr_count( $empty_html, 'data-sp-activity-empty' )
		&& false !== strpos( $empty_html, 'No changes yet' )
		&& false !== strpos( $empty_html, 'No activity is currently stored.' )
		&& 0 === substr_count( $empty_html, 'data-sp-activity-group' )
		&& 0 === substr_count( $empty_html, 'data-sp-activity-row' )
		&& false === strpos( $empty_html, '7 recorded events' ),
		'Activity Log empty state has no phantom timeline card, fake count or unbacked action'
	);
} finally {
	if ( $post_id > 0 ) {
		wp_delete_post( $post_id, true );
	}
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
