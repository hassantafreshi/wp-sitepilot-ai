<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * The Easy Form Builder add-ons the copilot may offer to install, and whether this site's plan allows it.
 *
 * The install itself is never done here: the browser asks the administrator first and then calls EFB's
 * own installer (`add_addons_Emsfb`), which checks the nonce, the capability and the plan again and
 * downloads the add-on from EFB's own server. This class only names the add-on a refused request needs.
 */
final class EfbAddons {

	/** Add-on code => what it is for, and the EFB plan it needs (EFB's addon_required_package_efb: 3 = Free Plus or Pro, 1 = Pro). */
	public const OFFERABLE = array(
		'AdnSMF' => array(
			'name'    => 'Conditional Logic',
			'package' => 3,
		),
		'AdnSPF' => array(
			'name'    => 'Stripe',
			'package' => 1,
		),
		'AdnPAP' => array(
			'name'    => 'PayPal',
			'package' => 1,
		),
		'AdnPPF' => array(
			'name'    => 'ZarinPal / Persia Payment',
			'package' => 1,
		),
		'AdnSS'  => array(
			'name'    => 'SMS notifications',
			'package' => 1,
		),
	);

	/** The add-on a refusal reason points at, or null. */
	public static function for_reason( string $reason ): ?string {
		if ( 'logic_addon_inactive' === $reason ) {
			return 'AdnSMF';
		}
		if ( str_starts_with( $reason, 'addon_inactive:' ) ) {
			return substr( $reason, strlen( 'addon_inactive:' ) );
		}
		if ( str_starts_with( $reason, 'payment_addon_inactive:' ) ) {
			$gateway = substr( $reason, strlen( 'payment_addon_inactive:' ) );
			return EfbPayments::GATEWAYS[ $gateway ]['addon'] ?? null;
		}

		return null;
	}

	/**
	 * Whether this site's plan lets the add-on in. Asks EFB itself when it can, so the answer matches
	 * what its installer will decide; otherwise reads EFB's plan flag the same way.
	 */
	public static function plan_allows( string $code ): bool {
		if ( function_exists( 'get_efbFunction' ) ) {
			$efb = get_efbFunction();
			if ( is_object( $efb ) && method_exists( $efb, 'addon_plan_allows_install_efb' ) ) {
				return (bool) $efb->addon_plan_allows_install_efb( $code );
			}
		}
		$package = (int) ( self::OFFERABLE[ $code ]['package'] ?? 1 );
		$tier    = EfbLicense::status()['tier'];

		return 'pro' === $tier || ( 3 === $package && 'free_plus' === $tier );
	}

	/**
	 * The install offers that go with a set of refusal reasons: each add-on once, with what the plan allows.
	 *
	 * @param string[] $reasons
	 * @return array<int,array{code:string,name:string,allowed:bool,plan:string,upgrade_url:string}>
	 */
	public static function offers( array $reasons ): array {
		$out  = array();
		$seen = array();
		foreach ( $reasons as $reason ) {
			$code = self::for_reason( (string) $reason );
			if ( null === $code || isset( $seen[ $code ] ) || ! isset( self::OFFERABLE[ $code ] ) ) {
				continue;
			}
			$seen[ $code ] = true;
			$out[]         = array(
				'code'        => $code,
				'name'        => self::OFFERABLE[ $code ]['name'],
				'allowed'     => self::plan_allows( $code ),
				'plan'        => 3 === self::OFFERABLE[ $code ]['package'] ? 'free_plus' : 'pro',
				'upgrade_url' => EfbLicense::upgrade_url(),
			);
		}

		return $out;
	}
}
