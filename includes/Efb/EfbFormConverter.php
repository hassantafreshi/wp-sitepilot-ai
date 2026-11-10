<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

use AISAAutoPilot\Core\SiteLanguage;

/**
 * Converts a validated, clean form plan into a real EFB `form_structer` array.
 *
 * Input plan (from FormPlanValidator, EFB-configured):
 *   [ 'title', 'description'?, 'submit_button'?, 'confirmation_message'?,
 *     'notify_email'?, 'confirm_to_respondent'?, 'privacy_link'?, 'fields' => [
 *       ['key','type','label','required'(bool),'placeholder','help','options'(string[])], ... ] ]
 *
 * `notify_email` and `privacy_link` are never AI output: the controller fills
 * them from the site's own settings after validation.
 *
 * Output: flat array [ settings, step, field, option, option, field, ... ] matching EFB 4.x.
 * Pure PHP apart from __() so it is unit testable. The few texts the converter writes
 * itself (button labels, thank-you lines, a page or privacy fallback) are visitor-facing,
 * so they follow the site language, not the admin's dashboard language.
 */
final class EfbFormConverter {

	/** @var array<string,string>|null */
	private static ?array $texts = null;

	/**
	 * The texts EFB shows visitors that no plan supplies, in the site language.
	 *
	 * @return array<string,string>
	 */
	private static function texts(): array {
		if ( null === self::$texts ) {
			self::$texts = SiteLanguage::for_site(
				static fn (): array => array(
					'form'          => __( 'Form', 'ai-site-assistant-autopilot' ),
					/* translators: %d: page number of a multi-step form. */
					'step'          => __( 'Step %d', 'ai-site-assistant-autopilot' ),
					'heading'       => __( 'Heading', 'ai-site-assistant-autopilot' ),
					'privacy'       => __( 'Privacy Policy', 'ai-site-assistant-autopilot' ),
					'consent'       => __( 'I agree to the privacy policy.', 'ai-site-assistant-autopilot' ),
					'submit'        => __( 'Submit', 'ai-site-assistant-autopilot' ),
					'next'          => __( 'Next', 'ai-site-assistant-autopilot' ),
					'previous'      => __( 'Previous', 'ai-site-assistant-autopilot' ),
					'thank_you'     => __( 'Thanks for filling out the form.', 'ai-site-assistant-autopilot' ),
					'done'          => __( 'You’re all done', 'ai-site-assistant-autopilot' ),
					'tracking_code' => __( 'Confirmation Code', 'ai-site-assistant-autopilot' ),
					'fill_required' => __( 'Please fill in all required fields.', 'ai-site-assistant-autopilot' ),
					'pay_choose'    => __( 'Choose an option', 'ai-site-assistant-autopilot' ),
					'pay_amount'    => __( 'Amount', 'ai-site-assistant-autopilot' ),
					'pay_total'     => __( 'Total', 'ai-site-assistant-autopilot' ),
					'pay_stripe'    => __( 'Pay by card', 'ai-site-assistant-autopilot' ),
					'pay_paypal'    => __( 'Pay with PayPal', 'ai-site-assistant-autopilot' ),
					'pay_zarinpal'  => __( 'Pay online', 'ai-site-assistant-autopilot' ),
				)
			);
		}

		return self::$texts;
	}

	/** Forget the cached texts, so a later locale switch is honoured (tests). */
	public static function reset_texts(): void {
		self::$texts = null;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return array<int, array<string, mixed>>
	 */
	public function convert( array $plan ): array {
		$texts = self::texts();
		$title = (string) ( $plan['title'] ?? $texts['form'] );
		if ( '' === trim( $title ) ) {
			$title = $texts['form'];
		}

		$settings                         = EfbFormModel::template( 'form_settings' );
		$settings['formName']             = $title;
		$settings['button_single_text']   = $texts['submit'];
		$settings['button_Next_text']     = $texts['next'];
		$settings['button_Previous_text'] = $texts['previous'];
		if ( isset( $settings['thank_you_message'] ) && is_array( $settings['thank_you_message'] ) ) {
			$settings['thank_you_message']['thankYou']                   = $texts['thank_you'];
			$settings['thank_you_message']['done']                       = $texts['done'];
			$settings['thank_you_message']['trackingCode']               = $texts['tracking_code'];
			$settings['thank_you_message']['pleaseFillInRequiredFields'] = $texts['fill_required'];
			$confirmation = trim( (string) ( $plan['confirmation_message'] ?? '' ) );
			if ( '' !== $confirmation ) {
				$settings['thank_you_message']['thankYou'] = $confirmation;
			}
		}
		$submit = trim( (string) ( $plan['submit_button'] ?? '' ) );
		if ( '' !== $submit ) {
			$settings['button_single_text'] = $submit;
		}

		$step           = EfbFormModel::template( 'step' );
		$step['name']   = $title;
		$step['step']   = '1';
		$amount         = 1;
		$step['amount'] = (string) $amount;

		$items    = array( $settings, $step );
		$used_ids = array( '1' => true ); // step id_ is "1".

		// A plan turns multi-step by placing step breaks between its fields: every
		// field after a break belongs to the page that break opened.
		$step_no       = 1;
		$step_index    = 1; // Position of the open step object inside $items.
		$step_contents = 0; // Items placed on the open page so far.

		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? 'text' );

			if ( 'steps' === $type || 'step' === $type ) {
				$label = trim( (string) ( $field['label'] ?? '' ) );

				// A break with nothing above it just titles the page already open,
				// instead of leaving an empty first page.
				if ( 0 === $step_contents ) {
					if ( '' !== $label ) {
						$items[ $step_index ]['name'] = $label;
					}
					continue;
				}

				++$step_no;
				++$amount;
				$new_step                      = $this->build_step( '' !== $label ? $label : sprintf( $texts['step'], $step_no ) );
				$new_step['id_']               = (string) $step_no;
				$new_step['dataId']            = (string) $step_no;
				$new_step['id']                = (string) $step_no;
				$new_step['step']              = (string) $step_no;
				$new_step['amount']            = (string) $amount;
				$used_ids[ (string) $step_no ] = true;

				$items[]       = $new_step;
				$step_index    = count( $items ) - 1;
				$step_contents = 0;
				continue;
			}

			++$step_contents;
			if ( ! isset( $field['privacy_link'] ) && isset( $plan['privacy_link'] ) ) {
				$field['privacy_link'] = $plan['privacy_link'];
			}

			$key = $this->unique_id( (string) ( $field['key'] ?? ( $field['label'] ?? 'field' ) ), $used_ids );
			foreach ( $this->field_items( $field, $key, $used_ids ) as $item ) {
				++$amount;
				$item['step']   = (string) $step_no;
				$item['amount'] = (string) $amount;
				$items[]        = $item;
			}
		}

		// Online payment goes on the last page: the priced fields, the total, then the gateway itself.
		if ( is_array( $plan['payment'] ?? null ) ) {
			$payment = $plan['payment'];
			foreach ( $this->build_payment( $payment, array_map( 'strval', array_keys( $used_ids ) ) ) as $item ) {
				++$amount;
				++$step_contents;
				$item['step']   = (string) $step_no;
				$item['amount'] = (string) $amount;
				$items[]        = $item;
			}
			$items[0] = array_merge( $items[0], EfbPayments::form_settings( (string) $payment['gateway'], (string) ( $payment['currency'] ?? '' ) ) );
		}

		// Conditional logic planned with the form, already resolved against these same ids.
		if ( is_array( $plan['logic_rules'] ?? null ) && array() !== $plan['logic_rules'] ) {
			$items[0]['logic_rules'] = array_values( $plan['logic_rules'] );
			$items[0]['logic']       = true;
		}

		// A break after the last field would ship an empty final page.
		if ( 0 === $step_contents && $step_no > 1 ) {
			array_splice( $items, $step_index, 1 );
			--$step_no;
		}

		$items[0]['steps'] = (string) $step_no;
		if ( $step_no > 1 ) {
			$items[0]['button_state'] = 'multi';
		}

		return $this->apply_notifications( $items, $plan );
	}

	/**
	 * Build the EFB items (field + option items) for a single field, used when the
	 * chat adds a field to a live form. Amounts are left at "0" — the browser sets
	 * them on insert. IDs are made unique against the provided existing IDs.
	 *
	 * @param array<string, mixed> $field        {type,label,required,placeholder,help,width,options,settings}
	 * @param string[]             $existing_ids  IDs already present in the live form.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_field( array $field, array $existing_ids = array() ): array {
		$used = array();
		foreach ( $existing_ids as $id ) {
			$used[ (string) $id ] = true;
		}

		$key   = $this->unique_id( (string) ( $field['label'] ?? 'field' ), $used );
		$items = array();
		foreach ( $this->field_items( $field, $key, $used ) as $item ) {
			$item['step']   = '1';
			$item['amount'] = '0';
			$items[]        = $item;
		}

		return $items;
	}

	/**
	 * The fields of an online payment, in the order EFB's own builder lays them out: the priced choices
	 * (or a free amount), the total, and the gateway field. A validated payment from EfbPayments only:
	 * every price in it came from the request, and no key or secret is ever part of a form.
	 *
	 * @param array<string,mixed> $payment       Result of EfbPayments::validate_payment().
	 * @param string[]            $existing_ids  IDs already present in the form.
	 * @param bool                $with_gateway  False when the open form already has this gateway's field.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_payment( array $payment, array $existing_ids = array(), bool $with_gateway = true ): array {
		$texts   = self::texts();
		$gateway = (string) ( $payment['gateway'] ?? '' );
		$used    = $existing_ids;
		$out     = array();
		$push    = function ( array $field ) use ( &$used, &$out ): array {
			$built = $this->build_field( $field, $used );
			foreach ( $built as $item ) {
				$used[] = (string) ( $item['id_'] ?? '' );
			}
			$out = array_merge( $out, $built );

			return $built;
		};

		$items = is_array( $payment['items'] ?? null ) ? $payment['items'] : array();
		if ( array() !== $items ) {
			$type  = EfbPayments::CHOICE_FIELDS[ (string) ( $payment['choice_type'] ?? 'radio' ) ] ?? 'payRadio';
			$built = $push(
				array(
					'type'     => $type,
					'label'    => '' !== (string) ( $payment['label'] ?? '' ) ? (string) $payment['label'] : $texts['pay_choose'],
					'required' => true,
					'options'  => array_map( static fn ( array $item ): string => (string) $item['label'], $items ),
				)
			);
			// The n-th option carries the n-th price, written the way EFB's builder keeps one.
			$n = 0;
			foreach ( $out as $index => $item ) {
				if ( 'option' === ( $item['type'] ?? '' ) && isset( $items[ $n ] ) && ( $item['parent'] ?? '' ) === ( $built[0]['id_'] ?? '' ) ) {
					$out[ $index ]['price'] = rtrim( rtrim( number_format( (float) $items[ $n ]['price'], 2, '.', '' ), '0' ), '.' );
					++$n;
				}
			}
		} else {
			$push(
				array(
					'type'     => 'prcfld',
					'label'    => '' !== (string) ( $payment['amount_label'] ?? '' ) ? (string) $payment['amount_label'] : $texts['pay_amount'],
					'required' => true,
				)
			);
		}
		$push(
			array(
				'type'     => 'ttlprc',
				'label'    => $texts['pay_total'],
				'required' => false,
			)
		);
		if ( $with_gateway && null !== EfbPayments::gateway_field( $gateway ) ) {
			$push(
				array(
					'type'     => (string) EfbPayments::gateway_field( $gateway ),
					'label'    => $texts[ 'pay_' . $gateway ] ?? $texts['pay_stripe'],
					'required' => true,
				)
			);
		}

		return $out;
	}

	/**
	 * The fields and steps of converted items, in the shape the browser reports for an open form, so
	 * logic for a new form is resolved exactly as it would be for one already in the editor.
	 *
	 * @param array<int, array<string, mixed>> $items
	 * @return array{fields: array<int, array<string, mixed>>, steps: array<int, array<string, mixed>>}
	 */
	public static function logic_catalog( array $items ): array {
		$fields = array();
		$steps  = array();
		foreach ( $items as $item ) {
			$type = (string) ( $item['type'] ?? '' );
			if ( 'step' === $type ) {
				$steps[] = array(
					'id'    => (string) ( $item['id_'] ?? '' ),
					'label' => (string) ( $item['name'] ?? '' ),
				);
				continue;
			}
			if ( in_array( $type, array( 'form', 'option', 'r_matrix', 'heading', '' ), true ) || ! isset( $item['id_'] ) ) {
				continue;
			}
			$options = array();
			foreach ( $items as $child ) {
				if ( 'option' === ( $child['type'] ?? '' ) && (string) ( $child['parent'] ?? '' ) === (string) $item['id_'] ) {
					$options[] = array(
						'id'    => (string) $child['id_'],
						'value' => (string) ( $child['value'] ?? '' ),
					);
				}
			}
			$fields[] = array(
				'id'      => (string) $item['id_'],
				'label'   => (string) ( $item['name'] ?? '' ),
				'type'    => $type,
				'options' => $options,
			);
		}

		return array(
			'fields' => $fields,
			'steps'  => $steps,
		);
	}

	/**
	 * Build a structural EFB Step. The browser assigns the numeric id, step
	 * number and sequential amount against the live `valj_efb` array before it
	 * renders, because those values must be unique in the open form.
	 *
	 * @return array<string,mixed>
	 */
	public function build_step( string $label ): array {
		$step            = EfbFormModel::template( 'step' );
		$step['id_']     = '';
		$step['dataId']  = '';
		$step['id']      = '';
		$step['type']    = 'step';
		$step['classes'] = 'stepNavEfb';
		$step['name']    = $label;
		$step['step']    = '';
		$step['amount']  = '0';

		return $step;
	}

	/**
	 * The consent sentence a terms checkbox shows, with the site's own privacy
	 * page linked in EFB's [text](url) syntax.
	 *
	 * The AI never supplies a URL: any link or address it wrote is reduced to its
	 * text, and only the controller-provided privacy page is linked.
	 *
	 * @param array{url?:string,label?:string}|mixed $privacy_link
	 */
	public static function consent_text( string $sentence, $privacy_link = array() ): string {
		// [label](anything) keeps only the label; bare URLs go entirely.
		$text = (string) preg_replace( '/\[([^\]]*)\]\([^)]*\)/u', '$1', $sentence );
		$text = (string) preg_replace( '#\b(?:https?://|www\.)\S+#iu', '', $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( array( '[', ']' ), '', $text ) ) );
		if ( '' === $text ) {
			$text = self::texts()['consent'];
		}

		$url   = is_array( $privacy_link ) ? trim( (string) ( $privacy_link['url'] ?? '' ) ) : '';
		$label = is_array( $privacy_link ) ? trim( str_replace( array( '[', ']', '(', ')' ), '', (string) ( $privacy_link['label'] ?? '' ) ) ) : '';
		if ( '' === $url || 1 !== preg_match( '#^https?://[^\s()\[\]]+$#i', $url ) ) {
			return $text;
		}

		return sprintf( '%s ([%s](%s))', $text, '' !== $label ? $label : self::texts()['privacy'], $url );
	}

	/**
	 * The field object and its children, before step and amount are assigned.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,bool>  $used
	 * @return array<int, array<string, mixed>>
	 */
	private function field_items( array $field, string $key, array &$used ): array {
		$type = (string) ( $field['type'] ?? 'text' );

		if ( 'heading' === $type ) {
			$heading = EfbFormModel::overrides( 'heading' );
			unset( $heading['__replace__'] );
			$heading['id_']    = $key;
			$heading['dataId'] = $key . '-id';
			$heading['value']  = (string) ( $field['label'] ?? self::texts()['heading'] );

			return array( $heading );
		}

		$el                = array_merge( EfbFormModel::template( 'field_base' ), EfbFormModel::overrides( $type ) );
		$el['id_']         = $key;
		$el['dataId']      = $key . '-id';
		$el['type']        = $type;
		$el['name']        = (string) ( $field['label'] ?? '' );
		$el['placeholder'] = (string) ( $field['placeholder'] ?? ( $field['label'] ?? '' ) );
		$el['required']    = ! empty( $field['required'] ) ? '1' : '0';

		$help = trim( (string) ( $field['help'] ?? '' ) );
		if ( '' !== $help ) {
			$el['message'] = $help;
		}
		// EFB's own palette flags every Pro element so its renderer can gate it.
		if ( EfbFormModel::requires_pro_field( $type ) ) {
			$el['pro'] = '1';
		}

		// Desktop width from the optional width hint (mobile stays full-width).
		$width_map  = EfbFormModel::width_map();
		$el['size'] = $width_map[ (string) ( $field['width'] ?? 'full' ) ] ?? '100';

		$options = is_array( $field['options'] ?? null ) ? array_values( array_map( 'strval', $field['options'] ) ) : array();
		if ( 'yesNo' === $type && 2 === count( $options ) ) {
			$el['button_1_text'] = $options[0];
			$el['button_2_text'] = $options[1];
		}

		$children = array();
		if ( 'trmCheckbox' === $type ) {
			$sentence   = '' !== ( $options[0] ?? '' ) ? $options[0] : (string) ( $field['label'] ?? '' );
			$children[] = $this->child( 'option', $key, 1, self::consent_text( $sentence, $field['privacy_link'] ?? array() ), $used );
		} elseif ( 'table_matrix' === $type ) {
			$n = 0;
			foreach ( $options as $row ) {
				$children[] = $this->child( 'matrix_row', $key, ++$n, $row, $used );
			}
		} elseif ( array() !== $options && in_array( $type, array_merge( EfbFormModel::option_types(), array_values( EfbPayments::CHOICE_FIELDS ) ), true ) ) {
			$el = $this->apply_selection_cap( $el, count( $options ) );
			$n  = 0;
			foreach ( $options as $opt ) {
				$children[] = $this->child( 'option', $key, ++$n, $opt, $used );
			}
		}
		$el = $this->apply_plan_settings( $el, $field, $type );

		return array_merge( array( $el ), $children );
	}

	/**
	 * An option or matrix row owned by `$owner`, in EFB's flat child shape.
	 *
	 * @param array<string,bool> $used
	 * @return array<string,mixed>
	 */
	private function child( string $template, string $owner, int $n, string $value, array &$used ): array {
		$id              = $this->unique_id( $owner . ( 'matrix_row' === $template ? '_r' : '_o' ) . $n, $used );
		$child           = EfbFormModel::template( $template );
		$child['id_']    = $id;
		$child['dataId'] = $id . '-id';
		$child['parent'] = $owner;
		$child['type']   = 'matrix_row' === $template ? 'r_matrix' : 'option';
		$child['value']  = $value;
		$child['id_op']  = $id;

		return $child;
	}

	/**
	 * Turn on the notifications the admin approved, exactly the way EFB's own
	 * contact template does: the admin copy goes to the form's `email`, and the
	 * respondent's copy to the address typed in the form's email field.
	 *
	 * @param array<int, array<string, mixed>> $items
	 * @param array<string, mixed>             $plan
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_notifications( array $items, array $plan ): array {
		$admin = trim( (string) ( $plan['notify_email'] ?? '' ) );
		if ( '' !== $admin && false !== filter_var( $admin, FILTER_VALIDATE_EMAIL ) ) {
			$items[0]['email'] = $admin;
		}

		if ( ! empty( $plan['confirm_to_respondent'] ) ) {
			foreach ( $items as $item ) {
				if ( 'email' === ( $item['type'] ?? '' ) ) {
					$items[0]['sendEmail'] = true;
					$items[0]['email_to']  = (string) $item['id_'];
					break;
				}
			}
		}

		return $items;
	}

	/**
	 * Apply the plan's per-field settings, so a designed form arrives with its
	 * date bounds, upload limits and selection caps already right instead of
	 * needing a second round of edits. The catalog decides what survives.
	 *
	 * @param array<string,mixed> $el
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>
	 */
	private function apply_plan_settings( array $el, array $field, string $type ): array {
		$settings = $field['settings'] ?? array();
		if ( ! is_array( $settings ) || array() === $settings ) {
			return $el;
		}

		foreach ( EfbCustomizeSkill::settings_for_new_field( $settings, $type ) as $key => $value ) {
			$el[ $key ] = $value;
		}

		return $el;
	}

	/**
	 * Size a multi-select's selection cap to the options it actually owns.
	 *
	 * EFB refuses any pick once the selected count passes `maxSelect`, so the
	 * template's placeholder value would ship a field nobody can fill. Fields
	 * without the key (every non multi-select type) are returned untouched.
	 *
	 * @param array<string,mixed> $el
	 * @return array<string,mixed>
	 */
	private function apply_selection_cap( array $el, int $option_count ): array {
		if ( ! array_key_exists( 'maxSelect', $el ) ) {
			return $el;
		}

		$el['maxSelect'] = (string) max( 1, $option_count );

		return $el;
	}

	/**
	 * Encode to the exact string EFB stores: JSON with every " escaped to \".
	 *
	 * @param array<int, array<string, mixed>> $items
	 */
	public static function encode_for_storage( array $items ): string {
		$json = json_encode( $items, JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			$json = '[]';
		}

		return str_replace( '"', '\\"', $json );
	}

	/**
	 * @param array<string,bool> $used
	 */
	private function unique_id( string $base, array &$used ): string {
		$slug = strtolower( $base );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug ) ?? '';
		$slug = trim( $slug, '_' );
		if ( '' === $slug ) {
			$slug = 'field';
		}
		$slug = substr( $slug, 0, 40 );

		$id = $slug;
		$i  = 1;
		while ( isset( $used[ $id ] ) ) {
			++$i;
			$id = $slug . '_' . $i;
		}
		$used[ $id ] = true;

		return $id;
	}
}
