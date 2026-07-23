<?php
declare(strict_types=1);

namespace SitePilot\Core;

use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Content\KnowledgeBase;
use SitePilot\Chat\ConversationManager;

/**
 * Runs on plugin activation.
 */
final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( SITEPILOT_AI_BASENAME );
			wp_die(
				esc_html__( 'SitePilot AI requires PHP 8.1 or higher.', 'sitepilot-ai' ),
				esc_html__( 'Plugin activation error', 'sitepilot-ai' ),
				array( 'back_link' => true )
			);
		}

		NativeFormConnector::register_post_types();
		flush_rewrite_rules();

		// Seed default settings if none exist.
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}

		// Create the Knowledge Base table.
		$kb = new KnowledgeBase();
		$kb->create_table();

		// Create the Conversations table.
		$chat = new ConversationManager();
		$chat->create_table();
	}
}
