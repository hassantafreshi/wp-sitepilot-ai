<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Mirrors Easy Form Builder's own plan flag.
 *
 * EFB stores its plan in `emsfb_pro` and treats both 1 (Pro) and 3 (Free Plus)
 * as having Pro field components available in the builder.
 */
final class EfbLicense {

	/** @return array{code:int,tier:string,has_pro_features:bool,upgrade_url:string} */
	public static function status(): array {
		$code = function_exists( 'get_option' ) ? (int) get_option( 'emsfb_pro', 2 ) : 2;
		$tier = 1 === $code ? 'pro' : ( 3 === $code ? 'free_plus' : 'free' );

		return array(
			'code'             => $code,
			'tier'             => $tier,
			// Same condition EFB uses for its `pro_efb` builder variable.
			'has_pro_features' => in_array( $code, array( 1, 3 ), true ),
			'upgrade_url'      => self::upgrade_url(),
		);
	}

	public static function has_pro_features(): bool {
		return self::status()['has_pro_features'];
	}

	/** Official destination used by EFB's open_whiteStudio_efb('pro'). */
	public static function upgrade_url(): string {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : '' );

		return str_starts_with( (string) $locale, 'fa_' )
			? 'https://easyformbuilder.ir/#price'
			: 'https://whitestudio.team/#price';
	}
}
