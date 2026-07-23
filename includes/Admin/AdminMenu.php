<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Api\RestRoutes;
use SitePilot\Content\ContentIndexer;
use SitePilot\Core\Settings;
use SitePilot\Usage\UsageTracker;

/**
 * Registers admin menu pages and loads their assets.
 * Now includes 4 pages: Copilot, Chat, Knowledge Base, Settings, Logs.
 */
final class AdminMenu {

	public const SLUG_COPILOT      = 'sitepilot-ai';
	public const SLUG_CHAT         = 'sitepilot-ai-chat';
	public const SLUG_SETTINGS     = 'sitepilot-ai-settings';
	public const SLUG_LOGS         = 'sitepilot-ai-logs';
	public const SLUG_KB           = 'sitepilot-ai-kb';

	public function register(): void {
		$cap = 'manage_options';

		add_menu_page(
			__( 'SitePilot AI', 'sitepilot-ai' ),
			__( 'SitePilot AI', 'sitepilot-ai' ),
			$cap,
			self::SLUG_COPILOT,
			array( new CopilotPage(), 'render' ),
			'dashicons-superhero',
			58
		);

		add_submenu_page(
			self::SLUG_COPILOT,
			__( 'Copilot', 'sitepilot-ai' ),
			__( 'Copilot', 'sitepilot-ai' ),
			$cap,
			self::SLUG_COPILOT,
			array( new CopilotPage(), 'render' )
		);

		add_submenu_page(
			self::SLUG_COPILOT,
			__( 'AI Chat', 'sitepilot-ai' ),
			__( 'AI Chat', 'sitepilot-ai' ),
			$cap,
			self::SLUG_CHAT,
			array( new ChatPage(), 'render' )
		);

		add_submenu_page(
			self::SLUG_COPILOT,
			__( 'Knowledge Base', 'sitepilot-ai' ),
			__( 'Knowledge Base', 'sitepilot-ai' ),
			$cap,
			self::SLUG_KB,
			array( new KnowledgeBasePage(), 'render' )
		);

		add_submenu_page(
			self::SLUG_COPILOT,
			__( 'Settings', 'sitepilot-ai' ),
			__( 'Settings', 'sitepilot-ai' ),
			$cap,
			self::SLUG_SETTINGS,
			array( new SettingsPage(), 'render' )
		);

		add_submenu_page(
			self::SLUG_COPILOT,
			__( 'Logs', 'sitepilot-ai' ),
			__( 'Logs', 'sitepilot-ai' ),
			$cap,
			self::SLUG_LOGS,
			array( new LogsPage(), 'render' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		$is_copilot  = str_contains( $hook, self::SLUG_COPILOT ) && ! str_contains( $hook, self::SLUG_SETTINGS ) && ! str_contains( $hook, self::SLUG_LOGS ) && ! str_contains( $hook, self::SLUG_CHAT ) && ! str_contains( $hook, self::SLUG_KB );
		$is_chat     = str_contains( $hook, self::SLUG_CHAT );
		$is_kb       = str_contains( $hook, self::SLUG_KB );
		$is_settings = str_contains( $hook, self::SLUG_SETTINGS );

		if ( $is_copilot ) {
			wp_enqueue_style( 'sitepilot-admin', SITEPILOT_AI_URL . 'assets/admin/admin.css', array(), SITEPILOT_AI_VERSION );
			wp_enqueue_script( 'sitepilot-copilot', SITEPILOT_AI_URL . 'assets/admin/copilot.js', array(), SITEPILOT_AI_VERSION, true );
			wp_localize_script( 'sitepilot-copilot', 'SitePilotAI', array(
				'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'usage'   => ( new UsageTracker() )->get_threshold_warning(),
			) );
		}

		if ( $is_chat ) {
			wp_enqueue_style( 'sitepilot-chat', SITEPILOT_AI_URL . 'assets/admin/chat.css', array(), SITEPILOT_AI_VERSION );
			wp_enqueue_script( 'sitepilot-chat', SITEPILOT_AI_URL . 'assets/admin/chat.js', array(), SITEPILOT_AI_VERSION, true );
			wp_localize_script( 'sitepilot-chat', 'SitePilotChat', array(
				'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			) );
		}

		if ( $is_kb ) {
			wp_enqueue_style( 'sitepilot-kb', SITEPILOT_AI_URL . 'assets/admin/knowledge-base.css', array(), SITEPILOT_AI_VERSION );
			wp_enqueue_script( 'sitepilot-kb', SITEPILOT_AI_URL . 'assets/admin/knowledge-base.js', array(), SITEPILOT_AI_VERSION, true );
			wp_localize_script( 'sitepilot-kb', 'SitePilotKB', array(
				'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'total'   => ( new ContentIndexer() )->get_total_posts(),
			) );
		}

		if ( $is_settings ) {
			wp_enqueue_style( 'sitepilot-admin', SITEPILOT_AI_URL . 'assets/admin/admin.css', array(), SITEPILOT_AI_VERSION );
			wp_enqueue_script( 'sitepilot-settings', SITEPILOT_AI_URL . 'assets/admin/settings.js', array(), SITEPILOT_AI_VERSION, true );
			wp_localize_script( 'sitepilot-settings', 'SitePilotSettings', array(
				'restUrl'      => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'presets'      => Settings::presets(),
			) );
		}
	}
}
