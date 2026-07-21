<?php
declare(strict_types=1);

namespace SitePilot\Connectors;

use SitePilot\Efb\EfbFormConverter;
use SitePilot\Efb\EfbFormModel;

/**
 * Easy Form Builder connector.
 *
 * Creates a real EFB form by writing a native `form_structer` row into
 * `{prefix}emsfb_form`, exactly as EFB's own save handler does
 * (json_encode + escape every " to \"). The form then appears in the EFB forms
 * list and is fully editable with EFB's builder.
 *
 * A `sitepilot_ai_efb_create_form` filter can override the persistence entirely.
 */
final class EasyFormBuilderConnector implements ConnectorInterface {

	public function get_id(): string {
		return 'easy_form_builder';
	}

	public function get_name(): string {
		return __( 'Easy Form Builder', 'sitepilot-ai' );
	}

	public function is_available(): bool {
		$detected = defined( 'EMSFB_PLUGIN_VERSION' )
			|| class_exists( '\\Emsfb\\Create' )
			|| $this->table_exists();

		/**
		 * Force EFB availability on/off.
		 *
		 * @param bool $available
		 */
		return (bool) apply_filters( 'sitepilot_ai_efb_available', $detected );
	}

	public function get_capabilities(): array {
		return array( 'form.create' );
	}

	public function validate_action( array $plan ): array {
		$errors  = array();
		$allowed = EfbFormModel::allowed_types();

		if ( '' === trim( (string) ( $plan['title'] ?? '' ) ) ) {
			$errors[] = __( 'The form has no title.', 'sitepilot-ai' );
		}

		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			$type = is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '';
			if ( ! in_array( $type, $allowed, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Unsupported Easy Form Builder field type "%s".', 'sitepilot-ai' ), $type );
			}
		}

		return $errors;
	}

	public function execute_action( array $plan, array $context = array() ): array {
		// Allow a custom integration to fully override persistence.
		if ( has_filter( 'sitepilot_ai_efb_create_form' ) ) {
			$override = apply_filters( 'sitepilot_ai_efb_create_form', null, $plan, $context );
			if ( is_array( $override ) ) {
				return $override;
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';

		$items = ( new EfbFormConverter() )->convert( $plan );
		$value = EfbFormConverter::encode_for_storage( $items );
		$title = (string) ( $plan['title'] ?? 'Form' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->insert(
			$table,
			array(
				'form_name'       => $title,
				'form_structer'   => $value,
				'form_email'      => '',
				'form_type'       => 'form',
				'form_created_by' => (string) get_current_user_id(),
				'form_access_by'  => '',
				'form_create_date' => gmdate( 'Y-m-d H:i:s' ),
				'status'          => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $ok ) {
			return array(
				'status'  => 'failed',
				'code'    => 'execution_failed',
				'message' => __( 'The form could not be saved into Easy Form Builder.', 'sitepilot-ai' ),
			);
		}

		$form_id = (int) $wpdb->insert_id;

		return array(
			'status'    => 'success',
			'connector' => $this->get_name(),
			'form_id'   => $form_id,
			'edit_url'  => admin_url( 'admin.php?page=Emsfb' ),
			'shortcode' => sprintf( '[EMS_Form_Builder id=%d]', $form_id ),
			'is_draft'  => false,
			'warnings'  => array(),
		);
	}

	public function get_health(): array {
		return array(
			'available' => $this->is_available(),
			'version'   => defined( 'EMSFB_PLUGIN_VERSION' ) ? (string) EMSFB_PLUGIN_VERSION : '',
			'notes'     => $this->is_available()
				? __( 'Detected. New forms are created directly in Easy Form Builder.', 'sitepilot-ai' )
				: __( 'Easy Form Builder is not active.', 'sitepilot-ai' ),
		);
	}

	private function table_exists(): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return false;
		}
		$table = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
