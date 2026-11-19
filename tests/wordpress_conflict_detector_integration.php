<?php
/**
 * Local WordPress checks for the Integrations warning about another chat or AI assistant
 * plugin (Core\ConflictDetector, T31). Nothing leaves the site: the active plugin list is
 * replaced in this process with `option_active_plugins`, and plugin names come from a
 * primed plugin header cache, so no plugin is installed or activated.
 *
 * Proves no warning without such a plugin; an AI assistant is always reported, a chat
 * plugin only while the Answer Desk chat button is on; real plugin names and plural text;
 * the Open Plugins link; hiding is per administrator and per site, accepts only detected
 * plugins, and another listed plugin activated later brings the warning back; the route is for
 * administrators only; and the filter extends the list with valid kinds only.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

use AISAAutoPilot\Admin\IntegrationsPage;
use AISAAutoPilot\Core\ConflictDetector;
use AISAAutoPilot\Core\Settings;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};

global $wpdb;
$admin             = 1;
$original_settings = get_option( Settings::OPTION );
$original_hidden   = get_user_option( ConflictDetector::DISMISSED_OPTION, $admin );
$editor            = 0;
$fake              = array(
	'tidio-live-chat/tidio-elements.php' => 'Tidio QA',
	'chaty/cht-icons.php'                => 'Chaty QA',
	'ai-engine/ai-engine.php'            => 'AI Engine QA',
	'my-chatbot/my-chatbot.php'          => 'Lookalike QA',
);
$extra = array();
// The site's real plugins plus the fake ones chosen for each step.
$active_filter = static function ( $value ) use ( &$extra ) {
	return array_values( array_unique( array_merge( (array) $value, $extra ) ) );
};
// Plugin headers for the fake files, merged into WordPress's own plugin list.
$prime = static function () use ( $fake ): void {
	wp_cache_delete( 'plugins', 'plugins' );
	$all = get_plugins();
	foreach ( $fake as $file => $name ) {
		$all[ $file ] = array( 'Name' => $name, 'Version' => '1.0' );
	}
	wp_cache_set( 'plugins', array( '' => $all ), 'plugins' );
};
$render = static function (): string {
	wp_set_current_user( 1 );
	$_GET = array( 'page' => 'aisaautopilot-integrations' );
	ob_start();
	( new IntegrationsPage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();
	return $html;
};
$answer_desk = static function ( bool $on ): void {
	Settings::update( array( 'public_assistant_enabled' => $on, 'widget' => array( 'enabled' => $on ) ) );
};
$dismiss = static function ( array $plugins, int $user ): WP_REST_Response {
	wp_set_current_user( $user );
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/integrations/conflicts/dismiss' );
	$request->set_param( 'plugins', $plugins );
	$response = rest_do_request( $request );
	wp_set_current_user( 1 );
	return $response;
};

add_filter( 'option_active_plugins', $active_filter );
try {
	delete_user_option( $admin, ConflictDetector::DISMISSED_OPTION );
	$prime();

	// 1. No chat or AI plugin, no warning.
	$answer_desk( true );
	$check( array() === ( new ConflictDetector() )->detect() && false === strpos( $render(), 'data-sp-conflict=' ), 'No warning when no other chat or AI plugin is active' );

	// 2. An AI assistant and a chat plugin with the Answer Desk chat button off: only the AI warning.
	$extra = array( 'tidio-live-chat/tidio-elements.php', 'ai-engine/ai-engine.php', 'my-chatbot/my-chatbot.php' );
	$answer_desk( false );
	$html = $render();
	$check(
		false !== strpos( $html, 'data-sp-conflict="ai_assistant"' ) && false === strpos( $html, 'data-sp-conflict="chat_widget"' )
		&& false !== strpos( $html, '“AI Engine QA” is also an AI assistant' ) && false === strpos( $html, 'Lookalike QA' ),
		'An AI assistant is reported by its real name; a chat plugin is not while the Answer Desk chat button is off; a lookalike folder is not reported'
	);

	// 3. Chat button on: the chat plugin is reported with "may overlap" and the Open Plugins link.
	$answer_desk( true );
	$html = $render();
	$check(
		false !== strpos( $html, 'data-sp-conflict="chat_widget"' ) && false !== strpos( $html, 'Another chat plugin is active' )
		&& false !== strpos( $html, '“Tidio QA” also puts a chat button on your site. It may overlap' )
		&& false !== strpos( $html, 'href="' . esc_url( admin_url( 'plugins.php' ) ) . '"' ),
		'With the Answer Desk chat button on, the chat plugin is reported ("may overlap") with an Open Plugins link'
	);

	// 4. Two chat plugins: one warning naming both, plural text.
	$extra[] = 'chaty/cht-icons.php';
	$html    = $render();
	$check(
		1 === substr_count( $html, 'data-sp-conflict="chat_widget"' ) && false !== strpos( $html, 'Other chat plugins are active' )
		&& false !== strpos( $html, '“Tidio QA” and “Chaty QA” also put chat buttons' ),
		'Two chat plugins are named together in one warning with plural text'
	);

	// 5. Hiding only accepts plugins detected now; a non-administrator cannot hide.
	$bogus     = $dismiss( array( 'hello-dolly/hello.php', '../../wp-config.php' ), 1 );
	$editor    = wp_insert_user( array( 'user_login' => 'aisa_qa_conflict_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	$forbidden = $dismiss( array( 'tidio-live-chat/tidio-elements.php' ), (int) $editor );
	$check(
		400 === $bogus->get_status() && 'not_detected' === $bogus->get_data()['code'] && array() === ConflictDetector::dismissed( $admin )
		&& 403 === $forbidden->get_status() && array() === ConflictDetector::dismissed( (int) $editor ),
		'Hiding refuses plugins that are not detected (nothing stored) and refuses non-administrators (403)'
	);

	// 6. Hiding the chat warning keeps the AI warning; it is stored per site for this user.
	$hidden = $dismiss( array( 'tidio-live-chat/tidio-elements.php', 'chaty/cht-icons.php' ), 1 );
	$html   = $render();
	$meta   = get_user_meta( $admin, $wpdb->get_blog_prefix() . ConflictDetector::DISMISSED_OPTION, true );
	$check(
		200 === $hidden->get_status() && false === strpos( $html, 'data-sp-conflict="chat_widget"' ) && false !== strpos( $html, 'data-sp-conflict="ai_assistant"' )
		&& is_array( $meta ) && 2 === count( $meta ),
		'Hiding the chat warning keeps the AI warning, and is stored as a per-site option of this administrator'
	);

	// 7. Another chat plugin activated later brings the warning back, naming only the new one.
	$fake_new = 'click-to-chat-for-whatsapp/click-to-chat.php';
	$extra[]  = $fake_new;
	$all      = get_plugins();
	$all[ $fake_new ] = array( 'Name' => 'Click to Chat QA', 'Version' => '1.0' );
	wp_cache_set( 'plugins', array( '' => $all ), 'plugins' );
	$html = $render();
	$check(
		false !== strpos( $html, '“Click to Chat QA” also puts a chat button' ) && false === strpos( $html, 'Tidio QA' ),
		'A chat plugin activated after hiding brings the warning back, naming only the new plugin'
	);

	// 8. The filter adds a plugin; an unknown kind is ignored.
	$filter = static function ( array $list ): array {
		$list['my-chatbot']   = ConflictDetector::KIND_CHAT;
		$list['wrong-kind']   = 'spam';
		return $list;
	};
	add_filter( ConflictDetector::FILTER, $filter );
	$known = ConflictDetector::known();
	$html  = $render();
	remove_filter( ConflictDetector::FILTER, $filter );
	$check(
		isset( $known['my-chatbot'] ) && ! isset( $known['wrong-kind'] ) && false !== strpos( $html, 'Lookalike QA' ),
		'The aisaautopilot_conflicting_plugins filter extends the list; entries with an unknown kind are ignored'
	);
} finally {
	remove_filter( 'option_active_plugins', $active_filter );
	wp_cache_delete( 'plugins', 'plugins' );
	update_option( Settings::OPTION, $original_settings );
	if ( is_array( $original_hidden ) ) {
		update_user_option( $admin, ConflictDetector::DISMISSED_OPTION, $original_hidden );
	} else {
		delete_user_option( $admin, ConflictDetector::DISMISSED_OPTION );
	}
	if ( $editor && ! is_wp_error( $editor ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $editor );
	}
	wp_set_current_user( 0 );
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
