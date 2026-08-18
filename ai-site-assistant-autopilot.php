<?php
/**
 * Plugin Name: AI Site Assistant AutoPilot
 * Plugin URI: https://aisaautopilot.ai
 * Description: Safe AI copilot for email forms, voice commands, Easy Form Builder, and WooCommerce product drafts.
 * Version: 1.0.4
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: AI Site Assistant AutoPilot
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-site-assistant-autopilot
 * Domain Path: /languages
 *
 * @package AISAAutoPilot
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AISAAUTOPILOT_VERSION', '1.0.4' );
define( 'AISAAUTOPILOT_FILE', __FILE__ );
define( 'AISAAUTOPILOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISAAUTOPILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'AISAAUTOPILOT_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'AISAAutoPilot\\';
		$base_dir = AISAAUTOPILOT_DIR . 'includes/';
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

// Composer fallback. The path is not held in a plugin-scope variable, which
// would land in the global namespace unprefixed.
if ( is_readable( AISAAUTOPILOT_DIR . 'vendor/autoload.php' ) ) {
	require AISAAUTOPILOT_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, array( \AISAAutoPilot\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \AISAAutoPilot\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\AISAAutoPilot\Core\Plugin::instance()->boot();
	}
);
