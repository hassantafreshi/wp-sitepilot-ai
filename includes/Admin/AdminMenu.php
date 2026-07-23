<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Api\RestRoutes;
use SitePilot\Core\Settings;
use SitePilot\Usage\UsageTracker;

/**
 * Registers admin menu pages and loads their assets.
 */
final class AdminMenu {

	public const SLUG_COPILOT  = 'sitepilot-ai';
	public const SLUG_SETTINGS = 'sitepilot-ai-settings';
	public const SLUG_LOGS     = 'sitepilot-ai-logs';
	public const SLUG_KB       = 'sitepilot-ai-kb';
	public const SLUG_CHAT     = 'sitepilot-ai-chat';
	public const SLUG_USAGE    = 'sitepilot-ai-usage';

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

		$pages = array(
			array( __( 'Copilot', 'sitepilot-ai' ), self::SLUG_COPILOT, new CopilotPage(), 'render' ),
			array( __( 'AI Chat', 'sitepilot-ai' ), self::SLUG_CHAT, new ChatPage(), 'render' ),
			array( __( 'Knowledge Base', 'sitepilot-ai' ), self::SLUG_KB, new KnowledgeBasePage(), 'render' ),
			array( __( 'Usage', 'sitepilot-ai' ), self::SLUG_USAGE, new UsagePage(), 'render' ),
			array( __( 'Settings', 'sitepilot-ai' ), self::SLUG_SETTINGS, new SettingsPage(), 'render' ),
			array( __( 'Logs', 'sitepilot-ai' ), self::SLUG_LOGS, new LogsPage(), 'render' ),
		);

		foreach ( $pages as $p ) {
			add_submenu_page( self::SLUG_COPILOT, $p[0], $p[0], $cap, $p[1], array( $p[2], $p[3] ) );
		}
	}

	public function enqueue_assets( string $hook ): void {
		$is_sitepilot = str_contains( $hook, self::SLUG_COPILOT )
			|| str_contains( $hook, self::SLUG_CHAT )
			|| str_contains( $hook, self::SLUG_KB )
			|| str_contains( $hook, self::SLUG_USAGE )
			|| str_contains( $hook, self::SLUG_SETTINGS )
			|| str_contains( $hook, self::SLUG_LOGS );

		if ( ! $is_sitepilot ) {
			return;
		}

		// Common admin styles.
		wp_enqueue_style(
			'sitepilot-admin',
			SITEPILOT_AI_URL . 'assets/admin/admin.css',
			array(),
			SITEPILOT_AI_VERSION
		);

		// Page-specific assets.
		$is_chat  = str_contains( $hook, self::SLUG_CHAT );
		$is_usage = str_contains( $hook, self::SLUG_USAGE );

		if ( $is_chat ) {
			wp_enqueue_style(
				'sitepilot-chat',
				SITEPILOT_AI_URL . 'assets/admin/chat.css',
				array( 'sitepilot-admin' ),
				SITEPILOT_AI_VERSION
			);
			wp_enqueue_script(
				'sitepilot-voice',
				SITEPILOT_AI_URL . 'assets/admin/voice.js',
				array(),
				SITEPILOT_AI_VERSION,
				true
			);
			wp_enqueue_script(
				'sitepilot-chat',
				SITEPILOT_AI_URL . 'assets/admin/chat.js',
				array( 'sitepilot-voice' ),
				SITEPILOT_AI_VERSION,
				true
			);
		}

		if ( $is_usage ) {
			wp_enqueue_style(
				'sitepilot-usage',
				SITEPILOT_AI_URL . 'assets/admin/usage.css',
				array( 'sitepilot-admin' ),
				SITEPILOT_AI_VERSION
			);
			wp_enqueue_script(
				'sitepilot-usage',
				SITEPILOT_AI_URL . 'assets/admin/usage.js',
				array(),
				SITEPILOT_AI_VERSION,
				true
			);
		}

		// Common config for all SitePilot pages.
		$tracker = new UsageTracker();
		$config  = array(
			'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'usage'   => $tracker->get_threshold_warning(),
		);

		wp_localize_script( 'sitepilot-voice', 'SitePilotChat', $config );
		wp_localize_script( 'sitepilot-chat', 'SitePilotChat', $config );
		wp_localize_script( 'sitepilot-usage', 'SitePilotAI', $config );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
