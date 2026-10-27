<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\KnowledgeIndex;
use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Support\HandoffInbox;
use AISAAutoPilot\Chat\VisitorConversationStore;

/**
 * Runs on plugin activation.
 */
final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( AISAAUTOPILOT_BASENAME );
			wp_die(
				esc_html__( 'AI Site Assistant AutoPilot requires PHP 8.1 or higher.', 'ai-site-assistant-autopilot' ),
				esc_html__( 'Plugin activation error', 'ai-site-assistant-autopilot' ),
				array( 'back_link' => true )
			);
		}

		/*
		 * Carry data over from the pre-rebrand names before anything reads or
		 * seeds an option, otherwise the defaults below would be written first
		 * and the migration would then decline to overwrite them.
		 */
		RebrandMigrator::run();

		NativeFormConnector::register_post_types();
		flush_rewrite_rules();

		// Seed default settings if none exist.
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}

		// Create the Knowledge Base tables: posts, and their searchable passages.
		$kb = new KnowledgeBase();
		$kb->create_table();
		( new KnowledgeIndex() )->create_tables();

		// Create the Conversations table.
		$chat = new ConversationManager();
		$chat->create_table();

		// Create the private visitor-initiated handoff inbox table.
		HandoffInbox::ensure_schema();
		VisitorConversationStore::ensure_schema();
	}
}
