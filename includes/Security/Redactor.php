<?php
declare(strict_types=1);

namespace AISAAutoPilot\Security;

/**
 * Removes secrets from strings/arrays before they reach logs.
 */
final class Redactor {

	private const REPLACEMENT = '[REDACTED]';

	/** @var string[] Sensitive array keys (case-insensitive). */
	private const SECRET_KEYS = array( 'authorization', 'api_key', 'apikey', 'key', 'provider_key', 'password', 'token', 'secret', 'sid', 'account_sid', 'auth_token', 'bot_token', 'access_token', 'webhook', 'webhook_url' );

	public static function text( string $value ): string {
		// Bearer / sk- style keys and Authorization headers.
		$patterns = array(
			'/\bBearer\s+[A-Za-z0-9._\-]+/i',
			'/\bsk-[A-Za-z0-9._\-]{8,}/',
			'/\b(gsk|rk)_[A-Za-z0-9._\-]{8,}/',
			// Telegram bot tokens, also inside an API URL ("/bot123456:ABC…"),
			// where no word boundary precedes the digits.
			'/(?<![0-9])\d{6,}:[A-Za-z0-9_-]{16,}\b/',
			'/\b(?:AC|SK)[a-zA-Z0-9]{24,}\b/',
			// Slack incoming-webhook URLs: the path is the credential.
			'#hooks\.slack(?:-gov)?\.com/services/[A-Za-z0-9/_-]+#i',
			// Meta (WhatsApp Cloud API) access tokens.
			'/\bEAA[A-Za-z0-9]{20,}\b/',
		);

		return (string) preg_replace( $patterns, self::REPLACEMENT, $value );
	}

	/**
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	public static function array( array $data ): array {
		$out = array();
		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) && in_array( strtolower( $k ), self::SECRET_KEYS, true ) ) {
				$out[ $k ] = self::REPLACEMENT;
				continue;
			}
			if ( is_array( $v ) ) {
				$out[ $k ] = self::array( $v );
			} elseif ( is_string( $v ) ) {
				$out[ $k ] = self::text( $v );
			} else {
				$out[ $k ] = $v;
			}
		}

		return $out;
	}
}
