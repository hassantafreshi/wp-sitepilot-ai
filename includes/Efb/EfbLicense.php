<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * The site's Easy Form Builder plan.
 *
 * EFB itself answers this through its registry (`capabilities.plan`: free | free_plus | pro | pro_pending), and that
 * answer is used whenever EFB publishes it. Only an EFB too old to have the registry is read from its plan option
 * `emsfb_pro` the way EFB reads it (1 Pro, 3 Free Plus, 0 Pro not activated yet, anything else Free). The plan is
 * reported, never changed: only a person changes it in EFB's own upgrade dialog.
 */
final class EfbLicense {

	/** @return array{code:int,tier:string,plan:string,has_pro_features:bool,is_pro:bool,one_time:bool,source:string,upgrade_url:string} */
	public static function status(): array {
		$plan   = EfbCapabilities::plan();
		$source = 'registry';
		if ( '' === $plan ) {
			$source = 'option';
			$code   = function_exists( 'get_option' ) ? (int) get_option( 'emsfb_pro', 2 ) : 2;
			$plan   = 1 === $code ? 'pro' : ( 3 === $code ? 'free_plus' : ( 0 === $code ? 'pro_pending' : 'free' ) );
		} else {
			$caps = EfbCapabilities::block( 'plan' );
			$code = (int) ( $caps['package_type'] ?? ( 'pro' === $plan ? 1 : ( 'free_plus' === $plan ? 3 : 2 ) ) );
		}
		// A Pro licence that is not active yet unlocks nothing (EFB counts it as Free).
		$tier = 'pro_pending' === $plan ? 'free' : $plan;

		return array(
			'code'             => $code,
			'tier'             => $tier,
			'plan'             => $plan,
			// Free Plus and Pro both unlock EFB's "Pro fields" in its builder.
			'has_pro_features' => in_array( $tier, array( 'pro', 'free_plus' ), true ),
			// Pro itself: the mobile layout block and the Pro add-ons stay locked on Free Plus.
			'is_pro'           => 'pro' === $tier,
			// fa_IR sites buy Pro once (no yearly renewal); everywhere else it is a subscription.
			'one_time'         => EfbCapabilities::one_time_purchase(),
			'source'           => $source,
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
