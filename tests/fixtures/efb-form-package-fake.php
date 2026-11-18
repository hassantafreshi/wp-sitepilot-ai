<?php
/**
 * A stand-in for Easy Form Builder's form package API (version 1.1, or 1.2 with `$api`) for unit tests: it records what the connector hands
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

		/** @var array<string,mixed> Keys of efb_form_package_api() for this test (version 1.2: read, update, publish). */
		public static array $api = array();

		/** @var array<int,array<string,mixed>> 1.2: the stored forms read() / update() answer for. */
		public static array $forms = array();

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

		public static function read( $form_id ) {
			self::$calls[] = array( 'read', $form_id );
			if ( ! isset( self::$forms[ (int) $form_id ] ) ) {
				return new \WP_Error( 'package_form_missing', 'The form was not found.' );
			}
			$f = self::$forms[ (int) $form_id ];

			return array(
				'form_id'   => (int) $form_id,
				'form_name' => (string) $f['name'],
				'form_type' => 'form',
				'status'    => (string) ( $f['status'] ?? 'published' ),
				'base_hash' => hash( 'sha256', json_encode( $f['rows'] ) . $f['name'] ),
				'rows'      => $f['rows'],
			);
		}

		public static function update( $form_id, $patches, array $args = array() ) {
			self::$calls[] = array( 'update', $form_id, $patches, $args );
			if ( isset( self::$next['update_error'] ) ) {
				return new \WP_Error( (string) self::$next['update_error'][0], (string) self::$next['update_error'][1], self::$next['update_error'][2] ?? array() );
			}
			$read = self::read( $form_id );
			if ( is_wp_error( $read ) ) {
				return $read;
			}
			if ( ( $args['base_hash'] ?? '' ) !== $read['base_hash'] ) {
				return new \WP_Error( 'stale_hash', 'The form changed since it was read.', array( 'status' => 409 ) );
			}

			return array_merge(
				array(
					'form_id'   => (int) $form_id,
					'form_name' => $read['form_name'],
					'status'    => $read['status'],
					'base_hash' => 'new' . $read['base_hash'],
					'changed'   => array(),
					'warnings'  => array(),
				),
				self::$next['update'] ?? array()
			);
		}

		public static function publish( $form_id ) {
			self::$calls[] = array( 'publish', $form_id );
			if ( ! isset( self::$forms[ (int) $form_id ] ) ) {
				return new \WP_Error( 'package_form_missing', 'The form was not found.' );
			}
			self::$forms[ (int) $form_id ]['status'] = 'published';

			return true;
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
			return array_merge(
				array(
					'version'   => 1.1,
					'schema'    => 1,
					'class'     => 'Emsfb\\Form_Package',
					'functions' => array( 'export', 'validate', 'import', 'delete', 'import_entries' ),
				),
				\Emsfb\Form_Package::$api
			);
		}
	}
}
