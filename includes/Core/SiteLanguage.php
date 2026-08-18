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

	public static function response_rule( string $site_locale ): string {
		return sprintf(
			'Use %s as the default language before the user writes or speaks. Once the user uses another language, reply in that language and continue using it until they change language again.',
			self::normalize( $site_locale )
		);
	}
}
