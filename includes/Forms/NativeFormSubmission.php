<?php
declare(strict_types=1);

namespace SitePilot\Forms;

use SitePilot\Skills\FormPlanSchema;

/**
 * Validates a public submission against the immutable, approved form plan.
 *
 * The class is deliberately WordPress-free: it never writes data and can be
 * tested without a WordPress installation. The caller may use the returned
 * values only for a one-time delivery email.
 */
final class NativeFormSubmission {

	/**
	 * @param array<int, array<string,mixed>> $fields
	 * @param array<string,mixed>             $input Unslashed request values.
	 * @return array{ok:bool,values:array<string,string>,errors:array<int,string>}
	 */
	public function validate( array $fields, array $input ): array {
		$values = array();
		$errors = array();

		foreach ( $fields as $field ) {
			$key      = $this->key( (string) ( $field['key'] ?? '' ) );
			$type     = (string) ( $field['type'] ?? '' );
			$label    = $this->text( (string) ( $field['label'] ?? '' ), 120 );
			$required = ! empty( $field['required'] );

			if ( '' === $key || '' === $label || ! in_array( $type, FormPlanSchema::ALLOWED_FIELD_TYPES, true ) ) {
				$errors[] = 'invalid_form_definition';
				continue;
			}

			$raw   = $input[ 'sp_field_' . $key ] ?? null;
			$value = $this->field_value( $type, $raw, (array) ( $field['options'] ?? array() ) );

			if ( null === $value ) {
				$errors[] = 'invalid_' . $key;
				continue;
			}
			if ( $required && '' === $value ) {
				$errors[] = 'required_' . $key;
				continue;
			}
			if ( '' !== $value ) {
				$values[ $key ] = $value;
			}
		}

		return array(
			'ok'     => array() === $errors,
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Returns an email-safe, plain-text value or null for a tampered value.
	 *
	 * @param mixed    $raw
	 * @param string[] $options
	 */
	private function field_value( string $type, $raw, array $options ): ?string {
		if ( null === $raw ) {
			return '';
		}

		if ( 'checkbox' === $type ) {
			return $this->checkbox_value( $raw, $options );
		}

		if ( is_array( $raw ) ) {
			return null;
		}

		$value = 'textarea' === $type ? $this->textarea( (string) $raw, 3000 ) : $this->text( (string) $raw, 500 );
		if ( '' === $value ) {
			return '';
		}
		if ( 'email' === $type && false === filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return null;
		}
		if ( in_array( $type, array( 'select', 'radio' ), true ) && ! in_array( $value, $this->options( $options ), true ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * @param mixed    $raw
	 * @param string[] $options
	 */
	private function checkbox_value( $raw, array $options ): ?string {
		$allowed = $this->options( $options );
		if ( array() === $allowed ) {
			if ( is_array( $raw ) ) {
				return null;
			}
			return in_array( strtolower( trim( (string) $raw ) ), array( '1', 'on', 'yes', 'true' ), true ) ? 'Yes' : '';
		}

		$items = is_array( $raw ) ? $raw : array( $raw );
		if ( count( $items ) > count( $allowed ) ) {
			return null;
		}
		$selected = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				return null;
			}
			$value = $this->text( (string) $item, 80 );
			if ( '' === $value ) {
				continue;
			}
			if ( ! in_array( $value, $allowed, true ) ) {
				return null;
			}
			if ( ! in_array( $value, $selected, true ) ) {
				$selected[] = $value;
			}
		}

		return implode( ', ', $selected );
	}

	/** @param string[] $options @return string[] */
	private function options( array $options ): array {
		$clean = array();
		foreach ( $options as $option ) {
			$value = $this->text( (string) $option, 80 );
			if ( '' !== $value && ! in_array( $value, $clean, true ) ) {
				$clean[] = $value;
			}
		}
		return $clean;
	}

	private function key( string $value ): string {
		$key = strtolower( trim( $value ) );
		return preg_match( '/^[a-z0-9_]{1,50}$/', $key ) ? $key : '';
	}

	private function text( string $value, int $max ): string {
		$value = trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) ?? '' );
		return $this->limit( $value, $max );
	}

	private function textarea( string $value, int $max ): string {
		$value = trim( str_replace( array( "\r\n", "\r" ), "\n", strip_tags( $value ) ) );
		$value = preg_replace( '/[^\S\n]+/', ' ', $value ) ?? '';
		return $this->limit( $value, $max );
	}

	private function limit( string $value, int $max ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
