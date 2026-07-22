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
		$is_copilot  = str_contains( $hook, self::SLUG_COPILOT ) && ! str_contains( $hook, self::SLUG_SETTINGS ) && ! str_contains( $hook, self::SLUG_LOGS ) && ! str_contains( $hook, self::SLUG_KB );
		$is_settings = str_contains( $hook, self::SLUG_SETTINGS );
		$is_kb       = str_contains( $hook, self::SLUG_KB );

		if ( ! $is_copilot && ! $is_settings && ! $is_kb ) {
			return;
		}

		wp_enqueue_style(
			'sitepilot-admin',
			SITEPILOT_AI_URL . 'assets/admin/admin.css',
			array(),
			SITEPILOT_AI_VERSION
		);

		$boot = array(
			'restUrl'    => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'configured' => ( new \SitePilot\Providers\ProviderManager() )->is_configured(),
			'settingsUrl' => admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ),
			'isRTL'      => is_rtl(),
		);

		if ( $is_copilot ) {
			// Add usage/credit info to copilot page.
			$usage_tracker = new UsageTracker();
			$boot['usage'] = array(
				'warning'   => $usage_tracker->get_threshold_warning(),
				'remaining' => $usage_tracker->get_remaining(),
				'percent'   => $usage_tracker->get_usage_percent(),
				'budget'    => $usage_tracker->get_budget(),
			);

			wp_enqueue_script(
				'sitepilot-copilot',
				SITEPILOT_AI_URL . 'assets/admin/copilot.js',
				array( 'wp-i18n' ),
				SITEPILOT_AI_VERSION,
				true
			);
			wp_localize_script( 'sitepilot-copilot', 'SitePilotAI', $boot );
		}

		if ( $is_settings ) {
			wp_enqueue_script(
				'sitepilot-settings',
				SITEPILOT_AI_URL . 'assets/admin/settings.js',
				array(),
				SITEPILOT_AI_VERSION,
				true
			);
			$boot['presets'] = Settings::presets();
			wp_localize_script( 'sitepilot-settings', 'SitePilotAI', $boot );
		}

		if ( $is_kb ) {
			wp_enqueue_style(
				'sitepilot-kb',
				SITEPILOT_AI_URL . 'assets/admin/knowledge-base.css',
				array( 'sitepilot-admin' ),
				SITEPILOT_AI_VERSION
			);

			$indexer = new \SitePilot\Content\ContentIndexer();
			$boot['totalPosts'] = $indexer->get_total_posts();

			wp_enqueue_script(
				'sitepilot-kb',
				SITEPILOT_AI_URL . 'assets/admin/knowledge-base.js',
				array( 'wp-i18n' ),
				SITEPILOT_AI_VERSION,
				true
			);
			wp_localize_script( 'sitepilot-kb', 'SitePilotKB', $boot );
		}
	}
}
