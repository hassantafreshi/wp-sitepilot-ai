<?php
/** Temporarily switches only the local XAMPP SitePilot plugin entry. */
declare(strict_types=1);

$mode = $argv[1] ?? '';
$modes = array(
	'enable'    => 'sitepilot-ai-phase1-candidate/sitepilot-ai.php',
	'restore'   => 'wp-sitepilot-ai/sitepilot-ai.php',
	'candidate' => 'sitepilot-ai-phase1-candidate/sitepilot-ai.php',
	'release'   => 'sitepilot-ai/sitepilot-ai.php',
	'original'  => 'wp-sitepilot-ai/sitepilot-ai.php',
);
if ( ! isset( $modes[ $mode ] ) ) {
	fwrite( STDERR, "Usage: php switch_candidate_plugin.php candidate|release|original\n" );
	exit( 64 );
}

require 'C:\\xampp\\htdocs\\wp\\wp-load.php';

$variants  = array_values( array_unique( $modes ) );
$target    = $modes[ $mode ];
$plugins   = (array) get_option( 'active_plugins', array() );
$next      = array_values( array_diff( $plugins, $variants ) );
$next[]     = $target;

update_option( 'active_plugins', array_values( array_unique( $next ) ), false );
echo 'SitePilot local plugin switch: ' . $mode . PHP_EOL;
