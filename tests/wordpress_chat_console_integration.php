<?php
/**
 * Local WordPress contracts for the Ask your knowledge base console.
 *
 * Renders ChatPage in its real state and with the AI service forced off for one render,
 * checks the fa_IR copy, and proves conversation titles and stored answer status.
 * Conversations created here are deleted; the active user, locale and filter are
 * restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ChatPage;
use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;

$original_user = get_current_user_id();
$checks        = array();
$created       = array();
$manager       = new ConversationManager();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function (): string {
	ob_start();
	( new ChatPage() )->render();
	return (string) ob_get_clean();
};

$json_script = static function ( string $html, string $id ): array {
	if ( 1 !== preg_match( '/<script type="application\/json" id="' . preg_quote( $id, '/' ) . '">(.*?)<\/script>/s', $html, $match ) ) {
		return array();
	}
	$data = json_decode( $match[1], true );
	return is_array( $data ) ? $data : array();
};

// Forces "no AI service" for one render without touching the stored settings. A last-priority
// pre_option value wins over any earlier option filter, so the render sees exactly this value.
$stored_settings = get_option( Settings::OPTION, array() );
$without_model   = static function () use ( $stored_settings ): array {
	$value          = is_array( $stored_settings ) ? $stored_settings : array();
	$value['model'] = '';
	return $value;
};

$persian_question = 'ساعت کاری فروشگاه چیست؟';

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	$configured = ( new ProviderManager() )->is_configured();
	$kb         = new KnowledgeBase();
	$kb_total   = $kb->table_exists() ? (int) ( $kb->get_stats()['total'] ?? 0 ) : 0;
	$ready      = $configured && $kb_total > 0;

	$html   = $render();
	$config = $json_script( $html, 'sp-chat-config' );
	$i18n   = $json_script( $html, 'sp-chat-i18n' );

	$check(
		false !== strpos( $html, 'data-sp-chat-console' )
		&& false !== strpos( $html, 'id="sp-chat-list"' )
		&& false !== strpos( $html, 'role="log"' )
		&& false !== strpos( $html, 'id="sp-chat-input"' )
		&& false !== strpos( $html, 'id="sp-mic-btn"' )
		&& false !== strpos( $html, 'id="sp-chat-send"' ),
		'Console renders the conversation list, message log, composer, microphone and send controls'
	);
	$check( 3 === substr_count( $html, 'data-question=' ), 'Welcome offers three suggestions' );
	$check( 1 === preg_match( '/<button type="button" id="sp-chat-send"[^>]*\sdisabled/', $html ), 'Send starts disabled until a question is typed' );
	$check(
		array_key_exists( 'ready', $config ) && $ready === $config['ready']
		&& false !== strpos( (string) ( $config['gapsUrl'] ?? '' ), 'page=' . AdminMenu::SLUG_KB )
		&& str_ends_with( (string) ( $config['gapsUrl'] ?? '' ), '#sp-kb-gaps' )
		&& 1 === preg_match( '/data-sp-chat-ready="' . ( $ready ? '1' : '0' ) . '"/', $html ),
		'Config and markup report real readiness and link unanswered questions to the content gaps card'
	);
	$check(
		isset( $i18n['transcriptReady'], $i18n['deleteTitle'], $i18n['notFound'], $i18n['deleteLabel'] )
		&& false !== strpos( (string) $i18n['deleteLabel'], '%s' ),
		'Script copy for chat.js and voice.js is embedded, including the review-before-send transcript message'
	);
	$check(
		false === stripos( $html, 'api_key' ) && false === strpos( $html, Settings::KEY_OPTION ),
		'Console markup carries no credential fields'
	);
	if ( $ready ) {
		$check(
			false === strpos( $html, 'data-sp-chat-notice=' ) && 1 !== preg_match( '/<textarea id="sp-chat-input"[^>]*\sdisabled/', $html ),
			'A ready console shows no blocking notice and an enabled composer'
		);
	} elseif ( $configured ) {
		$check( false !== strpos( $html, 'data-sp-chat-notice="knowledge"' ), 'An empty index shows the Site Knowledge notice' );
	}

	add_filter( 'pre_option_' . Settings::OPTION, $without_model, PHP_INT_MAX );
	$offline = $render();
	remove_filter( 'pre_option_' . Settings::OPTION, $without_model, PHP_INT_MAX );
	$offline_config = $json_script( $offline, 'sp-chat-config' );
	$check(
		false !== strpos( $offline, 'data-sp-chat-notice="provider"' )
		&& false !== strpos( $offline, 'Not connected — answers are paused' )
		&& false === ( $offline_config['ready'] ?? true )
		&& 1 === preg_match( '/<textarea id="sp-chat-input"[^>]*\sdisabled/', $offline )
		&& 1 === preg_match( '/<button type="button" id="sp-mic-btn"[^>]*\sdisabled/', $offline )
		&& 3 === preg_match_all( '/<button type="button" class="sp-chat__chip" data-question="[^"]*"\s+disabled/', $offline ),
		'Without an AI service the console says so, links to Setup and locks the composer, microphone and suggestions'
	);
	$check(
		false !== strpos( $offline, 'Asking is locked until a service is connected' )
		&& false !== strpos( $offline, 'Open Setup &amp; Connection' )
		&& false !== strpos( $offline, 'placeholder="Locked — connect an AI service to ask a question"' )
		&& false !== strpos( $offline, 'Suggestions stay locked until a service is connected.' )
		&& false !== strpos( $offline, 'Nothing is sent while this is locked.' )
		&& ( $kb_total > 0 ) === ( false !== strpos( $offline, 'See what is indexed' ) ),
		'The locked console explains why, offers the way to Setup and only offers indexed content when there is some'
	);
	$check(
		false !== strpos( (string) ( $config['healthUrl'] ?? '' ), 'page=' . AdminMenu::SLUG_CONNECTIONS )
		&& false !== strpos( (string) ( $config['usageUrl'] ?? '' ), 'page=' . AdminMenu::SLUG_USAGE )
		&& isset( $i18n['errorTitle'], $i18n['errorProvider'], $i18n['askAgain'], $i18n['openHealth'], $i18n['openUsage'] ),
		'The error card has its Health and Usage & Cost links and its translated copy'
	);

	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa = $render();
		restore_previous_locale();
		$fa_i18n = $json_script( $fa, 'sp-chat-i18n' );
		$check(
			1 === preg_match( '/<h2 class="sp-chat__welcome-title">[^<]*[\x{0600}-\x{06FF}]/u', $fa )
			&& false !== strpos( $fa, 'فقط مدیران این کنسول را' )
			&& 1 === preg_match( '/[\x{0600}-\x{06FF}]/u', (string) ( $fa_i18n['transcriptReady'] ?? '' ) ),
			'fa_IR renders the console and its script copy in Persian'
		);
	} else {
		$check( false, 'fa_IR is available for the console copy check' );
	}

	$english_id = $manager->create();
	$created[]  = $english_id;
	$manager->add_message(
		$english_id,
		array(
			'role'    => 'user',
			'content' => 'Do you ship to Canada?',
		)
	);
	$english = $manager->get( $english_id );
	$check( 'Do you ship to Canada?' === (string) ( $english['title'] ?? '' ), 'A new conversation takes its title from the first question' );

	if ( switch_to_locale( 'fa_IR' ) ) {
		$persian_id  = $manager->create();
		$created[]   = $persian_id;
		$placeholder = (string) ( $manager->get( $persian_id )['title'] ?? '' );
		$manager->add_message(
			$persian_id,
			array(
				'role'    => 'user',
				'content' => $persian_question,
			)
		);
		$manager->add_message(
			$persian_id,
			array(
				'role'    => 'assistant',
				'content' => 'پاسخی در محتوای سایت پیدا نشد.',
				'sources' => array(),
				'status'  => 'not_found',
			)
		);
		restore_previous_locale();
		$persian  = $manager->get( $persian_id );
		$messages = json_decode( (string) ( $persian['messages'] ?? '[]' ), true );
		$check(
			'New conversation' !== $placeholder && $persian_question === (string) ( $persian['title'] ?? '' ),
			'A conversation started in Persian replaces its translated placeholder with the first question'
		);
		$check(
			'not_found' === ( $messages[1]['status'] ?? '' ) && 2 === (int) ( $persian['message_count'] ?? 0 ),
			'Stored answers keep their status, so a reopened conversation still marks unanswered questions'
		);
	}
} catch ( Throwable $error ) {
	$check( false, 'Chat console contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	remove_filter( 'pre_option_' . Settings::OPTION, $without_model, PHP_INT_MAX );
	foreach ( $created as $conversation_id ) {
		$manager->delete( (int) $conversation_id );
	}
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
