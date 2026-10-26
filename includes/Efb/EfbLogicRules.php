<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Conditional logic proposals for Easy Form Builder's Conditional Logic add-on
 * (AdnSMF), and plain-language explanations of rules a form already has.
 *
 * Scope is deliberately the field-level subset of EFB's `logic_rules`: show or
 * hide a field or step, make a field required or optional, and jump to a step.
 * Notification, confirmation, webhook and pricing rules, value writes and
 * calculations are never proposed — the catalog records a gating debt on those
 * EFB paths. Every reference is resolved against the open form; anything that
 * does not exist is rejected, never guessed. EFB's own sanitizer validates the
 * rule again when the admin saves.
 *
 * Shapes and limits mirror the add-on itself (vendores-efb, logic/): its
 * builder creates `{id: rule_xxxxxxxxx, scope: field, enabled, priority: 10,
 * stop_processing: false}` rules; its runtime and PHP validator start every
 * show_field / show_step target hidden and re-evaluate from those defaults,
 * so "show X only when…" is one rule; Free cannot add rules and Free Plus is
 * capped at three field rules of two conditions each.
 *
 * Pure PHP so it is unit testable.
 */
final class EfbLogicRules {

	public const FIELD_ACTIONS = array( 'show_field', 'hide_field', 'set_required', 'set_optional' );
	public const STEP_ACTIONS  = array( 'show_step', 'hide_step', 'jump_to_step' );

	/** Compares that need no value. */
	private const VALUELESS = array( 'is_empty', 'is_not_empty' );

	public const COMPARES = array( 'is', 'is_not', 'contains', 'not_contains', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' );

	/** Compares that only make sense on numbers. */
	private const NUMERIC = array( 'gt', 'gte', 'lt', 'lte' );

	/** The add-on builder's FREE_PLUS_LIMITS for the field tab. */
	public const FREE_PLUS_RULES      = 3;
	public const FREE_PLUS_CONDITIONS = 2;

	private const CHOICE = array( 'select', 'radio', 'checkbox', 'multiselect', 'chlCheckBox', 'chlRadio', 'imgRadio', 'yesNo', 'switch' );

	private const MAX_CONDITIONS = 6;
	private const MAX_ACTIONS    = 8;

	/**
	 * Turn one AI rule into EFB's stored rule shape.
	 *
	 * AI shape: { "name": "...", "match": "all"|"any",
	 *             "when": [ { "field": "<label or id>", "compare": "is", "value": "Business" } ],
	 *             "then": [ { "action": "show_field", "target": "<label, id or step>" } ] }
	 *
	 * @param mixed                            $raw
	 * @param array<int, array<string, mixed>> $fields Each: id, label, type, options? (list of {id, value}).
	 * @param array<int, array<string, mixed>> $steps  Each: id, label.
	 * @return array{ok:bool, rule?:array<string,mixed>, summary?:string, error?:string}
	 */
	public static function build( $raw, array $fields, array $steps ): array {
		if ( ! is_array( $raw ) ) {
			return self::fail( 'rule_not_an_object' );
		}

		$when = is_array( $raw['when'] ?? null ) ? array_values( $raw['when'] ) : array();
		$then = is_array( $raw['then'] ?? null ) ? array_values( $raw['then'] ) : array();
		if ( array() === $when || array() === $then ) {
			return self::fail( 'rule_needs_condition_and_action' );
		}
		if ( count( $when ) > self::MAX_CONDITIONS || count( $then ) > self::MAX_ACTIONS ) {
			return self::fail( 'rule_too_large' );
		}

		$items   = array();
		$sources = array();
		foreach ( $when as $condition ) {
			$built = self::condition( $condition, $fields );
			if ( isset( $built['error'] ) ) {
				return self::fail( $built['error'] );
			}
			$items[]                               = $built['item'];
			$sources[ $built['item']['field_id'] ] = true;
		}

		$actions = array();
		$seen    = array();
		foreach ( $then as $action ) {
			$built = self::action( $action, $fields, $steps );
			if ( isset( $built['error'] ) ) {
				return self::fail( $built['error'] );
			}
			$act = $built['action'];
			// A field that hides or requires itself loops or locks the visitor out.
			if ( in_array( $act['type'], self::FIELD_ACTIONS, true ) && isset( $sources[ $act['target'] ] ) ) {
				return self::fail( 'action_targets_its_own_condition:' . $act['target'] );
			}
			$signature = $act['type'] . '|' . $act['target'];
			if ( ! isset( $seen[ $signature ] ) ) {
				$seen[ $signature ] = true;
				$actions[]          = $act;
			}
		}

		$contradiction = self::contradiction( $actions );
		if ( null !== $contradiction ) {
			return self::fail( 'contradictory_actions:' . $contradiction );
		}

		$operator = in_array( strtolower( (string) ( $raw['match'] ?? 'all' ) ), array( 'any', 'or' ), true ) ? 'OR' : 'AND';
		$name     = self::clean( (string) ( $raw['name'] ?? '' ), 80 );
		$rule     = array(
			// Same id shape as the add-on's own _id('rule'): rule_ + 9 characters.
			'id'              => 'rule_' . substr( md5( (string) wp_json_encode( array( $items, $actions ) ) ), 0, 9 ),
			'name'            => '' !== $name ? $name : 'AI rule',
			// Step actions live in the same Fields tab as field actions.
			'scope'           => 'field',
			'enabled'         => true,
			'priority'        => 10,
			'stop_processing' => false,
			'conditions'      => array(
				'type'     => 'group',
				'operator' => $operator,
				'items'    => $items,
			),
			'actions'         => $actions,
		);

		return array(
			'ok'      => true,
			'rule'    => $rule,
			'summary' => self::sentence( $rule, $fields, $steps ),
		);
	}

	/**
	 * Whether the EFB plan lets one more rule with this many conditions in,
	 * by the add-on's own limits.
	 *
	 * @param string $tier            EfbLicense tier: pro, free_plus or free.
	 * @param int    $existing_rules  Field rules the form already has (plus any added earlier in this batch).
	 * @param int    $conditions      Conditions in the new rule.
	 * @return string Empty when allowed, otherwise the refusal reason.
	 */
	public static function plan_refusal( string $tier, int $existing_rules, int $conditions ): string {
		if ( 'pro' === $tier ) {
			return '';
		}
		if ( 'free_plus' !== $tier ) {
			return 'logic_needs_plan';
		}
		if ( $existing_rules >= self::FREE_PLUS_RULES ) {
			return 'logic_rule_limit:' . self::FREE_PLUS_RULES;
		}
		if ( $conditions > self::FREE_PLUS_CONDITIONS ) {
			return 'logic_condition_limit:' . self::FREE_PLUS_CONDITIONS;
		}

		return '';
	}

	/**
	 * Plain-language lines for the rules a form already has (read-only).
	 *
	 * @param array<mixed>                     $rules  The form's logic_rules.
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<int, array<string, mixed>> $steps
	 * @return string[]
	 */
	public static function explain( array $rules, array $fields, array $steps ): array {
		$lines = array();
		foreach ( array_slice( $rules, 0, 30 ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$line = self::sentence( $rule, $fields, $steps );
			if ( isset( $rule['enabled'] ) && false === (bool) $rule['enabled'] ) {
				/* translators: %s: rule description. */
				$line = sprintf( __( '(off) %s', 'ai-site-assistant-autopilot' ), $line );
			}
			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Same target driven both ways by rules that fire on identical conditions.
	 * The last one silently wins in EFB, which is almost never what was meant.
	 *
	 * @param array<mixed> $rules
	 * @return string[] Targets in conflict.
	 */
	public static function conflicts( array $rules ): array {
		$by_condition = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ( isset( $rule['enabled'] ) && ! $rule['enabled'] ) ) {
				continue;
			}
			$key = md5( (string) wp_json_encode( $rule['conditions'] ?? array() ) );
			foreach ( (array) ( $rule['actions'] ?? array() ) as $action ) {
				if ( is_array( $action ) ) {
					$by_condition[ $key ][] = array(
						'type'   => (string) ( $action['type'] ?? '' ),
						'target' => (string) ( $action['target'] ?? '' ),
					);
				}
			}
		}

		$out = array();
		foreach ( $by_condition as $actions ) {
			$target = self::contradiction( $actions );
			if ( null !== $target ) {
				$out[] = $target;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed                            $raw
	 * @param array<int, array<string, mixed>> $fields
	 * @return array{item?:array<string,mixed>, error?:string}
	 */
	private static function condition( $raw, array $fields ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'error' => 'condition_not_an_object' );
		}
		$field = self::find_field( (string) ( $raw['field'] ?? ( $raw['field_id'] ?? '' ) ), $fields );
		if ( null === $field ) {
			return array( 'error' => 'unknown_condition_field:' . self::clean( (string) ( $raw['field'] ?? '' ), 60 ) );
		}

		$compare = strtolower( trim( (string) ( $raw['compare'] ?? 'is' ) ) );
		$compare = array(
			'equals'       => 'is',
			'=='           => 'is',
			'='            => 'is',
			'not_equals'   => 'is_not',
			'!='           => 'is_not',
			'>'            => 'gt',
			'>='           => 'gte',
			'<'            => 'lt',
			'<='           => 'lte',
			'empty'        => 'is_empty',
			'not_empty'    => 'is_not_empty',
			'is_filled'    => 'is_not_empty',
			'is_answered'  => 'is_not_empty',
			'not_answered' => 'is_empty',
		)[ $compare ] ?? $compare;
		if ( ! in_array( $compare, self::COMPARES, true ) ) {
			return array( 'error' => 'unsupported_compare:' . $compare );
		}

		$value = '';
		if ( ! in_array( $compare, self::VALUELESS, true ) ) {
			$value = self::clean( is_scalar( $raw['value'] ?? null ) ? (string) $raw['value'] : '', 120 );
			if ( '' === $value ) {
				return array( 'error' => 'condition_needs_value:' . $field['id'] );
			}
			if ( in_array( $compare, self::NUMERIC, true ) && ! is_numeric( $value ) ) {
				return array( 'error' => 'numeric_compare_needs_number:' . $field['id'] );
			}
			// EFB's builder stores a choice condition as the option's id_; the
			// visible text alone would never match a select or multiselect row.
			$options = is_array( $field['options'] ?? null ) ? $field['options'] : array();
			if ( in_array( (string) $field['type'], self::CHOICE, true ) && array() !== $options ) {
				$option = self::find_option( $value, $options );
				if ( null === $option ) {
					return array( 'error' => 'unknown_option:' . $field['id'] . ':' . $value );
				}
				$value = $option;
			}
		}

		return array(
			'item' => array(
				'type'     => 'condition',
				'source'   => 'field',
				'field_id' => (string) $field['id'],
				'compare'  => $compare,
				'value'    => $value,
			),
		);
	}

	/**
	 * @param mixed                            $raw
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<int, array<string, mixed>> $steps
	 * @return array{action?:array{type:string,target:string}, error?:string}
	 */
	private static function action( $raw, array $fields, array $steps ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'error' => 'action_not_an_object' );
		}
		$type   = strtolower( trim( (string) ( $raw['action'] ?? ( $raw['type'] ?? '' ) ) ) );
		$type   = array(
			'show'     => 'show_field',
			'hide'     => 'hide_field',
			'require'  => 'set_required',
			'required' => 'set_required',
			'optional' => 'set_optional',
			'jump'     => 'jump_to_step',
			'go_to'    => 'jump_to_step',
			'skip_to'  => 'jump_to_step',
		)[ $type ] ?? $type;
		$target = (string) ( $raw['target'] ?? '' );

		if ( in_array( $type, self::FIELD_ACTIONS, true ) ) {
			$field = self::find_field( $target, $fields );
			if ( null === $field ) {
				return array( 'error' => 'unknown_action_target:' . self::clean( $target, 60 ) );
			}

			return array(
				'action' => array(
					'type'   => $type,
					'target' => (string) $field['id'],
				),
			);
		}

		if ( in_array( $type, self::STEP_ACTIONS, true ) ) {
			$step = self::find_step( $target, $steps );
			if ( null === $step ) {
				return array( 'error' => 'unknown_step:' . self::clean( $target, 60 ) );
			}

			return array(
				'action' => array(
					'type'   => $type,
					'target' => (string) $step['id'],
				),
			);
		}

		return array( 'error' => 'unsupported_action:' . self::clean( $type, 40 ) );
	}

	/**
	 * @param array<int, array{type:string,target:string}> $actions
	 */
	private static function contradiction( array $actions ): ?string {
		$opposites = array(
			'show_field'   => 'hide_field',
			'set_required' => 'set_optional',
			'show_step'    => 'hide_step',
		);
		$present   = array();
		foreach ( $actions as $action ) {
			$present[ $action['type'] . '|' . $action['target'] ] = true;
		}
		foreach ( $actions as $action ) {
			$opposite = $opposites[ $action['type'] ] ?? null;
			if ( null !== $opposite && isset( $present[ $opposite . '|' . $action['target'] ] ) ) {
				return $action['target'];
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed>              $rule
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<int, array<string, mixed>> $steps
	 */
	private static function sentence( array $rule, array $fields, array $steps ): string {
		$group    = is_array( $rule['conditions'] ?? null ) ? $rule['conditions'] : array();
		$joiner   = 'OR' === ( $group['operator'] ?? 'AND' ) ? __( ' or ', 'ai-site-assistant-autopilot' ) : __( ' and ', 'ai-site-assistant-autopilot' );
		$parts    = array();
		$compares = array(
			/* translators: 1: field label, 2: value. */
			'is'           => __( '“%1$s” is “%2$s”', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'is_not'       => __( '“%1$s” is not “%2$s”', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'contains'     => __( '“%1$s” contains “%2$s”', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'not_contains' => __( '“%1$s” does not contain “%2$s”', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'gt'           => __( '“%1$s” is more than %2$s', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'gte'          => __( '“%1$s” is at least %2$s', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'lt'           => __( '“%1$s” is less than %2$s', 'ai-site-assistant-autopilot' ),
			/* translators: 1: field label, 2: value. */
			'lte'          => __( '“%1$s” is at most %2$s', 'ai-site-assistant-autopilot' ),
			/* translators: %1$s: field label. */
			'is_empty'     => __( '“%1$s” is empty', 'ai-site-assistant-autopilot' ),
			/* translators: %1$s: field label. */
			'is_not_empty' => __( '“%1$s” is answered', 'ai-site-assistant-autopilot' ),
		);
		foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || 'condition' !== ( $item['type'] ?? 'condition' ) ) {
				$parts[] = __( '(a nested group)', 'ai-site-assistant-autopilot' );
				continue;
			}
			$field   = self::find_field( (string) ( $item['field_id'] ?? '' ), $fields );
			$label   = null !== $field ? (string) $field['label'] : (string) ( $item['field_id'] ?? '?' );
			$value   = is_array( $item['value'] ?? null ) ? implode( ', ', array_map( 'strval', $item['value'] ) ) : (string) ( $item['value'] ?? '' );
			$value   = null !== $field ? self::option_text( $value, $field ) : $value;
			$compare = (string) ( $item['compare'] ?? 'is' );
			$parts[] = isset( $compares[ $compare ] )
				? sprintf( $compares[ $compare ], $label, $value )
				: sprintf( '“%s” %s “%s”', $label, $compare, $value );
		}

		$verbs   = array(
			/* translators: %s: field label. */
			'show_field'   => __( 'show “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: field label. */
			'hide_field'   => __( 'hide “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: field label. */
			'set_required' => __( 'make “%s” required', 'ai-site-assistant-autopilot' ),
			/* translators: %s: field label. */
			'set_optional' => __( 'make “%s” optional', 'ai-site-assistant-autopilot' ),
			/* translators: %s: step name. */
			'show_step'    => __( 'show step “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: step name. */
			'hide_step'    => __( 'hide step “%s”', 'ai-site-assistant-autopilot' ),
			/* translators: %s: step name. */
			'jump_to_step' => __( 'jump to step “%s”', 'ai-site-assistant-autopilot' ),
		);
		$actions = array();
		foreach ( (array) ( $rule['actions'] ?? array() ) as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type   = (string) ( $action['type'] ?? '' );
			$target = (string) ( $action['target'] ?? '' );
			$named  = in_array( $type, self::STEP_ACTIONS, true ) ? self::find_step( $target, $steps ) : self::find_field( $target, $fields );
			$name   = null !== $named && '' !== (string) ( $named['label'] ?? '' ) ? (string) $named['label'] : $target;
			/* translators: 1: action name, 2: target. */
			$actions[] = isset( $verbs[ $type ] ) ? sprintf( $verbs[ $type ], $name ) : sprintf( __( '%1$s “%2$s”', 'ai-site-assistant-autopilot' ), str_replace( '_', ' ', $type ), $name );
		}

		/* translators: 1: conditions, 2: actions. */
		return sprintf( __( 'If %1$s, %2$s.', 'ai-site-assistant-autopilot' ), implode( $joiner, $parts ), implode( __( ', ', 'ai-site-assistant-autopilot' ), $actions ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, mixed>|null
	 */
	private static function find_field( string $needle, array $fields ): ?array {
		$needle = self::fold( $needle );
		if ( '' === $needle ) {
			return null;
		}
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && self::fold( (string) ( $field['id'] ?? '' ) ) === $needle ) {
				return $field;
			}
		}
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && self::fold( (string) ( $field['label'] ?? '' ) ) === $needle ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * A step by its id, its number in the form ("2", "step 2") or its name.
	 *
	 * @param array<int, array<string, mixed>> $steps
	 * @return array<string, mixed>|null
	 */
	private static function find_step( string $needle, array $steps ): ?array {
		$folded = self::fold( $needle );
		if ( '' === $folded ) {
			return null;
		}
		$number = preg_match( '/^(?:step|page)?\s*#?\s*(\d+)$/u', $folded, $m ) ? (int) $m[1] : 0;
		foreach ( array_values( $steps ) as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			if ( self::fold( (string) ( $step['id'] ?? '' ) ) === $folded || self::fold( (string) ( $step['label'] ?? '' ) ) === $folded
				|| ( $number > 0 && $number === $index + 1 ) ) {
				return $step;
			}
		}

		return null;
	}

	/**
	 * @param array<int, mixed> $options Each {id, value}.
	 */
	private static function find_option( string $needle, array $options ): ?string {
		$folded = self::fold( $needle );
		foreach ( $options as $option ) {
			if ( is_array( $option ) && ( self::fold( (string) ( $option['value'] ?? '' ) ) === $folded || self::fold( (string) ( $option['id'] ?? '' ) ) === $folded ) ) {
				return (string) ( $option['id'] ?? '' );
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private static function option_text( string $value, array $field ): string {
		foreach ( is_array( $field['options'] ?? null ) ? $field['options'] : array() as $option ) {
			if ( is_array( $option ) && (string) ( $option['id'] ?? '' ) === $value ) {
				return (string) ( $option['value'] ?? $value );
			}
		}

		return $value;
	}

	private static function fold( string $value ): string {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	private static function clean( string $value, int $max ): string {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * @return array{ok:false, error:string}
	 */
	private static function fail( string $error ): array {
		return array(
			'ok'    => false,
			'error' => $error,
		);
	}
}
