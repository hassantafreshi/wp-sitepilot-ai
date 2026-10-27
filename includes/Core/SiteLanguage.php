<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

/**
 * Keeps the initial assistant language aligned with the WordPress site while
 * allowing every conversation turn to follow the language the visitor uses.
 */
final class SiteLanguage {

	public static function site(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return self::normalize( (string) $locale );
	}

	public static function normalize( string $locale ): string {
		$locale = strtolower( trim( str_replace( '_', '-', $locale ) ) );
		if ( ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale ) ) {
			return 'en';
		}

		return $locale;
	}

	public static function primary( string $locale ): string {
		return explode( '-', self::normalize( $locale ) )[0];
	}

	/**
	 * The locale for a fixed message to a visitor who writes in another script than the
	 * site's: Persian or Arabic letters on a Latin-script site, Latin letters on a Persian
	 * or Arabic one. Empty when the scripts match, so a German visitor on a German site is
	 * never answered in English.
	 */
	public static function visitor_locale( string $text, string $site_locale ): string {
		$site_rtl = in_array( self::primary( $site_locale ), array( 'fa', 'ar', 'ur', 'ps', 'ckb' ), true );
		if ( ! $site_rtl && preg_match( '/[\x{0600}-\x{06FF}]/u', $text ) ) {
			// Persian has letters Arabic does not (پ چ ژ گ, and its own ک and ی).
			return preg_match( '/[\x{067E}\x{0686}\x{0698}\x{06AF}\x{06A9}\x{06CC}]/u', $text ) ? 'fa_IR' : 'ar';
		}
		if ( $site_rtl && preg_match( '/[a-z]/i', $text ) && ! preg_match( '/[\x{0600}-\x{06FF}]/u', $text ) ) {
			return 'en_US';
		}

		return '';
	}

	public static function response_rule( string $site_locale ): string {
		return sprintf(
			'Use %s as the default language before the user writes or speaks. Once the user uses another language, reply in that language and continue using it until they change language again.',
			self::normalize( $site_locale )
		);
	}
}
