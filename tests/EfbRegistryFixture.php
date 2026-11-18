<?php
/**
 * Easy Form Builder's capability registry for unit tests.
 *
 * `tests/fixtures/efb-capabilities-free-plus.json` is the registry a real Easy Form Builder (`dev4`, the SPLIT build)
 * returned from `get_efbFunction()->capabilities_efb()` on a Free Plus site with the Numbers & Calculations, Typed
 * Signature and Address & Postal add-ons active (trimmed to the blocks the copilot reads; no site address, no key).
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
