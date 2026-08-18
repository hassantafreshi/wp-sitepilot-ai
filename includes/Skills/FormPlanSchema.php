<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

/**
 * The contract for a form plan (MVP Form Copilot Spec §7-§8).
 * Kept WordPress-free so it can be shared by the validator, prompt, and tests.
 */
final class FormPlanSchema {

	/**
	 * Field types offered by the independent AISAAutoPilot Forms product.
	 *
	 * This is intentionally smaller than Easy Form Builder. AISAAutoPilot Forms is
	 * a single-step, email-only form flow; uploads, payments, passwords and
	 * account fields belong in an advanced form product instead.
	 */
	public const ALLOWED_FIELD_TYPES = array( 'text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'radio' );

	/** Field types explicitly forbidden for safety in the MVP. */
	public const FORBIDDEN_FIELD_TYPES = array( 'password', 'payment', 'credit_card', 'card', 'registration', 'user', 'ssn' );

	public const MAX_FIELDS = 20;

	public const RISK_LEVELS = array( 'low', 'medium', 'high' );

	/**
	 * The JSON shape we instruct the model to return.
	 */
	public static function json_shape_hint(): string {
		return <<<JSON
{
  "intent": "create_form",
  "form": {
    "title": "string (required)",
    "description": "string (optional)",
    "fields": [
      {
        "type": "one of: text | email | tel | textarea | select | checkbox | radio",
        "label": "string (required)",
        "required": true,
        "placeholder": "string (optional)",
        "options": ["only for select | radio | checkbox groups"]
      }
    ],
    "submit_button": "string (optional, defaults to Submit)",
    "confirmation_message": "string (optional)"
  },
  "risk_level": "low | medium | high",
  "notes": "short note to the admin (optional)"
}
JSON;
	}
}
