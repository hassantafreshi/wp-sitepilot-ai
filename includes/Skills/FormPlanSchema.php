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
	public const ALLOWED_FIELD_TYPES = array( 'text', 'email', 'tel', 'url', 'number', 'date', 'textarea', 'select', 'checkbox', 'radio' );

	/**
	 * Column width of a field, in twelfths of the row. Fields flow left to right and wrap when a
	 * row is full, so half + half sit side by side and third + third + third make three columns.
	 */
	public const WIDTHS = array(
		'full'           => 12,
		'three_quarters' => 9,
		'two_thirds'     => 8,
		'half'           => 6,
		'third'          => 4,
		'quarter'        => 3,
	);

	/** Field types explicitly forbidden for safety in the MVP. */
	public const FORBIDDEN_FIELD_TYPES = array( 'password', 'payment', 'credit_card', 'card', 'registration', 'user', 'ssn' );

	public const MAX_FIELDS = 20;

	public const RISK_LEVELS = array( 'low', 'medium', 'high' );

	/**
	 * The JSON shape we instruct the model to return.
	 */
	public static function json_shape_hint(): string {
		return implode(
			"\n",
			array(
				'{',
				'  "intent": "create_form",',
				'  "form": {',
				'    "title": "string (required)",',
				'    "description": "string (optional)",',
				'    "fields": [',
				'      {',
				'        "key": "keep the key of an existing field when changing a form; omit for a new field",',
				'        "type": "one of: text | email | tel | url | number | date | textarea | select | checkbox | radio",',
				'        "label": "string (required)",',
				'        "required": true,',
				'        "placeholder": "string (optional)",',
				'        "help": "short hint shown under the field (optional)",',
				'        "width": "full | half | third | quarter | two_thirds | three_quarters (default full)",',
				'        "options": ["only for select | radio | checkbox groups"],',
				'        "settings": { "min": "number or YYYY-MM-DD", "max": "number or YYYY-MM-DD", "step": "number" }',
				'      }',
				'    ],',
				'    "submit_button": "string (optional, defaults to Submit)",',
				'    "confirmation_message": "string (optional)"',
				'  },',
				'  "risk_level": "low | medium | high",',
				'  "notes": "short note to the admin (optional)"',
				'}',
			)
		);
	}
}
