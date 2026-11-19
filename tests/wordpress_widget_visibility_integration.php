<?php
/**
 * Local WordPress contracts for "Pages where the chat appears" (F-11).
 *
 * Proves the renderer hides the widget by single entry, content type and post category, reads every
 * key an earlier version could have stored (exclude_ids, exclude_pages, widget_exclude_ids), never
 * compares a category archive's term ID with entry IDs, and that the shortcode follows the same
 * rules. Proves the advanced Widget settings save only valid rules, empty the legacy keys when the
 * picker is saved, and leave the on/off switch, voice and handoff untouched; and that the Widget tab
 * summarises the rules. The twelve retired keys stay in the defaults.
 *
 * Fixture pages, posts and one category carry a marker and are deleted by ID in finally. The
 * settings option, the active user, the request globals, the main query and the locale are
 * restored in finally. Outbound HTTP and mail are blocked for the whole run.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerDeskWidgetPage;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Frontend\ChatWidget;
use AISAAutoPilot\Frontend\ChatWidgetSettings;

$missing        = new stdClass();
$original       = get_option( Settings::OPTION, $missing );
$original_user  = get_current_user_id();
$original_post  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- snapshot for restore.
$original_query = $GLOBALS['wp_query'] ?? null;
$original_main  = $GLOBALS['wp_the_query'] ?? null;
$checks         = array();
$outbound       = 0;
$created_posts  = array();
$created_term   = 0;
$marker         = 'aisaautopilot-visibility-fixture-' . wp_generate_password( 6, false );

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$block_http = static function () use ( &$outbound ) {
	++$outbound;
	return new WP_Error( 'aisaautopilot_test_blocked', 'Outbound HTTP is blocked in this test.' );
};
$block_mail = static function () use ( &$outbound ) {
	++$outbound;
	return false;
};
add_filter( 'pre_http_request', $block_http, 1 );
add_filter( 'pre_wp_mail', $block_mail, 1 );

/** Store the widget option exactly as given, with Answer Desk on in Integrations. */
$store = static function ( array $widget ): void {
	update_option(
		Settings::OPTION,
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array_merge( array( 'enabled' => true ), $widget ),
		),
		false
	);
};

/** Make the given query the main query, as a front-end request for that address would. */
$visit = static function ( array $args ): void {
	$GLOBALS['wp_query']     = new WP_Query( $args );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
};

$shows = static function (): bool {
	return '' !== ( new ChatWidget() )->render();
};

$customiser = static function ( array $post_data = array() ): string {
	$_POST = $post_data;
	ob_start();
	( new ChatWidgetSettings() )->render();
	$html  = (string) ob_get_clean();
	$_POST = array();
	return $html;
};

$widget_tab = static function (): string {
	ob_start();
	( new AnswerDeskWidgetPage() )->render();
	return (string) ob_get_clean();
};

$stored_widget = static function (): array {
	$stored = get_option( Settings::OPTION, array() );
	return is_array( $stored ) && isset( $stored['widget'] ) && is_array( $stored['widget'] ) ? $stored['widget'] : array();
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	// ── Fixture content ─────────────────────────────────────────
	// Fixture entries must never be queued for knowledge-base indexing.
	update_option( Settings::OPTION, array( 'kb_auto_index' => false ), false );
	$term = wp_insert_term( 'Visibility fixture ' . $marker, 'category' );
	if ( is_wp_error( $term ) ) {
		throw new RuntimeException( 'Could not create the fixture category.' );
	}
	$created_term = (int) $term['term_id'];

	$make = static function ( string $type, string $title, array $cats = array() ) use ( &$created_posts, $marker ): int {
		$id = wp_insert_post(
			array(
				'post_type'     => $type,
				'post_status'   => 'publish',
				'post_title'    => $title . ' ' . $marker,
				'post_content'  => 'Fixture for the widget visibility integration.',
				'post_category' => $cats,
			),
			true
		);
		if ( is_wp_error( $id ) || 0 === $id ) {
			throw new RuntimeException( 'Could not create a fixture entry.' );
		}
		$created_posts[] = (int) $id;
		return (int) $id;
	};

	$page_a    = $make( 'page', 'Checkout fixture' );
	$page_b    = $make( 'page', 'Contact fixture' );
	$post_in   = $make( 'post', 'Filed fixture', array( $created_term ) );
	$post_out  = $make( 'post', 'Other fixture', array( (int) get_option( 'default_category', 1 ) ) );
	$trashed   = $make( 'post', 'Trashed fixture' );
	$page_a_ti = get_the_title( $page_a );
	wp_trash_post( $trashed );

	// ── Retired keys and the shared reader ──────────────────────
	$defaults = Settings::widget_defaults();
	$retired  = array( 'btn_size', 'btn_shape', 'btn_color', 'btn_icon', 'btn_animation', 'btn_offset_x', 'btn_offset_y', 'window_border_radius', 'window_accent_color', 'window_header_color', 'window_font_size', 'window_shadow' );
	$check(
		array() === array_diff( $retired, array_keys( $defaults ) ) && 'all' === $defaults['widget_visibility'] && array() === $defaults['exclude_ids'],
		'The twelve retired keys stay in the defaults, and a new site shows the chat on every page'
	);

	$rules = Settings::widget_exclusions(
		array(
			'exclude_ids'        => array( '7', 7, 'abc', -3, array( 1 ) ),
			'exclude_pages'      => array( 9 ),
			'widget_exclude_ids' => array( '11' ),
			'exclude_types'      => array( 'Page', 'page', '' ),
			'exclude_cats'       => array( '4', 0 ),
		)
	);
	$check(
		array( 7, 9, 11 ) === $rules['ids'] && array( 'page' ) === $rules['types'] && array( 4 ) === $rules['cats'],
		'The shared reader merges all three ID keys, drops junk and duplicates, and normalises types and categories'
	);
	$many = Settings::widget_exclusions( array( 'exclude_ids' => range( 1, 250 ) ) );
	$check( Settings::WIDGET_EXCLUDE_IDS_MAX === count( $many['ids'] ), 'The reader caps single entries at the saved limit' );

	// ── Renderer ────────────────────────────────────────────────
	$store(
		array(
			'widget_visibility' => 'all',
			'exclude_ids'       => array( $page_a ),
			'exclude_types'     => array( 'page' ),
		)
	);
	$visit( array( 'page_id' => $page_a ) );
	$check( $shows(), '"Every page" ignores saved rules, so the chat shows on a listed page' );

	$store( array( 'widget_visibility' => 'exclude' ) );
	$visit( array( 'page_id' => $page_a ) );
	$check( $shows(), '"Except" with nothing chosen still shows the chat everywhere' );

	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_ids'       => array( $page_a ),
		)
	);
	$visit( array( 'page_id' => $page_a ) );
	$hidden_a = ! $shows();
	$shortcode_a = ChatWidget::render_standalone();
	$visit( array( 'page_id' => $page_b ) );
	$check( $hidden_a && $shows(), 'A chosen page hides the chat there and nowhere else (the key the default always had, exclude_ids)' );
	$check( '' === $shortcode_a, 'The [aisaautopilot_chat] shortcode follows the same rule' );

	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_pages'     => array( $page_b ),
		)
	);
	$visit( array( 'page_id' => $page_b ) );
	$check( ! $shows(), 'A page stored under exclude_pages is honoured (the old renderer read it and never used it)' );

	$store(
		array(
			'widget_visibility'  => 'exclude',
			'widget_exclude_ids' => array( (string) $page_a ),
		)
	);
	$visit( array( 'page_id' => $page_a ) );
	$check( ! $shows(), 'An ID stored as text under widget_exclude_ids is honoured' );

	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_types'     => array( 'page' ),
		)
	);
	$visit( array( 'page_id' => $page_a ) );
	$type_hides_page = ! $shows();
	$visit( array( 'p' => $post_in ) );
	$check( $type_hides_page && $shows(), 'A ticked content type hides every page of that type and leaves posts alone' );

	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_cats'      => array( $created_term ),
		)
	);
	$visit( array( 'p' => $post_in ) );
	$cat_hides_post = ! $shows();
	$visit( array( 'cat' => $created_term ) );
	$cat_hides_archive = ! $shows();
	$visit( array( 'p' => $post_out ) );
	$check( $cat_hides_post && $cat_hides_archive && $shows(), 'A ticked category hides its posts and its archive, not posts in other categories' );

	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_ids'       => array( $created_term ),
		)
	);
	$visit( array( 'cat' => $created_term ) );
	$check( $shows(), 'An entry ID equal to a category ID never hides that category archive' );

	// ── Advanced settings: render ───────────────────────────────
	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_ids'       => array( $page_a, $trashed ),
			'exclude_types'     => array( 'post' ),
			'voice_enabled'     => false,
			'handoff_enabled'   => true,
		)
	);
	$html = $customiser();
	$check(
		false !== strpos( $html, 'id="answer-desk-visibility"' )
		&& 1 === preg_match( '/name="widget\[widget_visibility\]" value="exclude"\s+checked/', $html )
		&& false !== strpos( $html, 'data-sp-where-url="' . esc_url( rest_url( 'wp/v2/search' ) ) . '"' ),
		'The settings page renders the card with "except" selected and the site search address'
	);
	$check(
		false !== strpos( $html, 'name="widget[exclude_types][]" value="page"' )
		&& 1 === preg_match( '/name="widget\[exclude_types\]\[\]" value="post"\s+checked/', $html )
		&& false === strpos( $html, 'value="attachment"' )
		&& false !== strpos( $html, 'name="widget[exclude_cats][]" value="' . $created_term . '"' ),
		'Content types (without media) and post categories are listed, with saved choices ticked'
	);
	$check(
		false !== strpos( $html, 'data-sp-where-item="' . $page_a . '"' )
		&& false !== strpos( $html, esc_html( $page_a_ti ) )
		&& false === strpos( $html, 'data-sp-where-item="' . $trashed . '"' )
		&& false !== strpos( $html, 'name="widget[exclude_types][]" value="" />' )
		&& false !== strpos( $html, 'name="widget[exclude_cats][]" value="" />' )
		&& false !== strpos( $html, 'name="widget[exclude_ids][]" value="" />' ),
		'A chosen page shows by title, a trashed one is left out, and each list posts an empty value so it can be cleared'
	);
	$check(
		false === strpos( $html, 'name="widget[enabled]"' ) && false === strpos( $html, 'name="widget[voice_enabled]"' ),
		'The on/off switch and voice stay on their own tabs'
	);

	// ── Advanced settings: save ─────────────────────────────────
	$store(
		array(
			'widget_visibility'  => 'all',
			'exclude_pages'      => array( $page_b ),
			'widget_exclude_ids' => array( $page_b ),
			'voice_enabled'      => false,
			'handoff_enabled'    => true,
		)
	);
	$saved = $customiser(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array(
				'widget_visibility' => 'exclude',
				'exclude_types'     => array( '', 'page', 'not_a_type', array( 'nested' ) ),
				'exclude_cats'      => array( '', (string) $created_term, '999999999' ),
				'exclude_ids'       => array( '', (string) $page_a, '999999999', 'abc', '-' . $page_b, (string) $trashed, (string) $page_a ),
			),
		)
	);
	$after = $stored_widget();
	$check( false !== strpos( $saved, 'Settings saved.' ), 'A valid save confirms it on the page' );
	$check(
		'exclude' === $after['widget_visibility']
		&& array( 'page' ) === $after['exclude_types']
		&& array( $created_term ) === $after['exclude_cats']
		&& array( $page_a ) === $after['exclude_ids'],
		'Only real content types, real categories and existing, untrashed entries are stored, once each'
	);
	$check(
		array() === $after['exclude_pages'] && array() === $after['widget_exclude_ids'],
		'Saving the picker empties the legacy keys it displayed, so a removed page stays removed'
	);
	$check(
		true === $after['enabled'] && false === $after['voice_enabled'] && true === $after['handoff_enabled'],
		'The on/off switch, voice and handoff are left exactly as they were'
	);

	$customiser(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array(
				'widget_visibility' => 'all',
				'exclude_types'     => array( '', 'page' ),
				'exclude_cats'      => array( '' ),
				'exclude_ids'       => array( '', (string) $page_a ),
			),
		)
	);
	$after = $stored_widget();
	$check(
		'all' === $after['widget_visibility'] && array( 'page' ) === $after['exclude_types'] && array() === $after['exclude_cats'] && array( $page_a ) === $after['exclude_ids'],
		'"Every page" keeps the chosen rules for later, and an empty list clears its rule'
	);

	$customiser(
		array(
			'_wpnonce' => 'not-a-valid-nonce',
			'widget'   => array( 'widget_visibility' => 'exclude' ),
		)
	);
	$check( 'all' === $stored_widget()['widget_visibility'], 'A request without a valid nonce changes nothing' );

	// ── Widget tab summary ──────────────────────────────────────
	$store( array( 'widget_visibility' => 'all' ) );
	$tab_all = $widget_tab();
	$store(
		array(
			'widget_visibility' => 'exclude',
			'exclude_ids'       => array( $page_a, 999999999 ),
			'exclude_cats'      => array( $created_term ),
		)
	);
	$tab_limited = $widget_tab();
	$store( array( 'widget_visibility' => 'exclude' ) );
	$tab_empty = $widget_tab();
	$check(
		false !== strpos( $tab_all, 'data-sp-widget-where="everywhere"' ) && false !== strpos( $tab_all, 'The chat shows on every page of your site.' ),
		'The Widget tab says the chat shows everywhere by default'
	);
	$check(
		false !== strpos( $tab_limited, 'data-sp-widget-where="limited"' )
		&& false !== strpos( $tab_limited, 'Hidden on 1 single entry and 1 category. Shown everywhere else.' )
		&& false !== strpos( $tab_limited, '#answer-desk-visibility' ),
		'The Widget tab counts only rules that still point at something and links to the editor'
	);
	$check(
		false !== strpos( $tab_empty, 'data-sp-widget-where="everywhere"' ) && false !== strpos( $tab_empty, 'none is chosen yet' ),
		'"Except" with nothing chosen is described as showing everywhere'
	);

	// ── Persian ─────────────────────────────────────────────────
	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa_card = $customiser();
		$fa_tab  = $widget_tab();
		restore_previous_locale();
		$check(
			1 === preg_match( '/<h2 class="sp-wz-card__title" id="sp-wz-where-title">[^<]*[\x{0600}-\x{06FF}]/u', $fa_card )
			&& 1 === preg_match( '/data-sp-widget-where="everywhere"[\s\S]*?<strong>[^<]*[\x{0600}-\x{06FF}]/u', $fa_tab ),
			'fa_IR renders the card title and the Widget tab row in Persian'
		);
	} else {
		$check( false, 'fa_IR is available for the visibility copy check' );
	}

	$check( 0 === $outbound, 'Nothing was sent anywhere during the run' );
} catch ( Throwable $error ) {
	$check( false, 'Widget visibility contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	foreach ( $created_posts as $created_id ) {
		wp_clear_scheduled_hook( \AISAAutoPilot\Content\ContentHooks::CRON_ACTION, array( $created_id ) );
		$entry = get_post( $created_id );
		if ( $entry instanceof WP_Post && false !== strpos( $entry->post_title, $marker ) ) {
			wp_delete_post( $created_id, true );
		}
	}
	if ( $created_term > 0 ) {
		$fixture_term = get_term( $created_term, 'category' );
		if ( $fixture_term instanceof WP_Term && false !== strpos( $fixture_term->name, $marker ) ) {
			wp_delete_term( $created_term, 'category' );
		}
	}
	if ( $original === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original, false );
	}
	$GLOBALS['wp_query']     = $original_query;
	$GLOBALS['wp_the_query'] = $original_main;
	$_POST                   = $original_post;
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	wp_set_current_user( $original_user );
	remove_filter( 'pre_http_request', $block_http, 1 );
	remove_filter( 'pre_wp_mail', $block_mail, 1 );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
