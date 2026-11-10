<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * What the Easy Form Builder copilot needs to know to add online payment to a form.
 *
 * EFB takes payment through one gateway per form (Stripe, PayPal or ZarinPal), each a paid add-on
 * with its own keys. The copilot may place the payment fields; it never reads, writes or sees a
 * key, a secret or a price it was not told. This class answers three questions only: which gateway
 * the administrator means, whether its add-on is on, and whether its keys have been saved at all.
 * Pure PHP apart from reading one option, so the rules are unit testable.
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
		return array_keys( self::GATEWAYS );
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
		);

		return $map[ $needle ] ?? null;
	}

	/** The EFB field type that is the gateway itself. */
	public static function gateway_field( string $gateway ): ?string {
		return self::GATEWAYS[ $gateway ]['field'] ?? null;
	}

	/** The gateway a field type belongs to, or null. */
	public static function gateway_of_field( string $field_type ): ?string {
		foreach ( self::GATEWAYS as $id => $definition ) {
			if ( $definition['field'] === $field_type ) {
				return $id;
			}
		}

		return null;
	}

	/** A currency EFB can charge in through this gateway, or the gateway's default. */
	public static function currency( string $gateway, string $requested ): string {
		$definition = self::GATEWAYS[ $gateway ] ?? null;
		if ( null === $definition ) {
			return 'usd';
		}
		$code = strtolower( trim( $requested ) );

		return in_array( $code, $definition['currencies'], true ) ? $code : $definition['default'];
	}

	/** Whether the administrator's currency is one this gateway accepts (an unknown one should be asked about, not guessed). */
	public static function supports_currency( string $gateway, string $requested ): bool {
		$definition = self::GATEWAYS[ $gateway ] ?? null;

		return null !== $definition && in_array( strtolower( trim( $requested ) ), $definition['currencies'], true );
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
			$keys    = null === $row['keys_saved'] ? 'unknown' : ( $row['keys_saved'] ? 'saved' : 'missing' );
			$lines[] = sprintf( '- %s (%s): add-on %s, keys %s, default currency %s', $row['label'], $id, $row['addon_active'] ? 'active' : 'not active', $keys, strtoupper( $row['default_currency'] ) );
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
		if ( ! $has_pro ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_requires_pro:' . $gateway,
			);
		}
		if ( empty( $status[ $gateway ]['addon_active'] ) ) {
			return array(
				'ok'     => false,
				'reason' => 'payment_addon_inactive:' . $gateway,
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
			$price = self::price( $item['price'] ?? null, '' !== $requested ? $requested : self::GATEWAYS[ $gateway ]['default'] );
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
}
