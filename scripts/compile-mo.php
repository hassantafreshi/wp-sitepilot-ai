<?php
/**
 * Compile a gettext PO file to MO using the POMO copy bundled with WordPress.
 *
 * Usage: php scripts/compile-mo.php <wp-root> <input.po> <output.mo>
 */
declare(strict_types=1);

if ( 4 !== $argc ) {
	fwrite( STDERR, "Usage: php scripts/compile-mo.php <wp-root> <input.po> <output.mo>\n" );
	exit( 64 );
}

$pomo = rtrim( $argv[1], '/\\' ) . '/wp-includes/pomo';
if ( ! is_file( $pomo . '/po.php' ) || ! is_file( $pomo . '/mo.php' ) ) {
	fwrite( STDERR, "WordPress POMO files were not found.\n" );
	exit( 66 );
}

// WordPress 6.9 POMO uses this PHP 8.4 helper while local release QA runs 8.2.
if ( ! function_exists( 'array_last' ) ) {
	function array_last( array $array ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		return array() === $array ? null : end( $array );
	}
}

require_once $pomo . '/po.php';
require_once $pomo . '/mo.php';

$po = new PO();
if ( ! $po->import_from_file( $argv[2] ) ) {
	fwrite( STDERR, "Could not parse the PO file.\n" );
	exit( 65 );
}

$mo = new MO();
$mo->merge_with( $po );
$mo->set_headers( $po->headers );
if ( ! $mo->export_to_file( $argv[3] ) ) {
	fwrite( STDERR, "Could not write the MO file.\n" );
	exit( 73 );
}

/*
 * MO::export_to_file() writes only entries that carry a translation, so
 * count( $mo->entries ) is the number of PO entries merged in, not the number
 * of entries the compiled MO actually contains. Report both, or the recorded
 * gate number overstates what ships.
 */
$merged     = count( $mo->entries );
$translated = 0;
foreach ( $mo->entries as $entry ) {
	foreach ( (array) $entry->translations as $translation ) {
		if ( '' !== trim( (string) $translation ) ) {
			++$translated;
			break;
		}
	}
}

printf(
	"%d PO entries merged; %d of them translated and written to the MO (%d untranslated, not written).\n",
	$merged,
	$translated,
	$merged - $translated
);
