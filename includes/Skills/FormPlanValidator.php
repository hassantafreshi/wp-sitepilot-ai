<?php
declare(strict_types=1);

namespace SitePilot\Skills;

/**
 * Validates and normalizes an AI-produced form plan.
 *
 * AI output is untrusted until it passes here. Pure PHP (no WordPress) so it is
 * fully unit testable. Output escaping happens later at render time.
 */
final class FormPlanValidator {

	public function validate( array $raw ): ValidationResult {
		$errors = array();

		// Accept either the whole object or a nested "form" object.
		$form = isset( $raw['form'] ) && is_array( $raw['form'] ) ? $raw['form'] : $raw;

		$title = $this->clean_text( (string) ( $form['title'] ?? '' ), 120 );
		if ( '' === $title ) {
			$errors[] = 'The form needs a title.';
		}

		$raw_fields = $form['fields'] ?? null;
		if ( ! is_array( $raw_fields ) || array() === $raw_fields ) {
			$errors[] = 'The form needs at least one field.';
			$raw_fields = array();
		}

		if ( count( $raw_fields ) > FormPlanSchema::MAX_FIELDS ) {
			$errors[] = sprintf( 'Too many fields (max %d).', FormPlanSchema::MAX_FIELDS );
			$raw_fields = array_slice( $raw_fields, 0, FormPlanSchema::MAX_FIELDS );
		}

		$fields    = array();
		$used_keys = array();
		$index     = 0;

		foreach ( $raw_fields as $raw_field ) {
			++$index;
			if ( ! is_array( $raw_field ) ) {
				$errors[] = sprintf( 'Field #%d is not valid.', $index );
				continue;
			}

			$type  = strtolower( trim( (string) ( $raw_field['type'] ?? '' ) ) );
			$label = $this->clean_text( (string) ( $raw_field['label'] ?? '' ), 120 );

			if ( in_array( $type, FormPlanSchema::FORBIDDEN_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( 'Field "%s" uses a forbidden type (%s) and was rejected for safety.', '' !== $label ? $label : 'field ' . $index, $type );
				continue;
			}

			if ( ! in_array( $type, FormPlanSchema::ALLOWED_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( 'Field "%s" uses an unsupported type (%s).', '' !== $label ? $label : 'field ' . $index, '' !== $type ? $type : 'empty' );
				continue;
			}

			if ( '' === $label ) {
				$errors[] = sprintf( 'Field #%d is missing a label.', $index );
				continue;
			}

			$key = $this->unique_key( $label, $used_keys );

			$field = array(
				'key'         => $key,
				'type'        => $type,
				'label'       => $label,
				'required'    => $this->to_bool( $raw_field['required'] ?? false ),
				'placeholder' => $this->clean_text( (string) ( $raw_field['placeholder'] ?? '' ), 120 ),
				'options'     => array(),
			);

			if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
				$options = $this->clean_options( $raw_field['options'] ?? array() );
				if ( array() === $options ) {
					$errors[] = sprintf( 'Field "%s" (%s) needs at least one option.', $label, $type );
					continue;
				}
				$field['options'] = $options;
			} elseif ( 'checkbox' === $type ) {
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

		$plan = array(
			'intent'               => 'create_form',
			'title'                => $title,
			'description'          => $this->clean_text( (string) ( $form['description'] ?? '' ), 300 ),
			'fields'               => $fields,
			'submit_button'        => $this->clean_text( (string) ( $form['submit_button'] ?? 'Submit' ), 40 ) ?: 'Submit',
			'confirmation_message' => $this->clean_text( (string) ( $form['confirmation_message'] ?? 'Thank you. We received your submission.' ), 300 ),
			'risk_level'           => $risk,
			'notes'                => $this->clean_text( (string) ( $raw['notes'] ?? '' ), 300 ),
			'requires_approval'    => true,
		);

		return ValidationResult::success( $plan );
	}

	private function clean_text( string $value, int $max ): string {
		$value = trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) ?? '' );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
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
	private function unique_key( string $label, array &$used ): string {
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
