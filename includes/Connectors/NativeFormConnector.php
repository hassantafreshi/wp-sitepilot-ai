<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

use AISAAutoPilot\Skills\FormPlanSchema;

/**
 * Built-in form connector — the safe fallback that always works.
 *
 * Stores the form as a `aisaautopilot_form` custom post type and renders it with the
 * [aisaautopilot_form] shortcode. This guarantees the full request→approve→create
 * loop is demonstrable even when no third-party form plugin is installed.
 */
final class NativeFormConnector implements ConnectorInterface {

	public const POST_TYPE = 'aisaautopilot_form';

	/**
	 * Submission post type.
	 *
	 * `register_post_type()` rejects names longer than 20 characters, so this is
	 * deliberately `_entry` rather than the longer `_submission`: the prefix
	 * alone is 13 characters and `aisaautopilot_submission` would be 24.
	 */
	public const SUBMISSION_TYPE = 'aisaautopilot_entry';

	public const META_KEY = '_aisaautopilot_form_plan';

	public function get_id(): string {
		return 'native_form';
	}

	public function get_name(): string {
		return __( 'AISAAutoPilot Built-in Forms', 'ai-site-assistant-autopilot' );
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
			$errors[] = __( 'The form has no title.', 'ai-site-assistant-autopilot' );
		}

		$fields = $plan['fields'] ?? array();
		if ( ! is_array( $fields ) || array() === $fields ) {
			$errors[] = __( 'The form has no fields.', 'ai-site-assistant-autopilot' );
			$fields   = array();
		}
		if ( count( $fields ) > FormPlanSchema::MAX_FIELDS ) {
			$errors[] = sprintf( /* translators: %d: maximum number of fields. */ __( 'A AISAAutoPilot Form can contain at most %d fields.', 'ai-site-assistant-autopilot' ), FormPlanSchema::MAX_FIELDS );
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				$errors[] = __( 'The form contains an invalid field definition.', 'ai-site-assistant-autopilot' );
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			if ( in_array( $type, FormPlanSchema::FORBIDDEN_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Field type "%s" is not allowed.', 'ai-site-assistant-autopilot' ), $type );
			} elseif ( ! in_array( $type, FormPlanSchema::ALLOWED_FIELD_TYPES, true ) ) {
				$errors[] = sprintf( /* translators: %s: field type. */ __( 'Unsupported field type "%s".', 'ai-site-assistant-autopilot' ), $type );
			}
			if ( '' === sanitize_key( (string) ( $field['key'] ?? '' ) ) || '' === trim( (string) ( $field['label'] ?? '' ) ) ) {
				$errors[] = __( 'Every form field needs a stable key and a label.', 'ai-site-assistant-autopilot' );
			}
			if ( in_array( $type, array( 'select', 'radio' ), true ) && array() === (array) ( $field['options'] ?? array() ) ) {
				$errors[] = __( 'Select and radio fields need at least one option.', 'ai-site-assistant-autopilot' );
			}
		}

		$recipient = sanitize_email( (string) ( $plan['notification_email'] ?? '' ) );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$errors[] = __( 'The form needs a valid notification email address.', 'ai-site-assistant-autopilot' );
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
				'message' => __( 'The form could not be saved.', 'ai-site-assistant-autopilot' ),
			);
		}

		update_post_meta( $post_id, self::META_KEY, wp_slash( $plan ) );

		return array(
			'status'    => 'success',
			'connector' => $this->get_name(),
			'form_id'   => (int) $post_id,
			'edit_url'  => (string) get_edit_post_link( $post_id, 'raw' ),
			'shortcode' => sprintf( '[aisaautopilot_form id="%d"]', (int) $post_id ),
			'is_draft'  => 'draft' === $status,
			'warnings'  => array(),
		);
	}

	public function get_health(): array {
		return array(
			'available' => true,
			'version'   => AISAAUTOPILOT_VERSION,
			'notes'     => __( 'Always available.', 'ai-site-assistant-autopilot' ),
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
					'label'           => __( 'AISAAutoPilot Forms', 'ai-site-assistant-autopilot' ),
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
					'label'        => __( 'Form Submissions', 'ai-site-assistant-autopilot' ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_menu' => false,
					'supports'     => array( 'title' ),
				)
			);
		}
	}
}
