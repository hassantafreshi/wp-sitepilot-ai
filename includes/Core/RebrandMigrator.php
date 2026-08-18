<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Content\KnowledgeBase;

/**
 * Carries data across the `sitepilot-ai` → `ai-site-assistant-autopilot` rename.
 *
 * Every persisted name the plugin owns changed with the rebrand: two custom
 * tables, the option keys, the two custom post types, the form-plan meta key,
 * and the indexing cron hook. Without this pass an upgraded site would look
 * factory-fresh — settings gone, knowledge base empty, saved forms invisible —
 * even though all the rows are still sitting in the database.
 *
 * Runs once, from the activation hook, guarded by {@see self::DONE_OPTION}.
 *
 * Note that the encrypted provider keys survive untouched: KeyVault derives its
 * encryption key from a salt that is deliberately frozen at the old slug, so
 * moving the ciphertext to a new option name is all that is required.
 */
final class RebrandMigrator {

	/** Set once the migration has completed, so it never runs twice. */
	private const DONE_OPTION = 'aisaautopilot_rebrand_migrated';

	private const OLD_PREFIX    = 'sitepilot_';
	private const OLD_AI_PREFIX = 'sitepilot_ai_';
	private const NEW_PREFIX    = 'aisaautopilot_';

	/** Legacy table suffix => current table suffix. */
	private const TABLES = array(
		'sitepilot_kb'    => KnowledgeBase::TABLE_SUFFIX,
		'sitepilot_chats' => ConversationManager::TABLE,
	);

	/** Legacy post type => current post type. */
	private const POST_TYPES = array(
		'sitepilot_form'       => NativeFormConnector::POST_TYPE,
		'sitepilot_submission' => NativeFormConnector::SUBMISSION_TYPE,
	);

	private const OLD_META_KEY = '_sitepilot_form_plan';
	private const OLD_CRON     = 'sitepilot_ai_index_post';
	private const NEW_CRON     = 'aisaautopilot_index_post';

	/**
	 * Migrate legacy data, unless this site has already been migrated.
	 */
	public static function run(): void {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		self::migrate_tables();
		self::migrate_options();
		self::migrate_post_types();
		self::migrate_post_meta();
		self::migrate_cron();

		add_option( self::DONE_OPTION, gmdate( 'c' ), '', false );
	}

	/**
	 * Rename the two custom tables, keeping their rows.
	 *
	 * A legacy table is only renamed when the new name is not already present,
	 * so a partially migrated site cannot clobber live data with stale data.
	 */
	private static function migrate_tables(): void {
		global $wpdb;

		foreach ( self::TABLES as $old_suffix => $new_suffix ) {
			$old = $wpdb->prefix . $old_suffix;
			$new = $wpdb->prefix . $new_suffix;

			if ( ! self::table_exists( $old ) || self::table_exists( $new ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers come from constants and the WP prefix; a rename cannot be prepared.
			$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
		}
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema probe during a one-off migration.
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Move every legacy option to its new key.
	 *
	 * The list is discovered from the database rather than hard-coded so that
	 * any option added before the rename is carried over, and the autoload flag
	 * is preserved so hot options stay hot and cold ones stay cold.
	 */
	private static function migrate_options(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration over option names; there is no API to list options by prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OLD_PREFIX ) . '%'
			)
		);

		foreach ( (array) $rows as $row ) {
			$old = (string) $row->option_name;
			$new = self::rename_key( $old );

			// Never overwrite a value the rebuilt plugin has already written.
			if ( null === $new || null !== self::raw_option( $new ) ) {
				continue;
			}

			$value = get_option( $old );
			if ( false === $value ) {
				continue;
			}

			add_option( $new, $value, '', self::is_autoloaded( (string) $row->autoload ) );
			delete_option( $old );
		}
	}

	/**
	 * Interpret the raw `autoload` column as a boolean.
	 *
	 * WordPress 6.6 widened this column: besides the historical `yes`/`no` it
	 * now also stores `on`/`off` and the auto-managed `auto`, `auto-on` and
	 * `auto-off`. Comparing against `yes` alone would silently drop options on
	 * any modern install, so every "on" spelling is matched here.
	 */
	private static function is_autoloaded( string $autoload ): bool {
		return in_array( $autoload, array( 'yes', 'on', 'auto', 'auto-on' ), true );
	}

	/**
	 * Read an option without the `false`-means-missing ambiguity.
	 *
	 * An option legitimately holding `false` must not look absent, otherwise the
	 * migration would treat an already-migrated key as free and overwrite it.
	 */
	private static function raw_option( string $name ) {
		$sentinel = new \stdClass();
		$value    = get_option( $name, $sentinel );

		return $value === $sentinel ? null : $value;
	}

	/**
	 * Map a legacy key onto its new name, or null when it is not ours.
	 *
	 * `sitepilot_ai_` collapses to the new prefix because the product name now
	 * carries the "AI" itself: `sitepilot_ai_settings` → `aisaautopilot_settings`.
	 */
	private static function rename_key( string $old ): ?string {
		if ( str_starts_with( $old, self::OLD_AI_PREFIX ) ) {
			return self::NEW_PREFIX . substr( $old, strlen( self::OLD_AI_PREFIX ) );
		}
		if ( str_starts_with( $old, self::OLD_PREFIX ) ) {
			return self::NEW_PREFIX . substr( $old, strlen( self::OLD_PREFIX ) );
		}

		return null;
	}

	/**
	 * Re-point existing forms and submissions at the renamed post types.
	 */
	private static function migrate_post_types(): void {
		global $wpdb;

		foreach ( self::POST_TYPES as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk column update; no per-post API would be sane here.
			$wpdb->update( $wpdb->posts, array( 'post_type' => $new ), array( 'post_type' => $old ) );
		}
	}

	/**
	 * Rename the stored form-plan meta key.
	 */
	private static function migrate_post_meta(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk key rename during a one-off migration.
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_key' => NativeFormConnector::META_KEY ),
			array( 'meta_key' => self::OLD_META_KEY )
		);
	}

	/**
	 * Move pending indexing jobs onto the new hook name, arguments intact.
	 *
	 * Each event carries the post ID it should re-index, so the arguments have
	 * to travel with it — rescheduling a bare hook would hand the handler no
	 * post to work on. The whole cron array is walked because there is no public
	 * API to enumerate the pending events of a hook across every argument set.
	 *
	 * Timestamps in the past are pushed out a minute so a backlog cannot all
	 * fire inside the activation request.
	 */
	private static function migrate_cron(): void {
		$cron = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			return;
		}

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! isset( $hooks[ self::OLD_CRON ] ) || ! is_array( $hooks[ self::OLD_CRON ] ) ) {
				continue;
			}

			foreach ( $hooks[ self::OLD_CRON ] as $event ) {
				$args = ( isset( $event['args'] ) && is_array( $event['args'] ) ) ? $event['args'] : array();
				if ( false === wp_next_scheduled( self::NEW_CRON, $args ) ) {
					wp_schedule_single_event( max( (int) $timestamp, time() + 60 ), self::NEW_CRON, $args );
				}
			}
		}

		// Clears every pending event for the hook regardless of its arguments,
		// which wp_clear_scheduled_hook() alone would not do.
		wp_unschedule_hook( self::OLD_CRON );
	}
}
