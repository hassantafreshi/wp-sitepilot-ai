<?php
/**
 * Easy Form Builder's capability registry for unit tests.
 *
 * `tests/fixtures/efb-capabilities-free-plus.json` is the registry a real Easy Form Builder (`dev4`, the SPLIT build)
 * returned from `get_efbFunction()->capabilities_efb()` on a Free Plus site with the Numbers & Calculations, Typed
 * Signature and Address & Postal add-ons active (trimmed to the blocks the copilot reads; no site address, no key).
 * Its `portal_core` block (and `split.contracts.portal`) is a fresh dump from EFB 4.3.2 `dev4` (portal contract 1.5): the
 * portal is the free plugin EFB Customer Portal, installed but not active on that site; `AdnPRT` left the catalogue in 1.1.
 * The helpers derive other sites from it the way EFB computes them: another plan, an add-on switched off or on.
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbCapabilities;

final class EfbRegistryFixture {

	private const RANK = array(
		'free'        => 0,
		'pro_pending' => 0,
		'free_plus'   => 1,
		'pro'         => 2,
	);

	/** @return array<string,mixed> */
	public static function load(): array {
		$raw = (string) file_get_contents( __DIR__ . '/fixtures/efb-capabilities-free-plus.json' );

		return (array) json_decode( $raw, true );
	}

	/**
	 * The registry for a plan and a set of active add-ons, recomputed with EFB's own rule
	 * (Builder_Api::capabilities(): available_now = plan reached and add-on on; reason plan before addon).
	 *
	 * @param array<string,bool> $addons Add-on code => active.
	 * @return array<string,mixed>
	 */
	public static function site( string $plan = 'free_plus', array $addons = array() ): array {
		$caps = self::load();
		foreach ( $addons as $code => $on ) {
			$caps['addons'][ $code ] = (bool) $on;
			if ( isset( $caps['split']['addons'][ $code ] ) ) {
				$caps['split']['addons'][ $code ]['installed'] = (bool) $on;
				$caps['split']['addons'][ $code ]['active']    = (bool) $on;
			}
		}
		$caps['plan']['name']         = $plan;
		$caps['plan']['package_type'] = array(
			'pro'         => 1,
			'free_plus'   => 3,
			'pro_pending' => 0,
			'free'        => 2,
		)[ $plan ];
		foreach ( $caps['features'] as $key => $entry ) {
			$package  = (string) ( $entry['package'] ?? 'pro' );
			$plan_ok  = self::RANK[ $plan ] >= self::RANK[ $package ];
			$addon    = $entry['addon'] ?? null;
			$addon_ok = null === $addon || ! empty( $caps['addons'][ $addon ] );
			$caps['features'][ $key ]['available_now'] = $plan_ok && $addon_ok;
			$caps['features'][ $key ]['reason']        = $plan_ok ? ( $addon_ok ? null : 'addon' ) : 'plan';
			if ( str_starts_with( (string) $key, 'addon:' ) ) {
				$caps['features'][ $key ]['installed']   = ! empty( $caps['addons'][ substr( (string) $key, 6 ) ] );
				$caps['features'][ $key ]['can_install'] = $plan_ok;
			}
		}
		foreach ( (array) ( $caps['split']['addons'] ?? array() ) as $code => $info ) {
			$plan_ok = self::RANK[ $plan ] >= self::RANK[ (string) ( $info['package'] ?? 'pro' ) ];
			$active  = ! empty( $caps['addons'][ $code ] ) && $plan_ok;

			$caps['split']['addons'][ $code ]['active']      = $active;
			$caps['split']['addons'][ $code ]['can_install'] = $plan_ok;
			$caps['split']['addons'][ $code ]['state']       = $active ? 'active' : ( $plan_ok ? 'addon' : 'plan' );
		}
		// EFB 4.3.2 (AI-6): payment_gateways lists every gateway of its catalogue, also while the add-on is off; the
		// fixture site registers none of them (a registered gateway is added by the test that needs it).
		foreach ( self::GATEWAYS as $slug => $info ) {
			if ( isset( $caps['payment_gateways'][ $slug ] ) ) {
				continue;
			}
			$plan_ok = self::RANK[ $plan ] >= self::RANK[ $info[2] ];
			// PAY-OUT (EFB 4.3.2 dev4): an older gateway's add-on that is on (and allowed) registers its framework field
			// and EFB names it in legacy_gateways.targets, as a fresh capabilities_efb() dump shows.
			if ( null !== $info[3] && ! empty( $caps['addons'][ $info[0] ] ) && $plan_ok ) {
				$caps['payment_gateways'][ $slug ]           = array(
					'active'      => true,
					'plan'        => $info[2],
					'field_type'  => $slug,
					'legacy_type' => $info[3],
					'can_install' => true,
					'addon'       => $info[0],
					'installed'   => true,
					'state'       => 'active',
					'label'       => $info[1],
					'configured'  => array(
						'test' => false,
						'live' => false,
					),
					'mode'        => 'test',
					'currencies'  => array(),
				);
				$caps['payment_keys'][ $slug ]               = false;
				$caps['legacy_gateways']['targets'][ $info[3] ] = $slug;
				$caps['features'][ 'field:' . $slug ]        = array(
					'kind'          => 'field',
					'package'       => $info[2],
					'addon'         => $info[0],
					'available_now' => true,
					'reason'        => null,
					'form_patch'    => array(
						'type'   => 'payment',
						'getway' => $slug,
					),
				);
				continue;
			}
			$caps['payment_gateways'][ $slug ] = array(
				'addon'       => $info[0],
				'installed'   => ! empty( $caps['addons'][ $info[0] ] ),
				'active'      => false,
				'plan'        => $info[2],
				'state'       => ! empty( $caps['addons'][ $info[0] ] ) ? 'update' : ( $plan_ok ? 'addon' : 'plan' ),
				'can_install' => $plan_ok,
				'label'       => $info[1],
				'field_type'  => $slug,
				'legacy_type' => $info[3],
				'configured'  => null,
				'mode'        => null,
			);
		}

		return $caps;
	}

	/** The catalogue EFB 4.3.2 lists in payment_gateways: slug => add-on, label, plan, legacy type. */
	private const GATEWAYS = array(
		'mollie'         => array( 'AdnMOL', 'Mollie', 'free_plus', null ),
		'payplug'        => array( 'AdnPPG', 'Payplug', 'free_plus', null ),
		'tappay'         => array( 'AdnTAP', 'Tap Payments', 'free_plus', null ),
		'omise'          => array( 'AdnOMS', 'Omise', 'free_plus', null ),
		'paytabs'        => array( 'AdnPTB', 'PayTabs', 'free_plus', null ),
		'paymob'         => array( 'AdnPMB', 'Paymob', 'free_plus', null ),
		'xendit'         => array( 'AdnXND', 'Xendit', 'free_plus', null ),
		'stripecheckout' => array( 'AdnSPF', 'Stripe Payment', 'free_plus', 'stripe' ),
		'paypalcheckout' => array( 'AdnPAP', 'PayPal Payment', 'free_plus', 'paypal' ),
		'zarinpalgate'   => array( 'AdnPPF', 'Persia Payment', 'pro', 'persiaPay' ),
	);

	/**
	 * The blocks EFB 4.3.2 adds for the AI builder (AI-1 … AI-7): the version, form.update, drafts, the save warnings
	 * in efb:form-saved, and the form package 1.2 (name_policy, status).
	 *
	 * @param array<string,mixed> $caps
	 * @return array<string,mixed>
	 */
	public static function ai_core( array $caps ): array {
		$caps['efb_version'] = '4.3.2';
		$caps['save_events'] = array( 'v' => 1 );
		$caps['form_update'] = array(
			'v'   => 1,
			'ops' => array( 'form', 'field', 'insert', 'remove', 'options', 'name' ),
		);
		$caps['form_status'] = array(
			'v'      => 1,
			'ready'  => true,
			'values' => array( 'published', 'draft' ),
		);

		return $caps;
	}

	/** Use a registry for the next calls (null registry with $active true: an EFB without a registry). */
	public static function apply( ?array $caps, bool $active = true ): void {
		EfbCapabilities::set_override( $caps, $active );
	}

	public static function reset(): void {
		EfbCapabilities::set_override( null, false );
	}
}
