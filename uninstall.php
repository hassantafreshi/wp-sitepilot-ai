<?php
/**
 * Uninstall hook - runs when plugin is deleted.
 */
declare(strict_types=1);
if(!defined('WP_UNINSTALL_PLUGIN'))exit;
delete_option('sitepilot_ai_settings');
delete_option('sitepilot_ai_provider_key');
delete_option('sitepilot_ai_logs');
delete_option('sitepilot_ai_usage_log');
delete_option('sitepilot_ai_usage_totals');
delete_option('sitepilot_ai_monthly_budget');
global$wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sitepilot_kb");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sitepilot_chats");
wp_clear_scheduled_hook('sitepilot_ai_index_post');
