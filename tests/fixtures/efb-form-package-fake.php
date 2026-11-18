<?php
/**
 * A stand-in for Easy Form Builder's form package API (version 1.1) for unit tests: it records what the connector hands
 * to EFB and answers in EFB's documented shapes (docs/form-package-contract.en.md). The real API is exercised on the
 * real WordPress site (tests/efb_split_e2e.cjs).
 */

declare(strict_types=1);

namespace Emsfb {

	final class Form_Package {
		/** @var array<int,array<string,mixed>> */
		public static array $calls = array();

		/** @var array<string,mixed> */
		public static array $next = array();

		public static function validate( $package, array $args = array() ): array {
			self::$calls[] = array( 'validate', $package, $args );

			return array_merge(
				array(
					'ok'      => true,
					'errors'  => array(),
					'notices' => array(),
					'dropped' => array(),
					'form'    => array( 'form_structer' => $package['form']['form_structer'] ?? array() ),
				),
				self::$next['validate'] ?? array()
			);
		}

		public static function import( $package, array $args = array() ) {
			self::$calls[] = array( 'import', $package, $args );
			if ( isset( self::$next['import_error'] ) ) {
				return new \WP_Error( 'package_not_saved', (string) self::$next['import_error'] );
			}

			return array_merge(
				array(
					'form_id'      => 42,
					'import_token' => 'tok0123456789abcdef',
					'form_name'    => (string) ( $args['name'] ?? '' ),
					'form_type'    => 'form',
					'shortcode'    => '[EMS_Form_Builder id=42]',
					'dropped'      => array(),
					'notices'      => array(),
				),
				self::$next['import'] ?? array()
			);
		}

		public static function delete( $form_id, $token ) {
			self::$calls[] = array( 'delete', $form_id, $token );

			return 'tok0123456789abcdef' === $token ? true : new \WP_Error( 'package_not_imported', 'This form was not created by that import.' );
		}
	}
}

namespace {
	if ( ! function_exists( 'efb_form_package_api' ) ) {
		function efb_form_package_api(): array {
			return array(
				'version'   => 1.1,
				'schema'    => 1,
				'class'     => 'Emsfb\\Form_Package',
				'functions' => array( 'export', 'validate', 'import', 'delete', 'import_entries' ),
			);
		}
	}
}
