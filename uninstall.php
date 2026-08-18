<?php
/**
 * Uninstall hook - runs when plugin is deleted.
 */
declare(strict_types=1);
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'aisaautopilot_settings' );
delete_option( 'aisaautopilot_provider_key' );
delete_option( 'aisaautopilot_embedding_key' );
delete_option( 'aisaautopilot_handoff_secrets' );
delete_option( 'aisaautopilot_provider_verified' );
// Cursor of the first-run wizard that Setup & Settings replaced.
delete_option( 'aisaautopilot_setup_step' );
delete_option( 'aisaautopilot_logs' );
delete_option( 'aisaautopilot_usage_log' );
delete_option( 'aisaautopilot_usage_totals' );
delete_option( 'aisaautopilot_monthly_budget' );
delete_option( 'aisaautopilot_kb_gaps' );
delete_option( 'aisaautopilot_kb_answer_cache' );
delete_option( 'aisaautopilot_kb_version' );
// Marker for the sitepilot-ai → ai-site-assistant-autopilot data migration. It
// must go too, so that a later reinstall alongside leftover legacy rows runs
// the migration again instead of assuming it already happened.
delete_option( 'aisaautopilot_rebrand_migrated' );
global $wpdb;

/*
 * Dropping the plugin's own tables is the whole point of an uninstall hook, so
 * the direct-query and schema-change warnings are expected here. The names are
 * built from WordPress' own prefix and a fixed suffix — no input reaches them —
 * and there is nothing to cache on a teardown that runs once.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_kb" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_chats" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
wp_clear_scheduled_hook( 'aisaautopilot_index_post' );
