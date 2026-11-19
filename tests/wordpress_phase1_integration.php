<?php
/**
 * Local WordPress integration check for the isolated candidate plugin.
 *
 * Run only after the candidate has been made active for this request. The
 * script creates one draft form and deletes that exact test post in finally.
 */
declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ChatPage;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Admin\ConnectionsPage;
use AISAAutoPilot\Admin\EasyFormBuilderPage;
use AISAAutoPilot\Admin\HistoryPage;
use AISAAutoPilot\Admin\HomePage;
use AISAAutoPilot\Admin\IntegrationsPage;
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

	// wp-admin/menu.php creates these before admin_menu runs; other plugins'
	// callbacks (WooCommerce's remove_menu_page()) expect them to exist.
	global $menu, $submenu;
	$menu    = is_array( $menu ) ? $menu : array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin menu this CLI test builds.
	$submenu = is_array( $submenu ) ? $submenu : array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin menu this CLI test builds.
	do_action( 'admin_menu' );
	$visible_menu_slugs = array_map(
		static fn( array $item ): string => (string) ( $item[2] ?? '' ),
		(array) ( $submenu[ AdminMenu::SLUG_HOME ] ?? array() )
	);
	$expected_menu_slugs = array(
		AdminMenu::SLUG_HOME,
		AdminMenu::SLUG_SETUP,
		AdminMenu::SLUG_INTEGRATIONS,
		AdminMenu::SLUG_KB,
		AdminMenu::SLUG_CONVERSATIONS,
		AdminMenu::SLUG_WC_DASHBOARD,
		AdminMenu::SLUG_FORM_BUILDER,
		AdminMenu::SLUG_FORM_STUDIO,
	);
	$legacy_menu_slugs = array(
		AdminMenu::SLUG_SETTINGS,
		AdminMenu::SLUG_FORMS,
		AdminMenu::SLUG_HISTORY,
		AdminMenu::SLUG_CHAT,
		AdminMenu::SLUG_EFB,
		AdminMenu::SLUG_WC,
		AdminMenu::SLUG_WIDGET,
		AdminMenu::SLUG_HANDOFFS,
		AdminMenu::SLUG_USAGE,
		AdminMenu::SLUG_CONNECTIONS,
		AdminMenu::SLUG_LOGS,
	);
	$check( $expected_menu_slugs === $visible_menu_slugs, 'visible admin sidebar matches the D-23 information architecture (8 main pages) in order' );
	$check( array() === array_intersect( $legacy_menu_slugs, $visible_menu_slugs ), 'legacy admin routes and the Overview tabs stay hidden from the sidebar' );

	$render = static function ( object $screen, array $query = array() ): string {
		$_GET = $query;
		ob_start();
		$screen->render();
		$html = (string) ob_get_clean();
		$_GET = array();
		return $html;
	};
	$page = $render( new CopilotPage(), array( 'mode' => 'forms' ) );
	$check( false !== strpos( $page, 'aisaautopilot-voice-toggle' ) && false !== strpos( $page, 'aisaautopilot-compose-screen' ) && false !== strpos( $page, 'aisaautopilot-review-screen' ), 'Form Studio keeps its exclusive voice/text compose and review UI, reached through the legacy Content Studio deep link' );
	$home = $render( new HomePage() );
	$check( false !== strpos( $home, 'sp-pagebar' ) && false !== strpos( $home, 'sp-weekstats' ) && false !== strpos( $home, 'page=' . AdminMenu::SLUG_SETUP ), 'Overview renders the mockup header, metrics and Setup route' );
	$integrations = $render( new IntegrationsPage(), array( 'page' => AdminMenu::SLUG_INTEGRATIONS ) );
	$check( false !== strpos( $integrations, 'data-sp-integrations' ) && 4 === substr_count( $integrations, 'data-sp-integration-group=' ) && 6 === substr_count( $integrations, 'data-sp-integration-card=' ), 'Integrations renders the four mockup groups and six capability cards' );
	$check( false !== strpos( $integrations, 'data-sp-integration-status="always-on"' ) && false !== strpos( $integrations, 'data-sp-integration-open="site-knowledge"' ) && false !== strpos( $integrations, 'data-sp-integration-dialog="site-knowledge"' ), 'Integrations keeps the Site Knowledge always-on state and accessible Configure dialog contract' );
	$connections = $render( new ConnectionsPage() );
	$check( false !== strpos( $connections, 'data-sp-connections' ) && 8 === substr_count( $connections, 'data-sp-health-row' ) && false !== strpos( $connections, 'sp-run-diagnostics' ), 'Health renders the eight-row mockup diagnostic list and action' );
	$native_forms = $render( new NativeFormsPage() );
	$check( false !== strpos( $native_forms, 'sp-pagebar' ) && false !== strpos( $native_forms, 'page=' . AdminMenu::SLUG_FORM_STUDIO ), 'legacy native-form management route remains usable, is not a sidebar page, and its "Create a form" action points at Form Studio' );
	$check( false !== strpos( $render( new EasyFormBuilderPage() ), 'Easy Form Builder' ), 'advanced form connector screen renders' );
	$logs = $render( new LogsPage() );
	$check(
		false !== strpos( $logs, 'data-sp-logs' )
		&& ( false !== strpos( $logs, 'data-sp-activity-timeline' ) || false !== strpos( $logs, 'data-sp-activity-empty' ) )
		&& false === strpos( $logs, 'sp-logs-search' )
		&& false === strpos( $logs, 'sp-logs-level' ),
		'Activity Log renders its canonical timeline or truthful empty-state contract without legacy filters'
	);
	$history = $render( new HistoryPage() );
	$check(
		false !== strpos( $history, 'data-sp-history' )
		&& false !== strpos( $history, 'data-content-history' )
		&& false !== strpos( $history, 'sp-content-history__export' )
		&& false === strpos( $history, 'sp-history-search' )
		&& false === strpos( $history, 'sp-tr--head' ),
		'Content Studio History renders its mockup card and CSV contract without legacy filters'
	);
	$chat = $render( new ChatPage() );
	$check( false !== strpos( $chat, 'data-sp-chat' ) && false !== strpos( $chat, 'sp-chat-input' ) && false !== strpos( $chat, 'sp-mic-btn' ), 'redesigned AI chat keeps text and voice composer contracts' );
	$kb_page = $render( new KnowledgeBasePage() );
	$check(
		false !== strpos( $kb_page, 'data-sp-kb-overview' )
		&& 4 === substr_count( $kb_page, 'data-sp-subtab=' )
		&& ( false !== strpos( $kb_page, 'sp-kb-start-index' ) || false !== strpos( $kb_page, 'data-sp-kb-open-picker' ) )
		&& false === strpos( $kb_page, 'id="sp-kb-rows"' ),
		'Site Knowledge keeps its canonical four-tab overview and a real indexing entry point'
	);
	$usage_page = $render( new UsagePage() );
	$check(
		false !== strpos( $usage_page, 'data-sp-usage' )
		&& 2 === substr_count( $usage_page, 'data-sp-usage-card=' )
		&& 7 === substr_count( $usage_page, 'data-sp-usage-day aria-label=' )
		&& false !== strpos( $usage_page, 'sp-usage-cap-open' )
		&& false !== strpos( $usage_page, 'sp-usage-cap-editor' )
		&& false === strpos( $usage_page, 'sp-usage-refresh' ),
		'Usage renders the mockup two-card cap and seven-day dashboard without legacy controls'
	);
	$setup_page = $render( new SettingsPage(), array( 'page' => AdminMenu::SLUG_SETUP ) );
	$check( false !== strpos( $setup_page, 'data-sp-setup-wizard' ) && 4 === substr_count( $setup_page, 'data-sp-setup-step=' ) && false !== strpos( $setup_page, 'data-sp-setup-complete' ), 'Setup renders the canonical four-step wizard and completion state' );
	$check( false !== strpos( $setup_page, 'data-sp-provider-card' ) && false !== strpos( $setup_page, 'sp-setup-testbox' ) && false !== strpos( $setup_page, 'data-sp-setup-integrations' ), 'Setup keeps provider selection, connection test and integration controls in the wizard' );
	$check( false !== strpos( $setup_page, 'sp-model-select' ) && false !== strpos( $setup_page, 'sp-refresh-models' ) && false !== strpos( $setup_page, 'sp-provider-catalog' ), 'Setup model picker offers a list and a live refresh instead of free text only' );
	$advanced_settings_page = $render( new SettingsPage(), array( 'page' => AdminMenu::SLUG_SETTINGS ) );
	$check( false !== strpos( $advanced_settings_page, 'sp-form-notification-email' ) && false !== strpos( $advanced_settings_page, 'sp-test-form-delivery' ), 'hidden Settings alias retains advanced delivery and connector controls' );
	$widget_page = $render( new ChatWidgetSettings() );
	$check( false !== strpos( $widget_page, 'data-sp-widget' ) && false !== strpos( $widget_page, 'widget[title]' ) && false !== strpos( $widget_page, 'sp-w-stage' ), 'redesigned widget keeps settings and live preview' );
	$woo_dashboard = $render( new WooCommerceDashboardPage(), array( 'tab' => 'products' ) );
	$check( false !== strpos( $woo_dashboard, 'data-sp-store-copilot' ) && false !== strpos( $woo_dashboard, 'data-sp-store-products' ) && false === strpos( $woo_dashboard, 'sp-stat-revenue' ) && false === strpos( $woo_dashboard, 'sp-sales-chart' ), 'Store Copilot Products tab keeps the mockup read-only product contract without legacy analytics targets' );
	$woo_settings = $render( new WooCommerceSettings() );
	$check( false !== strpos( $woo_settings, 'data-sp-store-copilot' ) && false !== strpos( $woo_settings, 'data-sp-store-tab="capabilities"' ) && false !== strpos( $woo_settings, 'data-sp-store-setting="enabled"' ) && false === strpos( $woo_settings, 'sp_woo_nonce' ), 'legacy WooCommerce alias now keeps the canonical Store capability contract' );
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
	// wp_tempnam() lives in wp-admin/includes/file.php, which a plain wp-load.php does not load.
	require_once ABSPATH . 'wp-admin/includes/file.php';
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
	$canonical_widget_output = (string) ob_get_clean();
	$check(
		false !== strpos( $canonical_widget_output, 'id="spw-toggle"' )
		&& false === strpos( $canonical_widget_output, 'aisaautopilot-public-chat' ),
		'enabling Answer Desk renders one configurable widget instead of a second incompatible launcher'
	);

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

	$delivery_recipient = Settings::form_notification_email();
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
	$delivery_sent             = ! empty( $delivery_data['ok'] );
	$delivery_recipient_exists = '' !== $delivery_recipient;
	$delivery_recipient_match  = $delivery_recipient === (string) ( $delivery_mail['to'] ?? '' );
	$delivery_data_free        = __( "This is a AISAAutoPilot Forms delivery test.\n\nNo visitor data is included in this message.", 'ai-site-assistant-autopilot' ) === (string) ( $delivery_mail['message'] ?? '' );
	$check( $delivery_recipient_exists && $delivery_sent && $delivery_recipient_match && $delivery_data_free, 'delivery test sends a data-free message to the configured recipient' );
	if ( ! ( $delivery_recipient_exists && $delivery_sent && $delivery_recipient_match && $delivery_data_free ) ) {
		printf(
			"Delivery diagnostic: status=%d code=%s recipient_configured=%s recipient_match=%s data_free=%s\n",
			$delivery_response->get_status(),
			sanitize_key( (string) ( $delivery_data['code'] ?? '' ) ),
			$delivery_recipient_exists ? 'yes' : 'no',
			$delivery_recipient_match ? 'yes' : 'no',
			$delivery_data_free ? 'yes' : 'no'
		);
	}
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
		$check( ! empty( $draft_result['ok'] ) && $product_id > 0 && 'draft' === get_post_status( $product_id ) && '1' === get_post_meta( $product_id, '_aisaautopilot_generated', true ), 'approved WooCommerce action creates exactly one product draft with an AutoPilot source marker' );
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
