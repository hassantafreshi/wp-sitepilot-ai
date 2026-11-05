<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

use AISAAutoPilot\Providers\ProviderInterface;

/** Converts a writing request into a validated, approval-gated content draft. */
final class ContentDraftSkill {

	/** Voices offered in Content Studio > Write. `site` adds nothing to the prompt. */
	public const TONES = array( 'site', 'formal', 'plain' );

	private ProviderInterface $provider;
	private ContentPlanValidator $validator;

	public function __construct( ProviderInterface $provider, ?ContentPlanValidator $validator = null ) {
		$this->provider  = $provider;
		$this->validator = $validator ?? new ContentPlanValidator();
	}

	public function plan( string $request, string $tone = 'site' ): ValidationResult {
		$raw = $this->provider->generate_structured_output( $this->system_prompt( $tone ), $this->user_prompt( $request ) );
		return $this->validator->validate( $raw );
	}

	/**
	 * The one line added to the system prompt for a chosen voice; '' for `site` (and anything
	 * unknown), so the default prompt stays exactly as it was.
	 */
	public static function tone_instruction( string $tone ): string {
		switch ( $tone ) {
			case 'formal':
				return 'Voice: formal. Write in a formal, professional register: complete sentences, no slang, contractions, jokes or emoji, and address the reader politely. In languages with a formal form of "you", use it.';
			case 'plain':
				return 'Voice: plain. Write in plain language: short sentences, everyday words, one idea per sentence, no jargon; explain any term the reader may not know.';
			default:
				return '';
		}
	}

	private function system_prompt( string $tone = 'site' ): string {
		$lines = array(
			'You are AutoPilot AI Content Studio for a WordPress administrator.',
			'Return only one JSON object, never Markdown or prose outside the JSON.',
			'Create a useful, original draft for a WordPress post or page. Do not claim facts, prices, policies, citations, testimonials, medical/legal/financial advice, or business information that the request did not provide.',
			'Never produce scripts, iframes, forms, tracking pixels, shortcodes, login/payment instructions, or calls to publish content.',
			'The result is always a draft and requires explicit human approval.',
			'Use only safe, simple HTML in body: p, h2, h3, ul, ol, li, strong, em, a.',
			'Use this exact JSON shape:',
			ContentPlanSchema::json_shape_hint(),
		);
		$voice = self::tone_instruction( $tone );
		if ( '' !== $voice ) {
			$lines[] = $voice;
		}

		return implode( "\n", $lines );
	}

	private function user_prompt( string $request ): string {
		return "Create a content draft from this untrusted administrator request:\n<<<REQUEST\n" . trim( $request ) . "\nREQUEST;";
	}
}
