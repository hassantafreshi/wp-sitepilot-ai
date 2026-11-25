<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * What the Easy Form Builder copilot needs to know to add online payment to a form.
 *
 * Payment gateways are always Easy Form Builder add-ons: Stripe, PayPal and Persia Pay on EFB's legacy gateway bridge
 * (their code is leaving EFB core for the add-ons), and the payment framework (PAY00) gateways Mollie, Payplug, Tap,
 * Omise, PayTabs, Paymob and Xendit, each registered by its own add-on. Whether a gateway can be used here, on which
 * plan, and whether its keys are saved comes only from EFB's registry (`features['field:<gateway>']`,
 * `capabilities.split`, `payment_gateways`, `payment_keys`); this class never assumes gateway code in EFB core.
 * When EFB does not report a gateway (an EFB without the registry), payment is refused with a plain reason.
 * The copilot may place the payment fields; it never reads, writes or sees a key, a secret or a price it was not told.
 */
final class EfbPayments {

	/**
	 * Gateway id => EFB field type, add-on code, settings page, the keys EFB needs and the currencies
	 * the gateway accepts (lowercase ISO codes, as EFB stores them).
	 */
	public const GATEWAYS = array(
		'stripe'   => array(
			'label'      => 'Stripe',
			'field'      => 'stripe',
			'addon'      => 'AdnSPF',
			'page'       => 'Emsfb_stripe_payment',
			'keys'       => array( 'stripePKey', 'stripeSKey' ),
			'currencies' => array( 'usd', 'eur', 'gbp', 'cad', 'aud', 'nzd', 'chf', 'sek', 'nok', 'dkk', 'pln', 'czk', 'huf', 'ron', 'bgn', 'sgd', 'hkd', 'inr', 'brl', 'mxn', 'aed', 'sar', 'try', 'jpy' ),
			'default'    => 'usd',
		),
		'paypal'   => array(
			'label'      => 'PayPal',
			'field'      => 'paypal',
			'addon'      => 'AdnPAP',
			'page'       => 'Emsfb_paypal_payment',
			'keys'       => array( 'paypalPKey' ),
			'currencies' => array( 'usd', 'eur', 'gbp', 'cad', 'aud', 'nzd', 'chf', 'sek', 'nok', 'dkk', 'pln', 'czk', 'huf', 'sgd', 'hkd', 'brl', 'mxn', 'ils', 'php', 'twd', 'thb', 'myr', 'jpy' ),
			'default'    => 'usd',
		),
		'zarinpal' => array(
			'label'      => 'ZarinPal',
			'field'      => 'persiaPay',
			'addon'      => 'AdnPPF',
			'page'       => 'Emsfb_addon',
			'keys'       => array(),
			'currencies' => array( 'irr' ),
			'default'    => 'irr',
		),
	);

	/**
	 * The payment framework (PAY00) gateways and the add-on of each, from EFB's own registry: since EFB 4.3.2
	 * (`payment_gateways`, AI-6) every gateway of its catalogue is listed with its add-on, plan and state, also while
	 * the add-on is off or not installed, so the copilot keeps no slug → add-on map of its own. An older EFB lists the
	 * registered gateways only; an off gateway is then unknown (EFB needs an update) and never offered. The PAY-OUT
	 * fields of the three older gateways (`legacy_type` stripe / paypal / persiaPay) are reported under GATEWAYS.
	 *
	 * @return array<string,string> slug => add-on code
	 */
	public static function pay00(): array {
		$out = array();
		foreach ( EfbCapabilities::payment_gateways() as $slug => $entry ) {
			$addon = (string) ( $entry['addon'] ?? '' );
			if ( '' === $addon || ! empty( $entry['legacy_type'] ) || 1 !== preg_match( '/^[a-z][a-z0-9]{1,19}$/', (string) $slug ) ) {
				continue;
			}
			$out[ (string) $slug ] = $addon;
		}

		return $out;
	}

	/**
	 * QA5-007 (EFB PAY-OUT, docs/autopilot-integration.md): Stripe, PayPal and ZarinPal moved to the payment framework.
	 * A new form uses the framework field EFB's registry names for the older type — `legacy_gateways.targets`
	 * (stripe → stripecheckout, paypal → paypalcheckout, persiaPay → zarinpalgate, listed while the add-on is on and
	 * recent), else the `payment_gateways` entry whose legacy_type is that type (also while the add-on is off) — and is
	 * built like any PAY00 gateway (EFB's own field template, form patch, currencies, keys, readiness). Null on an EFB
	 * that names no such field: the older field and its add-on contract, as before.
	 */
	public static function legacy_slug( string $gateway ): ?string {
		$legacy = isset( self::GATEWAYS[ $gateway ] ) ? self::GATEWAYS[ $gateway ]['field'] : null;
		if ( null === $legacy || ! EfbCapabilities::available() ) {
			return null;
		}
		$targets = EfbCapabilities::block( 'legacy_gateways' )['targets'] ?? array();
		$names   = 'persiaPay' === $legacy ? array( 'persiaPay', 'zarinPal', 'persiapay' ) : array( $legacy );
		foreach ( $names as $name ) {
			$slug = is_array( $targets ) && is_string( $targets[ $name ] ?? null ) ? $targets[ $name ] : '';
			if ( 1 === preg_match( '/^[a-z][a-z0-9]{1,29}$/', $slug ) ) {
				return $slug;
			}
		}
		foreach ( EfbCapabilities::payment_gateways() as $slug => $entry ) {
			if ( in_array( (string) ( $entry['legacy_type'] ?? '' ), $names, true ) && 1 === preg_match( '/^[a-z][a-z0-9]{1,29}$/', (string) $slug ) ) {
				return (string) $slug;
			}
		}

		return null;
	}

	/** The framework slug of a gateway: its own for a PAY00 gateway, the registry's target for Stripe / PayPal / ZarinPal. */
	public static function slug( string $gateway ): string {
		return self::legacy_slug( $gateway ) ?? $gateway;
	}

	/**
	 * Where a gateway's account keys are entered: EFB's own answer for a framework gateway (the readiness fix of
	 * pay_not_configured: Payments › Gateway settings), the older add-on screen otherwise.
	 */
	public static function settings_url( string $gateway ): string {
		if ( self::is_pay00( $gateway ) ) {
			$fix = null;
			if ( is_callable( array( '\\Emsfb\\Payments\\Readiness', 'fix_of' ) ) ) {
				try {
					$fix = \Emsfb\Payments\Readiness::fix_of( array( 'code' => 'pay_not_configured' ) );
				} catch ( \Throwable $e ) {
					$fix = null;
				}
			}
			$url = is_array( $fix ) ? (string) ( $fix['url'] ?? '' ) : '';

			return '' !== $url ? $url : admin_url( 'admin.php?page=Emsfb_payments&tab=gateways' );
		}

		return admin_url( 'admin.php?page=' . ( self::GATEWAYS[ $gateway ]['page'] ?? 'Emsfb_payments' ) );
	}

	/** Field types that carry a price; the first four have priced options or an amount, the last shows the total. */
	public const PRICED_FIELDS = array( 'payRadio', 'paySelect', 'payCheckbox', 'payMultiselect', 'prcfld', 'ttlprc' );

	/** The choice field each way of pricing maps to. */
	public const CHOICE_FIELDS = array(
		'radio'       => 'payRadio',
		'select'      => 'paySelect',
		'checkbox'    => 'payCheckbox',
		'multiselect' => 'payMultiselect',
	);

	/** The largest price or amount a single option may carry, a guard against a model's typo, not a business rule. */
	public const MAX_PRICE = 1000000;

	/** Rials are counted in large numbers (200,000 toman is 2,000,000 rials), so their guard is wider. */
	public const MAX_PRICE_IRR = 100000000000;

	/** @return string[] */
	public static function gateway_ids(): array {
		return array_merge( array_keys( self::GATEWAYS ), array_keys( self::pay00() ) );
	}

	/** Whether a gateway is built as a payment framework (PAY00) gateway: a PAY00 slug, or a moved older gateway. */
	public static function is_pay00( string $gateway ): bool {
		return isset( self::pay00()[ $gateway ] ) || null !== self::legacy_slug( $gateway );
	}

	/** A gateway's name for the admin: EFB's own label (the add-on's, or its catalogue's while it is off). */
	public static function label( string $gateway ): string {
		if ( isset( self::GATEWAYS[ $gateway ] ) ) {
			return self::GATEWAYS[ $gateway ]['label'];
		}
		$listed = EfbCapabilities::payment_gateways()[ $gateway ] ?? null;
		if ( is_array( $listed ) && '' !== (string) ( $listed['label'] ?? '' ) ) {
			return (string) $listed['label'];
		}

		return ucfirst( $gateway );
	}

	/** The add-on that provides a gateway (legacy bridge or PAY00), or null. */
	public static function addon_of( string $gateway ): ?string {
		$listed = null !== self::legacy_slug( $gateway ) ? ( EfbCapabilities::payment_gateways()[ self::slug( $gateway ) ]['addon'] ?? null ) : null;
		if ( is_string( $listed ) && '' !== $listed ) {
			return $listed;
		}

		return self::GATEWAYS[ $gateway ]['addon'] ?? ( self::pay00()[ $gateway ] ?? null );
	}

	/** The gateway a model or a visitor named, in any spelling, or null. */
	public static function resolve_gateway( string $name ): ?string {
		$needle = strtolower( preg_replace( '/[^a-z]/i', '', $name ) ?? '' );
		$map    = array(
			'stripe'        => 'stripe',
			'paypal'        => 'paypal',
			'zarinpal'      => 'zarinpal',
			'persiapay'     => 'zarinpal',
			'persiapayment' => 'zarinpal',
			'mollie'        => 'mollie',
			'payplug'       => 'payplug',
			'tap'           => 'tappay',
			'tappay'        => 'tappay',
			'tappayments'   => 'tappay',
			'omise'         => 'omise',
			'promptpay'     => 'omise',
			'paytabs'       => 'paytabs',
			'paymob'        => 'paymob',
			'xendit'        => 'xendit',
		);

		return $map[ $needle ] ?? null;
	}

	/** The EFB field type that is the gateway itself (a PAY00 gateway's field type is its slug). */
	public static function gateway_field( string $gateway ): ?string {
		if ( self::is_pay00( $gateway ) ) {
			return self::slug( $gateway );
		}

		return self::GATEWAYS[ $gateway ]['field'] ?? null;
	}

	/** The gateway a field type belongs to, or null. */
	public static function gateway_of_field( string $field_type ): ?string {
		foreach ( self::GATEWAYS as $id => $definition ) {
			// The older field, or the framework field it moved to.
			if ( $definition['field'] === $field_type || self::legacy_slug( $id ) === $field_type ) {
				return $id;
			}
		}

		return self::is_pay00( $field_type ) ? $field_type : null;
	}

	/**
	 * The currencies a gateway charges in, as EFB stores them: the legacy gateways in lower case, a PAY00 gateway as
	 * its add-on reports them (`payment_gateways.<slug>.currencies`).
	 *
	 * @return string[]
	 */
	public static function currencies( string $gateway ): array {
		if ( isset( self::GATEWAYS[ $gateway ] ) ) {
			// A moved gateway charges in what its add-on reports; it reports none yet, so its known list stays.
			$moved = null !== self::legacy_slug( $gateway ) ? ( EfbCapabilities::payment_gateways()[ self::slug( $gateway ) ]['currencies'] ?? null ) : null;
			if ( is_array( $moved ) && array() !== $moved ) {
				return array_values( array_map( 'strtolower', array_map( 'strval', $moved ) ) );
			}
			return self::GATEWAYS[ $gateway ]['currencies'];
		}
		$registered = EfbCapabilities::payment_gateways()[ $gateway ] ?? null;

		return is_array( $registered ) && is_array( $registered['currencies'] ?? null ) ? array_values( array_map( 'strval', $registered['currencies'] ) ) : array();
	}

	/** A currency EFB can charge in through this gateway, or the gateway's default. */
	public static function currency( string $gateway, string $requested ): string {
		$list = self::currencies( $gateway );
		if ( array() === $list ) {
			return isset( self::GATEWAYS[ $gateway ] ) || ! self::is_pay00( $gateway ) ? 'usd' : '';
		}
		foreach ( $list as $code ) {
			if ( 0 === strcasecmp( $code, trim( $requested ) ) ) {
				return $code;
			}
		}

		return isset( self::GATEWAYS[ $gateway ] ) ? self::GATEWAYS[ $gateway ]['default'] : $list[0];
	}

	/** Whether the administrator's currency is one this gateway accepts (an unknown one should be asked about, not guessed). */
	public static function supports_currency( string $gateway, string $requested ): bool {
		foreach ( self::currencies( $gateway ) as $code ) {
			if ( 0 === strcasecmp( $code, trim( $requested ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A price a person typed or a model returned, as a positive number with at most two decimals, or null.
	 *
	 * @param mixed $raw
	 */
	public static function price( $raw, string $currency = '' ): ?float {
		if ( is_string( $raw ) ) {
			// "$20", "20.50", "1,200" and Persian digits are all things people type.
			$raw = strtr(
				$raw,
				array(
					'۰' => '0',
					'۱' => '1',
					'۲' => '2',
					'۳' => '3',
					'۴' => '4',
					'۵' => '5',
					'۶' => '6',
					'۷' => '7',
					'۸' => '8',
					'۹' => '9',
					'٫' => '.',
					'٬' => '',
				)
			);
			$raw = preg_replace( '/[^0-9.]/', '', str_replace( ',', '', $raw ) ) ?? '';
		}
		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		$value = round( (float) $raw, 2 );

		$max = 'irr' === strtolower( $currency ) ? self::MAX_PRICE_IRR : self::MAX_PRICE;

		return $value > 0 && $value <= $max ? $value : null;
	}

	/**
	 * Which gateways the open editor has an add-on for, from the browser's own `efb_var.addons`.
	 * Only the three gateway codes are read, and only as on or off.
	 *
	 * @param mixed $addons
	 * @return array<string,bool> Gateway id => add-on active.
	 */
	public static function active_gateways( $addons ): array {
		$addons = is_array( $addons ) ? $addons : array();
		$out    = array();
		foreach ( self::GATEWAYS as $id => $definition ) {
			$out[ $id ] = (int) ( $addons[ $definition['addon'] ] ?? 0 ) >= 1;
		}

		return $out;
	}

	/**
	 * Whether the keys the gateway needs have been saved in EFB's settings. The values are never returned,
	 * logged or sent anywhere; only whether each is present and long enough to be a key.
	 *
	 * @param string|null $settings Raw `emsfb_settings`, or null to read the option.
	 */
	public static function keys_saved( string $gateway, ?string $settings = null ): ?bool {
		$definition = self::GATEWAYS[ $gateway ] ?? null;
		if ( null === $definition ) {
			return null;
		}
		if ( array() === $definition['keys'] ) {
			// ZarinPal keeps its merchant code with the add-on, not in the shared settings: unknown from here.
			return null;
		}
		if ( null === $settings ) {
			$settings = function_exists( 'get_option' ) ? get_option( 'emsfb_settings', '' ) : '';
		}
		$raw = is_string( $settings ) ? $settings : ( is_array( $settings ) || is_object( $settings ) ? (string) wp_json_encode( $settings ) : '' );
		foreach ( $definition['keys'] as $key ) {
			// The settings are JSON, sometimes stored with escaped quotes; a key counts when its value has real length.
			if ( 1 !== preg_match( '/\\\\?"' . preg_quote( $key, '/' ) . '\\\\?"\s*:\s*\\\\?"([^"\\\\]{6,})/', $raw ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Everything the chat needs to answer a payment request truthfully.
	 *
	 * @param mixed $addons The browser's `efb_var.addons`.
	 * @return array<string,array{label:string,addon_active:bool,keys_saved:?bool,field:string,page:string,default_currency:string}>
	 */
	public static function status( $addons, ?string $settings = null ): array {
		// EFB's registry is the only source when EFB publishes it: plan, add-on and keys, for every gateway.
		if ( EfbCapabilities::available() ) {
			return self::registry_status();
		}
		$active = self::active_gateways( $addons );
		$out    = array();
		foreach ( self::GATEWAYS as $id => $definition ) {
			$out[ $id ] = array(
				'label'            => $definition['label'],
				'addon_active'     => $active[ $id ],
				'keys_saved'       => self::keys_saved( $id, $settings ),
				'field'            => $definition['field'],
				'page'             => $definition['page'],
				'default_currency' => $definition['default'],
				// Without EFB's registry nothing says the gateway's add-on contract is there: payment is not offered.
				'known'            => false,
				'plan_ok'          => false,
				'package'          => 'free_plus',
				'state'            => null,
			);
		}

		return $out;
	}

	/**
	 * Every gateway as EFB's registry describes it on this site.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function registry_status(): array {
		$out = array();
		foreach ( self::gateway_ids() as $id ) {
			$field = (string) self::gateway_field( $id );
			$addon = (string) self::addon_of( $id );
			$state = EfbCapabilities::state( EfbCapabilities::field_key( $field ) );
			$info  = EfbCapabilities::addon( $addon );
			// EFB 4.3.2 (AI-6): the gateway's own catalogue entry says installed / active / plan / state, also while its
			// add-on is off. An older EFB: the field's and the add-on's registry entries, as before.
			$listed = self::is_pay00( $id ) ? ( EfbCapabilities::payment_gateways()[ self::slug( $id ) ] ?? null ) : null;
			if ( is_array( $listed ) && isset( $listed['active'], $listed['plan'] ) ) {
				$package = in_array( $listed['plan'], array( 'free', 'free_plus', 'pro' ), true ) ? (string) $listed['plan'] : 'free_plus';
				$plan_ok = EfbCapabilities::plan_at_least( $package ) && 'plan' !== ( $listed['state'] ?? '' );
				$active  = ! empty( $listed['active'] );
				$info    = array_merge(
					$info,
					array(
						'known' => true,
						'state' => (string) ( $listed['state'] ?? ( $active ? 'active' : 'addon' ) ),
						'name'  => $info['known'] ? $info['name'] : (string) ( $listed['label'] ?? $id ),
					)
				);
			} elseif ( ! $state['known'] && ! $info['known'] ) {
				continue;
			} else {
				// A PAY00 gateway is in the registry only while its add-on is on; its add-on entry says the plan.
				$package = $state['known'] ? $state['package'] : $info['package'];
				$plan_ok = $state['known'] ? 'plan' !== $state['reason'] : EfbCapabilities::plan_at_least( $info['package'] );
				// The add-on's own entry says whether it is on (the field's entry cannot tell while the plan blocks it).
				$active = $info['known'] ? $info['active'] : ( $state['known'] && null === $state['reason'] );
			}
			if ( 'update' === $info['state'] ) {
				$active = false;
			}
			$out[ $id ] = array(
				'label'            => self::label( $id ),
				'addon_active'     => $active,
				'keys_saved'       => self::is_pay00( $id ) ? EfbCapabilities::payment_keys_saved( self::slug( $id ) ) : EfbCapabilities::payment_keys_saved( 'zarinpal' === $id ? 'persiaPay' : $id ),
				'field'            => $field,
				'page'             => isset( self::GATEWAYS[ $id ] ) && ! self::is_pay00( $id ) ? self::GATEWAYS[ $id ]['page'] : 'Emsfb_payments',
				'settings_url'     => self::settings_url( $id ),
				'default_currency' => self::currency( $id, '' ),
				'known'            => true,
				'plan_ok'          => $plan_ok,
				'package'          => $package,
				'state'            => 'update' === $info['state'] ? 'update' : ( $active ? 'active' : ( $plan_ok ? 'addon' : 'plan' ) ),
				'addon'            => $addon,
				'addon_name'       => $info['name'],
			);
		}

		return $out;
	}

	/**
	 * The payment facts the model is given, in plain words, so it plans around what is really set up.
	 *
	 * @param array<string,array{label:string,addon_active:bool,keys_saved:?bool,field:string,page:string,default_currency:string}> $status
	 */
	public static function prompt_block( array $status ): string {
		$lines = array();
		foreach ( $status as $id => $row ) {
			if ( empty( $row['known'] ) ) {
				$lines[] = sprintf( '- %s (%s): not reported by this Easy Form Builder (it needs an update before payment can be added)', $row['label'], $id );
				continue;
			}
			$keys    = null === $row['keys_saved'] ? 'unknown' : ( $row['keys_saved'] ? 'saved' : 'missing' );
			$state   = (string) ( $row['state'] ?? '' );
			$addon   = $row['addon_active'] ? 'active' : 'not active';
			$lines[] = sprintf(
				'- %s (%s): add-on %s, plan %s, keys %s%s',
				$row['label'],
				$id,
				'update' === $state ? 'needs an update' : $addon,
				! empty( $row['plan_ok'] ) ? 'ok' : 'needs ' . (string) ( $row['package'] ?? 'pro' ),
				$keys,
				'' !== (string) $row['default_currency'] ? ', default currency ' . strtoupper( (string) $row['default_currency'] ) : ''
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Check a payment request from the model and turn it into something safe to build.
	 *
	 * Nothing here invents a price: every amount must come from the request. A request is refused, with a
	 * reason the chat can explain, when the gateway is unknown, the licence has no Pro fields, the gateway's
	 * add-on is off, the currency is one the gateway cannot charge, or no price or amount was given.
	 *
	 * @param mixed                                                                                       $raw
	 * @param array<string,array{label:string,addon_active:bool,keys_saved:?bool,field:string,page:string,default_currency:string}> $status Result of status().
	 * @return array{ok:bool,reason?:string,payment?:array{gateway:string,currency:string,label:string,choice_type:string,items:array<int,array{label:string,price:float}>,amount_label:string}}
	 */
	public static function validate_payment( $raw, array $status, bool $has_pro ): array {
		$raw     = is_array( $raw ) ? $raw : array();
		$gateway = self::resolve_gateway( (string) ( $raw['gateway'] ?? '' ) );
		if ( null === $gateway ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_gateway_unknown',
			);
		}
		unset( $has_pro );
		// Never assume gateway code in EFB core: only a gateway EFB's registry reports is used.
		if ( empty( $status[ $gateway ]['known'] ) ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_unverified:' . $gateway,
			);
		}
		if ( empty( $status[ $gateway ]['plan_ok'] ) ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_requires_pro:' . $gateway . ':' . (string) ( $status[ $gateway ]['package'] ?? 'pro' ),
			);
		}
		if ( empty( $status[ $gateway ]['addon_active'] ) ) {
			return array(
				'ok'     => false,
				'reason' => ( 'update' === ( $status[ $gateway ]['state'] ?? '' ) ? 'payment_addon_update:' : 'payment_addon_inactive:' ) . $gateway,
			);
		}
		if ( self::is_pay00( $gateway ) && array() === self::gateway_rows( $gateway, '', array() ) ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_template_missing:' . $gateway,
			);
		}

		$requested = trim( (string) ( $raw['currency'] ?? '' ) );
		if ( '' !== $requested && ! self::supports_currency( $gateway, $requested ) ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_currency_unsupported:' . $gateway . ':' . strtolower( substr( $requested, 0, 8 ) ),
			);
		}

		$items = array();
		foreach ( (array) ( $raw['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $item['label'] ?? '' ) ) ) );
			$price = self::price( $item['price'] ?? null, '' !== $requested ? $requested : self::currency( $gateway, '' ) );
			if ( '' === $label || null === $price ) {
				return array(
					'ok'     => false,
					'reason' => 'payment_needs_price',
				);
			}
			$items[] = array(
				'label' => function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 80 ) : substr( $label, 0, 80 ),
				'price' => $price,
			);
		}
		$items        = array_slice( $items, 0, 20 );
		$amount_label = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $raw['amount_label'] ?? '' ) ) ) );
		if ( array() === $items && '' === $amount_label ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_needs_price',
			);
		}

		$choice = strtolower( trim( (string) ( $raw['choice_type'] ?? 'radio' ) ) );
		if ( ! isset( self::CHOICE_FIELDS[ $choice ] ) ) {
			$choice = 'radio';
		}
		$label = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $raw['label'] ?? '' ) ) ) );

		return array(
			'ok'      => true,
			'payment' => array(
				'gateway'      => $gateway,
				'currency'     => self::currency( $gateway, $requested ),
				'label'        => function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 120 ) : substr( $label, 0, 120 ),
				'choice_type'  => $choice,
				// A list of priced options wins over a free amount; both at once would ask twice.
				'items'        => $items,
				'amount_label' => array() === $items ? ( function_exists( 'mb_substr' ) ? mb_substr( $amount_label, 0, 120 ) : substr( $amount_label, 0, 120 ) ) : '',
			),
		);
	}

	/**
	 * The form-level settings EFB itself writes when a gateway field is added in its builder
	 * (val-efb.js and admin-efb.js: type, getway, currency, paymentmethod, and persiaPay for ZarinPal).
	 *
	 * @return array<string,string>
	 */
	public static function form_settings( string $gateway, string $currency ): array {
		if ( self::is_pay00( $gateway ) ) {
			// EFB's own form patch for the gateway's field (features['field:<slug>'].form_patch), with the currency.
			$slug  = self::slug( $gateway );
			$entry = EfbCapabilities::feature( EfbCapabilities::field_key( $slug ) );
			$patch = is_array( $entry['form_patch'] ?? null ) ? $entry['form_patch'] : array();

			return array_merge(
				array(
					'type'          => 'payment',
					'getway'        => $slug,
					'paymentmethod' => 'charge',
				),
				array_map( 'strval', array_filter( $patch, 'is_scalar' ) ),
				array( 'currency' => self::currency( $gateway, $currency ) )
			);
		}
		$settings = array(
			'type'          => 'payment',
			'getway'        => 'zarinpal' === $gateway ? 'persiaPay' : $gateway,
			'currency'      => self::currency( $gateway, $currency ),
			'paymentmethod' => 'charge',
		);
		if ( 'zarinpal' === $gateway ) {
			$settings['persiaPay'] = 'zarinPal';
		}

		return $settings;
	}

	/**
	 * The field row of a PAY00 gateway exactly as EFB's builder makes it (`get_efbFunction()->field_templates_efb()`),
	 * with a fresh id and the given label; empty when EFB cannot give it.
	 *
	 * @param string[] $existing_ids
	 * @return array<int,array<string,mixed>>
	 */
	public static function gateway_rows( string $gateway, string $label, array $existing_ids ): array {
		if ( ! self::is_pay00( $gateway ) || ! function_exists( 'get_efbFunction' ) ) {
			return array();
		}
		$slug = self::slug( $gateway );
		try {
			$efb = get_efbFunction();
			$tpl = is_object( $efb ) && method_exists( $efb, 'field_templates_efb' ) ? $efb->field_templates_efb( $slug, '' !== $label ? array( 'label' => $label ) : array() ) : null;
		} catch ( \Throwable $e ) {
			$tpl = null;
		}
		$rows = is_array( $tpl ) && is_array( $tpl['rows'] ?? null ) ? $tpl['rows'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ( $row['type'] ?? '' ) !== $slug ) {
				continue;
			}
			while ( in_array( (string) ( $row['id_'] ?? '' ), $existing_ids, true ) ) {
				$row['id_'] = substr( bin2hex( random_bytes( 5 ) ), 0, 9 );
			}
			$row['dataId']   = (string) $row['id_'] . '-id';
			$row['required'] = '1';
			if ( '' !== $label ) {
				$row['name'] = $label;
			}
			$out[] = $row;
		}

		return $out;
	}
}
