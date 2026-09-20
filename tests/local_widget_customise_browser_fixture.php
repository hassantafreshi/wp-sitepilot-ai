<?php
/**
 * Creates and restores the isolated Widget customisation browser-QA fixture.
 *
 * Prepare snapshots the whole general settings option (including whether it existed) and sets
 * a known customise baseline: Answer Desk on so the visitor widget can be checked, automatic
 * indexing off, the chat shown on every page, and one published QA page carrying a marker for
 * "Pages where the chat appears". Restore deletes only pages with that marker and writes the
 * snapshot back exactly. Handoff credentials live in their own option and are never read or
 * changed here.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_widget_customise_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

const FIXTURE_OPTION = 'aisaautopilot_widget_customise_browser_fixture';
const QA_PAGE_META   = '_aisaautopilot_browser_fixture';
const QA_PAGE_MARKER = 'widget-visibility';
const QA_PAGE_TITLE  = 'Visibility QA checkout aisaautopilot-visibility-qa';

$delete_qa_pages = static function (): void {
	$ids = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'numberposts' => 20,
			'fields'      => 'ids',
			'meta_key'    => QA_PAGE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- local QA fixture only.
			'meta_value'  => QA_PAGE_MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- local QA fixture only.
		)
	);
	foreach ( $ids as $id ) {
		$entry = get_post( (int) $id );
		if ( $entry instanceof WP_Post && QA_PAGE_TITLE === $entry->post_title ) {
			wp_clear_scheduled_hook( \AISAAutoPilot\Content\ContentHooks::CRON_ACTION, array( (int) $id ) );
			wp_delete_post( (int) $id, true );
		}
	}
};

$restore = static function () use ( $delete_qa_pages ): void {
	$delete_qa_pages();
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here.
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'exists', $record ) ) {
		throw new RuntimeException( 'Widget customisation fixture snapshot is invalid; live settings were left untouched.' );
	}
	if ( ! empty( $record['exists'] ) ) {
		update_option( Settings::OPTION, $record['value'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Widget customisation browser fixture removed.\n";
	exit( 0 );
}

$restore();

try {
	$missing  = new stdClass();
	$original = get_option( Settings::OPTION, $missing );
	update_option(
		FIXTURE_OPTION,
		array(
			'exists' => $original !== $missing,
			'value'  => $original !== $missing ? $original : null,
		),
		false
	);

	$widget = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'kb_auto_index'            => false,
			'widget'                   => array_merge(
				$widget,
				array(
					'enabled'                      => true,
					'position'                     => 'bottom-right',
					'theme'                        => 'light',
					'color'                        => '#202a8d',
					'custom_colors'                => false,
					'colors'                       => array(),
					'bubble_radius'                => 30,
					'conversation_logging_enabled' => false,
					'conversation_notice'          => 'This conversation may be recorded to help our support team.',
					'widget_visibility'            => 'all',
					'exclude_types'                => array(),
					'exclude_cats'                 => array(),
					'exclude_ids'                  => array(),
					'exclude_pages'                => array(),
				)
			),
		)
	);

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => QA_PAGE_TITLE,
			'post_content' => 'Temporary page for the widget visibility browser check.',
			'meta_input'   => array( QA_PAGE_META => QA_PAGE_MARKER ),
		),
		true
	);
	if ( is_wp_error( $page_id ) || 0 === $page_id ) {
		throw new RuntimeException( 'Could not create the visibility QA page.' );
	}
	echo "{}\n";
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
