<?php
/**
 * Plugin Name: SitePilot AI
 * Plugin URI: https://sitepilot.ai
 * Description: AI copilot for WordPress — form builder, content intelligence, RAG chat, voice assistant, frontend widget.
 * Version: 0.5.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Author: SitePilot AI
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sitepilot-ai
 * Domain Path: /languages
 *
 * @package SitePilot\AI
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITEPILOT_AI_VERSION', '1.0.0' );
define( 'SITEPILOT_AI_FILE', __FILE__ );
define( 'SITEPILOT_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITEPILOT_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'SITEPILOT_AI_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
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
} );

// Composer fallback
$autoload = SITEPILOT_AI_DIR . 'vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require $autoload;
}

register_activation_hook( __FILE__, array( \SitePilot\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \SitePilot\Core\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', static function () {
	\SitePilot\Core\Plugin::instance()->boot();
} );
