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
	);

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
			return array(
				'fields'  => $fields,
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
			// A setting whose add-on is missing keeps the field itself (a drawn signature without the typed one).
			$alt = str_starts_with( $key, 'setting:' ) ? $type : self::alternative( $type, $creatable );

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
					/* translators: 1: field label, 2: plan name (Free Plus or Pro). */
					$need = sprintf( __( '“%1$s” needs Easy Form Builder %2$s.', 'ai-site-assistant-autopilot' ), $label, $plan );
			}
			if ( $setting && '' !== $alt ) {
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
