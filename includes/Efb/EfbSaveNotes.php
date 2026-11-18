<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Easy Form Builder's own answers about a form it validated or stored (form package `validate()` / `import()`: the
 * `dropped[]` keys, the `notices[]` of its save path, the payment readiness), turned into sentences for the admin.
 *
 * EFB's codes are stable (its SPLIT, F12b, PAY00 and F15 contracts); a code this class does not know is shown with
 * EFB's own message (`m`) when it has one and is otherwise left out, so nothing technical reaches the admin.
 */
final class EfbSaveNotes {

	/** Technical notices that need no word to the admin (ids renumbered, a step row added). */
	private const SILENT = array( 'ids_remapped', 'duplicate_ids', 'step_added', 'row_without_type', 'orphan_option', 'not_an_array', 'php_object' );

	/**
	 * @param array<int,array<string,mixed>> $dropped EFB's `dropped[]`.
	 * @param array<int,array<string,mixed>> $notices EFB's `notices[]` (save warnings included).
	 * @param array<string,mixed>|null       $payment `{ready, issues[]}` from import(), or null.
	 * @return string[]
	 */
	public static function messages( array $dropped, array $notices, ?array $payment = null ): array {
		$out = array();

		// SPLIT: keys of an add-on that is not active on this site are not stored (addon_inactive / addon_keys_dropped).
		$by_addon = array();
		foreach ( $dropped as $item ) {
			$code = (string) ( $item['code'] ?? ( $item['reason'] ?? '' ) );
			if ( in_array( $code, array( 'addon_inactive', 'addon_keys_dropped' ), true ) || 'addon_inactive' === ( $item['reason'] ?? '' ) ) {
				$addon                = (string) ( $item['addon'] ?? '' );
				$by_addon[ $addon ][] = (string) ( $item['key'] ?? '' );
			} elseif ( 'unknown_type' === $code ) {
				/* translators: %s: field type Easy Form Builder does not know. */
				$out[] = sprintf( __( 'Easy Form Builder does not know the field type “%s” on this site, so it is left out.', 'ai-site-assistant-autopilot' ), (string) ( $item['type'] ?? '' ) );
			}
		}
		foreach ( $by_addon as $addon => $keys ) {
			$name  = '' !== $addon ? EfbCapabilities::addon( $addon )['name'] : __( 'an add-on', 'ai-site-assistant-autopilot' );
			$out[] = sprintf(
				/* translators: 1: add-on name, 2: number of settings. */
				_n( 'Easy Form Builder leaves out %2$d setting of the %1$s add-on, which is not active on this site.', 'Easy Form Builder leaves out %2$d settings of the %1$s add-on, which is not active on this site.', count( array_filter( $keys ) ) ?: 1, 'ai-site-assistant-autopilot' ),
				$name,
				count( array_filter( $keys ) ) ?: 1
			);
		}

		foreach ( $notices as $notice ) {
			$code = (string) ( $notice['code'] ?? '' );
			if ( '' === $code || in_array( $code, self::SILENT, true ) ) {
				continue;
			}
			$text = self::notice( $code, $notice );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}

		if ( is_array( $payment ) && empty( $payment['ready'] ) ) {
			$issues = is_array( $payment['issues'] ?? null ) ? $payment['issues'] : array();
			$out[]  = __( 'Visitors cannot pay through this form yet: Easy Form Builder shows them a notice instead of the payment field until this is fixed.', 'ai-site-assistant-autopilot' );
			foreach ( array_slice( $issues, 0, 4 ) as $issue ) {
				if ( is_array( $issue ) ) {
					$text = self::notice( (string) ( $issue['code'] ?? '' ), $issue );
					if ( '' !== $text ) {
						$out[] = $text;
					}
				}
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * One EFB code in plain words.
	 *
	 * @param array<string,mixed> $item
	 */
	public static function notice( string $code, array $item ): string {
		$addon = (string) ( $item['addon'] ?? '' );
		switch ( $code ) {
			case 'addon_keys_kept':
				/* translators: %s: add-on name. */
				return sprintf( __( 'Easy Form Builder kept the saved settings of the %s add-on even though it is not active now; they work again once it is switched back on.', 'ai-site-assistant-autopilot' ), '' !== $addon ? EfbCapabilities::addon( $addon )['name'] : __( 'missing', 'ai-site-assistant-autopilot' ) );
			case 'logic_addon_inactive':
				return __( 'The conditional logic is saved with the form, but it only runs once Easy Form Builder’s Conditional Logic add-on is active.', 'ai-site-assistant-autopilot' );
			case 'captcha_addon_missing':
				return __( 'The CAPTCHA service this form asks for needs an Easy Form Builder add-on that is not active; until it is, the site’s Google reCAPTCHA (or the built-in spam checks) protects the form.', 'ai-site-assistant-autopilot' );
			case 'captcha_not_ready':
				return __( 'The CAPTCHA service has no keys yet. Add them in Easy Form Builder → Settings → Captchas; until then the form is protected by the fallback.', 'ai-site-assistant-autopilot' );
			case 'pay_calc_unavailable':
				return __( 'The amount to pay comes from a calculation, and the Numbers & Calculations add-on is not active: Easy Form Builder refuses the payment (nothing is charged) until it is.', 'ai-site-assistant-autopilot' );
			case 'pay_addon_inactive':
				return __( 'The payment field’s gateway add-on is not active, so the form cannot take payments yet. Activate it in Easy Form Builder → Add-ons.', 'ai-site-assistant-autopilot' );
			case 'pay_not_configured':
				return __( 'The payment gateway has no keys for its current mode. The site owner adds them in Easy Form Builder → Payments; I never see or enter keys.', 'ai-site-assistant-autopilot' );
			case 'pay_plan':
				return __( 'Online payment needs Easy Form Builder Free Plus or Pro on this site.', 'ai-site-assistant-autopilot' );
			case 'sms_disabled_addon_missing':
				return __( 'SMS notifications were switched off because Easy Form Builder’s SMS add-on is not active.', 'ai-site-assistant-autopilot' );
			case 'telegram_disabled_addon_missing':
				return __( 'Telegram notifications were switched off because Easy Form Builder’s Telegram add-on is not active.', 'ai-site-assistant-autopilot' );
			case 'verify_unavailable':
				return __( 'A one-time code cannot be sent yet: SMS codes need the SMS add-on with the WP SMS plugin, and e-mail codes need working e-mail on the site.', 'ai-site-assistant-autopilot' );
			case 'redirect_other_host':
				return __( 'The redirect after submit points to another site.', 'ai-site-assistant-autopilot' );
			case 'media_other_host':
				return __( 'An image in the form is loaded from another site.', 'ai-site-assistant-autopilot' );
		}
		// EFB's own sentence (in the site language) for anything else it reports at warning or error level.
		$message = trim( wp_strip_all_tags( (string) ( $item['m'] ?? '' ) ) );
		$level   = (string) ( $item['level'] ?? 'warning' );

		return '' !== $message && 'info' !== $level ? $message : '';
	}
}
