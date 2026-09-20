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
// Provider key saved through WordPress Connectors under this plugin's own setting name.
delete_option( 'aisaautopilot_wp_connector_key' );
delete_option( 'aisaautopilot_provider_verified' );
// Cursor of the first-run wizard that Setup & Settings replaced.
delete_option( 'aisaautopilot_setup_step' );
delete_option( 'aisaautopilot_logs' );
delete_option( 'aisaautopilot_usage_log' );
delete_option( 'aisaautopilot_usage_totals' );
delete_option( 'aisaautopilot_monthly_budget' );
delete_option( 'aisaautopilot_kb_gaps' );
delete_option( 'aisaautopilot_kb_saved_answers' );
delete_option( 'aisaautopilot_kb_answer_cache' );
delete_option( 'aisaautopilot_kb_version' );
delete_option( 'aisaautopilot_usage_period' );
delete_option( 'aisaautopilot_usage_period_contexts' );
delete_option( 'aisaautopilot_usage_daily' );
delete_option( 'aisaautopilot_usage_historical_totals' );
delete_option( 'aisaautopilot_usage_historical_log' );
delete_option( 'aisaautopilot_usage_period_archive' );
delete_option( 'aisaautopilot_voice_usage_log' );
delete_option( 'aisaautopilot_health_diagnostics' );
// Diagnostics stamp written by the 1.0.4 Connections page, orphaned since Health replaced it.
delete_option( 'aisaautopilot_last_diagnostics' );
delete_option( 'aisaautopilot_handoff_inbox_schema' );
delete_option( 'aisaautopilot_widget_chat_schema' );
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
// Visitor conversations and handoff requests hold visitors' messages and contact details.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_widget_chats" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_handoffs" );
// One-day replay locks of approved actions (ActionStore::claim_key()).
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'aisaautopilot_action_claim_' ) . '%' ) );
/*
 * Transients: rate-limit counters (a keyed hash of the caller, never a raw IP),
 * the 15-minute action preview and the provider-test record. They live in the
 * options table unless a persistent object cache holds them, in which case they
 * are not reachable here and expire on their own within an hour.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_aisaautopilot_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aisaautopilot_' ) . '%'
	)
);
/*
 * Anything else this plugin owns. RebrandMigrator renames *every* legacy
 * sitepilot_* option by prefix, so a site upgraded from the old slug can hold
 * aisaautopilot_* rows whose names this version no longer declares. The prefix
 * is frozen and belongs to this plugin alone, so the sweep cannot reach another
 * plugin's data.
 */
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'aisaautopilot_' ) . '%' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
wp_clear_scheduled_hook( 'aisaautopilot_index_post' );
