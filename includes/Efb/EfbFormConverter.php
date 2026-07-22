<?php
declare(strict_types=1);

namespace SitePilot\Efb;

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

		$settings = EfbFormModel::template( 'form_settings' );
		$settings['formName'] = $title;
		$settings['steps']    = '1';
		if ( isset( $settings['thank_you_message'] ) && is_array( $settings['thank_you_message'] ) ) {
			$confirmation = trim( (string) ( $plan['confirmation_message'] ?? '' ) );
			if ( '' !== $confirmation ) {
				$settings['thank_you_message']['thankYou'] = $confirmation;
			}
		}

		$step         = EfbFormModel::template( 'step' );
		$step['name'] = $title;
		$step['step'] = '1';
		$amount       = 1;
		$step['amount'] = (string) $amount;

		$items    = array( $settings, $step );
		$used_ids = array( '1' => true ); // step id_ is "1".

		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? 'text' );
			++$amount;

			$key = $this->unique_id( (string) ( $field['key'] ?? ( $field['label'] ?? 'field' ) ), $used_ids );

			if ( 'heading' === $type ) {
				$heading            = EfbFormModel::overrides( 'heading' );
				unset( $heading['__replace__'] );
				$heading['id_']    = $key;
				$heading['dataId'] = $key . '-id';
				$heading['value']  = (string) ( $field['label'] ?? 'Heading' );
				$heading['amount'] = (string) $amount;
				$heading['step']   = '1';
				$items[]           = $heading;
				continue;
			}

			$el              = array_merge( EfbFormModel::template( 'field_base' ), EfbFormModel::overrides( $type ) );
			$el['id_']       = $key;
			$el['dataId']    = $key . '-id';
			$el['type']      = $type;
			$el['name']      = (string) ( $field['label'] ?? '' );
			$el['placeholder'] = (string) ( $field['placeholder'] ?? ( $field['label'] ?? '' ) );
			$el['required']  = ! empty( $field['required'] ) ? '1' : '0';
			$el['step']      = '1';
			$el['amount']    = (string) $amount;

			// Desktop width from the optional width hint (mobile stays full-width).
			$width_map    = EfbFormModel::width_map();
			$width        = (string) ( $field['width'] ?? 'full' );
			$el['size']   = $width_map[ $width ] ?? '100';

			$items[] = $el;

			$options = $field['options'] ?? array();
			if ( is_array( $options ) && array() !== $options && in_array( $type, EfbFormModel::option_types(), true ) ) {
				$n = 0;
				foreach ( $options as $opt ) {
					++$n;
					++$amount;
					$opt_id            = $this->unique_id( $key . '_o' . $n, $used_ids );
					$option            = EfbFormModel::template( 'option' );
					$option['id_']     = $opt_id;
					$option['dataId']  = $opt_id . '-id';
					$option['parent']  = $key;
					$option['type']    = 'option';
					$option['value']   = (string) $opt;
					$option['id_op']   = $opt_id;
					$option['step']    = '1';
					$option['amount']  = (string) $amount;
					$items[]           = $option;
				}
			}
		}

		return $items;
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
