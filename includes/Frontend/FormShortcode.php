<?php
declare(strict_types=1);

namespace AISAAutoPilot\Frontend;

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Forms\FormOutbox;
use AISAAutoPilot\Forms\NativeFormSubmission;
use AISAAutoPilot\Forms\Turnstile;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Security\ClientAddress;
use AISAAutoPilot\Skills\FormPlanSchema;

/**
 * Renders [aisaautopilot_form id="123"] and delivers submissions by email only.
 *
 * Submission values are validated in memory, sent with wp_mail(), then
 * discarded. AISAAutoPilot never creates a submission post, post meta, log entry,
 * or option containing visitor-supplied values.
 */
final class FormShortcode {

	private const ACTION      = 'aisaautopilot_form_submit';
	private const RATE_LIMIT  = 5;
	private const RATE_WINDOW = 600;

	public function register(): void {
		add_shortcode( 'aisaautopilot_form', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				wp_register_style( 'aisaautopilot-form', AISAAUTOPILOT_URL . 'assets/frontend/form.css', array(), AISAAUTOPILOT_VERSION );
			}
		);
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'aisaautopilot_form' );
		$id   = (int) $atts['id'];

		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || NativeFormConnector::POST_TYPE !== $post->post_type ) {
			return '';
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) {
			return '';
		}

		$plan = get_post_meta( $id, NativeFormConnector::META_KEY, true );
		if ( ! is_array( $plan ) || empty( $plan['fields'] ) ) {
			return '';
		}

		$submitted = isset( $_GET['sp_form_sent'] ) && (int) $_GET['sp_form_sent'] === $id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.
		$error     = isset( $_GET['sp_form_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['sp_form_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.

		wp_enqueue_style( 'aisaautopilot-form' );

		ob_start();
		?>
		<?php // Declared here so labels and inputs read correctly even in a theme that never sets dir on <html>. ?>
		<div class="aisaautopilot-form" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
			<?php if ( $submitted ) : ?>
				<div class="aisaautopilot-form__confirmation" role="status">
					<?php echo esc_html( (string) ( $plan['confirmation_message'] ?? __( 'Thank you.', 'ai-site-assistant-autopilot' ) ) ); ?>
				</div>
			<?php else : ?>
				<?php if ( '' !== $error ) : ?>
					<div class="aisaautopilot-form__error" role="alert"><?php echo esc_html( $this->error_message( $error ) ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $plan['description'] ) ) : ?>
					<p class="aisaautopilot-form__desc"><?php echo esc_html( (string) $plan['description'] ); ?></p>
				<?php endif; ?>
				<form class="aisaautopilot-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $id ); ?>" />
					<?php wp_nonce_field( self::ACTION . '_' . $id, 'sp_nonce' ); ?>
					<div class="aisaautopilot-form__honeypot" aria-hidden="true">
						<label for="sp-website-<?php echo esc_attr( (string) $id ); ?>"><?php esc_html_e( 'Website', 'ai-site-assistant-autopilot' ); ?></label>
						<input id="sp-website-<?php echo esc_attr( (string) $id ); ?>" type="text" name="sp_website" tabindex="-1" autocomplete="off" />
					</div>
					<div class="aisaautopilot-form__fields">
						<?php echo $this->render_fields( (array) $plan['fields'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every dynamic field value is escaped below. ?>
					</div>
					<?php echo Turnstile::widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped in Turnstile::widget(). ?>
					<button type="submit" class="aisaautopilot-form__submit">
						<?php echo esc_html( (string) ( $plan['submit_button'] ?? __( 'Submit', 'ai-site-assistant-autopilot' ) ) ); ?>
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
			$req_mark = $required ? ' <span class="aisaautopilot-form__req" aria-hidden="true">*</span>' : '';

			if ( '' === $key || '' === $label ) {
				continue;
			}

			// Column width: fields flow left to right in twelfths of a row; an unknown width is a full row.
			$width    = isset( FormPlanSchema::WIDTHS[ (string) ( $field['width'] ?? '' ) ] ) ? (string) $field['width'] : 'full';
			$help     = trim( (string) ( $field['help'] ?? '' ) );
			$help_id  = 'sp-help-' . $key;
			$describe = '' !== $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
			$attrs    = self::input_attributes( $type, (array) ( $field['settings'] ?? array() ) );

			$html .= '<div class="aisaautopilot-form__row aisaautopilot-form__row--' . esc_attr( $width ) . '">';
			switch ( $type ) {
				case 'textarea':
					$html .= '<label for="' . esc_attr( $id_attr ) . '">' . $label . $req_mark . '</label>';
					$html .= '<textarea id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . $describe . '></textarea>';
					break;
				case 'select':
					$html .= '<label for="' . esc_attr( $id_attr ) . '">' . $label . $req_mark . '</label>';
					$html .= '<select id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '"' . $req_attr . $describe . '>';
					$html .= '<option value="">' . esc_html__( '— Select —', 'ai-site-assistant-autopilot' ) . '</option>';
					foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
						$html .= '<option value="' . esc_attr( (string) $opt ) . '">' . esc_html( (string) $opt ) . '</option>';
					}
					$html .= '</select>';
					break;
				case 'radio':
				case 'checkbox':
					$html .= $this->render_choice_field( $field, $name, $id_attr, $label, $required, $req_mark, $describe );
					break;
				case 'email':
				case 'tel':
				case 'url':
				case 'number':
				case 'date':
				case 'text':
				default:
					$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number', 'date' ), true ) ? $type : 'text';
					$html      .= '<label for="' . esc_attr( $id_attr ) . '">' . $label . $req_mark . '</label>';
					$html      .= '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" placeholder="' . $ph . '"' . $req_attr . $describe . $attrs . ' />';
			}
			if ( '' !== $help ) {
				$html .= '<small class="aisaautopilot-form__help" id="' . esc_attr( $help_id ) . '">' . esc_html( $help ) . '</small>';
			}
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * The min, max and step a number or date field carries, as HTML attributes (already escaped).
	 *
	 * @param array<string,mixed> $settings
	 */
	private static function input_attributes( string $type, array $settings ): string {
		$out = '';
		if ( 'number' === $type ) {
			foreach ( array( 'min', 'max' ) as $name ) {
				if ( isset( $settings[ $name ] ) && is_numeric( $settings[ $name ] ) ) {
					$out .= ' ' . $name . '="' . esc_attr( (string) $settings[ $name ] ) . '"';
				}
			}
			$step = isset( $settings['step'] ) && is_numeric( $settings['step'] ) && (float) $settings['step'] > 0 ? (string) $settings['step'] : 'any';
			$out .= ' step="' . esc_attr( $step ) . '" inputmode="decimal"';
		} elseif ( 'date' === $type ) {
			foreach ( array( 'min', 'max' ) as $name ) {
				$limit = NativeFormSubmission::date_limit( $settings[ $name ] ?? '' );
				if ( null !== $limit ) {
					$out .= ' ' . $name . '="' . esc_attr( $limit ) . '"';
				}
			}
		}

		return $out;
	}

	/** @param array<string,mixed> $field */
	private function render_choice_field( array $field, string $name, string $id_attr, string $label, bool $required, string $req_mark, string $describe = '' ): string {
		$type    = (string) $field['type'];
		$options = (array) ( $field['options'] ?? array() );
		$html    = '<fieldset class="aisaautopilot-form__choices"' . $describe . '><legend>' . $label . $req_mark . '</legend>';

		if ( 'checkbox' === $type && array() === $options ) {
			$html .= '<label class="aisaautopilot-form__check" for="' . esc_attr( $id_attr ) . '">';
			$html .= '<input type="checkbox" id="' . esc_attr( $id_attr ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $required ? ' required' : '' ) . ' /> ';
			$html .= $label . '</label>';
			return $html . '</fieldset>';
		}

		$field_name = 'checkbox' === $type ? $name . '[]' : $name;
		foreach ( $options as $index => $option ) {
			$choice_id = $id_attr . '-' . (int) $index;
			$html     .= '<label class="aisaautopilot-form__check" for="' . esc_attr( $choice_id ) . '">';
			$html     .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $choice_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $option ) . '" /> ';
			$html     .= esc_html( (string) $option ) . '</label>';
		}

		return $html . '</fieldset>';
	}

	/** Deliver a validated submission and redirect without persisting values. */
	public function handle_submit(): void {
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$nonce   = isset( $_POST['sp_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sp_nonce'] ) ) : '';

		if ( $form_id <= 0 || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $form_id ) ) {
			wp_die( esc_html__( 'Invalid form submission.', 'ai-site-assistant-autopilot' ), '', array( 'response' => 400 ) );
		}

		$post = get_post( $form_id );
		if ( ! $post || NativeFormConnector::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_die( esc_html__( 'Form not found.', 'ai-site-assistant-autopilot' ), '', array( 'response' => 404 ) );
		}

		$plan = get_post_meta( $form_id, NativeFormConnector::META_KEY, true );
		if ( ! is_array( $plan ) || empty( $plan['fields'] ) ) {
			wp_die( esc_html__( 'Form not found.', 'ai-site-assistant-autopilot' ), '', array( 'response' => 404 ) );
		}

		// A completed honeypot receives a harmless success redirect. No mail,
		// no logging and no rate-limit state is created for bot-supplied data.
		if ( ! empty( $_POST['sp_website'] ) ) {
			$this->redirect( $form_id, 'sent' );
		}
		if ( $this->is_rate_limited( $form_id ) ) {
			$this->redirect( $form_id, 'rate_limited' );
		}
		// Optional Cloudflare Turnstile: checked before anything else is done with the answers.
		if ( Turnstile::enabled() && ! Turnstile::verify( (string) wp_unslash( $_POST[ Turnstile::RESPONSE_FIELD ] ?? '' ) )['ok'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- an opaque token sent only to Cloudflare, length-checked in verify().
			$this->redirect( $form_id, 'spam_check' );
		}

		$input  = wp_unslash( $_POST );
		$result = ( new NativeFormSubmission() )->validate( (array) $plan['fields'], is_array( $input ) ? $input : array() );
		if ( ! $result['ok'] ) {
			$this->redirect( $form_id, 'invalid' );
		}

		$subject   = $this->email_subject( $plan );
		$body      = $this->email_body( $plan, $result['values'] );
		$headers   = $this->email_headers( $plan, $result['values'] );
		$recipient = sanitize_email( (string) ( $plan['notification_email'] ?? '' ) );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$this->audit_delivery( 'form.delivery_config_invalid', $form_id );
			$this->keep_or_fail( $form_id, $subject, $body, $headers, 'no_recipient' );
		}

		if ( ! wp_mail( $recipient, $subject, $body, $headers ) ) {
			$this->audit_delivery( 'form.email_failed', $form_id );
			$this->keep_or_fail( $form_id, $subject, $body, $headers, 'wp_mail_failed' );
		}

		$this->audit_delivery( 'form.email_sent', $form_id );
		$this->redirect( $form_id, 'sent' );
	}

	/**
	 * An email that could not be sent: kept for the administrator when they chose that
	 * (the visitor's answers are then safe and they see the normal thank-you), otherwise
	 * the visitor is asked to try again, as before.
	 *
	 * @param string[] $headers
	 */
	private function keep_or_fail( int $form_id, string $subject, string $body, array $headers, string $error ): void {
		if ( FormOutbox::enabled() && ( new FormOutbox() )->keep( $form_id, $subject, $body, $headers, $error ) > 0 ) {
			$this->audit_delivery( 'form.email_kept', $form_id );
			$this->redirect( $form_id, 'sent' );
		}
		$this->redirect( $form_id, 'delivery' );
	}

	/** @param array<string,mixed> $plan */
	private function email_subject( array $plan ): string {
		$site  = str_replace( array( "\r", "\n" ), '', wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
		$title = str_replace( array( "\r", "\n" ), '', wp_strip_all_tags( (string) ( $plan['title'] ?? __( 'Form', 'ai-site-assistant-autopilot' ) ) ) );
		return sprintf( '[%s] %s', $site, $title );
	}

	/** @param array<string,mixed> $plan @param array<string,string> $values */
	private function email_body( array $plan, array $values ): string {
		$lines = array(
			__( 'New AISAAutoPilot Form submission', 'ai-site-assistant-autopilot' ),
			/* translators: %s: the title of the submitted form. */
			sprintf( __( 'Form: %s', 'ai-site-assistant-autopilot' ), wp_strip_all_tags( (string) ( $plan['title'] ?? '' ) ) ),
			'',
		);
		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			$key = sanitize_key( (string) ( $field['key'] ?? '' ) );
			if ( '' === $key || ! isset( $values[ $key ] ) ) {
				continue;
			}
			$label   = wp_strip_all_tags( (string) ( $field['label'] ?? $key ) );
			$lines[] = $label . ': ' . $values[ $key ];
		}
		if ( 3 === count( $lines ) ) {
			$lines[] = __( '(No optional fields were completed.)', 'ai-site-assistant-autopilot' );
		}

		return implode( "\n", $lines );
	}

	/** @param array<string,mixed> $plan @param array<string,string> $values @return string[] */
	private function email_headers( array $plan, array $values ): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( 'email' !== ( $field['type'] ?? '' ) ) {
				continue;
			}
			$key   = sanitize_key( (string) ( $field['key'] ?? '' ) );
			$email = sanitize_email( (string) ( $values[ $key ] ?? '' ) );
			if ( '' !== $email && is_email( $email ) ) {
				$headers[] = 'Reply-To: ' . $email;
				break;
			}
		}
		return $headers;
	}

	private function is_rate_limited( int $form_id ): bool {
		$key   = $this->rate_limit_key( $form_id );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return false;
	}

	private function rate_limit_key( int $form_id ): string {
		$ip = ClientAddress::get();
		return 'aisaautopilot_form_rate_' . $form_id . '_' . substr( hash_hmac( 'sha256', $form_id . '|' . $ip, wp_salt( 'nonce' ) ), 0, 20 );
	}

	private function redirect( int $form_id, string $state ): void {
		$redirect = wp_get_referer() ?: get_permalink( $form_id );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = home_url();
		}
		$redirect = remove_query_arg( array( 'sp_form_sent', 'sp_form_error' ), $redirect );
		$args     = 'sent' === $state ? array( 'sp_form_sent' => $form_id ) : array( 'sp_form_error' => sanitize_key( $state ) );
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}

	private function error_message( string $error ): string {
		$messages = array(
			'invalid'      => __( 'Please check the required fields and try again.', 'ai-site-assistant-autopilot' ),
			'rate_limited' => __( 'Too many submissions. Please wait a few minutes and try again.', 'ai-site-assistant-autopilot' ),
			'spam_check'   => __( 'Please complete the spam check above the button and send the form again.', 'ai-site-assistant-autopilot' ),
			'delivery'     => __( 'We could not send your submission. Please try again later.', 'ai-site-assistant-autopilot' ),
		);
		return $messages[ $error ] ?? __( 'We could not process your submission. Please try again.', 'ai-site-assistant-autopilot' );
	}

	private function audit_delivery( string $event, int $form_id ): void {
		( new Logger() )->info( $event, 'AISAAutoPilot Form delivery outcome.', array( 'form_id' => $form_id ) );
	}
}
