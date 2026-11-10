<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Skills\ContentDraftSkill;

/**
 * The voice chosen in Content Studio > Write: the site default, Formal or Plain.
 *
 * The value is sent with /content/plan, frozen into the proposal (ActionStore), written
 * to the created draft as post meta, and remembered per administrator and per site so
 * Write opens on the last choice.
 */
final class ContentTone {

	public const DEFAULT = 'site';

	/** Per-site user option (update_user_option adds the site's table prefix). */
	public const USER_OPTION = 'aisaautopilot_content_tone';

	/** Post meta on drafts Content Studio created. */
	public const POST_META = '_aisaautopilot_content_tone';

	public static function is_valid( string $tone ): bool {
		return in_array( $tone, ContentDraftSkill::TONES, true );
	}

	/** A stored or posted value made safe; anything unknown becomes the default. */
	public static function normalize( $tone ): string {
		$tone = is_string( $tone ) ? $tone : '';
		return self::is_valid( $tone ) ? $tone : self::DEFAULT;
	}

	public static function label( string $tone ): string {
		switch ( $tone ) {
			case 'formal':
				return __( 'Formal', 'ai-site-assistant-autopilot' );
			case 'plain':
				return __( 'Plain', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Standard', 'ai-site-assistant-autopilot' );
		}
	}

	/** @return array<string, string> tone => label, in the order Write shows them. */
	public static function options(): array {
		$options = array();
		foreach ( ContentDraftSkill::TONES as $tone ) {
			$options[ $tone ] = self::label( $tone );
		}

		return $options;
	}

	public static function preferred( int $user_id ): string {
		return self::normalize( get_user_option( self::USER_OPTION, $user_id ) );
	}

	public static function remember( int $user_id, string $tone ): void {
		if ( $user_id > 0 && self::is_valid( $tone ) && self::preferred( $user_id ) !== $tone ) {
			update_user_option( $user_id, self::USER_OPTION, $tone );
		}
	}
}
