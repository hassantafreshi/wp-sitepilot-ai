<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Providers\ProviderInterface;

/**
 * Translates a plain-language customization command ("make phone required",
 * "reduce the width of email", "rename the form to Contact us") into a small,
 * strictly-validated set of edit operations the browser applies to the live
 * Easy Form Builder structure.
 */
final class EfbCustomizeSkill {

	public const OPS     = array( 'set_form_name', 'set_confirmation', 'set_field', 'set_field_property', 'set_form_property', 'add_field', 'add_step', 'remove_field', 'add_option', 'remove_option', 'rename_option', 'add_logic_rule' );
	private const PROPS  = array( 'label', 'required', 'placeholder', 'width', 'help' );
	private const WIDTHS = array( 'full', 'half', 'third', 'quarter' );

	/** Most edits are a handful of ops; a longer batch is truncated and reported. */
	private const MAX_OPS = 20;
	private const CHOICE  = array( 'select', 'radio', 'checkbox', 'multiselect' );

	private ProviderInterface $provider;

	public function __construct( ProviderInterface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 * @return array<int, array<string, mixed>> Validated operations.
	 * @throws \AISAAutoPilot\Providers\ProviderException
	 */
	public function plan( string $command, array $fields ): array {
		$raw = $this->provider->generate_structured_output(
			$this->system_prompt(),
			$this->user_prompt( $command, $fields )
		);

		$ops = $raw['operations'] ?? array();

		return is_array( $ops ) ? self::validate_ops( $ops, $fields ) : array();
	}

	/**
	 * Validate a raw operations array against the strict vocabulary.
	 *
	 * @param array<mixed> $ops
	 * @return array<int, array<string, mixed>>
	 */
	public static function validate_ops( array $ops, array $fields = array() ): array {
		return self::validate_ops_verbose( $ops, $fields )['ops'];
	}

	/**
	 * Validate ops AND report why rejected ones were dropped — used for logging
	 * and honest chat replies ("signature is not supported"), so a failed action
	 * never silently pretends to succeed.
	 *
	 * `add_logic_rule` is only accepted with `$context['logic']` true (EFB's
	 * Conditional Logic add-on is active on the open form), within the EFB plan's
	 * rule limits (`tier`, `existing_rules`), and is resolved against `$fields`
	 * (with their options) and `$context['steps']`.
	 *
	 * @param array<mixed>                                                                    $ops
	 * @param array<int, array<string, mixed>>                                                $fields
	 * @param array{logic?:bool, steps?:array<int, mixed>, tier?:string, existing_rules?:int} $context
	 * @return array{ops: array<int, array<string, mixed>>, dropped: array<int, array{op:string, reason:string}>}
	 */
	public static function validate_ops_verbose( array $ops, array $fields = array(), array $context = array() ): array {
		$out         = array();
		$dropped     = array();
		$seen        = 0;
		$added_rules = 0;

		foreach ( $ops as $op ) {
			++$seen;
			if ( ! is_array( $op ) ) {
				$dropped[] = array(
					'op'     => 'unknown',
					'reason' => 'not_an_object',
				);
				continue;
			}
			$name = (string) ( $op['op'] ?? '' );
			if ( ! in_array( $name, self::OPS, true ) ) {
				$dropped[] = array(
					'op'     => '' !== $name ? $name : 'unknown',
					'reason' => 'unknown_operation',
				);
				continue;
			}

			switch ( $name ) {
				case 'set_form_name':
				case 'set_confirmation':
					$value = self::clean( (string) ( $op['value'] ?? '' ), 300 );
					if ( '' !== $value ) {
						$out[] = array(
							'op'    => $name,
							'value' => $value,
						);
					} else {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'empty_value',
						);
					}
					break;

				case 'set_field':
					$target = self::clean( (string) ( $op['target'] ?? '' ), 120 );
					$prop   = strtolower( (string) ( $op['prop'] ?? '' ) );
					if ( '' === $target || ! in_array( $prop, self::PROPS, true ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => '' === $target ? 'missing_target' : 'unknown_prop:' . $prop,
						);
						break;
					}
					$value = $op['value'] ?? '';
					if ( 'required' === $prop ) {
						$value = self::to_bool( $value );
					} elseif ( 'width' === $prop ) {
						$value = strtolower( (string) $value );
						if ( ! in_array( $value, self::WIDTHS, true ) ) {
							$dropped[] = array(
								'op'     => $name,
								'reason' => 'invalid_width:' . $value,
							);
							break;
						}
					} else {
						$value = self::clean( (string) $value, 200 );
					}
					$out[] = array(
						'op'     => 'set_field',
						'target' => $target,
						'prop'   => $prop,
						'value'  => $value,
					);
					break;

				case 'set_field_property':
					$target   = self::clean( (string) ( $op['target'] ?? '' ), 120 );
					$property = EfbFormModel::resolve_field_property( (string) ( $op['property'] ?? ( $op['prop'] ?? '' ) ) );
					if ( '' === $target || null === $property ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => '' === $target ? 'missing_target' : 'unknown_property',
						);
						break;
					}
					$definition = EfbFormModel::field_property( $property );
					if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'property_not_writable:' . $property,
						);
						break;
					}
					$field_type = self::field_type_for_target( $target, $fields );
					if ( array() !== $fields && null === $field_type ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'unknown_target:' . $target,
						);
						break;
					}
					if ( null !== $field_type
						&& ( ! self::property_applies_to_type( $definition, $field_type )
							|| self::type_has_no_control_for( $property, $field_type ) ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'property_not_available_for_type:' . $property . ':' . $field_type,
						);
						break;
					}
					$valid = self::validate_catalog_value( $op['value'] ?? null, $definition, $field_type );
					if ( ! $valid['ok'] || ( null !== $field_type && ! self::type_allows_value( $property, $field_type, $valid['value'] ) ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'invalid_property_value:' . $property,
						);
						break;
					}
					$out[] = array(
						'op'       => 'set_field_property',
						'target'   => $target,
						'property' => $property,
						'value'    => $valid['value'],
					);
					break;

				case 'set_form_property':
					$property = EfbFormModel::resolve_form_property( (string) ( $op['property'] ?? ( $op['prop'] ?? '' ) ) );
					if ( null === $property ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'unknown_property',
						);
						break;
					}
					$definition = EfbFormModel::form_property( $property );
					if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'property_not_writable:' . $property,
						);
						break;
					}
					$valid = self::validate_catalog_value( $op['value'] ?? null, $definition );
					if ( ! $valid['ok'] ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'invalid_property_value:' . $property,
						);
						break;
					}
					$out[] = array(
						'op'       => 'set_form_property',
						'property' => $property,
						'value'    => $valid['value'],
					);
					break;

				case 'add_field':
					$f     = is_array( $op['field'] ?? null ) ? $op['field'] : array();
					$type  = EfbFormModel::resolve_field_type( (string) ( $f['type'] ?? '' ) );
					$label = self::clean( (string) ( $f['label'] ?? '' ), 120 );
					if ( '' === $label ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'missing_label',
						);
						break;
					}
					if ( null === $type ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'unsupported_type:' . trim( (string) ( $f['type'] ?? '' ) ),
						);
						break;
					}
					if ( 'steps' === $type ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'step_requires_add_step',
						);
						break;
					}
					if ( in_array( $type, EfbFormModel::forbidden_types(), true ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'forbidden_type:' . $type,
						);
						break;
					}
					if ( ! in_array( $type, EfbFormModel::addable_types(), true ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'recognized_but_not_creatable:' . $type,
						);
						break;
					}
					$width   = strtolower( (string) ( $f['width'] ?? 'full' ) );
					$options = self::clean_options( $f['options'] ?? array() );
					if ( ( in_array( $type, self::CHOICE, true ) || in_array( $type, EfbFormModel::option_types(), true ) ) && array() === $options ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'choice_needs_options:' . $type,
						);
						break;
					}
					$add      = array(
						'op'    => 'add_field',
						'field' => array(
							'type'        => $type,
							'label'       => $label,
							'required'    => self::to_bool( $f['required'] ?? false ),
							'placeholder' => self::clean( (string) ( $f['placeholder'] ?? '' ), 120 ),
							'help'        => self::clean( (string) ( $f['help'] ?? '' ), 200 ),
							'width'       => in_array( $width, self::WIDTHS, true ) ? $width : 'full',
							'options'     => $options,
						),
					);
					$step_ref = self::clean_step_ref( (string) ( $op['step_ref'] ?? '' ) );
					if ( '' !== $step_ref ) {
						$add['step_ref'] = $step_ref;
					}
					$out[] = $add;
					break;

				case 'add_step':
					$label = self::clean( (string) ( $op['label'] ?? ( $op['value'] ?? '' ) ), 120 );
					if ( '' === $label ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'missing_label',
						);
						break;
					}
					$step     = array(
						'op'    => 'add_step',
						'label' => $label,
					);
					$step_ref = self::clean_step_ref( (string) ( $op['step_ref'] ?? '' ) );
					if ( '' !== $step_ref ) {
						$step['step_ref'] = $step_ref;
					}
					$out[] = $step;
					break;

				case 'remove_field':
					$target = self::clean( (string) ( $op['target'] ?? '' ), 120 );
					if ( '' !== $target ) {
						$out[] = array(
							'op'     => 'remove_field',
							'target' => $target,
						);
					} else {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'missing_target',
						);
					}
					break;

				case 'add_option':
				case 'remove_option':
					$target = self::clean( (string) ( $op['target'] ?? '' ), 120 );
					$value  = self::clean( (string) ( $op['value'] ?? '' ), 120 );
					if ( '' !== $target && '' !== $value ) {
						$out[] = array(
							'op'     => $name,
							'target' => $target,
							'value'  => $value,
						);
					} else {
						$dropped[] = array(
							'op'     => $name,
							'reason' => '' === $target ? 'missing_target' : 'empty_value',
						);
					}
					break;

				case 'rename_option':
					$target = self::clean( (string) ( $op['target'] ?? '' ), 120 );
					$from   = self::clean( (string) ( $op['from'] ?? '' ), 120 );
					$to     = self::clean( (string) ( $op['value'] ?? ( $op['to'] ?? '' ) ), 120 );
					if ( '' === $target || '' === $from || '' === $to || $from === $to ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => '' === $target ? 'missing_target' : ( $from === $to && '' !== $to ? 'no_change' : 'empty_value' ),
						);
						break;
					}
					$out[] = array(
						'op'     => 'rename_option',
						'target' => $target,
						'from'   => $from,
						'value'  => $to,
					);
					break;

				case 'add_logic_rule':
					if ( empty( $context['logic'] ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'logic_addon_inactive',
						);
						break;
					}
					$built = EfbLogicRules::build( $op['rule'] ?? null, $fields, is_array( $context['steps'] ?? null ) ? $context['steps'] : array() );
					if ( empty( $built['ok'] ) || ! isset( $built['rule'] ) ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => 'invalid_logic_rule:' . (string) ( $built['error'] ?? 'unknown' ),
						);
						break;
					}
					$refusal = EfbLogicRules::plan_refusal(
						(string) ( $context['tier'] ?? 'pro' ),
						(int) ( $context['existing_rules'] ?? 0 ) + $added_rules,
						count( $built['rule']['conditions']['items'] )
					);
					if ( '' !== $refusal ) {
						$dropped[] = array(
							'op'     => $name,
							'reason' => $refusal,
						);
						break;
					}
					++$added_rules;
					$out[] = array(
						'op'      => 'add_logic_rule',
						'rule'    => $built['rule'],
						'summary' => (string) ( $built['summary'] ?? '' ),
					);
					break;
			}

			// Stopping at the cap must be reported: the caller turns `dropped` into
			// an honest reply, so a silent break would claim the skipped edits ran.
			if ( count( $out ) >= self::MAX_OPS ) {
				$skipped = count( $ops ) - $seen;
				if ( $skipped > 0 ) {
					$dropped[] = array(
						'op'     => 'batch',
						'reason' => 'operation_limit_reached:' . self::MAX_OPS . ':' . $skipped . '_skipped',
					);
				}
				break;
			}
		}

		return array(
			'ops'     => $out,
			'dropped' => $dropped,
		);
	}

	private function system_prompt(): string {
		$capabilities = $this->customization_capabilities_for_prompt();

		return implode(
			"\n",
			array(
				'You convert a WordPress admin\'s plain-language request into edit operations for an existing form.',
				'Respond with ONLY a JSON object: { "operations": [ ... ] }. No prose, no code fences.',
				'',
				'Allowed operations (use the field\'s exact label or id from the provided list as "target"):',
				'- { "op": "set_form_name", "value": "New form title" }',
				'- { "op": "set_confirmation", "value": "Thank-you message shown after submit" }',
				'- { "op": "set_field", "target": "<field label or id>", "prop": "label|required|placeholder|width|help", "value": <string, or true/false for required, or one of full|half|third|quarter for width> }',
				'- { "op": "set_field_property", "target": "<field label or id>", "property": "<one supported FIELD_PROPERTIES key>", "value": <value matching that property> }',
				'- { "op": "set_form_property", "property": "<one supported FORM_PROPERTIES key>", "value": <value matching that property> }',
				'- { "op": "add_step", "label": "Step title", "step_ref": "optional_key" }',
				'- { "op": "add_field", "step_ref": "optional_key", "field": { "type": "...", "label": "..." } }',
				'- { "op": "remove_field", "target": "<field label or id>" }',
				'- { "op": "add_option", "target": "<choice field label or id>", "value": "Option text" }',
				'- { "op": "remove_option", "target": "<choice field label or id>", "value": "Option text" }',
				'- { "op": "rename_option", "target": "<choice field label or id>", "from": "Current option text", "value": "New option text" }',
				'',
				'Rules:',
				'- Only emit operations that clearly match the request. If nothing matches, return {"operations":[]}.',
				'- Never invent fields that are not in the provided list (except values for set_field/add_option).',
				'- "width": full=100%, half=50%, third=33%, quarter=25%.',
				'- Do not emit operations for add-ons, credentials, payment settings, raw HTML, URLs, or conditional logic. Explain those as help instead.',
				'- A Step is structural. Always emit add_step for a step; never use heading as a substitute.',
				'',
				"{$capabilities}",
			)
		);
	}

	/**
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 */
	private function user_prompt( string $command, array $fields ): string {
		$list = array();
		foreach ( $fields as $f ) {
			$list[] = sprintf( '- id="%s" label="%s" type=%s', $f['id'] ?? '', $f['label'] ?? '', $f['type'] ?? '' );
		}
		$fields_text = implode( "\n", $list );
		$command     = trim( $command );

		return "Current fields:\n{$fields_text}\n\nAdmin request (untrusted):\n<<<REQ\n{$command}\nREQ;";
	}

	private static function clean( string $v, int $max ): string {
		$v = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $v ) ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	private static function to_bool( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}

		return in_array( strtolower( (string) $v ), array( 'true', '1', 'yes', 'required' ), true );
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	private static function clean_options( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $option ) {
			if ( is_array( $option ) ) {
				$option = $option['label'] ?? ( $option['value'] ?? '' );
			}
			$clean = self::clean( (string) $option, 80 );
			if ( '' !== $clean && ! in_array( $clean, $out, true ) ) {
				$out[] = $clean;
			}
		}

		return array_slice( $out, 0, 30 );
	}

	private static function clean_step_ref( string $value ): string {
		$value = strtolower( self::clean( $value, 40 ) );

		return (bool) preg_match( '/^[a-z][a-z0-9_-]*$/', $value ) ? $value : '';
	}

	private function customization_capabilities_for_prompt(): string {
		return EfbFormModel::customization_capabilities_for_prompt();
	}

	/**
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 */
	private static function field_type_for_target( string $target, array $fields ): ?string {
		$needle = strtolower( $target );
		foreach ( $fields as $field ) {
			$id    = strtolower( (string) ( $field['id'] ?? '' ) );
			$label = strtolower( (string) ( $field['label'] ?? '' ) );
			if ( $needle === $id || $needle === $label ) {
				return (string) ( $field['type'] ?? '' );
			}
		}

		return null;
	}

	/**
	 * Resolve a plan's per-field settings into the exact valj_efb keys EFB stores,
	 * dropping anything the type does not support. Shares one validation authority
	 * with set_field_property so a setting means the same thing whether it arrives
	 * when the form is designed or when it is edited afterwards.
	 *
	 * Pro-gated settings are skipped: form creation has no licence context, and a
	 * new form must never depend on a padlocked control.
	 *
	 * @param array<string,mixed> $settings Raw property => value pairs from the plan.
	 * @return array<string,mixed> Storage key => stored value.
	 */
	public static function settings_for_new_field( array $settings, string $type ): array {
		$out = array();

		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			$property = EfbFormModel::resolve_field_property( (string) $key );
			if ( null === $property ) {
				continue;
			}
			$definition = EfbFormModel::field_property( $property );
			if ( ! is_array( $definition ) || empty( $definition['ai_writable'] ) || empty( $definition['storage_key'] ) ) {
				continue;
			}
			if ( ! empty( $definition['requires_pro'] ) ) {
				continue;
			}
			if ( ! self::property_applies_to_type( $definition, $type ) || self::type_has_no_control_for( $property, $type ) ) {
				continue;
			}
			$valid = self::validate_catalog_value( $value, $definition, $type );
			if ( ! $valid['ok'] || ! self::type_allows_value( $property, $type, $valid['value'] ) ) {
				continue;
			}

			$stored = is_bool( $valid['value'] ) ? ( $valid['value'] ? '1' : '0' ) : (string) $valid['value'];
			if ( 'enum' === (string) ( $definition['value_type'] ?? '' ) && is_array( $definition['values'] ?? null ) && ! EfbFormModel::is_list( $definition['values'] ) ) {
				// Keyword enums store EFB's value, not the friendly key.
				$stored = (string) ( $definition['values'][ $valid['value'] ] ?? $stored );
			}

			$out[ (string) $definition['storage_key'] ] = $stored;
			foreach ( (array) ( $definition['mirror_keys'] ?? array() ) as $mirror ) {
				$out[ (string) $mirror ] = $stored;
			}
		}

		return $out;
	}

	/**
	 * Types can also narrow a shared property's value list: file_category offers
	 * "customize" on the drag-and-drop field but not on the plain upload field.
	 *
	 * @param mixed $value Already normalised by validate_catalog_value().
	 */
	private static function type_allows_value( string $property, string $type, $value ): bool {
		$definition = EfbFormModel::field_type( $type );
		$limits     = is_array( $definition ) ? ( $definition['property_values'] ?? array() ) : array();
		if ( ! is_array( $limits ) || ! isset( $limits[ $property ] ) || ! is_array( $limits[ $property ] ) ) {
			return true;
		}

		return in_array( (string) $value, array_map( 'strval', $limits[ $property ] ), true );
	}

	/**
	 * A property can be shared by every type yet still be missing from one type's
	 * settings panel — a step has no width, a range has no corner. Writing those
	 * keys would look accepted and change nothing, so the catalog lists them per
	 * type and they are refused here.
	 */
	private static function type_has_no_control_for( string $property, string $type ): bool {
		$definition = EfbFormModel::field_type( $type );
		$blocked    = is_array( $definition ) ? ( $definition['unsupported_properties'] ?? array() ) : array();

		return is_array( $blocked ) && in_array( $property, $blocked, true );
	}

	/** @param array<string, mixed> $definition */
	private static function property_applies_to_type( array $definition, string $type ): bool {
		$types = $definition['applies_to'] ?? array( '*' );
		if ( ! is_array( $types ) || array() === $types || in_array( '*', $types, true ) ) {
			return true;
		}

		return in_array( $type, $types, true );
	}

	/** Types whose milen/mlen hold a date rather than a length or a slider stop. */
	private const DATE_TYPES = array( 'date', 'pdate', 'ardate' );

	/**
	 * @param mixed                $raw
	 * @param array<string, mixed> $definition
	 * @param string|null          $field_type Target field type, when the op targets a field.
	 * @return array{ok:bool,value:mixed}
	 */
	private static function validate_catalog_value( $raw, array $definition, ?string $field_type = null ): array {
		$type = (string) ( $definition['value_type'] ?? 'string' );
		if ( 'boolean_flag' === $type ) {
			return array(
				'ok'    => true,
				'value' => self::to_bool( $raw ),
			);
		}

		if ( 'integer' === $type ) {
			if ( filter_var( $raw, FILTER_VALIDATE_INT ) === false ) {
				return array(
					'ok'    => false,
					'value' => null,
				);
			}
			$value = (int) $raw;
			if ( isset( $definition['minimum'] ) && $value < (int) $definition['minimum'] ) {
				return array(
					'ok'    => false,
					'value' => null,
				);
			}
			if ( isset( $definition['maximum'] ) && $value > (int) $definition['maximum'] ) {
				return array(
					'ok'    => false,
					'value' => null,
				);
			}

			return array(
				'ok'    => true,
				'value' => $value,
			);
		}

		if ( 'number_or_date' === $type ) {
			$value = self::clean( (string) $raw, 30 );

			// milen/mlen carry a length on text types and a bound date on the date
			// pickers. EFB resolves the literal 1 to today and feeds anything else
			// to strtotime, so a bare number there would silently bound nothing.
			$pattern = null !== $field_type && in_array( $field_type, self::DATE_TYPES, true )
				? '/^(?:1|\d{4}-\d{2}-\d{2})$/'
				: '/^\d{1,6}$/';

			if ( ! preg_match( $pattern, $value ) ) {
				return array(
					'ok'    => false,
					'value' => null,
				);
			}

			return array(
				'ok'    => true,
				'value' => $value,
			);
		}

		if ( 'hex_color' === $type ) {
			$value = strtolower( self::clean( (string) $raw, 7 ) );

			return array(
				'ok'    => (bool) preg_match( '/^#[a-f0-9]{6}$/', $value ),
				'value' => $value,
			);
		}

		if ( 'efb_color' === $type ) {
			$value = self::normalize_efb_color( $raw );

			return array(
				'ok'    => null !== $value,
				'value' => $value,
			);
		}

		if ( 'css_classes' === $type ) {
			$value = self::clean( (string) $raw, (int) ( $definition['max_length'] ?? 200 ) );

			return array(
				'ok'    => '' !== $value && (bool) preg_match( '/^[A-Za-z0-9_\-\s]+$/', $value ),
				'value' => $value,
			);
		}

		if ( 'enum' === $type ) {
			$value  = strtolower( self::clean( (string) $raw, 100 ) );
			$values = $definition['values'] ?? array();
			if ( ! is_array( $values ) ) {
				return array(
					'ok'    => false,
					'value' => null,
				);
			}
			if ( EfbFormModel::is_list( $values ) ) {
				foreach ( $values as $allowed ) {
					if ( strtolower( (string) $allowed ) === $value ) {
						return array(
							'ok'    => true,
							'value' => (string) $allowed,
						);
					}
				}
			} else {
				foreach ( $values as $key => $stored ) {
					if ( strtolower( (string) $key ) === $value || strtolower( (string) $stored ) === $value ) {
						return array(
							'ok'    => true,
							'value' => (string) $key,
						);
					}
				}
			}

			return array(
				'ok'    => false,
				'value' => null,
			);
		}

		$max   = (int) ( $definition['max_length'] ?? 500 );
		$value = self::clean( (string) $raw, $max );

		return array(
			'ok'    => '' !== $value,
			'value' => $value,
		);
	}

	/**
	 * Convert a named or hex colour into the six-digit format that the browser
	 * adapter maps to EFB's `btn-*` / `text-*` tokens. Accepting common Persian
	 * names keeps a request such as "دکمه قرمز با متن سفید" actionable.
	 *
	 * @param mixed $raw
	 */
	private static function normalize_efb_color( $raw ): ?string {
		$value = strtolower( trim( self::clean( (string) $raw, 50 ) ) );
		if ( (bool) preg_match( '/^#[a-f0-9]{6}$/', $value ) ) {
			return $value;
		}

		$colors = array(
			'red'     => '#ff455f',
			'قرمز'    => '#ff455f',
			'green'   => '#198754',
			'سبز'     => '#198754',
			'blue'    => '#0d6efd',
			'آبی'     => '#0d6efd',
			'ابي'     => '#0d6efd',
			'yellow'  => '#e9c31a',
			'زرد'     => '#e9c31a',
			'white'   => '#ffffff',
			'سفید'    => '#ffffff',
			'سفيد'    => '#ffffff',
			'black'   => '#212529',
			'مشکی'    => '#212529',
			'مشکي'    => '#212529',
			'gray'    => '#6c757d',
			'grey'    => '#6c757d',
			'خاکستری' => '#6c757d',
			'خاکستري' => '#6c757d',
			'pink'    => '#ff4b93',
			'صورتی'   => '#ff4b93',
			'صورتي'   => '#ff4b93',
			'purple'  => '#6f42c1',
			'بنفش'    => '#6f42c1',
			'orange'  => '#fd7e14',
			'نارنجی'  => '#fd7e14',
			'نارنجي'  => '#fd7e14',
		);

		return $colors[ $value ] ?? null;
	}
}
