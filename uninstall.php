<?php
/**
 * Uninstall handler — runs when the plugin is deleted from WordPress.
 *
 * Removes all plugin options, custom KB table, and conversation table.
 * Does NOT remove user-created content (forms, submissions).
 *
 * @package SitePilot\AI
 */

declare(strict_types=1);

use SitePilot\Content\KnowledgeBase;
use SitePilot\Chat\ConversationManager;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'sitepilot_ai_settings' );
delete_option( 'sitepilot_ai_provider_key' );
delete_option( 'sitepilot_ai_logs' );
delete_option( 'sitepilot_ai_usage_log' );
delete_option( 'sitepilot_ai_usage_totals' );
delete_option( 'sitepilot_ai_monthly_budget' );

// Drop the Knowledge Base table.
global $wpdb;
$kb_table = $wpdb->prefix . 'sitepilot_kb';
$wpdb->query( "DROP TABLE IF EXISTS {$kb_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Drop the Conversation table.
$chat_table = $wpdb->prefix . 'sitepilot_chats';
$wpdb->query( "DROP TABLE IF EXISTS {$chat_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'sitepilot_ai_index_post' );
