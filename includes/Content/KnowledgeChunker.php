<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

/**
 * Splits a post's sections into passages of about a paragraph or two.
 *
 * Search returns passages, not whole posts, so the model reads the exact lines that
 * answer the question instead of a summary that dropped the price. Breaks fall between
 * paragraphs, then between sentences, and only as a last resort inside a sentence.
 * Pure PHP; unit tested directly.
 */
final class KnowledgeChunker {

	/** Aim for passages of this many characters. */
	public const TARGET_CHARS = 900;

	/** Never exceed this many characters in one passage. */
	public const MAX_CHARS = 1400;

	/** A passage shorter than this is merged into its neighbour. */
	public const MIN_CHARS = 250;

	/**
	 * @param array<int, array{heading:string, text:string}> $sections
	 * @return array<int, array{heading:string, text:string}>
	 */
	public static function chunk( array $sections, string $facts = '', string $excerpt = '' ): array {
		$chunks = array();

		// Shop data and the excerpt are what visitors ask about most: their own passage, first.
		$lead = trim( implode( "\n", array_filter( array( $facts, $excerpt ) ) ) );
		if ( '' !== $lead ) {
			foreach ( self::split_long( $lead ) as $piece ) {
				$chunks[] = array(
					'heading' => '',
					'text'    => $piece,
				);
			}
		}

		$buffer  = '';
		$heading = '';
		$flush   = static function () use ( &$chunks, &$buffer, &$heading ): void {
			$text = trim( $buffer );
			if ( '' !== $text ) {
				$chunks[] = array(
					'heading' => $heading,
					'text'    => $text,
				);
			}
			$buffer = '';
		};

		foreach ( $sections as $section ) {
			$section_heading = trim( (string) ( $section['heading'] ?? '' ) );
			$paragraphs      = preg_split( '/\n+/u', trim( (string) ( $section['text'] ?? '' ) ) ) ?: array();

			// A new heading starts a new passage unless the current one is still too short to stand alone.
			if ( '' !== trim( $buffer ) && mb_strlen( $buffer ) >= self::MIN_CHARS ) {
				$flush();
			}
			if ( '' === trim( $buffer ) ) {
				$heading = $section_heading;
			} elseif ( '' !== $section_heading ) {
				$buffer .= "\n" . $section_heading;
			}

			foreach ( $paragraphs as $paragraph ) {
				$paragraph = trim( $paragraph );
				if ( '' === $paragraph ) {
					continue;
				}
				foreach ( self::split_long( $paragraph ) as $piece ) {
					if ( '' !== $buffer && mb_strlen( $buffer ) + mb_strlen( $piece ) + 1 > self::TARGET_CHARS && mb_strlen( $buffer ) >= self::MIN_CHARS ) {
						$flush();
						$heading = $section_heading;
					}
					$buffer .= ( '' === $buffer ? '' : "\n" ) . $piece;
				}
			}
		}
		$flush();

		return $chunks;
	}

	/**
	 * Break text longer than MAX_CHARS at sentence ends, then at spaces.
	 *
	 * @return array<int, string>
	 */
	private static function split_long( string $text ): array {
		if ( mb_strlen( $text ) <= self::MAX_CHARS ) {
			return array( $text );
		}
		$sentences = preg_split( '/(?<=[.!?؟。])\s+/u', $text ) ?: array( $text );
		$out       = array();
		$current   = '';
		foreach ( $sentences as $sentence ) {
			while ( mb_strlen( $sentence ) > self::MAX_CHARS ) {
				$cut = mb_strrpos( mb_substr( $sentence, 0, self::MAX_CHARS ), ' ' );
				$cut = false === $cut || $cut < self::MIN_CHARS ? self::MAX_CHARS : $cut;
				if ( '' !== $current ) {
					$out[]   = trim( $current );
					$current = '';
				}
				$out[]    = trim( mb_substr( $sentence, 0, $cut ) );
				$sentence = trim( mb_substr( $sentence, $cut ) );
			}
			if ( '' !== $current && mb_strlen( $current ) + mb_strlen( $sentence ) + 1 > self::TARGET_CHARS ) {
				$out[]   = trim( $current );
				$current = '';
			}
			$current .= ( '' === $current ? '' : ' ' ) . $sentence;
		}
		if ( '' !== trim( $current ) ) {
			$out[] = trim( $current );
		}

		return $out;
	}
}
