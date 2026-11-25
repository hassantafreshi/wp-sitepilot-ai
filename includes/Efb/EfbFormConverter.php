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
					'country'       => __( 'Country', 'ai-site-assistant-autopilot' ),
					'state'         => __( 'State / Province', 'ai-site-assistant-autopilot' ),
					'city'          => __( 'City', 'ai-site-assistant-autopilot' ),
					'postal_code'   => __( 'Postal code', 'ai-site-assistant-autopilot' ),
					'address_line'  => __( 'Address', 'ai-site-assistant-autopilot' ),
					'username'      => __( 'Username or email', 'ai-site-assistant-autopilot' ),
					'password'      => __( 'Password', 'ai-site-assistant-autopilot' ),
				)
			);
		}

		return self::$texts;
	}

	/**
	 * Labels for the country and state field that come along with a state or city list.
	 *
	 * @return array<string,string>
	 */
	public static function geo_labels(): array {
		$texts = self::texts();

		return array(
			'conturyList'   => $texts['country'],
			'stateProvince' => $texts['state'],
		);
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
		// QA5-012: field ids are random like the ones EFB's builder makes, so two forms on one page never share an id;
		// the plan's own seed keeps them the same every time this plan is converted (preview, logic, import).
		$this->seed = is_string( $plan['id_seed'] ?? null ) && '' !== $plan['id_seed'] ? $plan['id_seed'] : self::new_seed();
		$texts      = self::texts();
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
		$step['amount'] = $amount;

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
				$new_step['amount']            = $amount;
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

			$key = $this->field_id( (string) ( $field['key'] ?? ( $field['label'] ?? 'field' ) ), $used_ids );
			foreach ( $this->field_items( $field, $key, $used_ids ) as $item ) {
				// EFB numbers the fields and gives an option its field's number. It compares these numbers to
				// tie a state list to the country list before it, and after its own Save they are strings, so
				// two neighbours must stay neighbours: options do not use numbers up.
				if ( ! in_array( $item['type'] ?? '', array( 'option', 'r_matrix' ), true ) ) {
					++$amount;
				}
				$item['step']   = (string) $step_no;
				$item['amount'] = $amount;
				$items[]        = $item;
			}
		}

		// Online payment goes on the last page: the priced fields, the total, then the gateway itself.
		if ( is_array( $plan['payment'] ?? null ) ) {
			$payment = $plan['payment'];
			foreach ( $this->build_payment( $payment, array_map( 'strval', array_keys( $used_ids ) ) ) as $item ) {
				if ( ! in_array( $item['type'] ?? '', array( 'option', 'r_matrix' ), true ) ) {
					++$amount;
				}
				++$step_contents;
				$item['step']   = (string) $step_no;
				$item['amount'] = $amount;
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
		// F16: a new form gets EFB's own accessibility defaults (inline checks on leaving a field, an error summary,
		// "leave this page?" on several steps) from efb_form_defaults_efb(); keys already set stay.
		if ( function_exists( 'efb_form_defaults_efb' ) ) {
			$a11y = efb_form_defaults_efb( $step_no );
			if ( is_array( $a11y ) ) {
				$items[0] = $items[0] + array_map( 'strval', array_filter( $a11y, 'is_scalar' ) );
			}
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

		// A state list needs the country field it follows, a city list its state: they come along when the
		// form has none to attach to.
		$labels = self::geo_labels();
		$queue  = array();
		foreach ( (array) ( $field['with'] ?? array() ) as $companion ) {
			if ( isset( $labels[ $companion ] ) ) {
				$queue[] = array(
					'type'     => $companion,
					'label'    => $labels[ $companion ],
					'required' => ! empty( $field['required'] ),
					'width'    => (string) ( $field['width'] ?? 'full' ),
				);
			}
		}
		$queue[] = $field;

		$items      = array();
		$this->seed = self::new_seed();
		foreach ( $queue as $one ) {
			$key = $this->field_id( (string) ( $one['label'] ?? 'field' ), $used );
			foreach ( $this->field_items( $one, $key, $used ) as $item ) {
				$item['step']   = '1';
				$item['amount'] = 0;
				$items[]        = $item;
			}
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
		if ( $with_gateway && EfbPayments::is_pay00( $gateway ) ) {
			// A payment framework gateway's field is the row EFB's own builder makes for it (its add-on's template).
			$out = array_merge( $out, EfbPayments::gateway_rows( $gateway, $texts[ 'pay_' . $gateway ] ?? $texts['pay_zarinpal'], $used ) );
		} elseif ( $with_gateway && null !== EfbPayments::gateway_field( $gateway ) ) {
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
			if ( in_array( $type, array( 'form', 'option', 'r_matrix', '' ), true ) || ! isset( $item['id_'] ) ) {
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
				// A heading keeps its text in value; the editor reports name, else value, the same way.
				'label'   => (string) ( '' !== (string) ( $item['name'] ?? '' ) ? $item['name'] : ( $item['value'] ?? '' ) ),
				'type'    => $type,
				'step'    => (string) ( $item['step'] ?? '' ),
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
		$step['amount']  = 0;

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

		if ( 'heading' === $type || 'link' === $type || 'html' === $type ) {
			return array( $this->display_item( $type, $field, $key ) );
		}
		if ( 'address' === $type ) {
			// F17: a national address scheme is built by Easy Form Builder itself (its server templates).
			$scheme = strtolower( (string) ( $field['settings']['scheme'] ?? ( $field['settings']['address_scheme'] ?? '' ) ) );
			$rows   = '' !== $scheme ? self::efb_address_rows( $scheme, $field, $used ) : array();
			if ( array() !== $rows ) {
				return $rows;
			}
			return $this->address_items( $field, $key, $used );
		}

		$el = array_merge( EfbFormModel::template( 'field_base' ), EfbFormModel::overrides( $type ) );
		// Keys EFB's own palette leaves off this type (a recorder has no input icon).
		foreach ( (array) ( $el['__drop__'] ?? array() ) as $drop ) {
			unset( $el[ (string) $drop ] );
		}
		unset( $el['__drop__'] );
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
		} elseif ( in_array( $type, array( 'conturyList', 'stateProvince', 'cityList' ), true ) ) {
			$children = $this->geo_children( $type, $key, $used );
		} elseif ( array() !== $options && in_array( $type, array_merge( EfbFormModel::option_types(), array_values( EfbPayments::CHOICE_FIELDS ) ), true ) ) {
			$el = $this->apply_selection_cap( $el, count( $options ) );
			$n  = 0;
			foreach ( $options as $opt ) {
				$child = $this->child( 'option', $key, ++$n, $opt, $used );
				if ( 'imgRadio' === $type ) {
					// EFB's image picker: a caption, a short description and the picture. The picture starts as
					// EFB's own placeholder; the admin chooses the real one in the field's settings.
					$child['sub_value'] = (string) ( $field['descriptions'][ $n - 1 ] ?? '' );
					$child['src']       = self::placeholder_image();
				}
				$children[] = $child;
			}
		}
		if ( 'maps' === $type ) {
			// The palette's label is not a placeholder here; a map has none.
			$el['placeholder'] = $el['name'];
		}
		// A date in a Jalali or Hijri calendar (EfbFeatureGate::calendar_field()): EFB's advanced date field with that
		// calendar, in the row shape EFB's own upgrade of a date field writes (Calendar::upgrade_row(), F06 §4.1).
		$calendar = 'date' === $type ? EfbFeatureGate::date_calendars( $field ) : null;
		if ( null !== $calendar ) {
			$el['type']        = 'advdate';
			$el['elementId']   = 'advdate';
			$el['cal_v']       = 1;
			$el['cal_default'] = $calendar['default'];
			$el['cal_allowed'] = $calendar['allowed'];
			$el['cal_switch']  = count( $calendar['allowed'] ) > 1 ? 1 : 0;
			$el['date_mode']   = 'single';
			$el['date_min']    = array( 'type' => 'none' );
			$el['date_max']    = array( 'type' => 'none' );
			unset( $field['settings']['calendar'], $field['settings']['calendars'] );
		}
		$el = $this->apply_plan_settings( $el, $field, $type );

		return array_merge( array( $el ), $children );
	}

	/**
	 * The two fields EFB's sign-in form reads, with the fixed ids its runtime looks for. Only the ones the
	 * open form does not have yet are returned.
	 *
	 * @param string[] $existing_ids
	 * @return array<int,array<string,mixed>>
	 */
	public function build_login_fields( array $existing_ids = array() ): array {
		$texts = self::texts();
		$out   = array();
		foreach ( array(
			'emaillogin'    => array( 'text', $texts['username'] ),
			'passwordlogin' => array( 'password', $texts['password'] ),
		) as $id => $spec ) {
			if ( in_array( $id, $existing_ids, true ) ) {
				continue;
			}
			$el                = EfbFormModel::template( 'field_base' );
			$el['id_']         = $id;
			$el['dataId']      = $id . '-id';
			$el['type']        = $spec[0];
			$el['elementId']   = $spec[0];
			$el['name']        = $spec[1];
			$el['placeholder'] = $spec[1];
			$el['required']    = '1';
			$out[]             = $el;
		}

		return $out;
	}

	/**
	 * A display-only element (heading, link, HTML block): EFB keeps these in a short shape of their own.
	 *
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>
	 */
	private function display_item( string $type, array $field, string $key ): array {
		$item = EfbFormModel::overrides( $type );
		unset( $item['__replace__'] );
		$item['id_']    = $key;
		$item['dataId'] = $key . '-id';
		$label          = (string) ( $field['label'] ?? '' );

		if ( 'link' === $type ) {
			$item['value'] = '' !== $label ? $label : (string) ( $field['url'] ?? '' );
			$item['href']  = (string) ( $field['url'] ?? '' );
		} elseif ( 'html' === $type ) {
			// EFB stores the markup with its own two escapes and decodes them when it draws the form.
			$item['value'] = str_replace( array( '"', "\r\n", "\n", "\r" ), array( '@!', '@efb@nq#', '@efb@nq#', '@efb@nq#' ), (string) ( $field['html'] ?? '' ) );
		} else {
			$item['value'] = '' !== $label ? $label : self::texts()['heading'];
		}

		return $item;
	}

	/**
	 * EFB's Address tile: country, state and city in the order its runtime needs (picking a country reloads
	 * the state list right after it, a state the city list after that), then postal code and the address line.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,bool>  $used
	 * @return array<int,array<string,mixed>>
	 */
	private function address_items( array $field, string $key, array &$used ): array {
		$texts = self::texts();
		$label = trim( (string) ( $field['label'] ?? '' ) );
		$parts = array(
			array( 'conturyList', $texts['country'], 'conturyList', 'half' ),
			array( 'stateProvince', $texts['state'], 'stateProvince', 'half' ),
			array( 'cityList', $texts['city'], 'cityList', 'half' ),
			array( 'text', $texts['postal_code'], 'postalcode', 'half' ),
			array( 'text', '' !== $label && ! self::is_generic_address_label( $label ) ? $label : $texts['address_line'], 'address_line', 'full' ),
		);
		$out   = array();
		$first = true;
		foreach ( $parts as $part ) {
			$id                    = $first ? $key : $this->unique_id( $key . '_' . strtolower( $part[2] ), $used );
			$first                 = false;
			$items                 = $this->field_items(
				array(
					'type'     => $part[0],
					'label'    => $part[1],
					'required' => ! empty( $field['required'] ),
					'width'    => $part[3],
				),
				$id,
				$used
			);
			$items[0]['elementId'] = $part[2];
			foreach ( $items as $item ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	private static function is_generic_address_label( string $label ): bool {
		return (bool) preg_match( '/^(full |postal |mailing |shipping |billing |home )?address$/i', $label ) || in_array( $label, array( 'آدرس', 'نشانی', 'العنوان' ), true );
	}

	/**
	 * The ready-made options of a geographic field, exactly as EFB's palette creates them.
	 *
	 * @param array<string,bool> $used
	 * @return array<int,array<string,mixed>>
	 */
	private function geo_children( string $type, string $owner, array &$used ): array {
		$geo      = EfbFormModel::geo_presets();
		$children = array();
		$make     = function ( string $id, string $value, string $id_op, array $extra = array() ) use ( $owner, &$used ): array {
			$unique          = isset( $used[ $id ] ) ? $this->unique_id( $owner . '_' . $id, $used ) : $id;
			$used[ $unique ] = true;
			$child           = EfbFormModel::template( 'option' );
			$child['id_']    = $unique;
			$child['dataId'] = $unique . '-id';
			$child['parent'] = $owner;
			$child['value']  = $value;
			$child['id_op']  = $id_op;

			return array_merge( $child, $extra );
		};

		if ( 'conturyList' === $type ) {
			$n = 0;
			foreach ( (array) ( $geo['countries'] ?? array() ) as $country ) {
				$name       = (string) ( $country[1] ?? '' );
				$local      = (string) ( $country[2] ?? '' );
				$children[] = $make( $owner . '_c' . ( ++$n ), '' !== $local && $local !== $name ? $name . ' (' . $local . ')' : $name, strtolower( (string) ( $country[0] ?? '' ) ) );
			}
		} elseif ( 'stateProvince' === $type ) {
			foreach ( (array) ( $geo['states'] ?? array() ) as $state ) {
				$children[] = $make( (string) $state['id'], (string) $state['value'], (string) $state['id_op'], array( 's2' => (string) $state['s2'] ) );
			}
		} else {
			foreach ( (array) ( $geo['cities'] ?? array() ) as $city ) {
				$children[] = $make( (string) $city['id'], (string) $city['value'], (string) $city['id'] );
			}
		}

		return $children;
	}

	/** EFB's own placeholder picture for an image option, in the form EFB stores an address. */
	private static function placeholder_image(): string {
		$url = function_exists( 'plugins_url' ) && defined( 'EMSFB_PLUGIN_FILE' )
			? plugins_url( 'includes/admin/assets/image/header.png', EMSFB_PLUGIN_FILE )
			: ( function_exists( 'plugins_url' ) ? plugins_url( 'easy-form-builder/includes/admin/assets/image/header.png' ) : '' );

		// The same three steps EFB's builder uses: the scheme's two slashes become one marker, then every slash.
		$url = (string) preg_replace( '#(https?:)//#', '$1@efb@', (string) $url );

		return (string) preg_replace( '#/+#', '@efb@', $url );
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

	/** @var array<int,string> Province lists of national address schemes that EFB's page loads (F17 needs_states). */
	private array $needs_states = array();

	/**
	 * The rows of an address in a national scheme (F17: ir, sa, ae, eg, us, uk, ca, de, jp, tr, intl) exactly as
	 * Easy Form Builder's server templates make them (`field_templates_efb('address', ['scheme' => …])`). Empty when
	 * EFB does not offer the scheme here (an older EFB, or the address formats need a higher plan): the plain address
	 * block is used then. A fixed country's province list comes back empty (`needs_states`): EFB's page loads it.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,bool>  $used
	 * @return array<int,array<string,mixed>>
	 */
	private function efb_address_rows( string $scheme, array $field, array &$used ): array {
		if ( ! preg_match( '/^[a-z]{2,5}$/', $scheme ) || ! EfbCapabilities::state( 'setting:addr_scheme' )['available'] || ! function_exists( 'get_efbFunction' ) ) {
			return array();
		}
		$formats = EfbCapabilities::block( 'name_address' )['address_schemes'] ?? array();
		if ( ! is_array( $formats ) || ! in_array( $scheme, $formats, true ) ) {
			return array();
		}
		try {
			$efb = get_efbFunction();
			$tpl = is_object( $efb ) && method_exists( $efb, 'field_templates_efb' ) ? $efb->field_templates_efb(
				'address',
				array(
					'scheme'   => $scheme,
					'required' => ! empty( $field['required'] ),
				)
			) : null;
		} catch ( \Throwable $e ) {
			$tpl = null;
		}
		$rows = is_array( $tpl ) && is_array( $tpl['rows'] ?? null ) ? array_values( array_filter( $tpl['rows'], 'is_array' ) ) : array();
		foreach ( $rows as $row ) {
			$id = (string) ( $row['id_'] ?? '' );
			if ( '' === $id || isset( $used[ $id ] ) ) {
				return array();
			}
		}
		foreach ( $rows as $row ) {
			$used[ (string) $row['id_'] ] = true;
		}
		foreach ( (array) ( $tpl['needs_states'] ?? array() ) as $needs ) {
			$this->needs_states[] = is_array( $needs ) ? (string) ( $needs['id_'] ?? ( $needs['id'] ?? '' ) ) : (string) $needs;
		}

		return $rows;
	}

	/**
	 * Province lists of the last converted form that Easy Form Builder's page loads itself (F17 `needs_states`).
	 *
	 * @return array<int,string>
	 */
	public function needs_states(): array {
		return $this->needs_states;
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

		// EFB 4.3.2 (owner rule): the FIRST e-mail field of a new form is the visitor's confirmation recipient
		// (`noti` 1, row 0 `email_to` = its id, `sendEmail` on); `email_to` holds one field, so later e-mail fields
		// stay off. When the admin asked for no confirmation, the first field is switched off (`noti` 0) and
		// `email_to` is empty; `sendEmail` then stays on only for the admin's own notification.
		$confirm = ! empty( $plan['confirm_to_respondent'] );
		$first   = null;
		foreach ( $items as $i => $item ) {
			if ( 0 === $i || 'email' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			if ( null === $first ) {
				$first              = $i;
				$items[ $i ]['noti'] = $confirm ? 1 : 0;
				continue;
			}
			unset( $items[ $i ]['noti'] );
		}
		if ( $confirm && null !== $first ) {
			$items[0]['sendEmail'] = true;
			$items[0]['email_to']  = (string) $items[ $first ]['id_'];
			// EFB's own rule has the last word when it publishes it (a field already switched on is kept).
			if ( EfbCapabilities::email_confirmation_default_on() && function_exists( 'efb_email_recipient_default_efb' ) ) {
				$checked = efb_email_recipient_default_efb( $items );
				$items   = is_array( $checked ) && isset( $checked[0] ) ? array_values( $checked ) : $items;
			}
		} else {
			// No recipient; `sendEmail` keeps the template's "off" (the admin's own copy goes to row 0 `email`).
			$items[0]['email_to'] = '';
		}

		return $items;
	}

	/**
	 * Whether a new form confirms to the visitor by e-mail. With EFB 4.3.2 (`capabilities.email_confirmation`) it is
	 * on unless the model, on the admin's word, set `confirm_to_respondent` to false; older EFB keeps the switch the
	 * model set.
	 *
	 * @param array<string,mixed> $plan     The validated plan.
	 * @param array<string,mixed> $raw_form The model's form object before validation.
	 */
	public static function confirmation_choice( array $plan, array $raw_form = array() ): bool {
		if ( ! EfbCapabilities::email_confirmation_default_on() ) {
			return ! empty( $plan['confirm_to_respondent'] );
		}
		if ( ! array_key_exists( 'confirm_to_respondent', $raw_form ) || null === $raw_form['confirm_to_respondent'] || '' === $raw_form['confirm_to_respondent'] ) {
			return true;
		}
		$value = $raw_form['confirm_to_respondent'];
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
		}

		return ! in_array( $value, array( false, 0, '0', 'false', 'no', 'off' ), true );
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

	/** The seed of this conversion's field ids (a plan's id_seed, else a fresh one). */
	private string $seed = '';

	/** A fresh seed for a plan's field ids. */
	public static function new_seed(): string {
		return bin2hex( random_bytes( 8 ) );
	}

	/**
	 * A field's id as EFB's builder makes one: nine lower-case letters and digits (Math.random().toString(36)), random
	 * across forms, the same for the same plan and key, starting with a letter, unique in this form.
	 *
	 * @param array<string,bool> $used
	 */
	private function field_id( string $key, array &$used ): string {
		for ( $n = 0; ; ++$n ) {
			$hex = hash( 'sha256', $this->seed . '|' . $key . '|' . $n );
			$id  = chr( ord( 'a' ) + (int) hexdec( substr( $hex, 13, 2 ) ) % 26 ) . substr( str_pad( base_convert( substr( $hex, 0, 13 ), 16, 36 ), 11, '0', STR_PAD_LEFT ), -8 );
			if ( ! isset( $used[ $id ] ) ) {
				$used[ $id ] = true;
				return $id;
			}
		}
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
