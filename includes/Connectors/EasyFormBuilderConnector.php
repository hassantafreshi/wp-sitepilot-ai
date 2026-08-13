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

	/** The first EFB release verified against SitePilot's storage converter. */
	private const MIN_SUPPORTED_VERSION = '4.1.0';

	/** @var string[] */
	private const REQUIRED_COLUMNS = array(
		'form_id',
		'form_name',
		'form_structer',
		'form_email',
		'form_type',
		'form_created_by',
		'form_access_by',
		'form_create_date',
		'status',
	);

	public function get_id(): string {
		return 'easy_form_builder';
	}

	public function get_name(): string {
		return __( 'Easy Form Builder', 'sitepilot-ai' );
	}

	public function is_available(): bool {
		$detected = $this->plugin_is_active()
			&& $this->version_is_supported()
			&& $this->has_required_schema();

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

		if ( ! $this->is_available() ) {
			return array( __( 'Easy Form Builder is not active or does not match the supported 4.1+ storage contract.', 'sitepilot-ai' ) );
		}

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
		$errors = $this->validate_action( $plan );
		if ( array() !== $errors ) {
			return array(
				'status'  => 'failed',
				'code'    => 'connector_not_compatible',
				'message' => implode( ' ', $errors ),
			);
		}

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
				'form_name'        => $title,
				'form_structer'    => $value,
				'form_email'       => '',
				'form_type'        => 'form',
				'form_created_by'  => (string) get_current_user_id(),
				'form_access_by'   => '',
				'form_create_date' => gmdate( 'Y-m-d H:i:s' ),
				'status'           => 1,
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
			// Open the new form directly in the Easy Form Builder editor.
			'edit_url'  => admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . $form_id ),
			'list_url'  => admin_url( 'admin.php?page=Emsfb' ),
			'shortcode' => sprintf( '[EMS_Form_Builder id=%d]', $form_id ),
			'is_draft'  => false,
			'warnings'  => array(),
		);
	}

	public function get_health(): array {
		$active    = $this->plugin_is_active();
		$version   = $this->version();
		$supported = $this->version_is_supported();
		$schema_ok = $this->has_required_schema();
		$available = $this->is_available();
		$notes     = __( 'Easy Form Builder is not active.', 'sitepilot-ai' );
		if ( $active && ! $supported ) {
			$notes = sprintf( __( 'Easy Form Builder %1$s is detected, but SitePilot currently supports version %2$s or newer.', 'sitepilot-ai' ), $version ?: '—', self::MIN_SUPPORTED_VERSION );
		} elseif ( $active && ! $schema_ok ) {
			$notes = __( 'Easy Form Builder is active, but its form storage table is incomplete. Run its update/repair process before creating forms.', 'sitepilot-ai' );
		} elseif ( $available ) {
			$notes = __( 'Compatible. New forms are created in Easy Form Builder and open in its editor.', 'sitepilot-ai' );
		}

		return array(
			'available'  => $available,
			'active'     => $active,
			'version'    => $version,
			'compatible' => $active && $supported && $schema_ok,
			'notes'      => $notes,
		);
	}

	private function plugin_is_active(): bool {
		return defined( 'EMSFB_PLUGIN_VERSION' ) || class_exists( '\\Emsfb\\Create' );
	}

	private function version(): string {
		return defined( 'EMSFB_PLUGIN_VERSION' ) ? (string) EMSFB_PLUGIN_VERSION : '';
	}

	private function version_is_supported(): bool {
		$version = $this->version();
		return '' !== $version && version_compare( $version, self::MIN_SUPPORTED_VERSION, '>=' );
	}

	private function has_required_schema(): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! $this->table_exists() ) {
			return false;
		}

		$table = $wpdb->prefix . 'emsfb_form';
		// The table name is made only from WordPress' trusted prefix; it is not user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
		return array() === array_diff( self::REQUIRED_COLUMNS, array_map( 'strval', is_array( $columns ) ? $columns : array() ) );
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
