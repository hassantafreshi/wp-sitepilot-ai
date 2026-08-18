<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Converts a validated, clean form plan into a real EFB `form_structer` array.
 *
 * Input plan (from FormPlanValidator, EFB-configured):
 *   [ 'title', 'description'?, 'confirmation_message'?, 'fields' => [
 *       ['key','type','label','required'(bool),'placeholder','options'(string[])], ... ] ]
 *
 * Output: flat array [ settings, step, field, option, option, field, ... ] matching EFB 4.x.
 * Pure PHP (no WordPress) so it is unit testable.
 */
final class EfbFormConverter {

	/**
	 * @param array<string, mixed> $plan
	 * @return array<int, array<string, mixed>>
	 */
	public function convert( array $plan ): array {
		$title = (string) ( $plan['title'] ?? 'Form' );
		if ( '' === trim( $title ) ) {
			$title = 'Form';
		}

		$settings             = EfbFormModel::template( 'form_settings' );
		$settings['formName'] = $title;
		if ( isset( $settings['thank_you_message'] ) && is_array( $settings['thank_you_message'] ) ) {
			$confirmation = trim( (string) ( $plan['confirmation_message'] ?? '' ) );
			if ( '' !== $confirmation ) {
				$settings['thank_you_message']['thankYou'] = $confirmation;
			}
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
				$new_step                      = $this->build_step( '' !== $label ? $label : 'Step ' . $step_no );
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

			++$amount;
			++$step_contents;

			$key = $this->unique_id( (string) ( $field['key'] ?? ( $field['label'] ?? 'field' ) ), $used_ids );

			if ( 'heading' === $type ) {
				$heading = EfbFormModel::overrides( 'heading' );
				unset( $heading['__replace__'] );
				$heading['id_']    = $key;
				$heading['dataId'] = $key . '-id';
				$heading['value']  = (string) ( $field['label'] ?? 'Heading' );
				$heading['amount'] = (string) $amount;
				$heading['step']   = (string) $step_no;
				$items[]           = $heading;
				continue;
			}

			$el                = array_merge( EfbFormModel::template( 'field_base' ), EfbFormModel::overrides( $type ) );
			$el['id_']         = $key;
			$el['dataId']      = $key . '-id';
			$el['type']        = $type;
			$el['name']        = (string) ( $field['label'] ?? '' );
			$el['placeholder'] = (string) ( $field['placeholder'] ?? ( $field['label'] ?? '' ) );
			$el['required']    = ! empty( $field['required'] ) ? '1' : '0';
			$el['step']        = (string) $step_no;
			$el['amount']      = (string) $amount;

			// Desktop width from the optional width hint (mobile stays full-width).
			$width_map  = EfbFormModel::width_map();
			$width      = (string) ( $field['width'] ?? 'full' );
			$el['size'] = $width_map[ $width ] ?? '100';

			$options     = $field['options'] ?? array();
			$has_options = is_array( $options ) && array() !== $options && in_array( $type, EfbFormModel::option_types(), true );
			if ( $has_options ) {
				$el = $this->apply_selection_cap( $el, count( $options ) );
			}
			$el = $this->apply_plan_settings( $el, $field, $type );

			$items[] = $el;

			if ( $has_options ) {
				$n = 0;
				foreach ( $options as $opt ) {
					++$n;
					++$amount;
					$opt_id           = $this->unique_id( $key . '_o' . $n, $used_ids );
					$option           = EfbFormModel::template( 'option' );
					$option['id_']    = $opt_id;
					$option['dataId'] = $opt_id . '-id';
					$option['parent'] = $key;
					$option['type']   = 'option';
					$option['value']  = (string) $opt;
					$option['id_op']  = $opt_id;
					$option['step']   = (string) $step_no;
					$option['amount'] = (string) $amount;
					$items[]          = $option;
				}
			}
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

		return $items;
	}

	/**
	 * Build the EFB items (field + option items) for a single field, used when the
	 * chat adds a field to a live form. Amounts are left at "0" — the browser sets
	 * them on insert. IDs are made unique against the provided existing IDs.
	 *
	 * @param array<string, mixed> $field        {type,label,required,placeholder,width,options}
	 * @param string[]             $existing_ids  IDs already present in the live form.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_field( array $field, array $existing_ids = array() ): array {
		$used = array();
		foreach ( $existing_ids as $id ) {
			$used[ (string) $id ] = true;
		}

		$type = (string) ( $field['type'] ?? 'text' );
		$key  = $this->unique_id( (string) ( $field['label'] ?? 'field' ), $used );

		if ( 'heading' === $type ) {
			$h = EfbFormModel::overrides( 'heading' );
			unset( $h['__replace__'] );
			$h['id_']    = $key;
			$h['dataId'] = $key . '-id';
			$h['value']  = (string) ( $field['label'] ?? 'Heading' );
			$h['amount'] = '0';
			$h['step']   = '1';

			return array( $h );
		}

		$el                = array_merge( EfbFormModel::template( 'field_base' ), EfbFormModel::overrides( $type ) );
		$el['id_']         = $key;
		$el['dataId']      = $key . '-id';
		$el['type']        = $type;
		$el['name']        = (string) ( $field['label'] ?? '' );
		$el['placeholder'] = (string) ( $field['placeholder'] ?? ( $field['label'] ?? '' ) );
		$el['required']    = ! empty( $field['required'] ) ? '1' : '0';
		$el['step']        = '1';
		$el['amount']      = '0';

		$width_map  = EfbFormModel::width_map();
		$el['size'] = $width_map[ (string) ( $field['width'] ?? 'full' ) ] ?? '100';

		$options     = $field['options'] ?? array();
		$has_options = is_array( $options ) && array() !== $options && in_array( $type, EfbFormModel::option_types(), true );
		if ( $has_options ) {
			$el = $this->apply_selection_cap( $el, count( $options ) );
		}
		$el = $this->apply_plan_settings( $el, $field, $type );

		$items = array( $el );

		if ( $has_options ) {
			$n = 0;
			foreach ( $options as $opt ) {
				++$n;
				$oid              = $this->unique_id( $key . '_o' . $n, $used );
				$option           = EfbFormModel::template( 'option' );
				$option['id_']    = $oid;
				$option['dataId'] = $oid . '-id';
				$option['parent'] = $key;
				$option['type']   = 'option';
				$option['value']  = (string) $opt;
				$option['id_op']  = $oid;
				$option['step']   = '1';
				$option['amount'] = '0';
				$items[]          = $option;
			}
		}

		return $items;
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
