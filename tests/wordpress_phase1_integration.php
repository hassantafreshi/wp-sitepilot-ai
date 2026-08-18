<?php
/**
 * Local WordPress integration check for the isolated candidate plugin.
 *
 * Run only after the candidate has been made active for this request. The
 * script creates one draft form and deletes that exact test post in finally.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ChatPage;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Admin\ConnectionsPage;
use AISAAutoPilot\Admin\EasyFormBuilderPage;
use AISAAutoPilot\Admin\HistoryPage;
use AISAAutoPilot\Admin\HomePage;
use AISAAutoPilot\Admin\KnowledgeBasePage;
use AISAAutoPilot\Admin\LogsPage;
use AISAAutoPilot\Admin\NativeFormsPage;
use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Admin\UsagePage;
use AISAAutoPilot\Admin\WooCommerceDashboardPage;
use AISAAutoPilot\Admin\WooCommerceSettings;
use AISAAutoPilot\Api\FormDeliveryController;
use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Api\PublicChatController;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Api\VoiceController;
use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Connectors\WooCommerceProductSkill;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Frontend\ChatWidgetSettings;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Support\HandoffTelegram;
use AISAAutoPilot\Voice\VoiceTranscriber;

wp_set_current_user( 1 );
$checks = array();
$form_id = 0;
$product_id = 0;
$efb_form_id = 0;
$delivery_mail = array();
$voice_tmp = '';
$original_settings = get_option( Settings::OPTION, null );
$original_handoff_secrets = get_option( Settings::HANDOFF_SECRET_OPTION, null );
$security_conversation_id = 0;
$security_user_id = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'name' => $name, 'ok' => $ok );
};

try {
	$check( class_exists( NativeFormConnector::class ), 'candidate plugin booted' );
	$check( post_type_exists( NativeFormConnector::POST_TYPE ), 'native form post type registered' );

	do_action( 'admin_menu' );
	global $submenu;
	$slugs = array();
	foreach ( (array) ( $submenu[ AdminMenu::SLUG_SETUP ] ?? array() ) as $item ) {
		$slugs[] = (string) ( $item[2] ?? '' );
	}
	$check( in_array( AdminMenu::SLUG_SETUP, $slugs, true ) && in_array( AdminMenu::SLUG_FORM_BUILDER, $slugs, true ) && in_array( AdminMenu::SLUG_FORMS, $slugs, true ), 'wireframe form menus registered' );

	$render = static function ( object $screen ): string {
		ob_start();
		$screen->render();
		return (string) ob_get_clean();
	};
	$page = $render( new CopilotPage() );
	$check( false !== strpos( $page, 'aisaautopilot-voice-toggle' ) && false !== strpos( $page, 'aisaautopilot-compose-screen' ) && false !== strpos( $page, 'aisaautopilot-review-screen' ), 'exclusive Build with AI voice/text UI renders' );
	$home = $render( new HomePage() );
	$check( false !== strpos( $home, 'sp-page-head' ) && false !== strpos( $home, 'page=' . AdminMenu::SLUG_SETUP ), 'home renders and points at the merged Setup & Settings screen' );
	$check( false !== strpos( $render( new ConnectionsPage() ), 'Connections &amp; health' ), 'connections health screen renders' );
	$check( false !== strpos( $render( new NativeFormsPage() ), 'AISAAutoPilot Forms' ), 'simple forms management screen renders' );
	$check( false !== strpos( $render( new EasyFormBuilderPage() ), 'Easy Form Builder' ), 'advanced form connector screen renders' );
	$check( false !== strpos( $render( new LogsPage() ), 'Activity Log' ), 'activity log screen renders' );
	$history = $render( new HistoryPage() );
	$check( false !== strpos( $history, 'Action history' ) && false !== strpos( $history, 'sp-history-search' ), 'action history screen renders with its filters' );
	$chat = $render( new ChatPage() );
	$check( false !== strpos( $chat, 'data-sp-chat' ) && false !== strpos( $chat, 'sp-chat-input' ) && false !== strpos( $chat, 'sp-mic-btn' ), 'redesigned AI chat keeps text and voice composer contracts' );
	$kb_page = $render( new KnowledgeBasePage() );
	$check( false !== strpos( $kb_page, 'data-sp-kb' ) && false !== strpos( $kb_page, 'sp-kb-start-index' ), 'redesigned knowledge base keeps indexing contract' );
	$usage_page = $render( new UsagePage() );
	$check( false !== strpos( $usage_page, 'data-sp-usage' ) && false !== strpos( $usage_page, 'sp-usage-refresh' ) && false !== strpos( $usage_page, 'sp-save-budget' ), 'redesigned usage keeps dashboard controls' );
	$settings_page = $render( new SettingsPage() );
	$check( false !== strpos( $settings_page, 'sp-test' ) && false !== strpos( $settings_page, 'sp-form-notification-email' ) && false !== strpos( $settings_page, 'sp-test-form-delivery' ), 'merged settings keeps provider and delivery controls' );
	$check( false !== strpos( $settings_page, 'sp-setup-testbox' ) && false !== strpos( $settings_page, 'data-sp-provider-chips' ), 'merged settings absorbs the setup wizard connect-and-test steps' );
	$check( false !== strpos( $settings_page, 'sp-model-select' ) && false !== strpos( $settings_page, 'sp-refresh-models' ) && false !== strpos( $settings_page, 'sp-provider-catalog' ), 'model picker offers a list and a live refresh instead of free text only' );
	$widget_page = $render( new ChatWidgetSettings() );
	$check( false !== strpos( $widget_page, 'data-sp-widget' ) && false !== strpos( $widget_page, 'widget[title]' ) && false !== strpos( $widget_page, 'sp-w-stage' ), 'redesigned widget keeps settings and live preview' );
	$woo_dashboard = $render( new WooCommerceDashboardPage() );
	$check( false !== strpos( $woo_dashboard, 'data-sp-wc-dashboard' ) && false !== strpos( $woo_dashboard, 'sp-stat-revenue' ), 'redesigned WooCommerce dashboard keeps live data targets' );
	$woo_settings = $render( new WooCommerceSettings() );
	$check( false !== strpos( $woo_settings, 'data-sp-wc' ) && false !== strpos( $woo_settings, 'woocommerce[enabled]' ) && false !== strpos( $woo_settings, 'sp_woo_nonce' ) && false !== strpos( $woo_settings, 'sp-woo-plan' ), 'redesigned WooCommerce AI keeps settings and draft review contract' );
	$efb_health = ( new EasyFormBuilderConnector() )->get_health();
	$check( ! empty( $efb_health['compatible'] ) && version_compare( (string) $efb_health['version'], '4.1.0', '>=' ), 'Easy Form Builder passes the active version and schema contract' );
	$efb_result = ( new EasyFormBuilderConnector() )->execute_action(
		array(
			'title' => 'AISAAutoPilot EFB integration test',
			'fields' => array(
				array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
				array( 'type' => 'select', 'label' => 'Topic', 'required' => false, 'options' => array( 'Sales', 'Support' ) ),
			),
		)
	);
	$efb_form_id = (int) ( $efb_result['form_id'] ?? 0 );
	global $wpdb;
	$efb_row = $efb_form_id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT form_name, form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $efb_form_id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( 'success' === ( $efb_result['status'] ?? '' ) && is_array( $efb_row ) && 'AISAAutoPilot EFB integration test' === ( $efb_row['form_name'] ?? '' ) && false !== strpos( (string) ( $efb_row['form_structer'] ?? '' ), 'Name' ), 'Easy Form Builder creates an editable native form using its verified storage contract' );

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check( isset( $routes['/aisaautopilot/v1/forms/delivery/test'] ) && isset( $routes['/aisaautopilot/v1/woocommerce/plan'] ) && isset( $routes['/aisaautopilot/v1/woocommerce/analytics/dashboard'] ), 'Phase 1 delivery and WooCommerce routes are registered' );
	$check( isset( $routes['/aisaautopilot/v1/provider/test'] ) && isset( $routes['/aisaautopilot/v1/provider/models'] ), 'provider test and live model-list routes are registered' );
	$check( isset( $routes['/aisaautopilot/v1/chat/voice/transcribe'] ) && isset( $routes['/aisaautopilot/v1/public/voice'] ) && ! isset( $routes['/aisaautopilot/v1/voice/transcribe'] ), 'voice routes use protected admin upload and rate-limited public upload contracts' );
	$voice_response = ( new VoiceController() )->transcribe_chat( new \WP_REST_Request( 'POST' ) );
	$voice_data = $voice_response->get_data();
	$check( 400 === $voice_response->get_status() && 'invalid_audio' === ( $voice_data['code'] ?? '' ), 'admin voice endpoint rejects missing audio before contacting an AI provider' );
	$voice_tmp = wp_tempnam( 'aisaautopilot-voice-contract' );
	file_put_contents( $voice_tmp, 'a' );
	$check( 'audio_too_long' === VoiceTranscriber::validate_file( array( 'tmp_name' => $voice_tmp, 'size' => 1, 'type' => 'audio/webm' ), 45001 ), 'voice contract caps recordings at 45 seconds' );
	$temporary_settings = Settings::all();
	$temporary_settings['widget'] = array_merge( Settings::widget_defaults(), array( 'enabled' => true ) );
	$temporary_settings['public_assistant_enabled'] = true;
	update_option( Settings::OPTION, $temporary_settings, false );
	Settings::set_handoff_secret( 'handoff_telegram_token', 'must-not-leave-server' );
	$widget_settings = ( new ChatWidgetController() )->settings()->get_data();
	$check( empty( $widget_settings['handoff_telegram_token'] ) && ! empty( $widget_settings['voice_enabled'] ), 'public widget settings omit delivery credentials while retaining voice preferences' );
	$public_voice_response = ( new PublicChatController() )->transcribe( new \WP_REST_Request( 'POST' ) );
	$public_voice_data = $public_voice_response->get_data();
	$check( 400 === $public_voice_response->get_status() && 'invalid_audio' === ( $public_voice_data['code'] ?? '' ), 'public widget voice accepts only the safe upload contract' );
	$rate_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$rate_ip  = substr( preg_replace( '/[^0-9a-fA-F:.]/', '', $rate_ip ) ?? '', 0, 64 );
	$rate_key = 'aisaautopilot_rate_public_text_' . hash_hmac( 'sha256', 'ip:' . $rate_ip, wp_salt( 'auth' ) );
	delete_transient( $rate_key );
	$check( RateLimiter::allow( 'public_text', 1, MINUTE_IN_SECONDS ) && ! RateLimiter::allow( 'public_text', 1, MINUTE_IN_SECONDS ), 'central security rate limiter denies excess requests' );
	$handoff_request = new \WP_REST_Request( 'POST' );
	$handoff_request->set_param( 'messages', array( array( 'role' => 'user', 'content' => 'Test' ) ) );
	$handoff_response = ( new ChatWidgetController() )->handoff( $handoff_request );
	$check( 403 === $handoff_response->get_status() && 'handoff_disabled' === ( $handoff_response->get_data()['code'] ?? '' ), 'public handoff cannot deliver unless explicitly enabled' );
	Settings::set_handoff_secret( 'handoff_telegram_token', '123456789:abcdefghijklmnop' );
	$stored_handoff = get_option( Settings::HANDOFF_SECRET_OPTION, array() );
	$stored_settings = get_option( Settings::OPTION, array() );
	$check( ! empty( $stored_handoff['handoff_telegram_token'] ) && false === strpos( (string) $stored_handoff['handoff_telegram_token'], '123456789:abcdefghijklmnop' ) && empty( $stored_settings['widget']['handoff_telegram_token'] ) && '123456789:abcdefghijklmnop' === ( new HandoffTelegram() )->get_token(), 'handoff credentials are encrypted and unavailable in normal settings' );
	$manager = new ConversationManager();
	$security_conversation_id = $manager->create( 'Temporary ownership test' );
	$security_user_id = wp_insert_user(
		array(
			'user_login' => 'aisaautopilot-security-' . wp_generate_password( 12, false, false ),
			'user_pass'  => wp_generate_password( 32, true, true ),
			'user_email' => 'aisaautopilot-security-' . wp_generate_password( 10, false, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	if ( is_wp_error( $security_user_id ) ) {
		$security_user_id = 0;
		$check( false, 'conversations are isolated between administrators' );
	} else {
		wp_set_current_user( $security_user_id );
		$check( null === ( new ConversationManager() )->get( $security_conversation_id ), 'conversations are isolated between administrators' );
		wp_set_current_user( 1 );
	}
	ob_start();
	Plugin::instance()->render_widget();
	$legacy_widget_output = (string) ob_get_clean();
	$check( '' === trim( $legacy_widget_output ), 'enabling the public assistant suppresses the legacy widget so no duplicate launcher is rendered' );

	$plan = array(
		'title'              => 'P1 integration test form',
		'description'        => 'Temporary test form.',
		'notification_email' => (string) get_option( 'admin_email' ),
		'submit_button'      => 'Send',
		'confirmation_message' => 'Thanks',
		'fields'             => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'placeholder' => '', 'options' => array() ),
			array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
			array( 'key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => false, 'placeholder' => '', 'options' => array( 'Sales', 'Support' ) ),
		),
	);
	$connector = new NativeFormConnector();
	$check( array() === $connector->validate_action( $plan ), 'approved email-only form contract validates' );
	$result = $connector->execute_action( $plan, array( 'status' => 'draft' ) );
	$form_id = (int) ( $result['form_id'] ?? 0 );
	$check( 'success' === ( $result['status'] ?? '' ) && $form_id > 0, 'native connector creates draft form' );
	$check( $plan === get_post_meta( $form_id, NativeFormConnector::META_KEY, true ), 'only form definition is stored' );
	$markup = do_shortcode( sprintf( '[aisaautopilot_form id="%d"]', $form_id ) );
	$check( false !== strpos( $markup, 'aisaautopilot-form__honeypot' ) && false === strpos( $markup, 'multipart/form-data' ), 'front-end form has honeypot and no upload surface' );

	delete_transient( 'aisaautopilot_form_delivery_test_' . get_current_user_id() );
	add_filter(
		'pre_wp_mail',
		static function ( $pre, array $args ) use ( &$delivery_mail ) {
			$delivery_mail = $args;
			return true;
		},
		10,
		2
	);
	$delivery_response = ( new FormDeliveryController() )->test();
	$delivery_data     = $delivery_response->get_data();
	$check( ! empty( $delivery_data['ok'] ) && (string) get_option( 'admin_email' ) === (string) ( $delivery_mail['to'] ?? '' ) && false !== strpos( (string) ( $delivery_mail['message'] ?? '' ), 'No visitor data' ), 'delivery test sends a data-free message to the configured recipient' );
	$rate_limited = ( new FormDeliveryController() )->test();
	$check( 429 === $rate_limited->get_status(), 'delivery test is rate-limited per administrator' );
	delete_transient( 'aisaautopilot_form_delivery_test_' . get_current_user_id() );

	if ( WooCommerceConnector::is_active() ) {
		$settings = Settings::all();
		$settings['woocommerce'] = array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false );
		update_option( Settings::OPTION, $settings, false );
		$store = new ActionStore();
		$draft_action = $store->create(
			array(
				'name' => 'AISAAutoPilot P1 test draft', 'description' => 'Temporary product created by the integration test.', 'short_description' => '',
				'price' => '19.99', 'regular_price' => '19.99', 'sale_price' => '', 'categories' => array(), 'sku' => '', 'weight' => '',
				'stock_status' => 'instock', 'stock_quantity' => 0, 'manage_stock' => false, 'status' => 'draft', 'type' => 'simple',
			),
			'woocommerce_product_draft'
		);
		$draft_result = ( new WooCommerceProductSkill( $store ) )->execute( $draft_action );
		$product_id = (int) ( $draft_result['product_id'] ?? 0 );
		$check( ! empty( $draft_result['ok'] ) && $product_id > 0 && 'draft' === get_post_status( $product_id ), 'approved WooCommerce action creates exactly one product draft' );
		$replay_result = ( new WooCommerceProductSkill( $store ) )->execute( $draft_action );
		$check( empty( $replay_result['ok'] ) && 'action_expired' === ( $replay_result['code'] ?? '' ), 'WooCommerce product proposal cannot be replayed' );
	} else {
		$check( ! WooCommerceConnector::is_active(), 'WooCommerce product draft actions stay disabled until WooCommerce is active' );
	}
} finally {
	if ( $form_id > 0 ) {
		wp_delete_post( $form_id, true );
	}
	if ( $product_id > 0 ) {
		wp_delete_post( $product_id, true );
	}
	if ( $efb_form_id > 0 ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $efb_form_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	if ( $security_conversation_id > 0 ) {
		wp_set_current_user( 1 );
		( new ConversationManager() )->delete( $security_conversation_id );
	}
	if ( $security_user_id > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $security_user_id );
	}
	if ( '' !== $voice_tmp && file_exists( $voice_tmp ) ) {
		unlink( $voice_tmp );
	}
	if ( null === $original_settings ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	if ( null === $original_handoff_secrets ) {
		delete_option( Settings::HANDOFF_SECRET_OPTION );
	} else {
		update_option( Settings::HANDOFF_SECRET_OPTION, $original_handoff_secrets, false );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
