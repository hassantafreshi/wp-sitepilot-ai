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
 * Runs on plugin activation, and sets up any site of a network the activation did not reach.
 */
final class Activator {

	/** Records that this site has the plugin's tables and default settings. */
	public const SITE_OPTION = 'aisaautopilot_site_setup';

	private const SITE_VERSION = '1';

	/**
	 * @param bool $network_wide True when a multisite network administrator activates the plugin for every site.
	 */
	public static function activate( $network_wide = false ): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( AISAAUTOPILOT_BASENAME );
			wp_die(
				esc_html__( 'AI Site Assistant AutoPilot requires PHP 8.1 or higher.', 'ai-site-assistant-autopilot' ),
				esc_html__( 'Plugin activation error', 'ai-site-assistant-autopilot' ),
				array( 'back_link' => true )
			);
		}

		if ( is_multisite() && $network_wide ) {
			// On a large network this request would time out; each site then sets itself up on
			// its own first request instead (maybe_setup_site()).
			if ( ! wp_is_large_network() ) {
				Network::for_each_site( array( self::class, 'setup_site' ) );
			}
		} else {
			self::setup_site();
		}

		flush_rewrite_rules();
	}

	/**
	 * Called on init: a site of a network that was added after network activation, or that
	 * a large network's activation skipped, gets its tables on its first request.
	 */
	public static function maybe_setup_site(): void {
		if ( self::SITE_VERSION !== get_option( self::SITE_OPTION ) ) {
			self::setup_site();
		}
	}

	/** Sets up the current site: data carried over from the old name, settings and tables. */
	public static function setup_site(): void {
		/*
		 * Carry data over from the pre-rebrand names before anything reads or
		 * seeds an option, otherwise the defaults below would be written first
		 * and the migration would then decline to overwrite them.
		 */
		RebrandMigrator::run();

		NativeFormConnector::register_post_types();

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

		update_option( self::SITE_OPTION, self::SITE_VERSION, true );
	}
}
