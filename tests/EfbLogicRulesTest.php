<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbLogicRules;
use PHPUnit\Framework\TestCase;

/**
 * T74: conditional logic proposals for EFB's Conditional Logic add-on.
 */
final class EfbLogicRulesTest extends TestCase {

	/** @return array<int, array<string, mixed>> */
	private function fields(): array {
		return array(
			array(
				'id'      => 'ctype',
				'label'   => 'Customer type',
				'type'    => 'radio',
				'options' => array(
					array(
						'id'    => 'ctype_o1',
						'value' => 'Business',
					),
					array(
						'id'    => 'ctype_o2',
						'value' => 'Individual',
					),
				),
			),
			array(
				'id'    => 'company',
				'label' => 'Company name',
				'type'  => 'text',
			),
			array(
				'id'    => 'team',
				'label' => 'Team size',
				'type'  => 'number',
			),
		);
	}

	/** @return array<int, array<string, string>> */
	private function steps(): array {
		return array(
			array(
				'id'    => '1',
				'label' => 'About you',
			),
			array(
				'id'    => '2',
				'label' => 'Company details',
			),
		);
	}

	public function test_a_choice_condition_is_stored_as_the_option_id(): void {
		$built = EfbLogicRules::build(
			array(
				'name' => 'Company only for businesses',
				'when' => array(
					array(
						'field'   => 'Customer type',
						'compare' => 'is',
						'value'   => 'business',
					),
				),
				'then' => array(
					array(
						'action' => 'show_field',
						'target' => 'Company name',
					),
				),
			),
			$this->fields(),
			$this->steps()
		);

		$this->assertTrue( $built['ok'] );
		$rule = $built['rule'];
		// The add-on builder's own shape: rule_ + 9 characters, Fields tab scope.
		$this->assertMatchesRegularExpression( '/^rule_[a-z0-9]{9}$/', $rule['id'] );
		$this->assertSame( 'field', $rule['scope'] );
		$this->assertTrue( $rule['enabled'] );
		$this->assertSame( 10, $rule['priority'] );
		$this->assertFalse( $rule['stop_processing'] );
		$this->assertSame( 'AND', $rule['conditions']['operator'] );
		$this->assertSame(
			array(
				'type'     => 'condition',
				'source'   => 'field',
				'field_id' => 'ctype',
				'compare'  => 'is',
				'value'    => 'ctype_o1',
			),
			$rule['conditions']['items'][0]
		);
		$this->assertSame(
			array(
				array(
					'type'   => 'show_field',
					'target' => 'company',
				),
			),
			$rule['actions']
		);
		$this->assertSame( 'If “Customer type” is “Business”, show “Company name”.', $built['summary'] );
	}

	public function test_step_actions_and_any_match_stay_in_the_fields_tab(): void {
		$built = EfbLogicRules::build(
			array(
				'name'  => 'Company details',
				'match' => 'any',
				'when'  => array(
					array(
						'field'   => 'ctype',
						'compare' => 'is',
						'value'   => 'Business',
					),
					array(
						'field'   => 'Team size',
						'compare' => '>',
						'value'   => '10',
					),
				),
				'then'  => array(
					array(
						'action' => 'require',
						'target' => 'company',
					),
					array(
						'action' => 'show_step',
						'target' => 'step 2',
					),
				),
			),
			$this->fields(),
			$this->steps()
		);

		$this->assertTrue( $built['ok'] );
		$rule = $built['rule'];
		$this->assertSame( 'field', $rule['scope'] );
		$this->assertSame( 'OR', $rule['conditions']['operator'] );
		$this->assertSame( array( 'is', 'gt' ), array_column( $rule['conditions']['items'], 'compare' ) );
		$this->assertSame( array( 'set_required', 'show_step' ), array_column( $rule['actions'], 'type' ) );
		$this->assertSame( array( 'company', '2' ), array_column( $rule['actions'], 'target' ) );
		$this->assertSame( 'If “Customer type” is “Business” or “Team size” is more than 10, make “Company name” required, show step “Company details”.', $built['summary'] );
	}

	public function test_plan_limits_follow_the_add_on(): void {
		$this->assertSame( '', EfbLogicRules::plan_refusal( 'pro', 50, 9 ) );
		$this->assertSame( 'logic_needs_plan', EfbLogicRules::plan_refusal( 'free', 0, 1 ) );
		$this->assertSame( '', EfbLogicRules::plan_refusal( 'free_plus', 2, 2 ) );
		$this->assertSame( 'logic_rule_limit:3', EfbLogicRules::plan_refusal( 'free_plus', 3, 1 ) );
		$this->assertSame( 'logic_condition_limit:2', EfbLogicRules::plan_refusal( 'free_plus', 0, 3 ) );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function invalid_rules(): array {
		$when = array(
			array(
				'field'   => 'Customer type',
				'compare' => 'is',
				'value'   => 'Business',
			),
		);
		$then = array(
			array(
				'action' => 'show_field',
				'target' => 'Company name',
			),
		);

		return array(
			'unknown field'      => array(
				array(
					'when' => array(
						array(
							'field'   => 'Budget',
							'compare' => 'is',
							'value'   => 'x',
						),
					),
					'then' => $then,
				),
				'unknown_condition_field',
			),
			'unknown option'     => array(
				array(
					'when' => array(
						array(
							'field'   => 'Customer type',
							'compare' => 'is',
							'value'   => 'Nonprofit',
						),
					),
					'then' => $then,
				),
				'unknown_option',
			),
			'self reference'     => array(
				array(
					'when' => $when,
					'then' => array(
						array(
							'action' => 'hide_field',
							'target' => 'Customer type',
						),
					),
				),
				'action_targets_its_own_condition',
			),
			'contradiction'      => array(
				array(
					'when' => $when,
					'then' => array(
						array(
							'action' => 'show_field',
							'target' => 'company',
						),
						array(
							'action' => 'hide_field',
							'target' => 'company',
						),
					),
				),
				'contradictory_actions',
			),
			'value writes'       => array(
				array(
					'when' => $when,
					'then' => array(
						array(
							'action' => 'set_value',
							'target' => 'company',
						),
					),
				),
				'unsupported_action',
			),
			'missing step'       => array(
				array(
					'when' => $when,
					'then' => array(
						array(
							'action' => 'jump_to_step',
							'target' => 'step 7',
						),
					),
				),
				'unknown_step',
			),
			'number needed'      => array(
				array(
					'when' => array(
						array(
							'field'   => 'Team size',
							'compare' => 'gt',
							'value'   => 'many',
						),
					),
					'then' => $then,
				),
				'numeric_compare_needs_number',
			),
			'no action'          => array( array( 'when' => $when ), 'rule_needs_condition_and_action' ),
			'unknown compare'    => array(
				array(
					'when' => array(
						array(
							'field'   => 'Customer type',
							'compare' => 'regex',
							'value'   => '.*',
						),
					),
					'then' => $then,
				),
				'unsupported_compare',
			),
		);
	}

	/**
	 * @dataProvider invalid_rules
	 * @param array<string, mixed> $raw
	 */
	public function test_invalid_rules_are_refused_with_a_reason( array $raw, string $error ): void {
		$built = EfbLogicRules::build( $raw, $this->fields(), $this->steps() );

		$this->assertFalse( $built['ok'] );
		$this->assertStringStartsWith( $error, $built['error'] );
	}

	public function test_conflicts_and_explanations_of_existing_rules(): void {
		$conditions = array(
			'type'     => 'group',
			'operator' => 'AND',
			'items'    => array(
				array(
					'type'     => 'condition',
					'source'   => 'field',
					'field_id' => 'ctype',
					'compare'  => 'is',
					'value'    => 'ctype_o2',
				),
			),
		);
		$rules      = array(
			array(
				'id'         => 'a',
				'conditions' => $conditions,
				'actions'    => array(
					array(
						'type'   => 'hide_field',
						'target' => 'company',
					),
				),
			),
			array(
				'id'         => 'b',
				'conditions' => $conditions,
				'actions'    => array(
					array(
						'type'   => 'show_field',
						'target' => 'company',
					),
				),
			),
			array(
				'id'         => 'c',
				'enabled'    => false,
				'conditions' => $conditions,
				'actions'    => array(
					array(
						'type'   => 'jump_to_step',
						'target' => '2',
					),
				),
			),
		);

		$this->assertSame( array( 'company' ), EfbLogicRules::conflicts( $rules ) );
		$this->assertSame( array(), EfbLogicRules::conflicts( array( $rules[0] ) ) );

		$lines = EfbLogicRules::explain( $rules, $this->fields(), $this->steps() );
		$this->assertSame( 'If “Customer type” is “Individual”, hide “Company name”.', $lines[0] );
		$this->assertSame( '(off) If “Customer type” is “Individual”, jump to step “Company details”.', $lines[2] );
	}

	public function test_logic_ops_need_the_add_on_and_are_resolved_by_the_validator(): void {
		$op = array(
			'op'   => 'add_logic_rule',
			'rule' => array(
				'when' => array(
					array(
						'field'   => 'Customer type',
						'compare' => 'is',
						'value'   => 'Business',
					),
				),
				'then' => array(
					array(
						'action' => 'show',
						'target' => 'Company name',
					),
				),
			),
		);

		$off = EfbCustomizeSkill::validate_ops_verbose( array( $op ), $this->fields() );
		$this->assertSame( array(), $off['ops'] );
		$this->assertSame( 'logic_addon_inactive', $off['dropped'][0]['reason'] );

		$on = EfbCustomizeSkill::validate_ops_verbose(
			array( $op ),
			$this->fields(),
			array(
				'logic' => true,
				'steps' => $this->steps(),
			)
		);
		$this->assertCount( 1, $on['ops'] );
		$this->assertSame( 'show_field', $on['ops'][0]['rule']['actions'][0]['type'] );
		// What reaches the browser is the resolved EFB rule, never the model's shape.
		$this->assertArrayNotHasKey( 'when', $on['ops'][0]['rule'] );
		$this->assertSame( 'ctype_o1', $on['ops'][0]['rule']['conditions']['items'][0]['value'] );

		// Free Plus: the add-on's three-rule cap counts rules already on the form
		// and rules added earlier in the same batch.
		$capped = EfbCustomizeSkill::validate_ops_verbose(
			array( $op, $op ),
			$this->fields(),
			array(
				'logic'          => true,
				'tier'           => 'free_plus',
				'existing_rules' => 2,
			)
		);
		$this->assertCount( 1, $capped['ops'] );
		$this->assertSame( 'logic_rule_limit:3', $capped['dropped'][0]['reason'] );

		$free = EfbCustomizeSkill::validate_ops_verbose(
			array( $op ),
			$this->fields(),
			array(
				'logic' => true,
				'tier'  => 'free',
			)
		);
		$this->assertSame( 'logic_needs_plan', $free['dropped'][0]['reason'] );

		$bad = EfbCustomizeSkill::validate_ops_verbose(
			array(
				array(
					'op'   => 'add_logic_rule',
					'rule' => array(
						'when' => array( array( 'field' => 'Nope' ) ),
						'then' => array(),
					),
				),
			),
			$this->fields(),
			array( 'logic' => true )
		);
		$this->assertSame( 'invalid_logic_rule:rule_needs_condition_and_action', $bad['dropped'][0]['reason'] );
	}
}
