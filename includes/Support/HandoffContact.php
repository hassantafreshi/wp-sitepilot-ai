<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

/**
 * Contact details a visitor leaves with a handoff request: name, email, mobile number and website.
 *
 * The site owner chooses per detail whether the handoff card asks for it ('off', 'optional' or
 * 'required'). A detail that is off is never read from the request, so nothing is collected that
 * the owner did not ask for. Values are stored as plain text and escaped by every output.
 */
final class HandoffContact {

	public const NAME_MAX    = 120;
	public const PHONE_MAX   = 40;
	public const WEBSITE_MAX = 255;

	/**
	 * Reads the details that are switched on and sorts them into clean values, missing required
	 * details and invalid ones.
	 *
	 * @param array<string,string> $modes Detail key to 'off', 'optional' or 'required'.
	 * @param array<string,mixed>  $input Raw request values by detail key.
	 * @return array{values:array{name:string,email:string,phone:string,website:string},missing:array<int,string>,invalid:array<int,string>}
	 */
	public static function collect( array $modes, array $input ): array {
		$values  = array(
			'name'    => '',
			'email'   => '',
			'phone'   => '',
			'website' => '',
		);
		$missing = array();
		$invalid = array();
		foreach ( array_keys( $values ) as $key ) {
			$mode = (string) ( $modes[ $key ] ?? 'off' );
			if ( 'optional' !== $mode && 'required' !== $mode ) {
				continue;
			}
			$raw = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
			if ( '' === $raw ) {
				if ( 'required' === $mode ) {
					$missing[] = $key;
				}
				continue;
			}
			$clean = self::clean( $key, $raw );
			if ( null === $clean ) {
				$invalid[] = $key;
				continue;
			}
			$values[ $key ] = $clean;
		}
		return array(
			'values'  => $values,
			'missing' => $missing,
			'invalid' => $invalid,
		);
	}

	/** A clean value for one detail, or null when a non-empty value is not valid. */
	public static function clean( string $key, string $raw ): ?string {
		switch ( $key ) {
			case 'name':
				$name = mb_substr( trim( sanitize_text_field( $raw ) ), 0, self::NAME_MAX );
				return '' !== $name ? $name : null;
			case 'email':
				$email = sanitize_email( $raw );
				return '' !== $email && is_email( $email ) ? $email : null;
			case 'phone':
				return self::phone( $raw );
			case 'website':
				return self::website( $raw );
		}
		return null;
	}

	/**
	 * A mobile or phone number as the visitor wrote it, with Persian and Arabic-Indic digits turned into
	 * Latin ones: an optional leading +, digits, spaces, dots, dashes and brackets, and 6 to 15 digits.
	 */
	public static function phone( string $raw ): ?string {
		$phone = trim( (string) preg_replace( '/\s+/u', ' ', self::latin_digits( $raw ) ) );
		if ( '' === $phone || mb_strlen( $phone ) > self::PHONE_MAX || 1 !== preg_match( '/^\+?[0-9 ().\-]+$/', $phone ) ) {
			return null;
		}
		$digits = strlen( (string) preg_replace( '/\D/', '', $phone ) );
		return $digits >= 6 && $digits <= 15 ? $phone : null;
	}

	/**
	 * A web address. "example.com" becomes "https://example.com"; only http and https are accepted, the
	 * host needs a dot and a top-level domain, and non-Latin (IDN) hosts are allowed.
	 */
	public static function website( string $raw ): ?string {
		$url = trim( $raw );
		if ( '' === $url || 1 === preg_match( '/\s/u', $url ) ) {
			return null;
		}
		if ( 1 !== preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) ) {
			// No scheme at all, unless it looks like one ("javascript:alert(1)"), which is refused below.
			if ( 1 === preg_match( '#^[a-z][a-z0-9+.\-]*:(?!\d)#i', $url ) ) {
				return null;
			}
			$url = 'https://' . $url;
		}
		$parts  = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = (string) ( $parts['host'] ?? '' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}\-]{0,61}[\p{L}\p{N}])?\.)+\p{L}{2,63}$/u', $host ) ) {
			return null;
		}
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		return '' !== $clean && mb_strlen( $clean ) <= self::WEBSITE_MAX ? $clean : null;
	}

	/** Persian (۰-۹) and Arabic-Indic (٠-٩) digits as Latin digits. */
	public static function latin_digits( string $text ): string {
		return strtr(
			$text,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}
}
