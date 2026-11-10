<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

/**
 * Small, deterministic edits to a form plan: what a click in Form Studio does, with no AI call.
 *
 * The result is only a candidate. It goes back through FormPlanValidator, which decides what is
 * allowed, so this class never has to repeat those rules; it just moves the plan around.
 * Pure PHP, so it is unit testable without WordPress.
 */
final class FormPlanEditor {

	private const FIELD_TEXTS = array( 'label', 'placeholder', 'help' );
	private const FORM_TEXTS  = array( 'title', 'description', 'submit_button', 'confirmation_message' );

	/**
	 * @param array<string,mixed>             $plan
	 * @param array<int,array<string,mixed>>  $ops
	 * @return array<string,mixed>|null The edited plan, or null when an operation does not apply.
	 */
	public static function apply( array $plan, array $ops ): ?array {
		$fields = array_values( array_filter( (array) ( $plan['fields'] ?? array() ), 'is_array' ) );

		foreach ( $ops as $op ) {
			if ( ! is_array( $op ) ) {
				return null;
			}
			$name = (string) ( $op['op'] ?? '' );

			if ( 'set_form' === $name ) {
				$prop = (string) ( $op['prop'] ?? '' );
				if ( ! in_array( $prop, self::FORM_TEXTS, true ) ) {
					return null;
				}
				$plan[ $prop ] = (string) ( $op['value'] ?? '' );
				continue;
			}

			$index = self::index_of( $fields, (string) ( $op['key'] ?? '' ) );
			if ( null === $index ) {
				return null;
			}

			switch ( $name ) {
				case 'set_width':
					$fields[ $index ]['width'] = (string) ( $op['value'] ?? 'full' );
					break;
				case 'set_required':
					$fields[ $index ]['required'] = ! empty( $op['value'] );
					break;
				case 'set_text':
					$prop = (string) ( $op['prop'] ?? '' );
					if ( ! in_array( $prop, self::FIELD_TEXTS, true ) ) {
						return null;
					}
					$fields[ $index ][ $prop ] = (string) ( $op['value'] ?? '' );
					break;
				case 'add_option':
					$fields[ $index ]['options']   = (array) ( $fields[ $index ]['options'] ?? array() );
					$fields[ $index ]['options'][] = (string) ( $op['value'] ?? '' );
					break;
				case 'remove_option':
					$fields[ $index ]['options'] = array_values(
						array_filter(
							(array) ( $fields[ $index ]['options'] ?? array() ),
							static fn ( $option ): bool => (string) ( $op['value'] ?? '' ) !== (string) $option
						)
					);
					break;
				case 'remove':
					array_splice( $fields, $index, 1 );
					break;
				case 'move':
					$fields = self::move( $fields, $index, (string) ( $op['direction'] ?? '' ) );
					if ( null === $fields ) {
						return null;
					}
					break;
				default:
					return null;
			}
		}

		$plan['fields'] = $fields;

		return $plan;
	}

	/** @param array<int,array<string,mixed>> $fields */
	private static function index_of( array $fields, string $key ): ?int {
		if ( '' === $key ) {
			return null;
		}
		foreach ( $fields as $index => $field ) {
			if ( ( $field['key'] ?? '' ) === $key ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function move( array $fields, int $index, string $direction ): ?array {
		$last = count( $fields ) - 1;
		$to   = array(
			'up'     => max( 0, $index - 1 ),
			'down'   => min( $last, $index + 1 ),
			'top'    => 0,
			'bottom' => $last,
		)[ $direction ] ?? null;
		if ( null === $to ) {
			return null;
		}
		$item = array_splice( $fields, $index, 1 );
		array_splice( $fields, $to, 0, $item );

		return $fields;
	}
}
