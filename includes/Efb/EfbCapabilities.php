<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Easy Form Builder's own plan and add-on registry, read at run time.
 *
 * EFB publishes one registry (`get_efbFunction()->capabilities_efb()`, the same data as `efb_var.capabilities`
 * in its builder): the site's plan, every field type and setting with the plan and add-on it needs and whether it
 * can be used now, the add-ons that left core (`split`), the stable contracts (form package, payments, portal, …),
 * the visitor confirmation e-mail default, the payment gateways and the CAPTCHA services. This class is the only
 * place the copilot reads it, so no plan level, add-on code or gateway list is kept by hand here. It never writes
 * anything to EFB and never sees a key: the registry reports keys as configured true / false only.
 *
 * When EFB is too old to publish the registry, every question answers "unknown" (null / false) and the callers
 * fall back to their older checks or explain that Easy Form Builder needs an update.
 */
final class EfbCapabilities {

	/** @var array<string,mixed>|null Registry read for this request (or set by a test). */
	private static ?array $cache = null;

	private static bool $loaded = false;

	/** @var array<string,mixed>|null A registry a test or the offline server supplies instead of EFB's. */
	private static ?array $override = null;

	private static bool $overridden = false;

	/** Plan rank as EFB orders them (`Builder_Api::PLAN_RANK`; a Pro licence that is not active counts as Free). */
	private const RANK = array(
		'free'        => 0,
		'pro_pending' => 0,
		'free_plus'   => 1,
		'pro'         => 2,
	);

	/**
	 * Replace the registry (tests, the offline browser server). `null` with $active true means "EFB has no registry".
	 *
	 * @param array<string,mixed>|null $capabilities
	 */
	public static function set_override( ?array $capabilities, bool $active = true ): void {
		self::$override   = $capabilities;
		self::$overridden = $active;
		self::$cache      = null;
		self::$loaded     = false;
	}

	/** Forget the registry read for this request (after an add-on install or a plan change). */
	public static function reset(): void {
		self::$cache  = null;
		self::$loaded = false;
	}

	/**
	 * The whole registry, or null when this EFB does not publish one.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function all(): ?array {
		if ( self::$overridden ) {
			return self::$override;
		}
		if ( self::$loaded ) {
			return self::$cache;
		}
		self::$loaded = true;
		$caps         = null;
		try {
			if ( function_exists( 'get_efbFunction' ) ) {
				$efb = get_efbFunction();
				if ( is_object( $efb ) && method_exists( $efb, 'capabilities_efb' ) ) {
					$caps = $efb->capabilities_efb();
				}
			}
			if ( ! is_array( $caps ) && class_exists( '\\Emsfb\\Builder_Api' ) && is_callable( array( '\\Emsfb\\Builder_Api', 'capabilities' ) ) ) {
				$caps = \Emsfb\Builder_Api::capabilities();
			}
		} catch ( \Throwable $e ) {
			$caps = null;
		}
		self::$cache = is_array( $caps ) && is_array( $caps['features'] ?? null ) ? $caps : null;

		return self::$cache;
	}

	/** Whether EFB publishes its registry (API version 1 or later). */
	public static function available(): bool {
		$caps = self::all();

		return null !== $caps && (int) ( $caps['api_version'] ?? 0 ) >= 1;
	}

	/**
	 * One top-level block of the registry (`split`, `email_confirmation`, `portal_core`, …), or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function block( string $name ): ?array {
		$caps  = self::all();
		$block = null !== $caps ? ( $caps[ $name ] ?? null ) : null;

		return is_array( $block ) ? $block : null;
	}

	/**
	 * The site's plan as EFB names it: free | free_plus | pro | pro_pending, or '' when unknown.
	 */
	public static function plan(): string {
		$plan = self::block( 'plan' );
		$name = is_array( $plan ) ? (string) ( $plan['name'] ?? '' ) : '';

		return isset( self::RANK[ $name ] ) ? $name : '';
	}

	/** Whether the site's plan is at least $required (free | free_plus | pro). Unknown plans count as Free. */
	public static function plan_at_least( string $required, ?string $plan = null ): bool {
		$plan = $plan ?? self::plan();

		return ( self::RANK[ $plan ] ?? 0 ) >= ( self::RANK[ $required ] ?? 2 );
	}

	/**
	 * Easy Form Builder sells Pro to Persian (fa_IR) sites as a one-time purchase from easyformbuilder.ir: no
	 * subscription, no renewal reminder (F21 does nothing there). Everywhere else Pro is a yearly subscription.
	 */
	public static function one_time_purchase( ?string $locale = null ): bool {
		$locale = $locale ?? ( function_exists( 'get_locale' ) ? (string) get_locale() : '' );

		return str_starts_with( $locale, 'fa' );
	}

	/**
	 * The registry entry of a feature key (`field:calc`, `setting:esign_mode`, `addon:AdnCLC`, `limit:steps`), or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function feature( string $key ): ?array {
		$caps  = self::all();
		$entry = null !== $caps && is_array( $caps['features'] ?? null ) ? ( $caps['features'][ $key ] ?? null ) : null;

		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * What the registry says about one feature, in one shape every caller can read.
	 *
	 * @return array{known:bool,available:bool,reason:?string,package:string,addon:?string,state:?string}
	 *         `reason`: null (usable now), 'plan' (a higher plan), 'addon' (an add-on to install or switch on),
	 *         'update' (the add-on is older than its contract). Unknown keys report known false and available true,
	 *         so an EFB that does not list a feature is never refused here.
	 */
	public static function state( string $key ): array {
		$entry = self::feature( $key );
		if ( null === $entry ) {
			return array(
				'known'     => false,
				'available' => true,
				'reason'    => null,
				'package'   => 'free',
				'addon'     => null,
				'state'     => null,
			);
		}
		$addon  = is_string( $entry['addon'] ?? null ) && '' !== $entry['addon'] ? (string) $entry['addon'] : null;
		$reason = isset( $entry['reason'] ) && is_string( $entry['reason'] ) ? $entry['reason'] : null;
		$state  = null;
		if ( null !== $addon ) {
			$info  = self::addon( $addon );
			$state = $info['state'];
			// An add-on from before its contract reads as installed, but EFB does not load it: it needs an update.
			if ( 'update' === $state && ( null === $reason || 'addon' === $reason ) ) {
				$reason = 'update';
			}
		}
		$available = ! empty( $entry['available_now'] ) && 'update' !== $reason;

		return array(
			'known'     => true,
			'available' => $available,
			'reason'    => $available ? null : ( $reason ?? 'plan' ),
			'package'   => in_array( $entry['package'] ?? '', array( 'free', 'free_plus', 'pro' ), true ) ? (string) $entry['package'] : 'pro',
			'addon'     => $addon,
			'state'     => $state,
		);
	}

	/**
	 * The registry key a field type is listed under. Every stored type is `field:<type>`; the palette's composite
	 * ids (name, address) too.
	 */
	public static function field_key( string $type ): string {
		return 'field:' . $type;
	}

	/**
	 * One add-on as EFB describes it: its name (in the admin's language when EFB has the phrase), the plan it
	 * installs on, and whether it is installed, switched on, or held back by the plan or an update.
	 *
	 * @return array{code:string,name:string,package:string,installed:bool,active:bool,state:?string,can_install:bool,known:bool}
	 */
	public static function addon( string $code ): array {
		$split = self::block( 'split' );
		$info  = is_array( $split['addons'][ $code ] ?? null ) ? $split['addons'][ $code ] : null;
		$entry = self::feature( 'addon:' . $code );
		$caps  = self::all();

		$installed = null !== $info ? ! empty( $info['installed'] ) : ( null !== $entry ? ! empty( $entry['installed'] ) : ! empty( $caps['addons'][ $code ] ) );
		$active    = null !== $info ? ! empty( $info['active'] ) : ( null !== $entry ? ! empty( $entry['available_now'] ) : ! empty( $caps['addons'][ $code ] ) );
		$package   = (string) ( $info['package'] ?? ( $entry['package'] ?? 'pro' ) );
		$state     = isset( $info['state'] ) && is_string( $info['state'] ) ? $info['state'] : null;
		if ( null === $state && null !== $entry ) {
			$state = ! empty( $entry['available_now'] ) ? 'active' : ( 'plan' === ( $entry['reason'] ?? '' ) ? 'plan' : 'addon' );
		}

		return array(
			'code'        => $code,
			'name'        => self::addon_name( $code, is_array( $info ) ? (string) ( $info['name'] ?? '' ) : '', is_array( $entry ) ? (string) ( $entry['label_key'] ?? '' ) : '' ),
			'package'     => in_array( $package, array( 'free', 'free_plus', 'pro' ), true ) ? $package : 'pro',
			'installed'   => $installed,
			'active'      => $active,
			'state'       => $state,
			'can_install' => null !== $info ? ! empty( $info['can_install'] ) : ( null !== $entry ? ! empty( $entry['can_install'] ) : false ),
			'known'       => null !== $info || null !== $entry,
		);
	}

	/**
	 * An add-on's name for the admin: EFB's own phrase (translated by EFB) when it has one, else the registry's name.
	 */
	private static function addon_name( string $code, string $registry_name, string $label_key ): string {
		$name = '';
		if ( '' !== $label_key && function_exists( 'get_efbFunction' ) ) {
			try {
				$efb = get_efbFunction();
				if ( is_object( $efb ) && method_exists( $efb, 'text_efb' ) ) {
					$phrases = $efb->text_efb( array( $label_key ) );
					$name    = is_array( $phrases ) ? (string) ( $phrases[ $label_key ] ?? '' ) : '';
				}
			} catch ( \Throwable $e ) {
				$name = '';
			}
		}
		if ( '' === trim( $name ) ) {
			$name = $registry_name;
		}
		$name = trim( html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ) );

		return '' !== $name ? $name : $code;
	}

	/**
	 * Feature key => add-on code for every feature that left EFB core (`capabilities.split.moved`).
	 *
	 * @return array<string,string>
	 */
	public static function moved(): array {
		$split = self::block( 'split' );
		$moved = is_array( $split['moved'] ?? null ) ? $split['moved'] : array();

		return array_filter( array_map( 'strval', $moved ) );
	}

	/** EFB's Add-ons page, where a person installs an add-on (`capabilities.split.install.page`). */
	public static function addons_page(): string {
		$split = self::block( 'split' );
		$page  = is_array( $split['install'] ?? null ) ? (string) ( $split['install']['page'] ?? '' ) : '';

		return '' !== $page ? $page : ( function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=Emsfb_addon' ) : 'admin.php?page=Emsfb_addon' );
	}

	/**
	 * EFB's own upgrade dialog for a feature (F18): it opens the dialog only, a person's click changes the plan.
	 */
	public static function upgrade_dialog_url( string $feature ): string {
		$path = 'admin.php?page=Emsfb_create&efb_upgrade=' . rawurlencode( $feature ) . '&efb_origin=sitepilot';

		return function_exists( 'admin_url' ) ? admin_url( $path ) : $path;
	}

	/**
	 * The visitor confirmation e-mail rule (EFB 4.3.2, `capabilities.email_confirmation`), or null on older EFB.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function email_confirmation(): ?array {
		return self::block( 'email_confirmation' );
	}

	/** Whether a new form's first e-mail field confirms to the visitor by default. */
	public static function email_confirmation_default_on(): bool {
		$rule = self::email_confirmation();

		return null !== $rule && 'on' === (string) ( $rule['default'] ?? '' );
	}

	/**
	 * The customer portal's core contract (F20-core, `capabilities.portal_core`), or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function portal(): ?array {
		return self::block( 'portal_core' );
	}

	/**
	 * EFB's add-on contract for the customer portal (`split.contracts.portal`, F20-core): version and whether a
	 * provider is active. A portal connector checks it before it registers `portal.link`.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function portal_contract(): ?array {
		$split    = self::block( 'split' );
		$contract = is_array( $split['contracts']['portal'] ?? null ) ? $split['contracts']['portal'] : null;

		return $contract;
	}

	/**
	 * The form package API EFB offers for creating a form through its own save path, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function form_package_api(): ?array {
		if ( ! function_exists( 'efb_form_package_api' ) || ! class_exists( '\\Emsfb\\Form_Package' ) ) {
			return null;
		}
		try {
			$api = efb_form_package_api();
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_array( $api ) ? $api : null;
	}

	/** The form package contract version (1.1 adds import_token and delete()), or 0 when there is none. */
	public static function form_package_version(): float {
		$api = self::form_package_api();

		return null !== $api ? (float) ( $api['version'] ?? 0 ) : 0.0;
	}

	/**
	 * PAY00 gateways this site has (only active gateway add-ons register one): slug => what EFB tells about it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function payment_gateways(): array {
		$gateways = self::block( 'payment_gateways' );

		return is_array( $gateways ) ? array_filter( $gateways, 'is_array' ) : array();
	}

	/**
	 * Whether a gateway's keys are saved for its current mode (`payment_keys.<slug>`), null when EFB does not say.
	 */
	public static function payment_keys_saved( string $slug ): ?bool {
		$keys = self::block( 'payment_keys' );

		return is_array( $keys ) && array_key_exists( $slug, $keys ) ? (bool) $keys[ $slug ] : null;
	}

	/**
	 * The CAPTCHA services EFB lists (`capabilities.spam.providers`), each {id, label, plan, addon, available, state, ready}.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function captcha_providers(): array {
		$spam = self::block( 'spam' );

		return is_array( $spam['providers'] ?? null ) ? array_values( array_filter( $spam['providers'], 'is_array' ) ) : array();
	}

	/**
	 * The limit EFB sets for a plan (`limit:steps`, `limit:logic`), or null for "no limit" / unknown.
	 *
	 * @return mixed
	 */
	public static function limit( string $name, ?string $plan = null ) {
		$entry = self::feature( 'limit:' . $name );
		if ( null === $entry || ! is_array( $entry['limits'] ?? null ) ) {
			return null;
		}
		$plan = $plan ?? self::plan();
		$plan = 'pro_pending' === $plan || '' === $plan ? 'free' : $plan;

		return $entry['limits'][ $plan ] ?? null;
	}

	/** A plan's name for the admin. */
	public static function plan_label( string $plan ): string {
		switch ( $plan ) {
			case 'pro':
				return __( 'Pro', 'ai-site-assistant-autopilot' );
			case 'free_plus':
				return __( 'Free Plus', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Free', 'ai-site-assistant-autopilot' );
		}
	}

	/** Where a plan is bought: Free Plus is activated in EFB itself; Pro from the EFB store (fa_IR: one payment). */
	public static function plan_note( string $plan ): string {
		if ( 'free_plus' === $plan ) {
			return __( 'Free Plus costs nothing: a person activates it in Easy Form Builder’s own upgrade dialog, and it adds a small public credit link to pages that show a form.', 'ai-site-assistant-autopilot' );
		}
		if ( 'pro' === $plan ) {
			return self::one_time_purchase()
				? __( 'On a Persian site Easy Form Builder Pro is bought once from easyformbuilder.ir; there is no yearly renewal.', 'ai-site-assistant-autopilot' )
				: __( 'Easy Form Builder Pro is a yearly subscription from whitestudio.team.', 'ai-site-assistant-autopilot' );
		}

		return '';
	}

	/**
	 * The registry, reduced to the facts the copilot's model needs on this site (no key, no address, no secret).
	 */
	public static function prompt_facts(): string {
		if ( ! self::available() ) {
			return "EFB_REGISTRY=no (Easy Form Builder is older than 4.3.2: say a feature needs an update rather than promising it)\n";
		}
		$plan  = self::plan();
		$lines = array( 'EFB_REGISTRY=yes', 'EFB_PLAN_NAME=' . ( '' !== $plan ? $plan : 'unknown' ) . ( self::one_time_purchase() ? ' (Persian site: Pro is a one-time purchase, no renewal)' : '' ) );
		$addons = array();
		$split  = self::block( 'split' );
		foreach ( (array) ( $split['addons'] ?? array() ) as $code => $info ) {
			if ( is_array( $info ) ) {
				$addons[] = sprintf( '%s (%s): %s', self::addon( (string) $code )['name'], (string) ( $info['package'] ?? '' ), (string) ( $info['state'] ?? '' ) );
			}
		}
		if ( array() !== $addons ) {
			$lines[] = 'ADD-ONS THAT LEFT EFB CORE (name (plan to install): active | addon = not installed | plan = plan too low | update): ' . implode( '; ', $addons );
		}
		$missing = array();
		$caps    = self::all();
		foreach ( (array) ( $caps['features'] ?? array() ) as $key => $entry ) {
			if ( is_array( $entry ) && str_starts_with( (string) $key, 'field:' ) && empty( $entry['available_now'] ) ) {
				$missing[] = substr( (string) $key, 6 ) . ' (' . ( 'addon' === ( $entry['reason'] ?? '' ) ? 'add-on' : 'needs ' . (string) ( $entry['package'] ?? 'pro' ) ) . ')';
			}
		}
		if ( array() !== $missing ) {
			$lines[] = 'FIELD TYPES THIS SITE CANNOT RENDER NOW: ' . implode( ', ', $missing );
		}
		$lines[] = 'VISITOR_CONFIRMATION_EMAIL_DEFAULT=' . ( self::email_confirmation_default_on() ? 'on' : 'off' );
		$captcha = array();
		foreach ( self::captcha_providers() as $provider ) {
			$captcha[] = sprintf( '%s (%s%s)', (string) ( $provider['label'] ?? $provider['id'] ?? '' ), ! empty( $provider['available'] ) ? 'available' : 'needs its add-on, ' . (string) ( $provider['plan'] ?? '' ), ! empty( $provider['ready'] ) ? ', keys set' : '' );
		}
		if ( array() !== $captcha ) {
			$lines[] = 'CAPTCHA SERVICES: ' . implode( '; ', $captcha );
		}
		$portal = self::portal();
		if ( null !== $portal ) {
			$lines[] = 'CUSTOMER_PORTAL=' . ( ! empty( $portal['ready'] ) ? 'ready' : 'not ready (' . (string) ( $portal['reason'] ?? 'addon' ) . '; it is the Pro add-on ' . (string) ( $portal['addon'] ?? 'AdnPRT' ) . ')' );
		}
		$steps = self::limit( 'steps' );
		$lines[] = 'STEP_LIMIT=' . ( is_int( $steps ) ? (string) $steps : 'none' );

		return implode( "\n", $lines ) . "\n";
	}
}
