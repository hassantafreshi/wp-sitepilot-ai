<?php
/**
 * Not a test. A stand-in for Easy Form Builder's get_efbFunction()->field_templates_efb( $palette_id ): the field row
 * EFB's builder makes for a payment framework gateway (shape of a capabilities dump of EFB 4.3.2 dev4). Only the slugs
 * a test lists in $GLOBALS['aisa_test_field_templates'] have a template; every other one answers no rows, as EFB does
 * for a gateway whose add-on is off. The object has no other method, so the plugin's other get_efbFunction() readers
 * (method_exists() guarded) see nothing.
 */

declare(strict_types=1);

if ( ! function_exists( 'get_efbFunction' ) ) {
	final class AISA_Test_Efb_Function {
		/**
		 * @param array<string,mixed> $args
		 * @return array<string,mixed>
		 */
		public function field_templates_efb( string $palette_id, array $args = array() ): array {
			$listed = (array) ( $GLOBALS['aisa_test_field_templates'] ?? array() );
			if ( ! in_array( $palette_id, $listed, true ) ) {
				return array( 'rows' => array() );
			}

			return array(
				'palette_id' => $palette_id,
				'rows'       => array(
					array(
						'id_'       => 'ms2u0u927',
						'dataId'    => 'ms2u0u927-id',
						'type'      => $palette_id,
						'elementId' => $palette_id,
						'name'      => (string) ( $args['label'] ?? $palette_id ),
						'required'  => 0,
					),
				),
				'form_patch' => array(
					'type'   => 'payment',
					'getway' => $palette_id,
				),
			);
		}
	}

	function get_efbFunction(): AISA_Test_Efb_Function {
		return new AISA_Test_Efb_Function();
	}
}
