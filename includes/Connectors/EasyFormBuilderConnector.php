<?php
declare(strict_types=1);

namespace SitePilot\Connectors;

use SitePilot\Skills\FormPlanSchema;

/**
 * Easy Form Builder adapter.
 *
 * The exact write API of the target plugin is not bundled here, so the actual
 * persistence is delegated to a filter. This keeps the core honest and lets a
 * verified EFB integration be plugged in without touching this class:
 *
 *   add_filter( 'sitepilot_ai_efb_available', '__return_true' );
 *   add_filter( 'sitepilot_ai_efb_create_form', function ( $result, $plan, $ctx ) {
 *       // Build the form using the real Easy Form Builder API and return:
 *       // ['status'=>'success','form_id'=>..,'edit_url'=>..,'shortcode'=>..].
 *       return $result;
 *   }, 10, 3 );
 *
 * Until a real integration is registered, is_available() returns false and the
 * registry falls back to the built-in connector.
 */
final class EasyFormBuilderConnector implements ConnectorInterface {

	public function get_id(): string {
		return 'easy_form_builder';
	}

	public function get_name(): string {
		return __( 'Easy Form Builder', 'sitepilot-ai' );
	}

	public function is_available(): bool {
		$detected = defined( 'EASY_FORM_BUILDER_VERSION' )
			|| class_exists( 'Easy_Form_Builder' )
			|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'easy-form-builder/easy-form-builder.php' ) );

		$has_integration = has_filter( 'sitepilot_ai_efb_create_form' );

		/**
		 * Allow a real EFB integration to declare availability.
		 *
		 * @param bool $available Detected + integration present.
		 */
		return (bool) apply_filters( 'sitepilot_ai_efb_available', $detected && $has_integration );
	}

	public function get_capabilities(): array {
		return array( 'form.create' );
	}

	public function validate_action( array $plan ): array {
		$errors = array();

		if ( '' === trim( (string) ( $plan['title'] ?? '' ) ) ) {
			$errors[] = __( 'The form has no title.', 'sitepilot-ai' );
		}

		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			$type = is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '';
			if ( ! in_array( $type, FormPlanSchema::ALLOWED_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Unsupported field type "%s".', 'sitepilot-ai' ), $type );
			}
		}

		return $errors;
	}

	public function execute_action( array $plan, array $context = array() ): array {
		$default = array(
			'status'  => 'failed',
			'code'    => 'connector_not_available',
			'message' => __( 'No Easy Form Builder integration is registered.', 'sitepilot-ai' ),
		);

		/**
		 * Perform the real EFB form creation.
		 *
		 * @param array<string,mixed> $default Default (failure) result.
		 * @param array<string,mixed> $plan    Normalized form plan.
		 * @param array<string,mixed> $context Execution context.
		 */
		$result = apply_filters( 'sitepilot_ai_efb_create_form', $default, $plan, $context );

		return is_array( $result ) ? $result : $default;
	}

	public function get_health(): array {
		return array(
			'available' => $this->is_available(),
			'version'   => defined( 'EASY_FORM_BUILDER_VERSION' ) ? (string) EASY_FORM_BUILDER_VERSION : '',
			'notes'     => $this->is_available()
				? __( 'Integration registered.', 'sitepilot-ai' )
				: __( 'Install Easy Form Builder and register the integration filter to enable.', 'sitepilot-ai' ),
		);
	}
}
