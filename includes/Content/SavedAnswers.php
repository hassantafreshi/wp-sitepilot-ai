<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Support\HandoffContact;
use AISAAutoPilot\Support\OptionLock;

/**
 * Answers the site owner wrote for questions visitors ask.
 *
 * When a visitor asks a stand-alone question that matches a saved answer, ChatEngine replies with the
 * owner's text and makes no AI request. Matching is exact after normalising: letter case, spaces,
 * punctuation, Persian and Arabic letter and digit variants, and zero-width characters are ignored, so
 * "Do you ship to Canada?" matches "do you ship to canada". A differently worded question does not match;
 * the admin screen says so. Answers are plain text, stored as typed and escaped by every output.
 */
final class SavedAnswers {

	private const OPTION      = 'aisaautopilot_kb_saved_answers';
	public const MAX_KEEP     = 200;
	public const QUESTION_MAX = 300;
	public const ANSWER_MAX   = 2000;

	/**
	 * All saved answers, most recently changed first.
	 *
	 * @return array<int, array{id:string,question:string,answer:string,created:string,updated:string,hits:int,last_used:string}>
	 */
	public function list(): array {
		$items = array_values( $this->all() );
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcmp( $b['updated'], $a['updated'] );
			}
		);
		return $items;
	}

	/**
	 * @return array<string, array{id:string,question:string,answer:string,created:string,updated:string,hits:int,last_used:string}>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$out = array();
		foreach ( $stored as $id => $item ) {
			if ( is_array( $item ) && '' !== (string) ( $item['answer'] ?? '' ) ) {
				$out[ (string) $id ] = self::normalize( (string) $id, $item );
			}
		}
		return $out;
	}

	/** The saved answer that matches a question, or null. */
	public function find( string $question ): ?array {
		$key = self::key( $question );
		if ( '' === $key ) {
			return null;
		}
		$all = $this->all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Saves an answer for a question. With $previous_id, the answer stored under that id is replaced, so
	 * rewording the question moves the answer instead of leaving a copy behind.
	 *
	 * @return array{ok:bool,code:string,item?:array<string,mixed>}
	 */
	public function save( string $question, string $answer, string $previous_id = '' ): array {
		$question = mb_substr( self::plain( $question, false ), 0, self::QUESTION_MAX );
		$answer   = self::plain( $answer, true );
		$key      = self::key( $question );
		if ( '' === $key ) {
			return array(
				'ok'   => false,
				'code' => 'invalid_question',
			);
		}
		if ( '' === $answer || mb_strlen( $answer ) > self::ANSWER_MAX ) {
			return array(
				'ok'   => false,
				'code' => 'invalid_answer',
			);
		}

		$result = array(
			'ok'   => false,
			'code' => 'invalid_question',
		);
		$now    = gmdate( 'c' );
		OptionLock::mutate(
			self::OPTION,
			static function ( array $stored ) use ( $key, $question, $answer, $previous_id, $now, &$result ): array {
				$existing = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? self::normalize( $key, $stored[ $key ] ) : null;
				if ( null !== $existing && '' !== $previous_id && $previous_id !== $key ) {
					$result = array(
						'ok'   => false,
						'code' => 'duplicate_question',
					);
					return $stored;
				}
				$moved = '' !== $previous_id && $previous_id !== $key && isset( $stored[ $previous_id ] ) && is_array( $stored[ $previous_id ] )
					? self::normalize( $previous_id, $stored[ $previous_id ] )
					: null;
				$base  = $existing ?? $moved;
				if ( null === $base && count( $stored ) >= self::MAX_KEEP ) {
					$result = array(
						'ok'   => false,
						'code' => 'saved_answers_full',
					);
					return $stored;
				}
				if ( null !== $moved ) {
					unset( $stored[ $previous_id ] );
				}
				$item           = array(
					'question'  => $question,
					'answer'    => $answer,
					'created'   => null !== $base ? $base['created'] : $now,
					'updated'   => $now,
					'hits'      => null !== $base ? $base['hits'] : 0,
					'last_used' => null !== $base ? $base['last_used'] : '',
				);
				$stored[ $key ] = $item;
				$result         = array(
					'ok'   => true,
					'code' => null !== $base ? 'updated' : 'created',
					'item' => self::normalize( $key, $item ),
				);
				return $stored;
			}
		);
		return $result;
	}

	public function delete( string $id ): bool {
		if ( ! isset( $this->all()[ $id ] ) ) {
			return false;
		}
		OptionLock::mutate(
			self::OPTION,
			static function ( array $stored ) use ( $id ): array {
				unset( $stored[ $id ] );
				return $stored;
			}
		);
		return true;
	}

	/** Counts one use of a saved answer in a visitor or console reply. */
	public function record_use( string $id ): void {
		$now = gmdate( 'c' );
		OptionLock::mutate(
			self::OPTION,
			static function ( array $stored ) use ( $id, $now ): array {
				if ( isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ) {
					$stored[ $id ]['hits']      = max( 0, (int) ( $stored[ $id ]['hits'] ?? 0 ) ) + 1;
					$stored[ $id ]['last_used'] = $now;
				}
				return $stored;
			}
		);
	}

	/**
	 * The id a question is stored and matched under, or '' when nothing is left to match. Case, spacing,
	 * punctuation, symbols, zero-width characters, diacritics, Arabic letter forms and Persian or
	 * Arabic-Indic digits are ignored.
	 */
	public static function key( string $question ): string {
		$text = html_entity_decode( $question, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = mb_strtolower( HandoffContact::latin_digits( $text ) );
		$text = strtr(
			$text,
			array(
				'ي' => 'ی',
				'ى' => 'ی',
				'ك' => 'ک',
				'ة' => 'ه',
				'أ' => 'ا',
				'إ' => 'ا',
			)
		);
		$text = (string) preg_replace( '/[\p{Mn}\p{P}\p{S}\p{Z}\p{Cc}\p{Cf}\s]+/u', '', $text );
		return '' === $text ? '' : md5( $text );
	}

	/** Plain text as typed: invalid UTF-8 and control characters removed; line breaks kept for answers. */
	private static function plain( string $text, bool $multiline ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( $text, true ) );
		$text = (string) preg_replace( $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', '', $text );
		return trim( $text );
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array{id:string,question:string,answer:string,created:string,updated:string,hits:int,last_used:string}
	 */
	private static function normalize( string $id, array $item ): array {
		return array(
			'id'        => $id,
			'question'  => (string) ( $item['question'] ?? '' ),
			'answer'    => (string) ( $item['answer'] ?? '' ),
			'created'   => (string) ( $item['created'] ?? '' ),
			'updated'   => (string) ( $item['updated'] ?? '' ),
			'hits'      => max( 0, (int) ( $item['hits'] ?? 0 ) ),
			'last_used' => (string) ( $item['last_used'] ?? '' ),
		);
	}
}
