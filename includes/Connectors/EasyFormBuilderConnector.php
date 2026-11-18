<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbFormModel;

/**
 * Easy Form Builder connector.
 *
 * A new form is created through Easy Form Builder's own API, the form package contract (`efb_form_package_api()`,
 * `Emsfb\Form_Package`, version 1.1): `validate()` shows what EFB would store before the admin approves (writes
 * nothing), and `import()` stores it through the same save path as EFB's builder (its sanitizer, the F03 … F17
 * normalizers, the hooks, versions and caches). This connector never writes EFB's tables or options itself; an
 * Easy Form Builder without that API is told to update instead.
 *
 * A `aisaautopilot_efb_create_form` filter can override the persistence entirely.
 */
final class EasyFormBuilderConnector implements ConnectorInterface {

	/** The first EFB release verified against AISAAutoPilot's storage converter. */
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
		return __( 'Easy Form Builder', 'ai-site-assistant-autopilot' );
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
		return (bool) apply_filters( 'aisaautopilot_efb_available', $detected );
	}

	public function get_capabilities(): array {
		return array( 'form.create', 'form.validate' );
	}

	/** Whether EFB offers the API this connector creates forms with (the form package contract). */
	public function can_create(): bool {
		return null !== EfbCapabilities::form_package_api();
	}

	public function validate_action( array $plan ): array {
		$errors  = array();
		$allowed = EfbFormModel::allowed_types();

		if ( ! $this->is_available() ) {
			return array( __( 'Easy Form Builder is not active or does not match the supported 4.1+ storage contract.', 'ai-site-assistant-autopilot' ) );
		}
		if ( ! $this->can_create() ) {
			return array( self::update_message() );
		}

		if ( '' === trim( (string) ( $plan['title'] ?? '' ) ) ) {
			$errors[] = __( 'The form has no title.', 'ai-site-assistant-autopilot' );
		}

		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			$type = is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '';
			if ( ! in_array( $type, $allowed, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Unsupported Easy Form Builder field type "%s".', 'ai-site-assistant-autopilot' ), $type );
			}
		}

		return $errors;
	}

	/** Why a form cannot be created on an Easy Form Builder without its form package API. */
	public static function update_message(): string {
		return __( 'Update Easy Form Builder to version 4.3.2 or newer to create forms here. It creates forms through its own save path, and AI Site Assistant AutoPilot never writes Easy Form Builder’s tables itself.', 'ai-site-assistant-autopilot' );
	}

	/**
	 * The form package (schema 1) EFB imports for a plan: the rows the converter builds, nothing else.
	 *
	 * @param array<string,mixed> $plan
	 * @return array<string,mixed>
	 */
	public static function package( array $plan ): array {
		$rows  = ( new EfbFormConverter() )->convert( $plan );
		$title = (string) ( $plan['title'] ?? 'Form' );

		return array(
			'schema'         => 1,
			'generator'      => 'ai-site-assistant-autopilot',
			'plugin_version' => defined( 'EMSFB_PLUGIN_VERSION' ) ? (string) EMSFB_PLUGIN_VERSION : '',
			'form'           => array(
				'form_name'     => $title,
				'form_type'     => (string) ( $rows[0]['type'] ?? 'form' ),
				'efb_version'   => 2,
				'form_structer' => $rows,
			),
		);
	}

	/**
	 * What EFB would store for this plan and what it would leave out or warn about, without writing anything
	 * (`Form_Package::validate()`). Null when this EFB has no form package API.
	 *
	 * @param array<string,mixed> $plan
	 * @return array{ok:bool,errors:array<int,array<string,mixed>>,dropped:array<int,array<string,mixed>>,notices:array<int,array<string,mixed>>,rows:array<int,array<string,mixed>>}|null
	 */
	public function preview_check( array $plan ): ?array {
		if ( ! $this->can_create() ) {
			return null;
		}
		if ( ! class_exists( '\\Emsfb\\Form_Package' ) ) {
			return null;
		}
		try {
			$result = \Emsfb\Form_Package::validate( self::package( $plan ) );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_array( $result ) ) {
			return null;
		}

		return array(
			'ok'      => ! empty( $result['ok'] ),
			'errors'  => array_values( array_filter( (array) ( $result['errors'] ?? array() ), 'is_array' ) ),
			'dropped' => array_values( array_filter( (array) ( $result['dropped'] ?? array() ), 'is_array' ) ),
			'notices' => array_values( array_filter( (array) ( $result['notices'] ?? array() ), 'is_array' ) ),
			'rows'    => is_array( $result['form']['form_structer'] ?? null ) ? $result['form']['form_structer'] : array(),
		);
	}

	public function execute_action( array $plan, array $context = array() ): array {
		$errors = $this->validate_action( $plan );
		if ( array() !== $errors ) {
			return array(
				'status'  => 'failed',
				'code'    => $this->can_create() ? 'connector_not_compatible' : 'efb_api_missing',
				'message' => implode( ' ', $errors ),
			);
		}

		// Allow a custom integration to fully override persistence.
		if ( has_filter( 'aisaautopilot_efb_create_form' ) ) {
			$override = apply_filters( 'aisaautopilot_efb_create_form', null, $plan, $context );
			if ( is_array( $override ) ) {
				return $override;
			}
		}

		$title  = (string) ( $plan['title'] ?? 'Form' );
		$result = null;
		try {
			$result = ! class_exists( '\\Emsfb\\Form_Package' ) ? null : \Emsfb\Form_Package::import(
				self::package( $plan ),
				array(
					'name'    => $title,
					'user_id' => get_current_user_id(),
				)
			);
		} catch ( \Throwable $e ) {
			$result = null;
		}

		if ( is_wp_error( $result ) || ! is_array( $result ) || (int) ( $result['form_id'] ?? 0 ) <= 0 ) {
			$message = is_wp_error( $result ) ? wp_strip_all_tags( $result->get_error_message() ) : '';

			return array(
				'status'  => 'failed',
				'code'    => is_wp_error( $result ) ? sanitize_key( (string) $result->get_error_code() ) : 'execution_failed',
				'message' => '' !== $message
					/* translators: %s: Easy Form Builder's own reason. */
					? sprintf( __( 'Easy Form Builder did not save the form: %s', 'ai-site-assistant-autopilot' ), $message )
					: __( 'The form could not be saved into Easy Form Builder.', 'ai-site-assistant-autopilot' ),
			);
		}

		$form_id = (int) $result['form_id'];

		return array(
			'status'       => 'success',
			'connector'    => $this->get_name(),
			'form_id'      => $form_id,
			'form_name'    => (string) ( $result['form_name'] ?? $title ),
			// Open the new form directly in the Easy Form Builder editor.
			'edit_url'     => admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . $form_id ),
			'list_url'     => admin_url( 'admin.php?page=Emsfb' ),
			'shortcode'    => is_string( $result['shortcode'] ?? null ) && '' !== $result['shortcode'] ? (string) $result['shortcode'] : sprintf( '[EMS_Form_Builder id=%d]', $form_id ),
			'is_draft'     => false,
			// Package 1.1: the token that lets the same user undo this creation through Form_Package::delete().
			'import_token' => EfbCapabilities::form_package_version() >= 1.1 ? (string) ( $result['import_token'] ?? '' ) : '',
			'dropped'      => array_values( array_filter( (array) ( $result['dropped'] ?? array() ), 'is_array' ) ),
			'notices'      => array_values( array_filter( (array) ( $result['notices'] ?? array() ), 'is_array' ) ),
			'payment'      => is_array( $result['payment'] ?? null ) ? $result['payment'] : null,
			'warnings'     => array(),
		);
	}

	/**
	 * Undo a form this connector created, through EFB's own `Form_Package::delete()` (package 1.1): only the form the
	 * import with that token created, for the user who created it or an administrator.
	 *
	 * @return true|\WP_Error
	 */
	public function undo_create( int $form_id, string $import_token ) {
		if ( EfbCapabilities::form_package_version() < 1.1 || ! class_exists( '\\Emsfb\\Form_Package' ) || ! is_callable( array( '\\Emsfb\\Form_Package', 'delete' ) ) ) {
			return new \WP_Error( 'efb_undo_unavailable', __( 'This Easy Form Builder cannot undo a created form; delete it in Easy Form Builder’s forms list.', 'ai-site-assistant-autopilot' ) );
		}
		$done = \Emsfb\Form_Package::delete( $form_id, $import_token );

		return is_wp_error( $done ) ? $done : true;
	}

	/**
	 * @return array{available:bool,active:bool,version:string,compatible:bool,notes:string}
	 */
	public function get_health(): array {
		$active    = $this->plugin_is_active();
		$version   = $this->version();
		$supported = $this->version_is_supported();
		$schema_ok = $this->has_required_schema();
		$available = $this->is_available();
		$notes     = __( 'Easy Form Builder is not active.', 'ai-site-assistant-autopilot' );
		if ( $active && ! $supported ) {
			/* translators: 1: detected Easy Form Builder version, 2: minimum supported version. */
			$notes = sprintf( __( 'Easy Form Builder %1$s is detected, but AISAAutoPilot currently supports version %2$s or newer.', 'ai-site-assistant-autopilot' ), $version ?: '—', self::MIN_SUPPORTED_VERSION );
		} elseif ( $active && ! $schema_ok ) {
			$notes = __( 'Easy Form Builder is active, but its form storage table is incomplete. Run its update/repair process before creating forms.', 'ai-site-assistant-autopilot' );
		} elseif ( $available && ! $this->can_create() ) {
			$notes = self::update_message();
		} elseif ( $available ) {
			$notes = __( 'Compatible. New forms are created in Easy Form Builder and open in its editor.', 'ai-site-assistant-autopilot' );
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

		// SHOW COLUMNS takes no bound parameters, so the name is checked against the
		// characters MySQL allows in an unquoted identifier first. It is built from
		// WordPress' own prefix plus a fixed suffix and never from user input, so
		// this can only fail on a malformed prefix.
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- validated identifier; SHOW COLUMNS cannot be prepared.
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
