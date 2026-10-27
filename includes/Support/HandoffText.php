<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

/**
 * Shapes the plain-text handoff summary for channels with length or format limits.
 * Pure PHP; unit tested directly.
 */
final class HandoffText {

	/**
	 * Split text into pieces of at most $max characters, between lines where possible.
	 * Counts characters, not bytes, so a Persian line is never cut inside a letter.
	 *
	 * @return array<int, string>
	 */
	public static function split( string $text, int $max ): array {
		if ( mb_strlen( $text ) <= $max ) {
			return array( $text );
		}
		$chunks  = array();
		$current = '';
		foreach ( explode( "\n", $text ) as $line ) {
			if ( mb_strlen( $line ) > $max ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}
				foreach ( mb_str_split( $line, $max ) as $piece ) {
					$chunks[] = $piece;
				}
				continue;
			}
			$candidate = '' === $current ? $line : $current . "\n" . $line;
			if ( mb_strlen( $candidate ) > $max ) {
				$chunks[] = $current;
				$current  = $line;
			} else {
				$current = $candidate;
			}
		}
		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	/**
	 * The text on one line, lines joined with " · " and runs of spaces collapsed, cut to
	 * $max characters. A WhatsApp template parameter takes no line breaks or tabs.
	 */
	public static function one_line( string $text, int $max ): string {
		$lines = array_filter( array_map( 'trim', preg_split( '/\R/u', $text ) ?: array() ), static fn( string $line ): bool => '' !== $line );
		$line  = (string) preg_replace( '/\s+/u', ' ', implode( ' · ', $lines ) );
		if ( mb_strlen( $line ) <= $max ) {
			return $line;
		}

		return rtrim( mb_substr( $line, 0, $max - 1 ) ) . '…';
	}
}
