<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Changing a SAVED Easy Form Builder form from the copilot (EFB 4.3.2, form.update; contract in EFB's
 * docs/autopilot-integration.md "AI-2").
 *
 * The copilot plans the same operations it applies to a form open in EFB's editor. For a saved form they become EFB's
 * own patches (`form`, `field`, `insert`, `remove`, `options`, `name`), which EFB applies only to the version that was
 * read (`base_hash`), gates against its registry (plan, add-ons) and saves through its own save path. An operation that
 * has no safe patch (payment, conditional logic, a setting with its own rules in the editor) is listed for the admin
 * to do in EFB's editor; nothing is guessed.
 */
final class EfbFormUpdate {

	private const WIDTH = array(
		'full'    => '100',
		'half'    => '50',
		'third'   => '33',
		'quarter' => '25',
	);

	private const GEO_TYPES = array( 'conturyList', 'stateProvince', 'cityList' );

	/** Value types the patch writes as they are (the editor's catalogValue()); others are left to the editor. */
	private const SIMPLE_VALUES = array( 'boolean_flag', 'enum', 'integer', 'number_or_date', 'string', 'text' );

	/**
	 * What the copilot reads of a saved form: the same shape the editor sends for an open form.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array{fields:array<int,array<string,mixed>>,steps:array<int,array<string,string>>,logic_rules:array<int,mixed>,captcha:bool,form_type:string,form_name:string}
	 */
	public static function view( array $rows ): array {
		$fields = array();
		$steps  = array();
		foreach ( $rows as $i => $row ) {
			if ( 0 === $i || ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['type'] ?? '' );
			if ( 'step' === $type ) {
				$steps[] = array(
					'id'    => (string) ( $row['id_'] ?? ( $row['step'] ?? '' ) ),
					'label' => (string) ( $row['name'] ?? '' ),
				);
				continue;
			}
			if ( ! self::is_field( $row ) ) {
				continue;
			}
			$options = array();
			if ( ! in_array( $type, self::GEO_TYPES, true ) ) {
				foreach ( $rows as $o ) {
					if ( is_array( $o ) && 'option' === ( $o['type'] ?? '' ) && (string) ( $o['parent'] ?? '' ) === (string) $row['id_'] ) {
						$options[] = array(
							'id'    => (string) ( $o['id_'] ?? '' ),
							'value' => (string) ( $o['value'] ?? '' ),
						);
					}
				}
			}
			$fields[] = array(
				'id'       => (string) $row['id_'],
				'label'    => (string) ( $row['name'] ?? ( $row['value'] ?? $row['id_'] ) ),
				'type'     => $type,
				'required' => in_array( $row['required'] ?? 0, array( true, 1, '1' ), true ),
				'step'     => (string) ( $row['step'] ?? '' ),
				'options'  => $options,
			);
		}
		$captcha = $rows[0]['captcha'] ?? false;

		return array(
			'fields'      => $fields,
			'steps'       => $steps,
			'logic_rules' => is_array( $rows[0]['logic_rules'] ?? null ) ? array_values( $rows[0]['logic_rules'] ) : array(),
			'captcha'     => in_array( $captcha, array( true, 1, '1', 'true' ), true ),
			'form_type'   => (string) ( $rows[0]['type'] ?? 'form' ),
			'form_name'   => (string) ( $rows[0]['formName'] ?? '' ),
		);
	}

	/** @param array<string,mixed> $row */
	private static function is_field( array $row ): bool {
		$type = (string) ( $row['type'] ?? '' );

		return '' !== $type && isset( $row['id_'] ) && ! in_array( $type, array( 'form', 'step', 'option', 'r_matrix', 'payment', 'survey', 'login', 'register', 'subscribe', 'smartForm' ), true );
	}

	/**
	 * The field an operation names (its id, or its label as the editor's findField() reads it), or null.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>|null
	 */
	private static function find( array $rows, $target ): ?array {
		$t = strtolower( trim( (string) $target ) );
		if ( '' === $t ) {
			return null;
		}
		foreach ( $rows as $i => $row ) {
			if ( $i > 0 && is_array( $row ) && self::is_field( $row ) && ( strtolower( (string) $row['id_'] ) === $t || strtolower( (string) ( $row['name'] ?? '' ) ) === $t ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * The copilot's operations as EFB patches.
	 *
	 * @param array<int,array<string,mixed>> $ops  Operations the server already validated and gated (as for an open form).
	 * @param array<int,array<string,mixed>> $rows The saved form (Form_Package::read()).
	 * @return array{patches:array<int,array<string,mixed>>,changes:array<int,string>,skipped:array<int,array{op:string,reason:string,text:string}>}
	 */
	public static function patches( array $ops, array $rows ): array {
		$patches = array();
		$changes = array();
		$skipped = array();
		$removed = array();
		$choices = array(); // field id => the option list after the operations (kept ids: references stay).
		$touched = array(); // the fields whose choices an operation changed.
		$fields  = self::field_props();
		$forms   = self::form_props();
		$skip    = static function ( array $op, string $reason, string $text ) use ( &$skipped ): void {
			$skipped[] = array(
				'op'     => (string) ( $op['op'] ?? '' ),
				'reason' => $reason,
				'text'   => $text,
			);
		};
		$option_list = static function ( array $field ) use ( &$choices, $rows ): array {
			$id = (string) $field['id_'];
			if ( ! isset( $choices[ $id ] ) ) {
				$choices[ $id ] = array();
				foreach ( $rows as $o ) {
					if ( is_array( $o ) && 'option' === ( $o['type'] ?? '' ) && (string) ( $o['parent'] ?? '' ) === $id ) {
						$one = array(
							'id_'   => (string) ( $o['id_'] ?? '' ),
							'value' => (string) ( $o['value'] ?? '' ),
						);
						foreach ( array( 'price', 'opt_value' ) as $k ) {
							if ( isset( $o[ $k ] ) && is_scalar( $o[ $k ] ) ) {
								$one[ $k ] = (string) $o[ $k ];
							}
						}
						$choices[ $id ][] = $one;
					}
				}
			}

			return $choices[ $id ];
		};
		foreach ( $ops as $op ) {
			if ( ! is_array( $op ) ) {
				continue;
			}
			$name   = (string) ( $op['op'] ?? '' );
			$target = (string) ( $op['target'] ?? '' );
			switch ( $name ) {
				case 'set_form_name':
					$value     = trim( (string) ( $op['value'] ?? '' ) );
					$patches[] = array(
						'op'    => 'name',
						'value' => $value,
					);
					$patches[] = array(
						'op'  => 'form',
						'set' => array( 'formName' => $value ),
					);
					/* translators: %s: the new form name. */
					$changes[] = sprintf( __( 'Rename the form to “%s”', 'ai-site-assistant-autopilot' ), $value );
					break;
				case 'set_confirmation':
					$thanks             = is_array( $rows[0]['thank_you_message'] ?? null ) ? $rows[0]['thank_you_message'] : array();
					$thanks['thankYou'] = (string) ( $op['value'] ?? '' );
					$patches[]          = array(
						'op'  => 'form',
						'set' => array( 'thank_you_message' => $thanks ),
					);
					$changes[]          = __( 'Change the confirmation message', 'ai-site-assistant-autopilot' );
					break;
				case 'set_field':
					$field = self::find( $rows, $target );
					if ( null === $field || isset( $removed[ (string) $field['id_'] ] ) ) {
						$skip( $op, 'field_not_found', $target );
						break;
					}
					$prop  = (string) ( $op['prop'] ?? '' );
					$value = $op['value'] ?? '';
					$set   = array(
						'label'       => array( 'name', (string) $value ),
						'required'    => array( 'required', $value ? '1' : '0' ),
						'placeholder' => array( 'placeholder', (string) $value ),
						'width'       => array( 'size', self::WIDTH[ (string) $value ] ?? '100' ),
						'help'        => array( 'message', (string) $value ),
					)[ $prop ] ?? null;
					if ( null === $set ) {
						$skip( $op, 'unsupported_field_property', $target );
						break;
					}
					$patches[] = array(
						'op'  => 'field',
						'id'  => (string) $field['id_'],
						'set' => array( $set[0] => $set[1] ),
					);
					$changes[] = self::field_change_text( $prop, (string) ( $field['name'] ?? $target ), $value );
					break;
				case 'set_field_property':
					$field = self::find( $rows, $target );
					$def   = $fields[ (string) ( $op['property'] ?? '' ) ] ?? null;
					if ( null === $field ) {
						$skip( $op, 'field_not_found', $target );
						break;
					}
					if ( ! is_array( $def ) || empty( $def['storage_key'] ) || ! empty( $def['special'] ) || ! in_array( (string) ( $def['value_type'] ?? '' ), self::SIMPLE_VALUES, true ) || ! self::applies( $def, $field ) ) {
						$skip( $op, 'editor_only', (string) ( $op['property'] ?? '' ) );
						break;
					}
					$stored = self::catalog_value( $def, $op['value'] ?? '', false );
					$set    = array( (string) $def['storage_key'] => $stored );
					foreach ( (array) ( $def['mirror_keys'] ?? array() ) as $mirror ) {
						$set[ (string) $mirror ] = $stored;
					}
					$patches[] = array(
						'op'  => 'field',
						'id'  => (string) $field['id_'],
						'set' => $set,
					);
					/* translators: 1: setting name, 2: field label. */
					$changes[] = sprintf( __( 'Change %1$s of “%2$s”', 'ai-site-assistant-autopilot' ), (string) ( $def['label'] ?? $op['property'] ), (string) ( $field['name'] ?? $target ) );
					break;
				case 'set_form_property':
					$def  = $forms[ (string) ( $op['property'] ?? '' ) ] ?? null;
					$path = is_array( $def ) ? (string) ( $def['storage_path'] ?? '' ) : '';
					if ( '' === $path || str_contains( $path, '.' ) || ! empty( $def['special'] ) || ! in_array( (string) ( $def['value_type'] ?? '' ), self::SIMPLE_VALUES, true ) ) {
						$skip( $op, 'editor_only', (string) ( $op['property'] ?? '' ) );
						break;
					}
					$patches[] = array(
						'op'  => 'form',
						'set' => array( $path => self::catalog_value( $def, $op['value'] ?? '', true ) ),
					);
					/* translators: %s: setting name. */
					$changes[] = sprintf( __( 'Change the form setting %s', 'ai-site-assistant-autopilot' ), (string) ( $def['label'] ?? $op['property'] ) );
					break;
				case 'add_step':
					$item = is_array( $op['item'] ?? null ) ? $op['item'] : array( 'type' => 'step' );
					if ( 'step' !== ( $item['type'] ?? '' ) ) {
						$skip( $op, 'step_template_missing', '' );
						break;
					}
					$item['id_']  = 'step_new';
					$item['name'] = (string) ( $op['label'] ?? ( $item['name'] ?? '' ) );
					$patches[]    = array(
						'op'   => 'insert',
						'rows' => array( $item ),
					);
					/* translators: %s: step name. */
					$changes[] = sprintf( __( 'Add the step “%s”', 'ai-site-assistant-autopilot' ), $item['name'] );
					break;
				case 'add_field':
					$items = array_values( array_filter( (array) ( $op['items'] ?? array() ), 'is_array' ) );
					$head  = null;
					foreach ( $items as $it ) {
						if ( ! in_array( (string) ( $it['type'] ?? '' ), array( 'option', 'r_matrix' ), true ) ) {
							$head = $it;
							break;
						}
					}
					if ( null === $head ) {
						$skip( $op, 'field_template_missing', '' );
						break;
					}
					$insert = array(
						'op'   => 'insert',
						'rows' => $items,
					);
					$after  = isset( $op['after'] ) ? self::find( $rows, $op['after'] ) : null;
					$before = isset( $op['before'] ) ? self::find( $rows, $op['before'] ) : null;
					if ( null !== $after ) {
						$insert['after'] = (string) $after['id_'];
					} elseif ( null !== $before ) {
						$insert['before'] = (string) $before['id_'];
					} elseif ( isset( $op['step'] ) && is_numeric( $op['step'] ) ) {
						$insert['step'] = (int) $op['step'];
					}
					$patches[] = $insert;
					$label     = (string) ( $head['name'] ?? ( $op['field']['label'] ?? '' ) );
					/* translators: %s: field label. */
					$changes[] = sprintf( __( 'Add the field “%s”', 'ai-site-assistant-autopilot' ), $label );
					break;
				case 'remove_field':
					$field = self::find( $rows, $target );
					if ( null === $field || isset( $removed[ (string) $field['id_'] ] ) ) {
						$skip( $op, 'field_not_found', $target );
						break;
					}
					$removed[ (string) $field['id_'] ] = true;
					$patches[]                         = array(
						'op'  => 'remove',
						'ids' => array( (string) $field['id_'] ),
					);
					/* translators: %s: field label. */
					$changes[] = sprintf( __( 'Remove the field “%s”', 'ai-site-assistant-autopilot' ), (string) ( $field['name'] ?? $target ) );
					break;
				case 'move_field':
					$field  = self::find( $rows, $target );
					$anchor = self::find( $rows, $op['after'] ?? ( $op['before'] ?? '' ) );
					if ( null === $field || null === $anchor || $field['id_'] === $anchor['id_'] || isset( $removed[ (string) $field['id_'] ] ) ) {
						$skip( $op, 'field_not_found', $target );
						break;
					}
					$group = array();
					foreach ( $rows as $i => $r ) {
						if ( $i > 0 && is_array( $r ) && ( $r === $field || (string) ( $r['parent'] ?? '' ) === (string) $field['id_'] ) ) {
							$group[] = $r;
						}
					}
					$patches[] = array(
						'op'  => 'remove',
						'ids' => array( (string) $field['id_'] ),
					);
					$patches[] = array(
						'op'                                  => 'insert',
						'rows'                                => $group,
						isset( $op['after'] ) ? 'after' : 'before' => (string) $anchor['id_'],
					);
					/* translators: 1: field label, 2: the other field's label. */
					$changes[] = sprintf( isset( $op['after'] ) ? __( 'Move “%1$s” after “%2$s”', 'ai-site-assistant-autopilot' ) : __( 'Move “%1$s” before “%2$s”', 'ai-site-assistant-autopilot' ), (string) ( $field['name'] ?? $target ), (string) ( $anchor['name'] ?? '' ) );
					break;
				case 'add_option':
				case 'remove_option':
				case 'rename_option':
					$field = self::find( $rows, $target );
					if ( null === $field || isset( $removed[ (string) $field['id_'] ] ) ) {
						$skip( $op, 'field_not_found', $target );
						break;
					}
					$list  = $option_list( $field );
					$value = (string) ( $op['value'] ?? '' );
					$id    = (string) $field['id_'];
					if ( 'add_option' === $name ) {
						$list[] = array( 'value' => $value );
						/* translators: 1: option, 2: field label. */
						$changes[] = sprintf( __( 'Add the option “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ), $value, (string) ( $field['name'] ?? $target ) );
					} else {
						$from  = strtolower( 'rename_option' === $name ? (string) ( $op['from'] ?? '' ) : $value );
						$found = false;
						foreach ( $list as $k => $one ) {
							if ( strtolower( (string) $one['value'] ) === $from ) {
								$found = true;
								if ( 'remove_option' === $name ) {
									unset( $list[ $k ] );
								} else {
									$list[ $k ]['value'] = $value;
								}
							}
						}
						if ( ! $found ) {
							$skip( $op, 'option_not_found', $target );
							break;
						}
						$changes[] = 'remove_option' === $name
							/* translators: 1: option, 2: field label. */
							? sprintf( __( 'Remove the option “%1$s” from “%2$s”', 'ai-site-assistant-autopilot' ), $value, (string) ( $field['name'] ?? $target ) )
							/* translators: 1: old option, 2: new option. */
							: sprintf( __( 'Rename the option “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ), (string) ( $op['from'] ?? '' ), $value );
					}
					$choices[ $id ]   = array_values( $list );
					$touched[ $id ] = true;
					break;
				default:
					// Payment, conditional logic: they need the editor's own checks and panels.
					$skip( $op, 'editor_only', $name );
			}
		}
		foreach ( $choices as $id => $list ) {
			if ( isset( $removed[ $id ] ) || ! isset( $touched[ $id ] ) ) {
				continue;
			}
			if ( array() === $list ) {
				$skipped[] = array(
					'op'     => 'remove_option',
					'reason' => 'last_option',
					'text'   => $id,
				);
				continue;
			}
			$patches[] = array(
				'op'      => 'options',
				'id'      => $id,
				'options' => $list,
			);
		}

		return array(
			'patches' => $patches,
			'changes' => array_values( array_unique( $changes ) ),
			'skipped' => $skipped,
		);
	}

	/** One plain sentence for a set_field change. */
	private static function field_change_text( string $prop, string $label, $value ): string {
		switch ( $prop ) {
			case 'label':
				/* translators: 1: old label, 2: new label. */
				return sprintf( __( 'Rename “%1$s” to “%2$s”', 'ai-site-assistant-autopilot' ), $label, (string) $value );
			case 'required':
				/* translators: %s: field label. */
				return sprintf( $value ? __( 'Make “%s” required', 'ai-site-assistant-autopilot' ) : __( 'Make “%s” optional', 'ai-site-assistant-autopilot' ), $label );
			case 'placeholder':
				/* translators: %s: field label. */
				return sprintf( __( 'Change the placeholder of “%s”', 'ai-site-assistant-autopilot' ), $label );
			case 'width':
				/* translators: %s: field label. */
				return sprintf( __( 'Change the width of “%s”', 'ai-site-assistant-autopilot' ), $label );
			default:
				/* translators: %s: field label. */
				return sprintf( __( 'Change the help text of “%s”', 'ai-site-assistant-autopilot' ), $label );
		}
	}

	/** @param array<string,mixed> $def @param array<string,mixed> $field */
	private static function applies( array $def, array $field ): bool {
		$types = $def['applies_to'] ?? null;

		return ! is_array( $types ) || array() === $types || in_array( '*', $types, true ) || in_array( (string) ( $field['type'] ?? '' ), $types, true );
	}

	/**
	 * The stored value, as the editor's catalogValue() writes it.
	 *
	 * @param array<string,mixed> $def
	 * @return mixed
	 */
	private static function catalog_value( array $def, $value, bool $is_form ) {
		$type = (string) ( $def['value_type'] ?? '' );
		if ( 'boolean_flag' === $type ) {
			return $is_form ? (bool) $value : ( $value ? '1' : '0' );
		}
		if ( 'enum' === $type ) {
			$values = $def['values'] ?? null;
			if ( is_array( $values ) ) {
				foreach ( $values as $k => $v ) {
					$stored = is_int( $k ) ? (string) $v : (string) $k;
					if ( 0 === strcasecmp( $stored, (string) $value ) || 0 === strcasecmp( (string) $v, (string) $value ) ) {
						return $stored;
					}
				}
			}

			return $value;
		}
		if ( 'integer' === $type || 'number_or_date' === $type ) {
			return (string) $value;
		}

		return $value;
	}

	/** @return array<string,array<string,mixed>> */
	private static function field_props(): array {
		return EfbFormModel::client_field_properties();
	}

	/** @return array<string,array<string,mixed>> */
	private static function form_props(): array {
		return EfbFormModel::client_form_properties();
	}
}
