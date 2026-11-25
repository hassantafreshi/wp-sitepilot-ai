<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Core\SiteLanguage;

/**
 * Never promise a field this site cannot render.
 *
 * Easy Form Builder moved several features out of its core into add-ons and keeps plan levels per field and setting
 * (its SPLIT, owner decision 2026-11-14). Before a planned field reaches a form, this gate asks EFB's own registry
 * (EfbCapabilities) whether the site can use it now. When it cannot, the admin is told which add-on (and plan) or
 * which plan is needed, and the field becomes the closest choice the site does have, or is left out when there is
 * none. A field the copilot cannot build itself (EFB has it, but only its builder adds it) is treated the same way.
 *
 * Nothing here knows a plan level or an add-on code by heart: the alternatives below are only "what is the nearest
 * ordinary field", and each one is itself checked against the registry before it is used.
 */
final class EfbFeatureGate {

	/**
	 * The closest ordinary field for a field the site cannot use. Each alternative is checked against the registry
	 * too; when it is not available either, the field is left out.
	 */
	public const ALTERNATIVES = array(
		'calc'            => 'number',
		'maps'            => 'text',
		'mobile'          => 'tel',
		'dadfile'         => 'file',
		'audio_recorder'  => 'file',
		'video_recorder'  => 'file',
		'screen_recorder' => 'file',
		'address'         => 'text',
		'stateProvince'   => 'text',
		'cityList'        => 'text',
		'conturyList'     => 'text',
		'switch'          => 'checkbox',
		'yesNo'           => 'radio',
		'chlCheckBox'     => 'checkbox',
		'chlRadio'        => 'radio',
		'imgRadio'        => 'radio',
		'ranking'         => 'checkbox',
		'pointr5'         => 'radio',
		'pointr10'        => 'radio',
		'rating'          => 'radio',
		'color'           => 'text',
		'pdate'           => 'date',
		'ardate'          => 'date',
		'advdate'         => 'date',
		'range'           => 'number',
		'link'            => 'heading',
		'html'            => 'heading',
		'url'             => 'text',
		// A booking slot without the Booking add-on (or only in EFB's builder) is still a date the visitor picks.
		'booking'         => 'date',
	);

	/**
	 * Calendar words a model uses, mapped to the calendars of EFB's advanced date field (F06, `field:advdate`, every
	 * plan). A Jalali or Hijri date is that field with its calendar, never EFB's legacy Pro fields pdate / ardate.
	 */
	private const CALENDARS = array(
		'persian'          => 'persian',
		'jalali'           => 'persian',
		'shamsi'           => 'persian',
		'solar_hijri'      => 'persian',
		'solar'            => 'persian',
		'iranian'          => 'persian',
		'pdate'            => 'persian',
		'شمسی'             => 'persian',
		'جلالی'            => 'persian',
		'hijri'            => 'islamic-umalqura',
		'islamic'          => 'islamic-umalqura',
		'qamari'           => 'islamic-umalqura',
		'lunar'            => 'islamic-umalqura',
		'lunar_hijri'      => 'islamic-umalqura',
		'arabic'           => 'islamic-umalqura',
		'umalqura'         => 'islamic-umalqura',
		'islamic_umalqura' => 'islamic-umalqura',
		'islamic-umalqura' => 'islamic-umalqura',
		'ardate'           => 'islamic-umalqura',
		'قمری'             => 'islamic-umalqura',
		'هجری'             => 'islamic-umalqura',
		'gregorian'        => 'gregory',
		'gregory'          => 'gregory',
		'miladi'           => 'gregory',
		'میلادی'           => 'gregory',
	);

	/** Field types that are a date in a given calendar. */
	private const CALENDAR_TYPES = array(
		'pdate'        => 'persian',
		'persian_date' => 'persian',
		'jalali_date'  => 'persian',
		'shamsi_date'  => 'persian',
		'ardate'       => 'islamic-umalqura',
		'hijri_date'   => 'islamic-umalqura',
		'arabic_date'  => 'islamic-umalqura',
	);

	/** The calendar a word names (persian | islamic-umalqura | gregory), or null. */
	public static function calendar_name( string $word ): ?string {
		$word = strtolower( trim( str_replace( array( ' ', '-' ), '_', $word ) ) );
		if ( 'islamic_umalqura' === $word ) {
			return 'islamic-umalqura';
		}

		return self::CALENDARS[ $word ] ?? ( self::CALENDARS[ str_replace( '_', '-', $word ) ] ?? null );
	}

	/**
	 * The calendars a planned field asks for: a Jalali / Hijri date type (or EFB's legacy pdate / ardate), or a date
	 * with settings.calendar and, for a calendar switch, settings.calendars ("persian,gregory"). Null for a plain
	 * Gregorian date or any other field.
	 *
	 * @param array<string,mixed> $field
	 * @return array{default:string, allowed:string[]}|null
	 */
	public static function date_calendars( array $field ): ?array {
		$raw      = strtolower( trim( str_replace( array( ' ', '-' ), '_', (string) ( $field['type'] ?? '' ) ) ) );
		$settings = is_array( $field['settings'] ?? null ) ? $field['settings'] : array();
		$default  = self::CALENDAR_TYPES[ $raw ] ?? null;
		if ( null === $default && in_array( $raw, array( 'date', 'advdate', 'advanced_date' ), true ) && is_scalar( $settings['calendar'] ?? null ) ) {
			$default = self::calendar_name( (string) $settings['calendar'] );
		}
		$allowed = array();
		foreach ( preg_split( '/[\s,|]+/', is_scalar( $settings['calendars'] ?? null ) ? (string) $settings['calendars'] : '' ) ?: array() as $word ) {
			$cal = '' !== $word ? self::calendar_name( $word ) : null;
			if ( null !== $cal && ! in_array( $cal, $allowed, true ) ) {
				$allowed[] = $cal;
			}
		}
		if ( null === $default ) {
			$default = $allowed[0] ?? null;
		}
		if ( null === $default || ( 'gregory' === $default && count( $allowed ) <= 1 ) ) {
			return null;
		}
		if ( ! in_array( $default, $allowed, true ) ) {
			array_unshift( $allowed, $default );
		}

		return array(
			'default' => $default,
			'allowed' => $allowed,
		);
	}

	/**
	 * A planned date in a Jalali or Hijri calendar as the copilot builds it: an ordinary date field whose settings name
	 * the calendar (the converter writes EFB's advanced date field, `advdate`, with cal_default). Only what the registry
	 * gates is gated: one calendar is every plan; letting the visitor switch between several is `setting:advdate_calendars`.
	 * Null when the field asks for no calendar.
	 *
	 * @param array<string,mixed> $field
	 * @return array{field:array<string,mixed>, blocked:?array<string,mixed>}|null
	 */
	public static function calendar_field( array $field ): ?array {
		$cal = self::date_calendars( $field );
		if ( null === $cal ) {
			return null;
		}
		$label    = trim( (string) ( $field['label'] ?? '' ) );
		$blocked  = null;
		$settings = is_array( $field['settings'] ?? null ) ? $field['settings'] : array();
		unset( $settings['calendar'], $settings['calendars'] );
		$allowed = $cal['allowed'];
		$adv     = EfbCapabilities::available() ? EfbCapabilities::state( 'field:advdate' ) : array( 'known' => true, 'available' => true );
		if ( ! empty( $adv['known'] ) && empty( $adv['available'] ) ) {
			// No advanced date field on this site at all: a plain date, said so.
			$blocked = array(
				'type'        => 'advdate',
				'label'       => '' !== $label ? $label : 'advdate',
				'feature'     => 'field:advdate',
				'reason'      => (string) ( $adv['reason'] ?? 'plan' ),
				'addon'       => null,
				'addon_name'  => '',
				'package'     => (string) ( $adv['package'] ?? 'free' ),
				'alternative' => 'date',
			);
			$field['type']     = 'date';
			$field['settings'] = $settings;

			return array(
				'field'   => $field,
				'blocked' => $blocked,
			);
		}
		if ( count( $allowed ) > 1 && EfbCapabilities::available() ) {
			$state = EfbCapabilities::state( 'setting:advdate_calendars' );
			if ( $state['known'] && ! $state['available'] ) {
				$blocked = array(
					'type'        => 'advdate',
					'label'       => '' !== $label ? $label : 'advdate',
					'feature'     => 'setting:advdate_calendars',
					'reason'      => (string) $state['reason'],
					'addon'       => null,
					'addon_name'  => '',
					'package'     => (string) $state['package'],
					'alternative' => 'date',
					'calendar'    => $cal['default'],
				);
				$allowed = array( $cal['default'] );
			}
		}
		$settings['calendar'] = $cal['default'];
		if ( count( $allowed ) > 1 ) {
			$settings['calendars'] = implode( ',', $allowed );
		}
		$field['type']     = 'date';
		$field['settings'] = $settings;

		return array(
			'field'   => $field,
			'blocked' => $blocked,
		);
	}

	/** A calendar's name in the admin's words. */
	public static function calendar_label( string $calendar ): string {
		switch ( $calendar ) {
			case 'persian':
				return __( 'Persian (solar Hijri) calendar', 'ai-site-assistant-autopilot' );
			case 'islamic-umalqura':
				return __( 'Hijri (lunar) calendar', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Gregorian calendar', 'ai-site-assistant-autopilot' );
		}
	}

	/** @return array<string,string> */
	private static function alternatives(): array {
		return self::ALTERNATIVES;
	}

	/** Words a model uses for EFB features this copilot cannot build itself, mapped to EFB's type. */
	private const EXTRA_ALIASES = array(
		'calculation'      => 'calc',
		'calculated'       => 'calc',
		'formula'          => 'calc',
		'computed'         => 'calc',
		'calculator'       => 'calc',
		'typed_signature'  => 'esign',
		'type_signature'   => 'esign',
		'ranking'          => 'ranking',
		'rank'             => 'ranking',
		'repeater'         => 'repeater',
		'repeat_group'     => 'repeater',
		'advanced_date'    => 'advdate',
		'advdate'          => 'advdate',
		'date_range'       => 'advdate',
		'section'          => 'section',
		'hidden_field'     => 'hiddenField',
		'hiddenfield'      => 'hiddenField',
		'persian_date'     => 'pdate',
		'jalali_date'      => 'pdate',
		'shamsi_date'      => 'pdate',
		'hijri_date'       => 'ardate',
		'arabic_date'      => 'ardate',
		'booking'          => 'booking',
		'appointment_slot' => 'booking',
	);

	/** Options of an alternative choice field when the original had none. */
	private static function default_options( string $original, string $label ): array {
		$texts = SiteLanguage::for_site(
			static fn (): array => array(
				'yes' => __( 'Yes', 'ai-site-assistant-autopilot' ),
				'no'  => __( 'No', 'ai-site-assistant-autopilot' ),
			)
		);
		if ( 'yesNo' === $original ) {
			return array( $texts['yes'], $texts['no'] );
		}
		if ( in_array( $original, array( 'pointr5', 'rating' ), true ) ) {
			return array( '1', '2', '3', '4', '5' );
		}
		if ( 'pointr10' === $original ) {
			return array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' );
		}

		return array( '' !== $label ? $label : $texts['yes'] );
	}

	/**
	 * The EFB type a model's field type means: the plugin's own aliases first, then the names of EFB features it
	 * cannot build. Unknown words come back unchanged (lower-cased) for the validator to judge.
	 */
	public static function resolve_type( string $raw ): string {
		$needle   = strtolower( trim( $raw ) );
		$needle   = str_replace( array( ' ', '-' ), '_', $needle );
		$resolved = EfbFormModel::resolve_field_type( $needle );
		if ( null !== $resolved && 'hidden' !== $resolved ) {
			return 'steps' === $resolved ? 'steps' : $resolved;
		}
		if ( isset( self::EXTRA_ALIASES[ $needle ] ) ) {
			return self::EXTRA_ALIASES[ $needle ];
		}
		foreach ( array( 'calc', 'esign', 'advdate', 'hiddenField', 'repeater', 'ranking', 'section' ) as $type ) {
			if ( strtolower( $type ) === $needle ) {
				return $type;
			}
		}

		return $needle;
	}

	/**
	 * The registry keys a planned field needs: its type, and a setting the type has to have to be what was asked
	 * (a typed signature is an e-signature with the Typed Signature add-on).
	 *
	 * @param array<string,mixed> $field
	 * @return string[]
	 */
	public static function feature_keys( string $type, array $field ): array {
		$keys     = array( EfbCapabilities::field_key( 'booking' === $type ? 'advdate' : $type ) );
		$settings = is_array( $field['settings'] ?? null ) ? $field['settings'] : array();
		$asked    = strtolower( (string) ( $field['type'] ?? '' ) );
		if ( 'esign' === $type && ( in_array( strtolower( (string) ( $settings['esign_mode'] ?? '' ) ), array( 'type', 'both' ), true ) || str_contains( $asked, 'typ' ) ) ) {
			$keys[] = 'setting:esign_mode';
		}
		if ( 'booking' === $type ) {
			$keys[] = 'setting:advdate_availability';
		}
		if ( 'range' === $type && 'dual' === strtolower( (string) ( $settings['range_mode'] ?? '' ) ) ) {
			$keys[] = 'setting:range_dual';
		}

		return $keys;
	}

	/**
	 * Check the fields a model planned for a NEW form, before the plan is validated.
	 *
	 * Without EFB's registry the fields come back untouched (the older plan checks still run later).
	 *
	 * @param array<int,mixed> $fields Raw field objects from the model.
	 * @return array{fields:array<int,mixed>, blocked:array<int,array<string,mixed>>}
	 */
	public static function new_form_fields( array $fields ): array {
		if ( ! EfbCapabilities::available() ) {
			// Without the registry only the calendar of a date is read (the older plan checks run later).
			return array(
				'fields'  => array_map(
					static function ( $field ) {
						$dated = is_array( $field ) ? self::calendar_field( $field ) : null;
						return null !== $dated ? $dated['field'] : $field;
					},
					$fields
				),
				'blocked' => array(),
			);
		}
		$creatable = EfbFormModel::allowed_types();
		$out       = array();
		$blocked   = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				$out[] = $field;
				continue;
			}
			// A Jalali or Hijri date is EFB's advanced date field with that calendar (every plan), not a legacy Pro field.
			$dated = self::calendar_field( $field );
			if ( null !== $dated ) {
				if ( null !== $dated['blocked'] ) {
					$blocked[] = $dated['blocked'];
				}
				$out[] = $dated['field'];
				continue;
			}
			$type = self::resolve_type( (string) ( $field['type'] ?? '' ) );
			if ( '' === $type || 'steps' === $type || 'step' === $type ) {
				$out[] = $field;
				continue;
			}
			$check = self::check( $type, $field, $creatable );
			if ( null === $check ) {
				$out[] = $field;
				continue;
			}
			$blocked[] = $check;
			if ( null !== $check['alternative'] ) {
				$replacement         = $field;
				$replacement['type'] = $check['alternative'];
				unset( $replacement['settings']['esign_mode'], $replacement['settings']['esign_font'], $replacement['settings']['range_mode'] );
				if ( in_array( $check['alternative'], EfbFormModel::option_types(), true ) && array() === array_filter( (array) ( $field['options'] ?? array() ) ) ) {
					$replacement['options'] = self::default_options( $type, (string) ( $field['label'] ?? '' ) );
				}
				if ( 'esign' === $check['alternative'] ) {
					unset( $replacement['settings'] );
				}
				$out[] = $replacement;
			}
		}

		return array(
			'fields'  => $out,
			'blocked' => $blocked,
		);
	}

	/**
	 * One field type the copilot wants to add (to a new or an open form): null when it can be used as it is, else what
	 * stands in the way and the field that takes its place.
	 *
	 * @param array<string,mixed> $field
	 * @param string[]|null       $creatable Types the copilot can build itself (null: EfbFormModel::allowed_types()).
	 * @return array{type:string,label:string,feature:string,reason:string,addon:?string,addon_name:string,package:string,alternative:?string}|null
	 */
	public static function check( string $type, array $field, ?array $creatable = null ): ?array {
		if ( ! EfbCapabilities::available() ) {
			return null;
		}
		$creatable = $creatable ?? EfbFormModel::allowed_types();
		$label     = trim( (string) ( $field['label'] ?? '' ) );

		foreach ( self::feature_keys( $type, $field ) as $key ) {
			$state = EfbCapabilities::state( $key );
			if ( ! $state['known'] || $state['available'] ) {
				continue;
			}
			$addon = $state['addon'];
			// A setting whose add-on is missing keeps the field itself (a drawn signature without the typed one), when
			// the copilot can build that field; else the closest field it can build (a booking slot becomes a date).
			$alt = str_starts_with( $key, 'setting:' ) && in_array( $type, $creatable, true ) ? $type : self::alternative( $type, $creatable );

			return array(
				'type'        => $type,
				'label'       => '' !== $label ? $label : $type,
				'feature'     => $key,
				'reason'      => (string) $state['reason'],
				'addon'       => $addon,
				'addon_name'  => null !== $addon ? EfbCapabilities::addon( $addon )['name'] : '',
				'package'     => null !== $addon && 'addon' === $state['reason'] ? EfbCapabilities::addon( $addon )['package'] : $state['package'],
				'alternative' => $alt,
			);
		}

		// EFB has it and the site may use it, but only EFB's builder adds it.
		if ( ! in_array( $type, $creatable, true ) && EfbCapabilities::state( EfbCapabilities::field_key( 'booking' === $type ? 'advdate' : $type ) )['known'] ) {
			return array(
				'type'        => $type,
				'label'       => '' !== $label ? $label : $type,
				'feature'     => EfbCapabilities::field_key( $type ),
				'reason'      => 'builder_only',
				'addon'       => null,
				'addon_name'  => '',
				'package'     => EfbCapabilities::state( EfbCapabilities::field_key( $type ) )['package'],
				'alternative' => self::alternative( $type, $creatable ),
			);
		}

		return null;
	}

	/**
	 * The nearest ordinary field the site can use and the copilot can build, or null.
	 *
	 * @param string[] $creatable
	 */
	public static function alternative( string $type, array $creatable ): ?string {
		$map  = self::alternatives();
		$seen = array( $type => true );
		$next = $map[ $type ] ?? null;
		while ( null !== $next && ! isset( $seen[ $next ] ) ) {
			$seen[ $next ] = true;
			if ( in_array( $next, $creatable, true ) && EfbCapabilities::state( EfbCapabilities::field_key( $next ) )['available'] ) {
				return $next;
			}
			$next = $map[ $next ] ?? null;
		}

		return null;
	}

	/**
	 * One plain sentence per refused field: what it needs (add-on and plan, or plan) and what the form got instead.
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 * @return string[]
	 */
	public static function messages( array $blocked ): array {
		$out = array();
		foreach ( $blocked as $item ) {
			$label   = (string) ( $item['label'] ?? '' );
			$plan    = EfbCapabilities::plan_label( (string) ( $item['package'] ?? 'pro' ) );
			$alt     = isset( $item['alternative'] ) && is_string( $item['alternative'] ) ? self::type_name( $item['alternative'] ) : '';
			$setting = str_starts_with( (string) ( $item['feature'] ?? '' ), 'setting:' );
			switch ( (string) ( $item['reason'] ?? '' ) ) {
				case 'addon':
					$need = 'free' === ( $item['package'] ?? '' )
						/* translators: 1: field label, 2: add-on name. */
						? sprintf( __( '“%1$s” needs Easy Form Builder’s %2$s add-on, which this site does not have yet (it installs on every plan).', 'ai-site-assistant-autopilot' ), $label, (string) $item['addon_name'] )
						/* translators: 1: field label, 2: add-on name, 3: plan name (Free Plus or Pro). */
						: sprintf( __( '“%1$s” needs Easy Form Builder’s %2$s add-on (%3$s plan), which this site does not have yet.', 'ai-site-assistant-autopilot' ), $label, (string) $item['addon_name'], $plan );
					break;
				case 'update':
					/* translators: 1: field label, 2: add-on name. */
					$need = sprintf( __( '“%1$s” needs a newer version of Easy Form Builder’s %2$s add-on; update it on the Add-ons page.', 'ai-site-assistant-autopilot' ), $label, (string) $item['addon_name'] );
					break;
				case 'builder_only':
					/* translators: %s: field label. */
					$need = sprintf( __( 'I cannot build “%s” myself yet; add it in the Easy Form Builder editor after the form is created.', 'ai-site-assistant-autopilot' ), $label );
					break;
				default:
					if ( 'setting:advdate_calendars' === ( $item['feature'] ?? '' ) ) {
						$out[] = sprintf(
							/* translators: 1: field label, 2: plan name (Free Plus or Pro), 3: calendar name. */
							__( 'Letting visitors switch calendars in “%1$s” needs Easy Form Builder %2$s, so it uses the %3$s only.', 'ai-site-assistant-autopilot' ),
							$label,
							$plan,
							self::calendar_label( (string) ( $item['calendar'] ?? 'gregory' ) )
						);
						continue 2;
					}
					$need = '' !== (string) ( $item['addon_name'] ?? '' )
						/* translators: 1: field label, 2: add-on name, 3: plan name (Free Plus or Pro). */
						? sprintf( __( '“%1$s” needs Easy Form Builder’s %2$s add-on, which comes with %3$s.', 'ai-site-assistant-autopilot' ), $label, (string) $item['addon_name'], $plan )
						/* translators: 1: field label, 2: plan name (Free Plus or Pro). */
						: sprintf( __( '“%1$s” needs Easy Form Builder %2$s.', 'ai-site-assistant-autopilot' ), $label, $plan );
			}
			if ( $setting && '' !== $alt && $alt === (string) ( $item['type'] ?? '' ) ) {
				/* translators: %s: field type name. */
				$then = sprintf( __( 'I kept it as a plain %s.', 'ai-site-assistant-autopilot' ), $alt );
			} elseif ( '' !== $alt ) {
				/* translators: %s: field type name. */
				$then = sprintf( __( 'I used a %s field instead, so the form works today.', 'ai-site-assistant-autopilot' ), $alt );
			} else {
				$then = __( 'I left it out of the form.', 'ai-site-assistant-autopilot' );
			}
			$out[] = $need . ' ' . $then;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Install offers for the add-ons the refused fields need, in the shape the copilot's install card reads.
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 * @return array<int,array{code:string,name:string,allowed:bool,plan:string,upgrade_url:string}>
	 */
	public static function offers( array $blocked ): array {
		$out  = array();
		$seen = array();
		foreach ( $blocked as $item ) {
			$code = (string) ( $item['addon'] ?? '' );
			if ( '' === $code || isset( $seen[ $code ] ) || ! in_array( $item['reason'] ?? '', array( 'addon', 'update' ), true ) ) {
				continue;
			}
			$seen[ $code ] = true;
			$addon         = EfbCapabilities::addon( $code );
			$out[]         = array(
				'code'        => $code,
				'name'        => $addon['name'],
				'allowed'     => $addon['can_install'],
				'plan'        => $addon['package'],
				'upgrade_url' => EfbCapabilities::upgrade_dialog_url( 'addon:' . $code ),
			);
		}

		return $out;
	}

	/**
	 * Whether any refused field needs a higher plan (the answer then carries EFB's upgrade dialog).
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 */
	public static function needs_plan( array $blocked ): bool {
		foreach ( $blocked as $item ) {
			if ( 'plan' === ( $item['reason'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The preview's machine-readable list of what needs an add-on or a plan (the browser shows it, tests read it).
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 * @return array<int,array<string,mixed>>
	 */
	public static function summary( array $blocked ): array {
		return array_map(
			static fn ( array $item ): array => array(
				'label'       => (string) ( $item['label'] ?? '' ),
				'type'        => (string) ( $item['type'] ?? '' ),
				'feature'     => (string) ( $item['feature'] ?? '' ),
				'reason'      => (string) ( $item['reason'] ?? '' ),
				'addon'       => $item['addon'] ?? null,
				'addon_name'  => (string) ( $item['addon_name'] ?? '' ),
				'package'     => (string) ( $item['package'] ?? '' ),
				'alternative' => $item['alternative'] ?? null,
			),
			$blocked
		);
	}

	/**
	 * A setting a model asked for that only EFB's editor sets (a one-time code, a CAPTCHA service, limits, dates, save
	 * and continue later, …): its name in the admin's words and the registry key that says its plan or add-on.
	 *
	 * @return array{name:string, feature:string}
	 */
	public static function editor_setting( string $key ): array {
		$k     = strtolower( $key );
		$has   = static fn ( string ...$words ): bool => (bool) array_filter( $words, static fn ( string $w ): bool => str_contains( $k, $w ) );
		$codes = $has( 'otp', 'verif', 'one_time' ) || ( $has( 'code' ) && ! $has( 'postal', 'post_code', 'zip', 'country', 'area' ) );
		if ( $codes && $has( 'sms', 'mobile', 'phone', 'tel' ) ) {
			return array(
				'name'    => __( 'a one-time code sent by SMS', 'ai-site-assistant-autopilot' ),
				'feature' => 'setting:verify_sms_otp',
			);
		}
		if ( $codes ) {
			return array(
				'name'    => __( 'a one-time code sent by e-mail', 'ai-site-assistant-autopilot' ),
				'feature' => 'setting:verify_email_otp',
			);
		}
		$map = array(
			array( array( 'captcha', 'turnstile', 'altcha', 'spam' ), __( 'a CAPTCHA service', 'ai-site-assistant-autopilot' ), 'setting:captcha_provider' ),
			array( array( 'esign', 'typed', 'signature' ), __( 'a typed signature', 'ai-site-assistant-autopilot' ), 'setting:esign_mode' ),
			array( array( 'range_mode', 'dual', 'two_handle' ), __( 'a two-handle slider', 'ai-site-assistant-autopilot' ), 'setting:range_dual' ),
			array( array( 'resume', 'save_and', 'save_progress', 'continue_later', 'draft' ), __( 'save and continue later', 'ai-site-assistant-autopilot' ), 'setting:form_drafts' ),
			array( array( 'partial' ), __( 'partial answers', 'ai-site-assistant-autopilot' ), 'setting:form_partials' ),
			array( array( 'per_user', 'per_person', 'per_ip', 'per_email' ), __( 'a limit per person', 'ai-site-assistant-autopilot' ), 'setting:form_person_limits' ),
			array( array( 'limit', 'max_entries', 'max_responses', 'max_submissions', 'entries', 'responses', 'capacity' ), __( 'a limit on the number of answers', 'ai-site-assistant-autopilot' ), 'setting:form_total_limit' ),
			array( array( 'close', 'open', 'deadline', 'schedule', 'expire', 'end_date', 'start_date' ), __( 'opening and closing dates', 'ai-site-assistant-autopilot' ), 'setting:form_schedule' ),
			array( array( 'unique' ), __( 'unique answers', 'ai-site-assistant-autopilot' ), 'setting:field_unique' ),
			array( array( 'domain' ), __( 'allowed e-mail domains', 'ai-site-assistant-autopilot' ), 'setting:field_email_domains' ),
			array( array( 'length', 'words', 'chars', 'characters' ), __( 'a text length rule', 'ai-site-assistant-autopilot' ), 'setting:field_text_limits' ),
			array( array( 'formula', 'calc', 'total' ), __( 'a calculation', 'ai-site-assistant-autopilot' ), 'field:calc' ),
			array( array( 'prefill', 'smart_tag', 'utm', 'query', 'url_param' ), __( 'filling the field from the page address', 'ai-site-assistant-autopilot' ), 'setting:hidden_field' ),
			array( array( 'role' ), __( 'who may see the form or the field', 'ai-site-assistant-autopilot' ), 'setting:form_roles' ),
		);
		foreach ( $map as $row ) {
			if ( $has( ...$row[0] ) ) {
				return array(
					'name'    => $row[1],
					'feature' => $row[2],
				);
			}
		}
		$definition = EfbFormModel::field_property( (string) ( EfbFormModel::resolve_field_property( $key ) ?? $key ) );
		$label      = is_array( $definition ) ? trim( (string) ( $definition['label'] ?? '' ) ) : '';

		return array(
			'name'    => '' !== $label ? $label : '“' . str_replace( '_', ' ', $key ) . '”',
			'feature' => '',
		);
	}

	/**
	 * One plain sentence per setting the copilot did not put in a new form (QA5-003): what it is, which plan or add-on
	 * it needs on this site, and that it is set in EFB's editor. Items: label (the field's, or '' for the form), key,
	 * reason (EfbCustomizeSkill::settings_for_new_field_verbose(), or "form" for a form-level key).
	 *
	 * @param array<int,array{label:string,key:string,reason:string}> $items
	 * @return string[]
	 */
	public static function not_applied( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$setting = self::editor_setting( (string) $item['key'] );
			$what    = '' !== (string) $item['label']
				/* translators: 1: field label, 2: what was asked for (e.g. a one-time code sent by SMS). */
				? sprintf( __( '“%1$s” with %2$s', 'ai-site-assistant-autopilot' ), (string) $item['label'], $setting['name'] )
				: $setting['name'];
			if ( 'not_for_type' === $item['reason'] ) {
				/* translators: %s: what was asked for. */
				$out[] = sprintf( __( 'Not in the form: %s. That kind of field has no such setting.', 'ai-site-assistant-autopilot' ), $what );
				continue;
			}
			if ( 'invalid_value' === $item['reason'] ) {
				/* translators: %s: what was asked for. */
				$out[] = sprintf( __( 'Not in the form: %s. Easy Form Builder does not accept that value; set it in its editor.', 'ai-site-assistant-autopilot' ), $what );
				continue;
			}
			$state = '' !== $setting['feature'] && EfbCapabilities::available() ? EfbCapabilities::state( $setting['feature'] ) : null;
			if ( 'requires_pro' === $item['reason'] && ( null === $state || ! $state['known'] ) ) {
				$state = array(
					'known'     => true,
					'available' => ! empty( EfbLicense::status()['is_pro'] ),
					'reason'    => 'plan',
					'package'   => 'pro',
					'addon'     => null,
				);
			}
			if ( null !== $state && $state['known'] && ! $state['available'] ) {
				$plan  = EfbCapabilities::plan_label( (string) $state['package'] );
				$addon = null !== $state['addon'] ? EfbCapabilities::addon( (string) $state['addon'] ) : null;
				if ( null !== $addon ) {
					// "SMS service Add-on" reads "… its SMS service add-on" in the sentence.
					$addon['name'] = trim( (string) preg_replace( '/\s+add-?on$/i', '', (string) $addon['name'] ) );
				}
				if ( null !== $addon && 'addon' === $state['reason'] ) {
					/* translators: 1: what was asked for, 2: add-on name, 3: plan name. */
					$out[] = sprintf( __( 'Not in the form: %1$s. It needs Easy Form Builder’s %2$s add-on (%3$s); then you set it in the Easy Form Builder editor.', 'ai-site-assistant-autopilot' ), $what, $addon['name'], EfbCapabilities::plan_label( $addon['package'] ) );
				} elseif ( null !== $addon ) {
					/* translators: 1: what was asked for, 2: plan name, 3: add-on name. */
					$out[] = sprintf( __( 'Not in the form: %1$s. It needs Easy Form Builder %2$s with its %3$s add-on; then you set it in the Easy Form Builder editor.', 'ai-site-assistant-autopilot' ), $what, $plan, $addon['name'] );
				} else {
					/* translators: 1: what was asked for, 2: plan name. */
					$out[] = sprintf( __( 'Not in the form: %1$s. It needs Easy Form Builder %2$s; then you set it in the Easy Form Builder editor.', 'ai-site-assistant-autopilot' ), $what, $plan );
				}
				continue;
			}
			$out[] = null !== $state && $state['known']
				/* translators: %s: what was asked for. */
				? sprintf( __( 'Not in the form: %s. I cannot set it on a new form; set it in the Easy Form Builder editor (this site has it).', 'ai-site-assistant-autopilot' ), $what )
				/* translators: %s: what was asked for. */
				: sprintf( __( 'Not in the form: %s. I cannot set it on a new form; set it in the Easy Form Builder editor.', 'ai-site-assistant-autopilot' ), $what );
		}

		return array_values( array_unique( $out ) );
	}

	/** A field type's name in the admin's words. */
	public static function type_name( string $type ): string {
		$names = array(
			'number'   => __( 'Number', 'ai-site-assistant-autopilot' ),
			'text'     => __( 'Text', 'ai-site-assistant-autopilot' ),
			'tel'      => __( 'Phone', 'ai-site-assistant-autopilot' ),
			'file'     => __( 'File upload', 'ai-site-assistant-autopilot' ),
			'checkbox' => __( 'Checkboxes', 'ai-site-assistant-autopilot' ),
			'radio'    => __( 'Single choice', 'ai-site-assistant-autopilot' ),
			'date'     => __( 'Date', 'ai-site-assistant-autopilot' ),
			'heading'  => __( 'Heading', 'ai-site-assistant-autopilot' ),
			'esign'    => __( 'Signature (drawn)', 'ai-site-assistant-autopilot' ),
		);
		if ( isset( $names[ $type ] ) ) {
			return $names[ $type ];
		}
		$definition = EfbFormModel::field_type( $type );

		return (string) ( $definition['label'] ?? $type );
	}
}
