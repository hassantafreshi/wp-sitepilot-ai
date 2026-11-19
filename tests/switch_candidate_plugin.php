<?php
/** Temporarily switches only the local XAMPP AISAAutoPilot plugin entry. */
declare(strict_types=1);

$mode = $argv[1] ?? '';
$modes = array(
	'enable'    => 'ai-site-assistant-autopilot-phase1-candidate/ai-site-assistant-autopilot.php',
	'restore'   => 'wp-ai-site-assistant-autopilot/ai-site-assistant-autopilot.php',
	'candidate' => 'ai-site-assistant-autopilot-phase1-candidate/ai-site-assistant-autopilot.php',
	'release'   => 'ai-site-assistant-autopilot/ai-site-assistant-autopilot.php',
	'original'  => 'wp-ai-site-assistant-autopilot/ai-site-assistant-autopilot.php',
);
if ( ! isset( $modes[ $mode ] ) ) {
	fwrite( STDERR, "Usage: php switch_candidate_plugin.php candidate|release|original\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
require aisa_test_wp_root() . '/wp-load.php';

$variants  = array_values( array_unique( $modes ) );
$target    = $modes[ $mode ];
$plugins   = (array) get_option( 'active_plugins', array() );
$next      = array_values( array_diff( $plugins, $variants ) );
$next[]     = $target;

update_option( 'active_plugins', array_values( array_unique( $next ) ), false );
echo 'AISAAutoPilot local plugin switch: ' . $mode . PHP_EOL;
