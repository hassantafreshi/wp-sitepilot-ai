<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Core\SiteLanguage;

/**
 * Deterministic form review: the checks an experienced form designer runs
 * before publishing, without sending anything to an AI provider.
 *
 * It reads only structure (types, labels, required flags, option counts) and
 * proposes, never applies: a finding may carry one ready `fix` operation that
 * goes through the normal customize validation and the admin's own Save.
 *
 * Pure PHP apart from __() so it is unit testable.
 */
final class EfbFormReview {

	/** Types that collect personal data and therefore call for consent. */
	private const PERSONAL = array( 'email', 'tel', 'mobile', 'file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder', 'maps' );

	/** Types that hold no answer: they are neither required nor counted. */
	private const DISPLAY = array( 'heading', 'link', 'html', 'steps', 'step' );

	private const CHOICE = array( 'select', 'radio', 'checkbox', 'multiselect', 'chlCheckBox', 'chlRadio', 'imgRadio' );

	/** Above this many answers on one page a form reads better as steps. */
	private const LONG_PAGE = 10;

	/**
	 * @param array<int, array<string, mixed>> $fields  Each: type, label, required?, options?
	 * @param array{steps?:int, captcha?:bool|null, shield?:bool} $context steps on the form; captcha true/false when known; shield = EFB's site-wide Form Security & Spam Protection add-on (AdnHSH) is active.
	 * @return array<int, array{code:string, severity:string, message:string, fix?:array<string,mixed>}>
	 */
	public static function review( array $fields, array $context = array() ): array {
		$findings = array();
		$inputs   = array();
		$labels   = array();
		$personal = false;
		$consent  = false;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( in_array( $type, self::DISPLAY, true ) || '' === $type ) {
				continue;
			}
			$inputs[] = $field;
			$label    = trim( (string) ( $field['label'] ?? '' ) );

			if ( 'trmCheckbox' === $type ) {
				$consent = true;
			}
			if ( in_array( $type, self::PERSONAL, true ) ) {
				$personal = true;
			}

			if ( '' === $label ) {
				$findings[] = array(
					'code'     => 'missing_label',
					'severity' => 'warning',
					/* translators: %s: field type. */
					'message'  => sprintf( __( 'A %s field has no visible label. Screen readers and your submission exports need one; a placeholder is not a label.', 'ai-site-assistant-autopilot' ), $type ),
				);
			} else {
				$labels[ function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label ) ][] = $label;
			}

			$options = is_array( $field['options'] ?? null ) ? $field['options'] : null;
			if ( null !== $options && in_array( $type, self::CHOICE, true ) && count( $options ) < 2 ) {
				$findings[] = array(
					'code'     => 'choice_too_few_options',
					'severity' => 'warning',
					/* translators: %s: field label. */
					'message'  => sprintf( __( '“%s” offers fewer than two choices, so the visitor has nothing to choose between.', 'ai-site-assistant-autopilot' ), $label ),
				);
			}
		}

		foreach ( $labels as $same ) {
			if ( count( $same ) > 1 ) {
				$findings[] = array(
					'code'     => 'duplicate_label',
					'severity' => 'warning',
					/* translators: %s: repeated field label. */
					'message'  => sprintf( __( 'Two fields are both called “%s”. Answers become impossible to tell apart in emails and exports.', 'ai-site-assistant-autopilot' ), $same[0] ),
				);
			}
		}

		if ( $personal && ! $consent ) {
			$findings[] = array(
				'code'     => 'personal_data_without_consent',
				'severity' => 'warning',
				'message'  => __( 'This form collects personal data but asks for no consent. Add a required consent checkbox that links to your privacy policy.', 'ai-site-assistant-autopilot' ),
				// The checkbox goes on the form, so its words follow the site language.
				'fix'      => SiteLanguage::for_site(
					static fn (): array => array(
						'op'    => 'add_field',
						'field' => array(
							'type'     => 'trmCheckbox',
							'label'    => __( 'Consent', 'ai-site-assistant-autopilot' ),
							'required' => true,
							'options'  => array( __( 'I agree that my details are stored and used to answer this request.', 'ai-site-assistant-autopilot' ) ),
						),
					)
				),
			);
		}

		$required = count(
			array_filter(
				$inputs,
				static fn ( array $f ): bool => ! empty( $f['required'] ) && 'trmCheckbox' !== (string) ( $f['type'] ?? '' )
			)
		);
		if ( $required > 6 && $required > 0.7 * count( $inputs ) ) {
			$findings[] = array(
				'code'     => 'too_many_required',
				'severity' => 'info',
				/* translators: 1: required field count, 2: total field count. */
				'message'  => sprintf( __( '%1$d of %2$d questions are required. Every compulsory field costs completions; keep only the ones you truly need.', 'ai-site-assistant-autopilot' ), $required, count( $inputs ) ),
			);
		}

		$steps = (int) ( $context['steps'] ?? 1 );
		if ( count( $inputs ) > self::LONG_PAGE && $steps <= 1 ) {
			$findings[] = array(
				'code'     => 'long_single_page',
				'severity' => 'info',
				/* translators: %d: number of questions. */
				'message'  => sprintf( __( '%d questions on one page is a lot. Ask me to split it into steps so it feels shorter.', 'ai-site-assistant-autopilot' ), count( $inputs ) ),
			);
		}

		// Unknown counts as off: a new form never starts with reCAPTCHA. Human
		// Shield guards every form on the site once its add-on is active.
		if ( true !== ( $context['captcha'] ?? null ) && empty( $context['shield'] ) && array() !== $inputs ) {
			$findings[] = array(
				'code'     => 'spam_protection',
				'severity' => 'info',
				'message'  => self::spam_advice(),
			);
		}

		return $findings;
	}

	/**
	 * What protects a form from spam on this site, from EFB's own CAPTCHA registry when it has one (F12, F12b):
	 * core always runs a honeypot and soft checks; Google reCAPTCHA v2, v2 invisible or v3 is chosen per form; other
	 * services are add-ons.
	 */
	public static function spam_advice(): string {
		$providers = EfbCapabilities::captcha_providers();
		if ( array() === $providers ) {
			return __( 'Public forms attract spam. Activate Easy Form Builder’s Form Security & Spam Protection add-on, or add reCAPTCHA keys in Easy Form Builder → Settings and turn on reCAPTCHA for this form.', 'ai-site-assistant-autopilot' );
		}
		$google = false;
		$addons = array();
		foreach ( $providers as $provider ) {
			if ( empty( $provider['addon'] ) ) {
				$google = $google || ! empty( $provider['ready'] );
				continue;
			}
			$addons[] = (string) ( $provider['label'] ?? $provider['id'] ?? '' ) . ( ! empty( $provider['available'] ) ? '' : ' (' . __( 'add-on', 'ai-site-assistant-autopilot' ) . ')' );
		}
		$base = __( 'Easy Form Builder already checks every form with a hidden honeypot and timing checks.', 'ai-site-assistant-autopilot' );
		$cap  = $google
			? __( 'For a CAPTCHA, turn on Google reCAPTCHA for this form and pick v2, v2 invisible or v3 in its settings.', 'ai-site-assistant-autopilot' )
			: __( 'For a CAPTCHA, add Google reCAPTCHA keys in Easy Form Builder → Settings → Captchas, then pick v2, v2 invisible or v3 in this form’s settings.', 'ai-site-assistant-autopilot' );
		/* translators: %s: comma-separated CAPTCHA services. */
		$more = array() !== $addons ? ' ' . sprintf( __( 'Other services come as add-ons on Free Plus and Pro: %s.', 'ai-site-assistant-autopilot' ), implode( ', ', $addons ) ) : '';

		return $base . ' ' . $cap . $more;
	}
}
