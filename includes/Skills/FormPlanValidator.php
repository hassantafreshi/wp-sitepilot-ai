<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

use AISAAutoPilot\Core\SiteLanguage;

/**
 * Validates and normalizes an AI-produced form plan.
 *
 * AI output is untrusted until it passes here. Pure PHP (no WordPress) so it is
 * fully unit testable. Output escaping happens later at render time.
 *
 * The allowed type set, option-required types, forbidden types, aliases, and max
 * fields are injectable so the same validator serves both the built-in connector
 * and the Easy Form Builder connector (which supports more field types).
 */
final class FormPlanValidator {

	/** @var string[] */
	private array $allowed;
	/** @var string[] */
	private array $option_required;
	/** @var string[] */
	private array $forbidden;
	/** @var array<string,string> */
	private array $aliases;
	/**
	 * Lowercased type => its exact name. Types are compared after lowercasing
	 * what the model wrote, and EFB spells some in camelCase (trmCheckbox).
	 *
	 * @var array<string,string>
	 */
	private array $canonical = array();
	private int $max_fields;

	/**
	 * @param string[]|null             $allowed         Allowed field types.
	 * @param string[]|null             $option_required Types that must have options.
	 * @param string[]|null             $forbidden       Rejected field types.
	 * @param array<string,string>|null $aliases        Alias => canonical type.
	 */
	public function __construct(
		?array $allowed = null,
		?array $option_required = null,
		?array $forbidden = null,
		?array $aliases = null,
		?int $max_fields = null
	) {
		$this->allowed         = $allowed ?? FormPlanSchema::ALLOWED_FIELD_TYPES;
		$this->option_required = $option_required ?? array( 'select', 'radio' );
		$this->forbidden       = $forbidden ?? FormPlanSchema::FORBIDDEN_FIELD_TYPES;
		// "phone" remains a friendly prompt alias; the persisted contract is
		// the HTML-native `tel` field type.
		$this->aliases    = $aliases ?? array(
			'phone'     => 'tel',
			'telephone' => 'tel',
		);
		$this->max_fields = $max_fields ?? FormPlanSchema::MAX_FIELDS;
		foreach ( array_merge( $this->allowed, $this->option_required, $this->forbidden ) as $known ) {
			$this->canonical[ strtolower( $known ) ] = $known;
		}
	}

	public function validate( array $raw ): ValidationResult {
		$errors = array();

		$form = isset( $raw['form'] ) && is_array( $raw['form'] ) ? $raw['form'] : $raw;

		$title = $this->clean_text( (string) ( $form['title'] ?? '' ), 120 );
		if ( '' === $title ) {
			$errors[] = 'The form needs a title.';
		}

		$raw_fields = $form['fields'] ?? null;
		if ( ! is_array( $raw_fields ) || array() === $raw_fields ) {
			$errors[]   = 'The form needs at least one field.';
			$raw_fields = array();
		}

		if ( count( $raw_fields ) > $this->max_fields ) {
			$errors[]   = sprintf( 'Too many fields (max %d).', $this->max_fields );
			$raw_fields = array_slice( $raw_fields, 0, $this->max_fields );
		}

		$fields      = array();
		$used_keys   = array();
		$seen_labels = array();
		$index       = 0;

		foreach ( $raw_fields as $raw_field ) {
			++$index;
			if ( ! is_array( $raw_field ) ) {
				$errors[] = sprintf( 'Field #%d is not valid.', $index );
				continue;
			}

			$type  = strtolower( trim( (string) ( $raw_field['type'] ?? '' ) ) );
			$type  = $this->aliases[ $type ] ?? $type;
			$type  = $this->canonical[ strtolower( $type ) ] ?? $type;
			$label = $this->clean_text( (string) ( $raw_field['label'] ?? '' ), 120 );

			if ( in_array( $type, $this->forbidden, true ) ) {
				$errors[] = sprintf( 'Field "%s" uses a forbidden type (%s) and was rejected for safety.', '' !== $label ? $label : 'field ' . $index, $type );
				continue;
			}

			if ( ! in_array( $type, $this->allowed, true ) ) {
				$errors[] = sprintf( 'Field "%s" uses an unsupported type (%s).', '' !== $label ? $label : 'field ' . $index, '' !== $type ? $type : 'empty' );
				continue;
			}

			// Heading is display-only: a label is enough, no input semantics.
			if ( '' === $label ) {
				$errors[] = sprintf( 'Field #%d is missing a label.', $index );
				continue;
			}

			// Two input fields with one label cannot be told apart by the visitor or in the notification
			// email. The prompt asks for distinct labels; a duplicate that still arrives is numbered.
			$label_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
			if ( ! in_array( $type, array( 'heading', 'steps', 'step' ), true ) ) {
				$seen_labels[ $label_key ] = ( $seen_labels[ $label_key ] ?? 0 ) + 1;
				if ( $seen_labels[ $label_key ] > 1 ) {
					$label = $this->clean_text( $label . ' (' . $seen_labels[ $label_key ] . ')', 120 );
				}
			}

			$key = $this->unique_key( $label, $used_keys, (string) ( $raw_field['key'] ?? '' ) );

			$field = array(
				'key'         => $key,
				'type'        => $type,
				'label'       => $label,
				'required'    => $this->to_bool( $raw_field['required'] ?? false ),
				'placeholder' => $this->clean_text( (string) ( $raw_field['placeholder'] ?? '' ), 120 ),
				// Short guidance shown under the field (EFB calls it the description).
				'help'        => $this->clean_text( (string) ( $raw_field['help'] ?? ( $raw_field['description'] ?? '' ) ), 200 ),
				'width'       => $this->normalize_width( (string) ( $raw_field['width'] ?? 'full' ) ),
				'options'     => array(),
				// Per-type settings (a date's earliest day, an upload's format…).
				// Kept as clean scalars here; the connector decides which of them
				// its own field type actually supports.
				'settings'    => $this->clean_settings( $raw_field['settings'] ?? array() ),
			);

			if ( in_array( $type, $this->option_required, true ) ) {
				$options = $this->clean_options( $raw_field['options'] ?? array() );
				if ( array() === $options ) {
					$errors[] = sprintf( 'Field "%s" (%s) needs at least one option.', $label, $type );
					continue;
				}
				$field['options'] = $options;
			} else {
				$field['options'] = $this->clean_options( $raw_field['options'] ?? array() );
			}

			$fields[] = $field;
		}

		if ( array() === $fields && array() === $errors ) {
			$errors[] = 'No valid fields could be created from the request.';
		}

		if ( array() !== $errors ) {
			return ValidationResult::failure( $errors );
		}

		$risk = strtolower( trim( (string) ( $raw['risk_level'] ?? 'medium' ) ) );
		if ( ! in_array( $risk, FormPlanSchema::RISK_LEVELS, true ) ) {
			$risk = 'medium';
		}

		// Visitors read these, so they follow the site language, not the admin's.
		$defaults = SiteLanguage::for_site(
			static fn (): array => array(
				'submit'       => __( 'Submit', 'ai-site-assistant-autopilot' ),
				'confirmation' => __( 'Thank you. We received your submission.', 'ai-site-assistant-autopilot' ),
			)
		);

		$plan = array(
			'intent'                => 'create_form',
			'title'                 => $title,
			'description'           => $this->clean_text( (string) ( $form['description'] ?? '' ), 300 ),
			'fields'                => $fields,
			'submit_button'         => $this->clean_text( (string) ( $form['submit_button'] ?? '' ), 40 ) ?: $defaults['submit'],
			'confirmation_message'  => $this->clean_text( (string) ( $form['confirmation_message'] ?? '' ), 300 ) ?: $defaults['confirmation'],
			'risk_level'            => $risk,
			'notes'                 => $this->clean_text( (string) ( $raw['notes'] ?? '' ), 300 ),
			// Only switches: the recipient addresses are never taken from the AI.
			'notify_admin'          => $this->to_bool( $form['notify_admin'] ?? ( $raw['notify_admin'] ?? false ) ),
			'confirm_to_respondent' => $this->to_bool( $form['confirm_to_respondent'] ?? ( $raw['confirm_to_respondent'] ?? false ) ),
			'requires_approval'     => true,
			// What the request needed that a native form cannot do, so Form Studio can offer Easy Form Builder.
			'needs_efb'             => $this->efb_features( $raw['needs_efb'] ?? ( $form['needs_efb'] ?? array() ) ),
		);

		return ValidationResult::success( $plan );
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	private function efb_features( $raw ): array {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $feature ) {
			$name = strtolower( str_replace( array( ' ', '-' ), '_', trim( (string) ( is_scalar( $feature ) ? $feature : '' ) ) ) );
			$name = array(
				'upload'     => 'file_upload',
				'file'       => 'file_upload',
				'logic'      => 'conditional_logic',
				'conditions' => 'conditional_logic',
				'steps'      => 'multi_step',
				'multistep'  => 'multi_step',
				'payments'   => 'payment',
			)[ $name ] ?? $name;
			if ( in_array( $name, FormPlanSchema::EFB_FEATURES, true ) && ! in_array( $name, $out, true ) ) {
				$out[] = $name;
			}
		}

		return $out;
	}

	private function clean_text( string $value, int $max ): string {
		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) ?? '' );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * @param mixed $raw
	 * @return array<string,scalar>
	 */
	private function clean_settings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || is_array( $value ) || is_object( $value ) || null === $value ) {
				continue;
			}
			$name = strtolower( $this->clean_text( $key, 40 ) );
			if ( '' === $name || ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
				continue;
			}
			$out[ $name ] = is_bool( $value ) ? $value : $this->clean_text( (string) $value, 100 );
			if ( count( $out ) >= 10 ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	private function clean_options( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $option ) {
			if ( is_array( $option ) ) {
				$option = $option['label'] ?? ( $option['value'] ?? '' );
			}
			$clean = $this->clean_text( (string) $option, 80 );
			if ( '' !== $clean && ! in_array( $clean, $out, true ) ) {
				$out[] = $clean;
			}
		}

		return array_slice( $out, 0, 30 );
	}

	/**
	 * @param array<string,bool> $used
	 */
	private function unique_key( string $label, array &$used, string $preferred = '' ): string {
		// A field that already has a key (a form being changed) keeps it, so a selection or a
		// reference to it stays valid; a new or clashing key is derived from the label.
		$preferred = strtolower( trim( $preferred ) );
		if ( 1 === preg_match( '/^[a-z0-9_]{1,40}$/', $preferred ) && ! isset( $used[ $preferred ] ) ) {
			$used[ $preferred ] = true;
			return $preferred;
		}
		$base = strtolower( $label );
		$base = preg_replace( '/[^a-z0-9]+/', '_', $base ) ?? '';
		$base = trim( $base, '_' );
		if ( '' === $base ) {
			$base = 'field';
		}
		$base = substr( $base, 0, 40 );

		$key = $base;
		$n   = 1;
		while ( isset( $used[ $key ] ) ) {
			++$n;
			$key = $base . '_' . $n;
		}
		$used[ $key ] = true;

		return $key;
	}

	private function normalize_width( string $value ): string {
		// A bare number counts columns (2 = half, 3 = third, 4 = quarter), as before.
		$v = strtolower( trim( str_replace( array( '%', ' ', '-' ), array( '', '', '_' ), $value ) ) );
		if ( in_array( $v, array( 'half', '50', '1/2', 'two', '2', 'one_half' ), true ) ) {
			return 'half';
		}
		if ( in_array( $v, array( 'third', '33', '1/3', '3', 'one_third' ), true ) ) {
			return 'third';
		}
		if ( in_array( $v, array( 'two_thirds', '66', '67', '2/3', 'two_third' ), true ) ) {
			return 'two_thirds';
		}
		if ( in_array( $v, array( 'quarter', '25', '1/4', 'one_quarter' ), true ) ) {
			return 'quarter';
		}
		if ( in_array( $v, array( 'three_quarters', '75', '3/4', 'three_quarter' ), true ) ) {
			return 'three_quarters';
		}

		return 'full';
	}

	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( 'true', '1', 'yes', 'required' ), true );
		}

		return (bool) $value;
	}
}
