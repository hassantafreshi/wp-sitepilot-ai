<?php
declare(strict_types=1);

namespace SitePilot\Frontend;

use SitePilot\Connectors\NativeFormConnector;

/**
 * Renders a built-in SitePilot form via [sitepilot_form id="123"] and handles
 * its submissions safely (nonce + sanitize + store).
 */
final class FormShortcode {

	private const ACTION = 'sitepilot_form_submit';

	public function register(): void {
		add_shortcode( 'sitepilot_form', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				wp_register_style( 'sitepilot-form', SITEPILOT_AI_URL . 'assets/frontend/form.css', array(), SITEPILOT_AI_VERSION );
			}
		);
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'sitepilot_form' );
		$id   = (int) $atts['id'];

		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || NativeFormConnector::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$plan = get_post_meta( $id, NativeFormConnector::META_KEY, true );
		if ( ! is_array( $plan ) || empty( $plan['fields'] ) ) {
			return '';
		}

		$submitted = isset( $_GET['sp_form_sent'] ) && (int) $_GET['sp_form_sent'] === $id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_enqueue_style( 'sitepilot-form' );

		ob_start();
		?>
		<div class="sitepilot-form">
			<?php if ( $submitted ) : ?>
				<div class="sitepilot-form__confirmation">
					<?php echo esc_html( (string) ( $plan['confirmation_message'] ?? __( 'Thank you.', 'sitepilot-ai' ) ) ); ?>
				</div>
			<?php else : ?>
				<?php if ( ! empty( $plan['description'] ) ) : ?>
					<p class="sitepilot-form__desc"><?php echo esc_html( (string) $plan['description'] ); ?></p>
				<?php endif; ?>
				<form class="sitepilot-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $id ); ?>" />
					<?php wp_nonce_field( self::ACTION . '_' . $id, 'sp_nonce' ); ?>
					<?php echo $this->render_fields( (array) $plan['fields'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per field below. ?>
					<button type="submit" class="sitepilot-form__submit">
						<?php echo esc_html( (string) ( $plan['submit_button'] ?? __( 'Submit', 'sitepilot-ai' ) ) ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 */
	private function render_fields( array $fields ): string {
		$html = '';
		foreach ( $fields as $field ) {
			$key      = sanitize_key( (string) ( $field['key'] ?? '' ) );
			$label    = esc_html( (string) ( $field['label'] ?? '' ) );
			$type     = (string) ( $field['type'] ?? 'text' );
			$required = ! empty( $field['required'] );
			$ph       = esc_attr( (string) ( $field['placeholder'] ?? '' ) );
			$name     = 'sp_field_' . $key;
			$id_attr  = 'sp-' . $key;
			$req_attr = $required ? ' required' : '';
			$req_mark = $required ? ' <span class="sitepilot-form__req">*</span>' : '';

			$html .= '<div class="sitepilot-form__row">';
			$html .= '<label for="' . esc_attr( $id_attr ) . '">' . $label . $req_mark . '</label>';

			switch ( $type ) {
				case 'textarea':
					$html .= '<textarea id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . '></textarea>';
					break;
				case 'select':
					$html .= '<select id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '"' . $req_attr . '>';
					$html .= '<option value="">' . esc_html__( '— Select —', 'sitepilot-ai' ) . '</option>';
					foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
						$html .= '<option value="' . esc_attr( (string) $opt ) . '">' . esc_html( (string) $opt ) . '</option>';
					}
					$html .= '</select>';
					break;
				case 'radio':
				case 'checkbox':
					$input_type = 'radio' === $type ? 'radio' : 'checkbox';
					$options    = (array) ( $field['options'] ?? array() );
					if ( 'checkbox' === $type && array() === $options ) {
						$html .= '<label class="sitepilot-form__check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . $req_attr . ' /> ' . $label . '</label>';
					} else {
						$field_name = 'checkbox' === $type ? $name . '[]' : $name;
						foreach ( $options as $opt ) {
							$html .= '<label class="sitepilot-form__check"><input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $opt ) . '" /> ' . esc_html( (string) $opt ) . '</label>';
						}
					}
					break;
				case 'file':
					$html .= '<input type="file" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '"' . $req_attr . ' />';
					break;
				case 'email':
					$html .= '<input type="email" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . ' />';
					break;
				case 'phone':
					$html .= '<input type="tel" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . ' />';
					break;
				default:
					$html .= '<input type="text" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . ' />';
			}

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Store a submission and redirect back with a confirmation flag.
	 */
	public function handle_submit(): void {
		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		$nonce   = isset( $_POST['sp_nonce'] ) ? sanitize_key( wp_unslash( $_POST['sp_nonce'] ) ) : '';

		if ( $form_id <= 0 || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $form_id ) ) {
			wp_die( esc_html__( 'Invalid form submission.', 'sitepilot-ai' ), '', array( 'response' => 400 ) );
		}

		$plan = get_post_meta( $form_id, NativeFormConnector::META_KEY, true );
		if ( ! is_array( $plan ) || empty( $plan['fields'] ) ) {
			wp_die( esc_html__( 'Form not found.', 'sitepilot-ai' ), '', array( 'response' => 404 ) );
		}

		$values = array();
		foreach ( (array) $plan['fields'] as $field ) {
			$key  = sanitize_key( (string) ( $field['key'] ?? '' ) );
			$name = 'sp_field_' . $key;
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $name ] );
			if ( is_array( $raw ) ) {
				$values[ $key ] = implode( ', ', array_map( 'sanitize_text_field', $raw ) );
			} else {
				$values[ $key ] = sanitize_textarea_field( (string) $raw );
			}
		}

		$submission_id = wp_insert_post(
			array(
				'post_type'   => NativeFormConnector::SUBMISSION_TYPE,
				'post_status' => 'private',
				/* translators: %s: form title. */
				'post_title'  => sprintf( __( 'Submission: %s', 'sitepilot-ai' ), (string) ( $plan['title'] ?? '' ) ),
			),
			true
		);

		if ( ! is_wp_error( $submission_id ) ) {
			update_post_meta( $submission_id, '_sitepilot_submission_form', $form_id );
			update_post_meta( $submission_id, '_sitepilot_submission_values', wp_slash( $values ) );
		}

		$redirect = wp_get_referer() ? wp_get_referer() : home_url();
		wp_safe_redirect( add_query_arg( 'sp_form_sent', $form_id, remove_query_arg( 'sp_form_sent', $redirect ) ) );
		exit;
	}
}
