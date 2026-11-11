<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Keeps internal names out of what the copilot says.
 *
 * The model knows Easy Form Builder by its storage keys, control ids and add-on codes, and now and then
 * repeats one in a reply: "(smsEnableEl)", "(AdnSS)", "(sms_collapse)". An admin has no use for those, so
 * they are taken out before the reply is shown. Pure PHP, unit testable without WordPress.
 */
final class EfbReplyText {

	/** Add-on codes (AdnSS, AdnSMF ...), control ids (smsEnableEl) and storage keys (sms_admins_phone_no). */
	private const INTERNAL = '(?:Adn[A-Z][A-Za-z]{1,4}|[a-z][A-Za-z0-9]*El|[a-z][a-z0-9]*(?:_[a-z0-9]+)+|valj_efb(?:\\[\\d+\\])?(?:\\.[A-Za-z_]+)*)';

	public static function clean( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}
		// "(smsEnableEl)", "( AdnSS )", "(`sms_collapse`)": the whole parenthesis goes.
		$text = (string) preg_replace( '/\\s*[(（]\\s*`?' . self::INTERNAL . '`?(?:\\s*[,،\\/]\\s*`?' . self::INTERNAL . '`?)*\\s*[)）]/u', '', $text );
		// A bare code in backticks.
		$text = (string) preg_replace( '/\\s*`' . self::INTERNAL . '`/u', '', $text );
		// A bare add-on code or control id in running text.
		$text = (string) preg_replace( '/\\s*\\b(?:Adn[A-Z][A-Za-z]{1,4}|[a-z]+[A-Z][A-Za-z0-9]*El)\\b/u', '', $text );

		return trim( (string) preg_replace( '/[ \\t]{2,}/', ' ', $text ) );
	}
}
