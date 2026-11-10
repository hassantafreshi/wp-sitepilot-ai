<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Answers "how do I …?" questions about the Easy Form Builder editor from a
 * curated, grounded knowledge base (no hallucinated UI steps).
 */
final class EfbKnowledge {

	/** @var array<string, mixed>|null */
	private static ?array $data = null;

	/** @return array<string, mixed> */
	public static function data(): array {
		if ( null === self::$data ) {
			$file       = __DIR__ . '/efb-knowledge.json';
			$raw        = is_readable( $file ) ? (string) file_get_contents( $file ) : '{}';
			$decoded    = json_decode( $raw, true );
			self::$data = is_array( $decoded ) ? $decoded : array();
		}

		return self::$data;
	}

	/** @return array<int, array<string, mixed>> */
	public static function entries(): array {
		$e = self::data()['entries'] ?? array();

		return is_array( $e ) ? $e : array();
	}

	/**
	 * Find the best-matching entry for a question and return a full instruction.
	 *
	 * @return array{ok:bool, id?:string, title?:string, answer?:string, suggestions?:array<int,array{id:string,title:string}>}
	 */
	public static function answer( string $question ): array {
		$q = self::normalize( $question );
		if ( '' === $q ) {
			return self::suggestions();
		}
		// Common words match half the entries ("how do I add the form ..."), so only words that say
		// something count; a question that shares none is answered with the topic list, not a wrong article.
		$stop   = array( 'the', 'and', 'for', 'how', 'can', 'you', 'your', 'with', 'this', 'that', 'form', 'forms', 'field', 'fields', 'add', 'set', 'make', 'change', 'want', 'need', 'please', 'into', 'from', 'what', 'where', 'does', 'its', 'are', 'not', 'use' );
		$tokens = array_filter( explode( ' ', $q ), static fn ( $t ) => strlen( $t ) >= 3 && ! in_array( $t, $stop, true ) );

		$best       = null;
		$best_score = 0;

		foreach ( self::entries() as $entry ) {
			$score     = 0;
			$aliases   = (array) ( $entry['aliases'] ?? array() );
			$aliases[] = (string) ( $entry['title'] ?? '' );

			foreach ( $aliases as $alias ) {
				$a = self::normalize( (string) $alias );
				if ( '' === $a ) {
					continue;
				}
				// Whole alias phrase appears in the question (strong signal).
				if ( str_contains( $q, $a ) ) {
					$score += 5 + substr_count( $a, ' ' );
				}
				// Token overlap (weaker signal).
				$alias_words = explode( ' ', $a );
				foreach ( $tokens as $t ) {
					if ( in_array( $t, $alias_words, true ) ) {
						++$score;
					}
				}
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $entry;
			}
		}

		if ( null === $best || $best_score < 3 ) {
			return self::suggestions();
		}

		return array(
			'ok'     => true,
			'id'     => (string) $best['id'],
			'title'  => (string) $best['title'],
			'answer' => self::compose( $best ),
		);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private static function compose( array $entry ): string {
		$ui    = self::data()['ui_pattern'] ?? array();
		$scope = (string) ( $entry['scope'] ?? 'field' );
		$steps = array();

		if ( 'field' === $scope && ! empty( $ui['open_field_settings'] ) ) {
			$steps[] = (string) $ui['open_field_settings'];
		} elseif ( 'form' === $scope && ! empty( $ui['open_form_settings'] ) ) {
			$steps[] = (string) $ui['open_form_settings'];
		}

		$steps[] = (string) ( $entry['answer'] ?? '' );

		if ( in_array( $scope, array( 'field', 'form' ), true ) && ! empty( $ui['save'] ) ) {
			$steps[] = (string) $ui['save'];
		}

		return implode( "\n\n", array_filter( $steps ) );
	}

	/**
	 * @return array{ok:bool, suggestions:array<int,array{id:string,title:string}>}
	 */
	private static function suggestions(): array {
		$out = array();
		foreach ( self::entries() as $entry ) {
			$out[] = array(
				'id'    => (string) $entry['id'],
				'title' => (string) $entry['title'],
			);
		}

		return array(
			'ok'          => false,
			'suggestions' => $out,
		);
	}

	private static function normalize( string $s ): string {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		$s = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );

		return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	}
}
