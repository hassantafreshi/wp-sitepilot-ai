<?php
/**
 * Plugin Name:       SitePilot AI
 * Plugin URI:        https://sitepilot.ai
 * Description:        Safe AI copilot for WordPress. Ask in plain language and SitePilot builds a form for you — you always preview and approve before anything is created. Temporary standalone mode talks directly to your AI provider (DeepSeek, cloud.ai, OpenAI-compatible).
 * Version:           0.1.2
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            SitePilot AI
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sitepilot-ai
 * Domain Path:       /languages
 *
 * @package SitePilot\AI
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'SITEPILOT_AI_VERSION', '0.1.2' );
define( 'SITEPILOT_AI_FILE', __FILE__ );
define( 'SITEPILOT_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITEPILOT_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'SITEPILOT_AI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimal PSR-4 autoloader for the SitePilot\ namespace.
 * Works without `composer install` so the plugin is installable as-is.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'SitePilot\\';
		$base_dir = SITEPILOT_AI_DIR . 'includes/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Prefer Composer autoload for dev/test tooling if present.
if ( is_readable( SITEPILOT_AI_DIR . 'vendor/autoload.php' ) ) {
	require SITEPILOT_AI_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, array( \SitePilot\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \SitePilot\Core\Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin after all plugins are loaded so connector detection is accurate.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\SitePilot\Core\Plugin::instance()->boot();
	}
);
