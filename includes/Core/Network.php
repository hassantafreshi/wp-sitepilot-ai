<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\KnowledgeIndex;
use AISAAutoPilot\Forms\FormOutbox;
use AISAAutoPilot\Support\HandoffInbox;

/**
 * Multisite: every site of a network keeps its own tables and options, so network
 * activation sets each site up and a deleted site takes the plugin's tables with it.
 * uninstall.php walks the sites the same way, without this class: the plugin is not
 * loaded when it is deleted.
 */
final class Network {

	/** Sites read per query while walking a network. */
	private const PAGE = 100;

	/** The plugin's own tables, without the site's prefix. */
	public const TABLES = array(
		KnowledgeBase::TABLE_SUFFIX,
		KnowledgeIndex::CHUNKS_TABLE,
		KnowledgeIndex::TERMS_TABLE,
		ConversationManager::TABLE,
		VisitorConversationStore::TABLE,
		HandoffInbox::TABLE,
		FormOutbox::TABLE,
	);

	public function register(): void {
		if ( is_multisite() ) {
			add_filter( 'wpmu_drop_tables', array( $this, 'site_tables' ), 10, 2 );
		}
	}

	/**
	 * Adds the plugin's tables to those WordPress drops with a deleted site.
	 *
	 * @param array<int, string> $tables
	 * @return array<int, string>
	 */
	public function site_tables( $tables, $site_id = 0 ): array {
		$prefix = $GLOBALS['wpdb']->get_blog_prefix( (int) $site_id );
		foreach ( self::TABLES as $table ) {
			$tables[] = $prefix . $table;
		}

		return array_values( array_unique( (array) $tables ) );
	}

	/**
	 * Runs $callback as each site of the current network, a page of sites at a time.
	 */
	public static function for_each_site( callable $callback ): void {
		$offset = 0;
		do {
			$ids = get_sites(
				array(
					'fields'                 => 'ids',
					'network_id'             => get_current_network_id(),
					'number'                 => self::PAGE,
					'offset'                 => $offset,
					'update_site_meta_cache' => false,
				)
			);
			foreach ( $ids as $id ) {
				switch_to_blog( (int) $id );
				try {
					$callback();
				} finally {
					restore_current_blog();
				}
			}
			$offset += self::PAGE;
		} while ( self::PAGE === count( $ids ) );
	}
}
