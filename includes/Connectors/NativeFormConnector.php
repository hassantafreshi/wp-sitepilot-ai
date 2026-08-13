<?php
declare(strict_types=1);

namespace SitePilot\Connectors;

use SitePilot\Skills\FormPlanSchema;

/**
 * Built-in form connector — the safe fallback that always works.
 *
 * Stores the form as a `sitepilot_form` custom post type and renders it with the
 * [sitepilot_form] shortcode. This guarantees the full request→approve→create
 * loop is demonstrable even when no third-party form plugin is installed.
 */
final class NativeFormConnector implements ConnectorInterface {

	public const POST_TYPE       = 'sitepilot_form';
	public const SUBMISSION_TYPE = 'sitepilot_submission';
	public const META_KEY        = '_sitepilot_form_plan';

	public function get_id(): string {
		return 'native_form';
	}

	public function get_name(): string {
		return __( 'SitePilot Built-in Forms', 'sitepilot-ai' );
	}

	public function is_available(): bool {
		return true;
	}

	public function get_capabilities(): array {
		return array( 'form.create' );
	}

	public function validate_action( array $plan ): array {
		$errors = array();

		if ( '' === trim( (string) ( $plan['title'] ?? '' ) ) ) {
			$errors[] = __( 'The form has no title.', 'sitepilot-ai' );
		}

		$fields = $plan['fields'] ?? array();
		if ( ! is_array( $fields ) || array() === $fields ) {
			$errors[] = __( 'The form has no fields.', 'sitepilot-ai' );
			$fields   = array();
		}
		if ( count( $fields ) > FormPlanSchema::MAX_FIELDS ) {
			$errors[] = sprintf( /* translators: %d: maximum number of fields. */ __( 'A SitePilot Form can contain at most %d fields.', 'sitepilot-ai' ), FormPlanSchema::MAX_FIELDS );
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				$errors[] = __( 'The form contains an invalid field definition.', 'sitepilot-ai' );
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( in_array( $type, FormPlanSchema::FORBIDDEN_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Field type "%s" is not allowed.', 'sitepilot-ai' ), $type );
			} elseif ( ! in_array( $type, FormPlanSchema::ALLOWED_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Unsupported field type "%s".', 'sitepilot-ai' ), $type );
			}
			if ( '' === sanitize_key( (string) ( $field['key'] ?? '' ) ) || '' === trim( (string) ( $field['label'] ?? '' ) ) ) {
				$errors[] = __( 'Every form field needs a stable key and a label.', 'sitepilot-ai' );
			}
			if ( in_array( $type, array( 'select', 'radio' ), true ) && array() === (array) ( $field['options'] ?? array() ) ) {
				$errors[] = __( 'Select and radio fields need at least one option.', 'sitepilot-ai' );
			}
		}

		$recipient = sanitize_email( (string) ( $plan['notification_email'] ?? '' ) );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$errors[] = __( 'The form needs a valid notification email address.', 'sitepilot-ai' );
		}

		return $errors;
	}

	public function execute_action( array $plan, array $context = array() ): array {
		$status = ( 'publish' === ( $context['status'] ?? 'draft' ) ) ? 'publish' : 'draft';

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => $status,
				'post_title'   => sanitize_text_field( (string) $plan['title'] ),
				'post_content' => sanitize_textarea_field( (string) ( $plan['description'] ?? '' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'failed',
				'code'    => 'execution_failed',
				'message' => __( 'The form could not be saved.', 'sitepilot-ai' ),
			);
		}

		update_post_meta( $post_id, self::META_KEY, wp_slash( $plan ) );

		return array(
			'status'    => 'success',
			'connector' => $this->get_name(),
			'form_id'   => (int) $post_id,
			'edit_url'  => (string) get_edit_post_link( $post_id, 'raw' ),
			'shortcode' => sprintf( '[sitepilot_form id="%d"]', (int) $post_id ),
			'is_draft'  => 'draft' === $status,
			'warnings'  => array(),
		);
	}

	public function get_health(): array {
		return array(
			'available' => true,
			'version'   => SITEPILOT_AI_VERSION,
			'notes'     => __( 'Always available.', 'sitepilot-ai' ),
		);
	}

	/**
	 * Register the form + submission post types. Called on init and activation.
	 */
	public static function register_post_types(): void {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			register_post_type(
				self::POST_TYPE,
				array(
					'label'           => __( 'SitePilot Forms', 'sitepilot-ai' ),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => false,
					'supports'        => array( 'title' ),
					'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
					'capability_type' => 'post',
					'map_meta_cap'    => true,
				)
			);
		}

		if ( ! post_type_exists( self::SUBMISSION_TYPE ) ) {
			register_post_type(
				self::SUBMISSION_TYPE,
				array(
					'label'        => __( 'Form Submissions', 'sitepilot-ai' ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_menu' => false,
					'supports'     => array( 'title' ),
				)
			);
		}
	}
}
