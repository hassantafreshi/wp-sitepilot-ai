<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

/**
 * Keeps the initial assistant language aligned with the WordPress site while
 * allowing every conversation turn to follow the language the visitor uses.
 */
final class SiteLanguage {

	/** Primary language codes written right to left. */
	private const RTL = array( 'fa', 'ar', 'ur', 'ps', 'ckb', 'he', 'ug', 'sd', 'dv', 'yi' );

	/**
	 * The language of the current request: the admin's own on a REST call with
	 * `_locale=user`, otherwise the site's. Conversations with the admin start here.
	 */
	public static function site(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return self::normalize( (string) $locale );
	}

	/**
	 * The site's own language, the one visitors read, whatever language the admin
	 * uses in the dashboard.
	 */
	public static function site_locale(): string {
		return self::normalize( function_exists( 'get_locale' ) ? (string) get_locale() : 'en' );
	}

	public static function is_rtl( string $locale ): bool {
		return in_array( self::primary( $locale ), self::RTL, true );
	}

	/**
	 * The prompt rule for text visitors will read on a form: title, labels,
	 * placeholders, help, options, button and messages. The site language wins over
	 * the language of the admin's request, so a Persian site gets a Persian form even
	 * when the admin asks in English; only an explicit ask for another language
	 * changes that.
	 */
	public static function form_text_rule( string $site_locale ): string {
		$locale = self::normalize( $site_locale );

		return sprintf(
			'Write every text a visitor will read on the form (title, labels, placeholders, help, options, button text and messages) in the site language "%1$s"%2$s, even when the request is written in another language: visitors read the form in the site language. Use another language only when the request explicitly asks for it (for example "in English"). Keep any wording the request quotes exactly as written.',
			$locale,
			self::is_rtl( $locale ) ? ' (a right-to-left language)' : ''
		);
	}

	/**
	 * Runs a callback under the site language, so a translated string that becomes
	 * part of a form (a button label, a consent sentence) follows the site and not
	 * the admin's dashboard language on a `_locale=user` request. Without WordPress,
	 * or when the two languages match, the callback simply runs.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function for_site( callable $callback ) {
		$switched = function_exists( 'switch_to_locale' ) && function_exists( 'determine_locale' )
			&& get_locale() !== determine_locale() && switch_to_locale( get_locale() );
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
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
		$site_rtl = self::is_rtl( $site_locale );
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
