<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Support\HandoffContact;

/**
 * The one way a question is compared, for saved answers, the answer cache and the list of
 * unanswered questions, so "Do you ship to Canada?" and "do you ship to canada" are the same
 * question everywhere, and so are Persian questions typed on an Arabic keyboard.
 *
 * This is the rule saved answers have always used, kept as it was so stored answers keep
 * matching. Pure PHP; unit tested directly.
 */
final class QuestionKey {

	/**
	 * The id a question is stored and matched under, or '' when nothing is left to match. Case, spacing,
	 * punctuation, symbols, zero-width characters, diacritics, Arabic letter forms and Persian or
	 * Arabic-Indic digits are ignored.
	 */
	public static function of( string $question ): string {
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
}
