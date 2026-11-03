<?php
/**
 * Uninstall hook - runs when plugin is deleted.
 */
declare(strict_types=1);
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
/**
 * Removes everything the plugin stored for the current site.
 */
function aisaautopilot_uninstall_site(): void {
	global $wpdb;

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
	delete_option( 'aisaautopilot_kb_index_format' );
	delete_option( 'aisaautopilot_kb_rebuild' );
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
	delete_option( 'aisaautopilot_kb_schema' );
	// What the last retention cleanup deleted (Support\Retention).
	delete_option( 'aisaautopilot_retention_last_run' );
	delete_option( 'aisaautopilot_form_outbox_schema' );
	// Marks a site of a network as set up (Activator::maybe_setup_site()).
	delete_option( 'aisaautopilot_site_setup' );
	delete_option( 'aisaautopilot_widget_chat_schema' );
	// Marker for the sitepilot-ai → ai-site-assistant-autopilot data migration. It
	// must go too, so that a later reinstall alongside leftover legacy rows runs
	// the migration again instead of assuming it already happened.
	delete_option( 'aisaautopilot_rebrand_migrated' );

	/*
	 * Dropping the plugin's own tables is the whole point of an uninstall hook, so
	 * the direct-query and schema-change warnings are expected here. The names are
	 * built from WordPress' own prefix and a fixed suffix — no input reaches them —
	 * and there is nothing to cache on a teardown that runs once.
	 */
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_kb" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_kb_chunks" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_kb_terms" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_chats" );
	// Visitor conversations and handoff requests hold visitors' messages and contact details.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_widget_chats" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_handoffs" );
	// Form submissions whose email failed, kept only when the administrator turned that on.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_form_outbox" );
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
	// Every queued event, whatever post it was for: wp_clear_scheduled_hook() only removes those without arguments.
	wp_unschedule_hook( 'aisaautopilot_index_post' );
	wp_unschedule_hook( 'aisaautopilot_kb_rebuild' );
	wp_unschedule_hook( 'aisaautopilot_retention_cleanup' );
	wp_unschedule_hook( 'aisaautopilot_form_outbox_retry' );
	// The description a Store Copilot approval replaced, kept only so Undo can restore it.
	delete_post_meta_by_key( '_aisaautopilot_previous_description' );
}

/*
 * On a network every site has its own tables and options, and the plugin can only be
 * deleted from the network screen, so each site is cleaned, a page of sites at a time.
 */
if ( is_multisite() ) {
	$aisaautopilot_offset = 0;
	do {
		$aisaautopilot_sites = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 100,
				'offset'                 => $aisaautopilot_offset,
				'update_site_meta_cache' => false,
			)
		);
		foreach ( $aisaautopilot_sites as $aisaautopilot_site ) {
			switch_to_blog( (int) $aisaautopilot_site );
			aisaautopilot_uninstall_site();
			restore_current_blog();
		}
		$aisaautopilot_offset += 100;
	} while ( 100 === count( $aisaautopilot_sites ) );

	/*
	 * A site deleted while the plugin was active on that site alone kept the plugin's tables:
	 * sites are deleted from the network screen, where such a plugin is not loaded. Only the
	 * plugin's own table names of a site that no longer exists are dropped.
	 */
	global $wpdb;
	$aisaautopilot_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' . $wpdb->esc_like( '_aisaautopilot_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( $aisaautopilot_tables as $aisaautopilot_table ) {
		if ( preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+)_aisaautopilot_(kb|kb_chunks|kb_terms|chats|widget_chats|handoffs|form_outbox)$/', (string) $aisaautopilot_table, $aisaautopilot_match ) && null === get_site( (int) $aisaautopilot_match[1] ) ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $aisaautopilot_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}
} else {
	aisaautopilot_uninstall_site();
}
