<?php
/**
 * Uninstall cleanup. Removes plugin settings, the encrypted key, and logs.
 * User-created forms/submissions are intentionally left in place.
 *
 * @package SitePilot\AI
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sitepilot_ai_settings' );
delete_option( 'sitepilot_ai_provider_key' );
delete_option( 'sitepilot_ai_logs' );
