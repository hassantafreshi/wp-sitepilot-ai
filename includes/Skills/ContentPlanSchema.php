<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

/** The safe, draft-only contract for Content Studio proposals. */
final class ContentPlanSchema {

	public const POST_TYPES  = array( 'post', 'page' );
	public const RISK_LEVELS = array( 'low', 'medium', 'high' );

	public static function json_shape_hint(): string {
		return implode(
			"\n",
			array(
				'{',
				'  "intent": "content_draft",',
				'  "content": {',
				'    "post_type": "post | page",',
				'    "title": "string",',
				'    "excerpt": "string",',
				'    "body": "safe HTML or plain text",',
				'    "meta_description": "string",',
				'    "slug": "optional URL slug"',
				'  },',
				'  "risk_level": "low | medium | high",',
				'  "notes": "string"',
				'}',
			)
		);
	}
}
