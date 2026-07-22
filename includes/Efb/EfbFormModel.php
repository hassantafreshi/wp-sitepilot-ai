<?php
declare(strict_types=1);

namespace SitePilot\Efb;

/**
 * Loads the compact EFB form model (field capabilities + default templates).
 *
 * `ai_capabilities` is the small catalog sent to the AI. `templates` hold the
 * full EFB default property maps applied locally by the converter and are never
 * sent to the AI — this keeps the model payload tiny.
 */
final class EfbFormModel {

	/** @var array<string, mixed>|null */
	private static ?array $data = null;

	/** @var array<string, mixed>|null */
	private static ?array $capability_catalog = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		if ( null === self::$data ) {
			$file = __DIR__ . '/efb-form-model.json';
			$raw  = is_readable( $file ) ? (string) file_get_contents( $file ) : '{}';
			$decoded = json_decode( $raw, true );
			self::$data = is_array( $decoded ) ? $decoded : array();
		}

		return self::$data;
	}

	/**
	 * Full EFB capability catalog. Unlike `data()`, this is not the compact
	 * create-form model: it records the real valj_efb keys, add-on dependencies,
	 * persistence rules, and which settings are safe for an AI proposal to edit.
	 *
	 * @return array<string, mixed>
	 */
	public static function capability_catalog(): array {
		if ( null === self::$capability_catalog ) {
			$file = __DIR__ . '/efb-capability-catalog.json';
			$raw  = is_readable( $file ) ? (string) file_get_contents( $file ) : '{}';
			$decoded = json_decode( $raw, true );
			self::$capability_catalog = is_array( $decoded ) ? $decoded : array();
		}

		return self::$capability_catalog;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function field_properties(): array {
		$properties = self::capability_catalog()['field_properties'] ?? array();

		return is_array( $properties ) ? $properties : array();
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function form_properties(): array {
		$properties = self::capability_catalog()['form_properties'] ?? array();

		return is_array( $properties ) ? $properties : array();
	}

	/**
	 * All field IDs known to the installed EFB model (including fields which are
	 * deliberately not automatically creatable because they need credentials,
	 * add-on setup, or a specialised child structure).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function field_types(): array {
		$types = self::capability_catalog()['field_types'] ?? array();

		return is_array( $types ) ? $types : array();
	}

	/** @return array<string, mixed>|null */
	public static function field_type( string $type ): ?array {
		$canonical = self::resolve_field_type( $type );

		return null !== $canonical ? ( self::field_types()[ $canonical ] ?? null ) : null;
	}

	/**
	 * Resolve a palette ID from its exact EFB ID, an existing compact-model
	 * alias, or the persisted `stored_type` (notably step <- steps).
	 */
	public static function resolve_field_type( string $type ): ?string {
		$needle = strtolower( trim( $type ) );
		if ( '' === $needle ) {
			return null;
		}

		$aliases = self::aliases();
		if ( isset( $aliases[ $needle ] ) ) {
			$needle = strtolower( (string) $aliases[ $needle ] );
		}

		foreach ( self::field_types() as $id => $definition ) {
			if ( strtolower( (string) $id ) === $needle ) {
				return (string) $id;
			}
			if ( is_array( $definition ) && strtolower( (string) ( $definition['stored_type'] ?? '' ) ) === $needle ) {
				return (string) $id;
			}
		}

		return null;
	}

	/** @return string[] */
	public static function addable_types(): array {
		$out = array();
		foreach ( self::field_types() as $id => $definition ) {
			if ( is_array( $definition ) && ! empty( $definition['ai_create'] ) && 'steps' !== $id ) {
				$out[] = (string) $id;
			}
		}

		return $out;
	}

	public static function requires_pro_field( string $type ): bool {
		$definition = self::field_type( $type );

		return is_array( $definition ) && 'pro' === (string) ( $definition['plan'] ?? '' );
	}

	/**
	 * Resolve a canonical property key from the key itself or an explicit alias.
	 */
	public static function resolve_field_property( string $key ): ?string {
		return self::resolve_property( $key, self::field_properties() );
	}

	/**
	 * Resolve a canonical form property key from the key itself or an explicit alias.
	 */
	public static function resolve_form_property( string $key ): ?string {
		return self::resolve_property( $key, self::form_properties() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function field_property( string $key ): ?array {
		$canonical = self::resolve_field_property( $key );

		return null !== $canonical ? ( self::field_properties()[ $canonical ] ?? null ) : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function form_property( string $key ): ?array {
		$canonical = self::resolve_form_property( $key );

		return null !== $canonical ? ( self::form_properties()[ $canonical ] ?? null ) : null;
	}

	/**
	 * Narrow, browser-safe subset consumed by the local operation executor.
	 * It intentionally excludes credentials, add-on configuration and properties
	 * which the server will never approve for automatic writes.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function client_field_properties(): array {
		return self::client_properties( self::field_properties(), 'storage_key' );
	}

	/** @return array<string, array<string, mixed>> */
	public static function client_form_properties(): array {
		return self::client_properties( self::form_properties(), 'storage_path' );
	}

	/**
	 * Compact property vocabulary for the AI prompt. The complete catalog stays
	 * local; only settings that the validator and browser can actually apply are
	 * advertised to the model.
	 */
	public static function customization_capabilities_for_prompt(): string {
		$field_lines = array();
		foreach ( self::field_properties() as $key => $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) ) {
				continue;
			}
			$field_lines[] = self::property_prompt_line( (string) $key, $definition );
		}

		$form_lines = array();
		foreach ( self::form_properties() as $key => $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) ) {
				continue;
			}
			$form_lines[] = self::property_prompt_line( (string) $key, $definition );
		}

		return "FIELD_PROPERTIES (use set_field_property):\n- " . implode( "\n- ", $field_lines )
			. "\nFORM_PROPERTIES (use set_form_property):\n- " . implode( "\n- ", $form_lines );
	}

	/** @return array<int, array<string, mixed>> */
	public static function capabilities(): array {
		$caps = self::data()['ai_capabilities'] ?? array();

		return is_array( $caps ) ? $caps : array();
	}

	/** @return string[] */
	public static function allowed_types(): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( $c ) => is_array( $c ) ? (string) ( $c['type'] ?? '' ) : '',
					self::capabilities()
				)
			)
		);
	}

	/** @return string[] Types that require an options array. */
	public static function option_types(): array {
		$out = array();
		foreach ( self::capabilities() as $c ) {
			if ( is_array( $c ) && ! empty( $c['needs_options'] ) ) {
				$out[] = (string) $c['type'];
			}
		}

		return $out;
	}

	/** @return array<string, string> */
	public static function aliases(): array {
		$a = self::data()['type_aliases'] ?? array();

		return is_array( $a ) ? $a : array();
	}

	/** @return string[] */
	public static function forbidden_types(): array {
		$f = self::data()['forbidden_types'] ?? array();

		return is_array( $f ) ? $f : array();
	}

	/** @return array<string, int> */
	public static function limits(): array {
		$l = self::data()['limits'] ?? array();

		return is_array( $l ) ? $l : array();
	}

	/** @return array<string, string> Width keyword => EFB size percent. */
	public static function width_map(): array {
		$w = self::data()['width_map'] ?? array();

		return is_array( $w ) && array() !== $w ? $w : array( 'full' => '100', 'half' => '50', 'third' => '33', 'quarter' => '25' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function template( string $key ): array {
		$t = self::data()['templates'][ $key ] ?? array();

		return is_array( $t ) ? $t : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function overrides( string $type ): array {
		$o = self::data()['templates']['field_overrides'][ $type ] ?? array();

		return is_array( $o ) ? $o : array();
	}

	/**
	 * Compact capability list rendered for the AI prompt.
	 */
	public static function capabilities_for_prompt(): string {
		$lines = array();
		foreach ( self::capabilities() as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$lines[] = sprintf(
				'- %s: %s%s',
				(string) $c['type'],
				(string) ( $c['label'] ?? '' ),
				! empty( $c['needs_options'] ) ? ' (requires "options")' : ''
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Full field knowledge for customization prompts. The model sees the actual
	 * EFB palette IDs, plan requirements and whether an operation is executable;
	 * it no longer guesses that a Step is a Heading.
	 */
	public static function field_types_for_prompt(): string {
		$lines = array();
		foreach ( self::field_types() as $id => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}
			$status = ! empty( $definition['ai_create'] ) ? 'add_field' : 'recognized; do not auto-create';
			if ( 'steps' === $id ) {
				$status = 'add_step only (structural, never a heading)';
			}
			$plan = (string) ( $definition['plan'] ?? 'free' );
			$label = (string) ( $definition['label'] ?? $id );
			$lines[] = sprintf( '- %s: %s [%s; %s]', $id, $label, $plan, $status );
		}

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, array<string, mixed>> $properties
	 */
	private static function resolve_property( string $key, array $properties ): ?string {
		$needle = strtolower( trim( $key ) );
		if ( '' === $needle ) {
			return null;
		}

		foreach ( $properties as $canonical => $definition ) {
			if ( strtolower( (string) $canonical ) === $needle ) {
				return (string) $canonical;
			}
			if ( ! is_array( $definition ) || ! is_array( $definition['aliases'] ?? null ) ) {
				continue;
			}
			foreach ( $definition['aliases'] as $alias ) {
				if ( strtolower( (string) $alias ) === $needle ) {
					return (string) $canonical;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, array<string, mixed>> $properties
	 * @return array<string, array<string, mixed>>
	 */
	private static function client_properties( array $properties, string $storage_key ): array {
		$out = array();
		foreach ( $properties as $key => $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) || empty( $definition[ $storage_key ] ) ) {
				continue;
			}
			$out[ (string) $key ] = array(
				$storage_key  => (string) $definition[ $storage_key ],
				'value_type'  => (string) ( $definition['value_type'] ?? 'string' ),
				'values'      => $definition['values'] ?? null,
				'applies_to'  => $definition['applies_to'] ?? array( '*' ),
				'style_path'  => $definition['style_path'] ?? null,
				'minimum'     => $definition['minimum'] ?? null,
				'maximum'     => $definition['maximum'] ?? null,
				'max_length'  => $definition['max_length'] ?? null,
			);
		}

		return $out;
	}

	/** @param array<string, mixed> $definition */
	private static function property_prompt_line( string $key, array $definition ): string {
		$type = (string) ( $definition['value_type'] ?? 'string' );
		$line = $key . ' (' . $type;
		if ( isset( $definition['values'] ) ) {
			$raw_values = is_array( $definition['values'] ) ? $definition['values'] : array();
			$values = array_is_list( $raw_values ) ? $raw_values : array_keys( $raw_values );
			if ( array() !== $values ) {
				$line .= ': ' . implode( '|', array_map( 'strval', $values ) );
			}
		}
		$line .= ')';
		if ( is_array( $definition['applies_to'] ?? null ) && array( '*' ) !== $definition['applies_to'] ) {
			$line .= ' for ' . implode( '|', array_map( 'strval', $definition['applies_to'] ) );
		}

		return $line;
	}
}
