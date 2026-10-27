<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

/**
 * Text normalisation and tokenising for the knowledge index.
 *
 * The same rules run on page text at index time and on the visitor's question at search
 * time, so "قيمت" typed on an Arabic keyboard finds "قیمت", "کتاب‌ها" finds "کتابها", and
 * "۱۴٫۵۰" finds "14.50". Pure PHP: no WordPress functions, so it is unit tested directly.
 */
final class KnowledgeText {

	/** Bumped whenever tokenising changes, so stored terms are rebuilt. */
	public const VERSION = 1;

	/** Longest term kept (characters); the index column holds 160 bytes. */
	private const MAX_TERM_CHARS = 40;

	private const CHAR_MAP = array(
		// Arabic letter forms to their Persian equivalents.
		'ي'        => 'ی',
		'ى'        => 'ی',
		'ئ'        => 'ی',
		'ك'        => 'ک',
		'ة'        => 'ه',
		'ۀ'        => 'ه',
		'أ'        => 'ا',
		'إ'        => 'ا',
		'آ'        => 'ا',
		'ٱ'        => 'ا',
		'ؤ'        => 'و',
		'ء'        => '',
		// Persian and Arabic-Indic digits, and their separators.
		'۰'        => '0',
		'۱'        => '1',
		'۲'        => '2',
		'۳'        => '3',
		'۴'        => '4',
		'۵'        => '5',
		'۶'        => '6',
		'۷'        => '7',
		'۸'        => '8',
		'۹'        => '9',
		'٠'        => '0',
		'١'        => '1',
		'٢'        => '2',
		'٣'        => '3',
		'٤'        => '4',
		'٥'        => '5',
		'٦'        => '6',
		'٧'        => '7',
		'٨'        => '8',
		'٩'        => '9',
		'٫'        => '.',
		'٬'        => ',',
		// Zero-width non-joiner separates Persian word parts: treat it as a space.
		"\u{200C}" => ' ',
		"\u{200B}" => '',
		"\u{200D}" => '',
		"\u{FEFF}" => '',
		'ـ'        => '',
	);

	/** Words too common to tell pages apart, per language, space separated. */
	private const STOPWORDS = array(
		'en'        => 'a an the and or but of to in on at for with from by as into about is are was were be been'
			. ' am do does did done have has had will would can could should shall may might must i me my'
			. ' we us our you your he she it its they them their this that these those there here what'
			. ' which who whom whose when where why how much many any some if then than so not no yes'
			. ' please hi hello thanks thank just also get got tell know want like way',
		'fa'        => 'از به با در که را رو این ان او و یا تا برای است هست هستند هستید هستی هستم بود شد شده می'
			. ' نمی ها های هایی چه چی چند چقدر ایا دارید داری دارم دارد داره من ما شما یک هم اما اگر کجا'
			. ' کجاست چطور چگونه لطفا باید هر همه دیگر نیز اینکه چون پس بر بی خود کنم کنید کند میشه میشود'
			. ' سلام ممنون مرسی',
		'fa_spoken' => 'چنده چطوره چیه کیه کدوم کدام هستش داریم دارین میخوام میخواهم بگید بگو یه اون اینجا اونجا'
			. ' خیلی ولی دیگه الان ببخشید',
		'ar'        => 'في من الى على عن هل ما هذا هذه التي الذي مع',
	);

	/** @var array<string, bool>|null */
	private static ?array $stop = null;

	/**
	 * Fold letter forms, digits and spacing so equal words compare equal. Keeps punctuation.
	 */
	public static function normalize( string $text ): string {
		if ( class_exists( '\Normalizer' ) ) {
			$folded = \Normalizer::normalize( $text, \Normalizer::FORM_KC );
			if ( is_string( $folded ) ) {
				$text = $folded;
			}
		}
		$text = strtr( $text, self::CHAR_MAP );
		// Arabic diacritics (harakat) and the superscript alef.
		$text = (string) preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $text );
		$text = mb_strtolower( $text, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Index terms of a text: normalised, stop words removed, lightly stemmed.
	 *
	 * @return array<int, string> In reading order, duplicates kept (term frequency matters).
	 */
	public static function tokens( string $text ): array {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', self::normalize( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$out = array();
		foreach ( $parts as $part ) {
			if ( self::is_stopword( $part ) ) {
				continue;
			}
			$term = self::stem( $part );
			if ( '' === $term || self::is_stopword( $term ) ) {
				continue;
			}
			// One-letter words are noise, but numbers ("9", "7") carry the facts people ask about.
			if ( 1 === mb_strlen( $term ) && ! ctype_digit( $term ) ) {
				continue;
			}
			$out[] = mb_substr( $term, 0, self::MAX_TERM_CHARS );
		}

		return $out;
	}

	/**
	 * Term frequencies of a text.
	 *
	 * @return array<string, int>
	 */
	public static function frequencies( string $text, int $weight = 1 ): array {
		$counts = array();
		foreach ( self::tokens( $text ) as $term ) {
			$counts[ $term ] = ( $counts[ $term ] ?? 0 ) + $weight;
		}

		return $counts;
	}

	/**
	 * Other forms to try for a question word that no page uses. A Persian word with an
	 * attached pronoun ("قیمتش", its price) is looked up without it. Search side only, so
	 * the index keeps whole words and "دست" is never cut to "دس".
	 *
	 * @return array<int, string>
	 */
	public static function variants( string $term ): array {
		if ( ! preg_match( '/^[\x{0600}-\x{06FF}]+$/u', $term ) ) {
			return array();
		}
		$out = array();
		foreach ( array( 'شان', 'تان', 'مان', 'شون', 'تون', 'مون', 'ش', 'ت', 'م' ) as $suffix ) {
			$base = mb_substr( $term, 0, mb_strlen( $term ) - mb_strlen( $suffix ) );
			if ( mb_strlen( $base ) >= 3 && str_ends_with( $term, $suffix ) ) {
				$out[] = self::stem( $base );
			}
		}

		return array_values( array_unique( array_diff( $out, array( $term ) ) ) );
	}

	public static function is_stopword( string $word ): bool {
		if ( null === self::$stop ) {
			self::$stop = array();
			foreach ( self::STOPWORDS as $list ) {
				foreach ( explode( ' ', $list ) as $stop ) {
					self::$stop[ self::normalize( $stop ) ] = true;
				}
			}
		}

		return isset( self::$stop[ $word ] );
	}

	/**
	 * Light, consistent stemming. It does not need to be linguistically right, only the
	 * same on both sides: plural "-s" in English, "-ها/-های" and the verb prefix "می"
	 * in Persian (typed with or without the zero-width joiner), "ال" in Arabic.
	 */
	private static function stem( string $word ): string {
		if ( preg_match( '/^[a-z]+$/', $word ) ) {
			$len = strlen( $word );
			if ( $len > 4 && str_ends_with( $word, 'ies' ) ) {
				return substr( $word, 0, -3 ) . 'y';
			}
			if ( $len > 3 && str_ends_with( $word, 's' ) && ! preg_match( '/(ss|us|is)$/', $word ) ) {
				return substr( $word, 0, -1 );
			}

			return $word;
		}

		$len = mb_strlen( $word );
		if ( $len >= 6 && str_starts_with( $word, 'نمی' ) ) {
			$word = mb_substr( $word, 3 );
		} elseif ( $len >= 5 && str_starts_with( $word, 'می' ) ) {
			$word = mb_substr( $word, 2 );
		} elseif ( $len >= 5 && str_starts_with( $word, 'ال' ) ) {
			$word = mb_substr( $word, 2 );
		}
		foreach ( array( 'هایی', 'های', 'ها' ) as $suffix ) {
			if ( mb_strlen( $word ) >= mb_strlen( $suffix ) + 3 && str_ends_with( $word, $suffix ) ) {
				return mb_substr( $word, 0, mb_strlen( $word ) - mb_strlen( $suffix ) );
			}
		}

		return $word;
	}
}
