<?php
/**
 * Local WordPress integration checks for the Site Knowledge mockup hub.
 *
 * The test adds one directly-seeded KB entry so it can verify the populated
 * canonical state without a provider request or a destructive table reset.
 */
declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\KnowledgeBasePage;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;

wp_set_current_user( 1 );

$checks            = array();
$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_get      = $_GET;
$post_id           = 0;
$knowledge_base    = new KnowledgeBase();
$indexer           = new ContentIndexer();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$restore_settings = static function ( $original, object $sentinel ): void {
	if ( $original === $sentinel ) {
		delete_option( Settings::OPTION );
		return;
	}

	update_option( Settings::OPTION, $original, false );
};

$render = static function ( string $tab = 'overview' ): string {
	$_GET = array(
		'page' => AdminMenu::SLUG_KB,
		'tab'  => $tab,
	);
	ob_start();
	( new KnowledgeBasePage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();

	return $html;
};

try {
	$knowledge_base->create_table();
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'AISAAutoPilot Site Knowledge UI fixture',
			'post_content' => 'Temporary local fixture for the Site Knowledge mockup contract.',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( $post_id->get_error_message() );
	}
	$post_id = (int) $post_id;
	wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );

	$seeded = $knowledge_base->upsert(
		array(
			'post_id'      => $post_id,
			'post_type'    => 'post',
			'title'        => 'AISAAutoPilot Site Knowledge UI fixture',
			'summary'      => 'Direct local fixture only; no AI provider is contacted.',
			'keywords'     => 'site knowledge, ui, fixture',
			'category'     => 'Testing',
			'content_type' => 'article',
			'content_hash' => hash( 'sha256', 'knowledge-hub-' . $post_id ),
			'token_count'  => 1,
		)
	);
	if ( ! $seeded ) {
		throw new RuntimeException( 'Could not seed the temporary Knowledge Base entry.' );
	}

	Settings::update( array( 'kb_auto_index' => false ) );
	$overview = $render();
	preg_match_all( '/data-sp-subtab="([^"]+)"/', $overview, $subtab_matches );
	$source_count = count( $indexer->get_indexable_types_with_counts() );

	$check(
		array( 'overview', 'collections', 'queue', 'sources' ) === ( $subtab_matches[1] ?? array() )
		&& 4 === substr_count( $overview, 'sp-subtabs__tab' ),
		'Site Knowledge renders exactly the four visible mockup tabs in canonical order'
	);
	$check(
		false !== strpos( $overview, 'data-sp-kb-overview' )
		&& false !== strpos( $overview, 'data-sp-kb-coverage' )
		&& false !== strpos( $overview, 'data-sp-kb-sources' )
		&& false !== strpos( $overview, 'Manual sync' )
		&& $source_count === substr_count( $overview, 'data-sp-kb-source=' ),
		'Populated Overview uses actual readable source data and the persisted auto-sync state'
	);
	$check(
		false === strpos( $overview, 'id="sp-kb-rows"' )
		&& false === strpos( $overview, 'id="sp-kb-search"' )
		&& false === strpos( $overview, 'Ask your KB' )
		&& 1 === substr_count( $overview, 'id="sp-kb-progress"' )
		&& 1 === substr_count( $overview, 'id="sp-kb-resume"' )
		&& false !== strpos( $overview, 'data-sp-kb-resume' )
		&& false !== strpos( $overview, 'If the local token cap pauses it' ),
		'Canonical Overview exposes one browser-session-only resume control for a token-cap pause'
	);

	$collections = $render( 'collections' );
	$queue       = $render( 'queue' );
	$sources     = $render( 'sources' );
	$check(
		false !== strpos( $collections, 'data-sp-kb-collections' )
		&& false !== strpos( $collections, 'Managed by index scope' )
		&& false === strpos( $collections, 'Add a collection' ),
		'Collections render as truthful derived collections without an unsupported persistence control'
	);
	$check(
		false !== strpos( $queue, 'data-sp-kb-queue' )
		&& false !== strpos( $queue, 'Browser session only' )
		&& false !== strpos( $queue, 'data-sp-kb-queue-scope="browser-session"' )
		&& false !== strpos( $queue, 'No active indexing pass' )
		&& false === strpos( $queue, 'Retry the 3 failed' )
		&& 1 === substr_count( $queue, 'id="sp-kb-progress"' ),
		'Queue is explicit about its session-only implementation and does not imitate a persistent retry queue'
	);
	$check(
		false !== strpos( $sources, 'data-sp-kb-sync-sources' )
		&& $source_count === substr_count( $sources, 'data-sp-kb-source=' )
		&& false !== strpos( $sources, 'Only the WordPress post types listed above are connected in this version.' ),
		'Sync Sources lists only the backend-supported WordPress source types'
	);

	$ask     = $render( 'ask' );
	$entries = $render( 'entries' );
	$check(
		false !== strpos( $ask, 'Open admin chat console' )
		&& false !== strpos( $entries, 'id="sp-kb-rows"' )
		&& false !== strpos( $entries, '"listFailedBody":' )
		&& false !== strpos( $entries, '"tryAgain":' )
		&& false === strpos( $ask, 'data-sp-subtab="ask"' )
		&& false === strpos( $entries, 'data-sp-subtab="entries"' ),
		'Legacy Ask and indexed-entry screens remain direct deep aliases, not visible tabs'
	);

	Settings::update( array( 'kb_auto_index' => true ) );
	$auto_sync_overview = $render();
	$check(
		false !== strpos( $auto_sync_overview, 'Auto-sync on publish' )
		&& false === strpos( $auto_sync_overview, 'Manual sync' ),
		'Overview reflects the enabled auto-index setting without changing Site Knowledge availability'
	);
} finally {
	if ( $post_id > 0 ) {
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $post_id ) );
		$knowledge_base->delete_by_post_id( $post_id );
		wp_delete_post( $post_id, true );
	}
	$restore_settings( $original_settings, $missing );
	$_GET = $original_get;
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
