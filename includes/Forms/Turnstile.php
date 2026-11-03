<?php
declare(strict_types=1);

namespace AISAAutoPilot\Forms;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Security\ClientAddress;
use AISAAutoPilot\Security\KeyVault;

/**
 * Optional Cloudflare Turnstile check for native forms.
 *
 * Spam is a common complaint about form plugins, and the native forms only had a
 * honeypot and a rate limit. Turnstile is free and asks most visitors nothing. It is off
 * until the administrator enters both keys under Advanced settings > Forms and delivery;
 * only then does a form page load Cloudflare's script and each submission's token get
 * checked with Cloudflare (with the visitor's IP address). Without an answer from
 * Cloudflare the submission is refused, so a bot cannot get through by blocking it.
 */
final class Turnstile {

	public const SECRET_OPTION = 'aisaautopilot_turnstile_secret';

	// A third-party service's own client, which WordPress.org allows (like reCAPTCHA); loaded only while the administrator has entered Turnstile keys, and disclosed under External services.
	public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- service client, see above.

	// A server-to-server API call, not a file served to visitors.
	public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- service API endpoint, see above.

	/** The field Cloudflare's widget adds to the form. */
	public const RESPONSE_FIELD = 'cf-turnstile-response';

	public static function site_key(): string {
		return self::clean_key( (string) Settings::get( 'turnstile_site_key', '' ) );
	}

	/** Both keys are set. */
	public static function enabled(): bool {
		return '' !== self::site_key() && '' !== self::secret();
	}

	public static function has_secret(): bool {
		$stored = get_option( self::SECRET_OPTION, '' );

		return is_string( $stored ) && '' !== $stored;
	}

	/** Stores the secret key encrypted; an empty value removes it. */
	public static function set_secret( string $plaintext ): void {
		$plaintext = self::clean_key( $plaintext );
		if ( '' === $plaintext ) {
			delete_option( self::SECRET_OPTION );
			return;
		}
		update_option( self::SECRET_OPTION, KeyVault::fromWordPress()->encrypt( $plaintext ), false );
	}

	/** Keys are letters, digits, "_" and "-"; anything else is dropped. */
	public static function clean_key( string $key ): string {
		return substr( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( $key ) ), 0, 200 );
	}

	/** The widget for a form, and Cloudflare's script on that page only. Empty when off. */
	public static function widget(): string {
		if ( ! self::enabled() ) {
			return '';
		}
		// Cloudflare serves this script unversioned and asks sites not to pin or cache-bust it.
		wp_enqueue_script(
			'aisaautopilot-turnstile',
			self::SCRIPT_URL,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- see above.
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		return sprintf(
			'<div class="aisaautopilot-form__turnstile cf-turnstile" data-sitekey="%1$s" data-language="%2$s" data-size="flexible"></div>',
			esc_attr( self::site_key() ),
			esc_attr( SiteLanguage::primary( SiteLanguage::site_locale() ) )
		);
	}

	/**
	 * Checks a submission's token with Cloudflare.
	 *
	 * @return array{ok:bool, code:string}
	 */
	public static function verify( string $token ): array {
		$token = trim( $token );
		if ( '' === $token || strlen( $token ) > 2048 ) {
			return array(
				'ok'   => false,
				'code' => 'missing',
			);
		}
		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => self::secret(),
					'response' => $token,
					'remoteip' => ClientAddress::get(),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			( new Logger() )->error( 'form.turnstile_unreachable', 'Cloudflare Turnstile could not be reached; the submission was refused.' );
			return array(
				'ok'   => false,
				'code' => 'unreachable',
			);
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return ! empty( $data['success'] )
			? array(
				'ok'   => true,
				'code' => 'ok',
			)
			: array(
				'ok'   => false,
				'code' => 'failed',
			);
	}

	private static function secret(): string {
		$stored = get_option( self::SECRET_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		try {
			return KeyVault::fromWordPress()->decrypt( $stored );
		} catch ( \Throwable $e ) {
			return '';
		}
	}
}
