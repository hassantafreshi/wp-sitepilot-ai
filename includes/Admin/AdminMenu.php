<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Frontend\ChatWidgetSettings;
use AISAAutoPilot\Usage\UsageTracker;

final class AdminMenu {

	public const SLUG_SETUP        = 'aisaautopilot-setup';
	public const SLUG_HOME         = 'aisaautopilot-home';
	public const SLUG_FORM_BUILDER = 'aisaautopilot-form-builder';
	public const SLUG_FORMS        = 'aisaautopilot-forms';
	public const SLUG_HISTORY      = 'aisaautopilot-history';
	public const SLUG_EFB          = 'aisaautopilot-efb';
	public const SLUG_CONNECTIONS  = 'aisaautopilot-connections';
	public const SLUG_CHAT         = 'aisaautopilot-chat';
	public const SLUG_KB           = 'aisaautopilot-kb';
	public const SLUG_USAGE        = 'aisaautopilot-usage';
	public const SLUG_SETTINGS     = 'aisaautopilot-settings';
	public const SLUG_LOGS         = 'aisaautopilot-logs';
	public const SLUG_WIDGET       = 'aisaautopilot-widget';
	public const SLUG_WC           = 'aisaautopilot-woocommerce';
	public const SLUG_WC_DASHBOARD = 'aisaautopilot-wc-dashboard';

	/**
	 * The menu order mirrors the wireframe's sidebar: setup, home, the create
	 * flow, then the tools, then the store, then diagnostics.
	 *
	 * Setup and Settings are one screen. The old settings slug stays registered
	 * so existing bookmarks and links keep resolving, but it is removed from the
	 * menu so the same page is not offered twice.
	 */
	public function register(): void {
		$cap      = 'manage_options';
		$settings = new SettingsPage();
		add_menu_page( 'AI Site Assistant AutoPilot', 'AI Site Assistant AutoPilot', $cap, self::SLUG_SETUP, array( $settings, 'render' ), 'dashicons-superhero', 58 );

		$pages = array(
			array( __( 'Setup & Settings', 'ai-site-assistant-autopilot' ), self::SLUG_SETUP, $settings ),
			array( __( 'Home', 'ai-site-assistant-autopilot' ), self::SLUG_HOME, new HomePage() ),
			array( __( 'Create with AI', 'ai-site-assistant-autopilot' ), self::SLUG_FORM_BUILDER, new CopilotPage() ),
			array( __( 'Action history', 'ai-site-assistant-autopilot' ), self::SLUG_HISTORY, new HistoryPage() ),
			array( __( 'AISAAutoPilot Forms', 'ai-site-assistant-autopilot' ), self::SLUG_FORMS, new NativeFormsPage() ),
			array( __( 'Easy Form Builder', 'ai-site-assistant-autopilot' ), self::SLUG_EFB, new EasyFormBuilderPage() ),
			array( __( 'AI Chat', 'ai-site-assistant-autopilot' ), self::SLUG_CHAT, new ChatPage() ),
			array( __( 'Knowledge Base', 'ai-site-assistant-autopilot' ), self::SLUG_KB, new KnowledgeBasePage() ),
			array( __( 'Usage', 'ai-site-assistant-autopilot' ), self::SLUG_USAGE, new UsagePage() ),
			array( __( 'Widget', 'ai-site-assistant-autopilot' ), self::SLUG_WIDGET, new ChatWidgetSettings() ),
		);

		if ( WooCommerceConnector::is_active() ) {
			$pages[] = array( __( 'WC Dashboard', 'ai-site-assistant-autopilot' ), self::SLUG_WC_DASHBOARD, new WooCommerceDashboardPage() );
			$pages[] = array( __( 'WooCommerce AI', 'ai-site-assistant-autopilot' ), self::SLUG_WC, new WooCommerceSettings() );
		}

		$pages[] = array( __( 'Connections & health', 'ai-site-assistant-autopilot' ), self::SLUG_CONNECTIONS, new ConnectionsPage() );
		$pages[] = array( __( 'Logs', 'ai-site-assistant-autopilot' ), self::SLUG_LOGS, new LogsPage() );

		foreach ( $pages as $page ) {
			add_submenu_page( self::SLUG_SETUP, $page[0], $page[0], $cap, $page[1], array( $page[2], 'render' ) );
		}

		// Legacy alias: registered so old links still render (and still check the
		// capability), then hidden so the merged screen appears once in the menu.
		add_submenu_page( self::SLUG_SETUP, __( 'Setup & Settings', 'ai-site-assistant-autopilot' ), __( 'Setup & Settings', 'ai-site-assistant-autopilot' ), $cap, self::SLUG_SETTINGS, array( $settings, 'render' ) );
		remove_submenu_page( self::SLUG_SETUP, self::SLUG_SETTINGS );
	}

	/**
	 * Per-screen scripts. key = page slug, value = list of [filename, localized-object-name].
	 */
	private const SCREEN_SCRIPTS = array(
		self::SLUG_SETUP        => array( array( 'settings.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_FORM_BUILDER => array( array( 'form-builder.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_HISTORY      => array( array( 'logs.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_LOGS         => array( array( 'logs.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_CHAT         => array( array( 'chat.js', 'AISAAutoPilotChat' ), array( 'voice.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_KB           => array( array( 'knowledge-base.js', 'AISAAutoPilotKB' ) ),
		self::SLUG_USAGE        => array( array( 'usage.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_SETTINGS     => array( array( 'settings.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_CONNECTIONS  => array( array( 'connections.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_WC           => array( array( 'woocommerce.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_WIDGET       => array( array( 'widget-settings.js', 'AISAAutoPilotAI' ) ),
	);

	public function enqueue_assets( string $hook ): void {
		unset( $hook ); // Slug is resolved from the request, not the hook suffix (which is ambiguous across our slugs).
		$slug = $this->current_screen_slug();
		if ( null === $slug ) {
			return;
		}

		// One stylesheet for every screen: the wireframe's design system.
		wp_enqueue_style( 'aisaautopilot-ui', AISAAUTOPILOT_URL . 'assets/admin/aisaautopilot-ui.css', array(), AISAAUTOPILOT_VERSION );

		$conf = array(
			'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'usage'   => ( new UsageTracker() )->get_threshold_warning(),
			'i18n'    => $this->script_strings(),
		);

		foreach ( self::SCREEN_SCRIPTS[ $slug ] ?? array() as $script ) {
			list($file, $object) = $script;
			$handle              = 'aisaautopilot-' . pathinfo( $file, PATHINFO_FILENAME );
			wp_enqueue_script( $handle, AISAAUTOPILOT_URL . 'assets/admin/' . $file, array(), AISAAUTOPILOT_VERSION, true );
			wp_localize_script( $handle, $object, $conf );
		}

		// The WooCommerce dashboard needs WC availability alongside the REST config.
		if ( self::SLUG_WC_DASHBOARD === $slug ) {
			wp_enqueue_script( 'aisaautopilot-dashboard', AISAAUTOPILOT_URL . 'assets/admin/dashboard.js', array(), AISAAUTOPILOT_VERSION, true );
			wp_localize_script(
				'aisaautopilot-dashboard',
				'AISAAutoPilotDashboard',
				array(
					'restUrl'  => $conf['restUrl'],
					'nonce'    => $conf['nonce'],
					'wcActive' => WooCommerceConnector::is_active(),
				)
			);
		}
	}

	/**
	 * Strings the shared scripts need. Kept here so every screen's JS reads the
	 * translated copy rather than hard-coded English.
	 *
	 * @return array<string, string>
	 */
	private function script_strings(): array {
		return array(
			'testing'      => __( 'Testing…', 'ai-site-assistant-autopilot' ),
			'testingBody'  => __( 'A short request was sent to the provider.', 'ai-site-assistant-autopilot' ),
			'testOk'       => __( 'Connection works', 'ai-site-assistant-autopilot' ),
			'testOkBody'   => __( 'Valid response received. You can continue.', 'ai-site-assistant-autopilot' ),
			'testFail'     => __( 'Key or endpoint was rejected', 'ai-site-assistant-autopilot' ),
			'testFailBody' => __( 'Replace the key or check the custom endpoint.', 'ai-site-assistant-autopilot' ),
			'networkError' => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'sending'      => __( 'Sending test…', 'ai-site-assistant-autopilot' ),
			'deliveryOk'   => __( 'Test accepted.', 'ai-site-assistant-autopilot' ),
			'deliveryFail' => __( 'Test failed.', 'ai-site-assistant-autopilot' ),
			'noChannels'   => __( 'No channel is enabled, so the handoff button stays hidden from visitors.', 'ai-site-assistant-autopilot' ),
			'iconTooLarge' => __( 'That image is too large. Please use a PNG under 200 KB.', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * Resolve the exact AISAAutoPilot admin page slug from the request, or null if not one of ours.
	 */
	private function current_screen_slug(): ?string {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only asset routing, no state change.
		$known = array(
			self::SLUG_SETUP,
			self::SLUG_HOME,
			self::SLUG_FORM_BUILDER,
			self::SLUG_FORMS,
			self::SLUG_HISTORY,
			self::SLUG_EFB,
			self::SLUG_CONNECTIONS,
			self::SLUG_CHAT,
			self::SLUG_KB,
			self::SLUG_USAGE,
			self::SLUG_WIDGET,
			self::SLUG_WC,
			self::SLUG_WC_DASHBOARD,
			self::SLUG_SETTINGS,
			self::SLUG_LOGS,
		);

		return in_array( $page, $known, true ) ? $page : null;
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
