<?php
/**
 * Creates and restores the isolated Advanced settings browser-QA fixture.
 *
 * Prepare snapshots the whole general settings option (including whether it existed) and sets
 * a known baseline for the switches the browser check changes. Restore writes the snapshot back
 * exactly. Provider keys, the embedding key and verification live in other options and are never
 * read or changed here; the browser check never types a key.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_advanced_settings_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

const FIXTURE_OPTION = 'aisaautopilot_advanced_settings_browser_fixture';

$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here.
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'exists', $record ) ) {
		throw new RuntimeException( 'Advanced settings fixture snapshot is invalid; live settings were left untouched.' );
	}
	if ( ! empty( $record['exists'] ) ) {
		update_option( Settings::OPTION, $record['value'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Advanced settings browser fixture removed.\n";
	exit( 0 );
}

$restore();

try {
	$missing  = new stdClass();
	$original = get_option( Settings::OPTION, $missing );
	update_option(
		FIXTURE_OPTION,
		array(
			'exists' => $original !== $missing,
			'value'  => $original !== $missing ? $original : null,
		),
		false
	);
	Settings::update(
		array(
			'embeddings_enabled'  => false,
			'prefer_efb'          => false,
			'default_form_status' => 'draft',
			'voice_model'         => 'whisper-1',
		)
	);
	echo "{}\n";
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
