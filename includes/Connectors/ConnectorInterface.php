<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

/**
 * A connector adapts the `form.create` capability to a real WordPress
 * implementation (built-in storage, Easy Form Builder, or others later): it
 * validates a plan and executes it after the administrator's approval.
 */
interface ConnectorInterface extends ConnectorBase {

	/**
	 * Validate a normalized form plan before execution.
	 *
	 * @param array<string, mixed> $plan Normalized plan from FormPlanValidator.
	 * @return string[] List of error messages (empty = valid).
	 */
	public function validate_action( array $plan ): array;

	/**
	 * Create the form. Only called after validation + admin approval.
	 *
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $context e.g. ['status' => 'draft'|'publish'].
	 * @return array<string, mixed> Result: status, form_id, edit_url, shortcode, warnings.
	 */
	public function execute_action( array $plan, array $context = array() ): array;
}
