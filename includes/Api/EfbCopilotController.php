<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Plugin;
use SitePilot\Efb\EfbCustomizeSkill;
use SitePilot\Efb\EfbFormConverter;
use SitePilot\Efb\EfbFormSkill;
use SitePilot\Efb\EfbKnowledge;
use SitePilot\Execution\ActionStore;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderException;
use SitePilot\Providers\ProviderManager;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Easy Form Builder Copilot (floating button on EFB pages).
 * plan: prompt -> validated EFB plan + preview. create: approve -> real EFB form.
 */
final class EfbCopilotController {

	private ProviderManager $providers;
	private ActionStore $store;
	private Logger $logger;

	public function __construct() {
		$this->providers = new ProviderManager();
		$this->store     = new ActionStore();
		$this->logger    = new Logger();
	}

	public function plan( WP_REST_Request $request ): WP_REST_Response {
		$prompt = trim( (string) $request->get_param( 'prompt' ) );

		if ( '' === $prompt ) {
			return $this->respond( array( 'message' => __( 'Please describe the form you want.', 'sitepilot-ai' ) ), false, 400, 'empty_prompt' );
		}
		if ( mb_strlen( $prompt ) > 2000 ) {
			return $this->respond( array( 'message' => __( 'Your request is too long.', 'sitepilot-ai' ) ), false, 400, 'prompt_too_long' );
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond(
				array( 'message' => __( 'Add your AI provider API key in SitePilot AI → Settings first.', 'sitepilot-ai' ) ),
				false,
				400,
				'provider_not_configured'
			);
		}

		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'sitepilot-ai' ) ), false, 400, 'connector_not_available' );
		}

		try {
			$result = ( new EfbFormSkill( $this->providers->make() ) )->plan( $prompt );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'efb.plan.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );

			return $this->respond( array( 'message' => $e->getMessage() ), false, 502, $e->error_code() );
		}

		if ( ! $result->ok ) {
			return $this->respond(
				array(
					'message' => __( 'The form plan was not safe or complete.', 'sitepilot-ai' ),
					'errors'  => $result->errors,
				),
				false,
				422,
				'validation_failed'
			);
		}

		$action_id = $this->store->create( $result->plan, 'easy_form_builder' );
		$this->logger->info( 'efb.plan.created', 'EFB form plan generated.', array( 'title' => $result->plan['title'] ) );

		return $this->respond(
			array(
				'action_id' => $action_id,
				'preview'   => $this->build_preview( $result->plan, $connector->get_name() ),
			),
			true
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$action_id = sanitize_text_field( (string) $request->get_param( 'action_id' ) );
		if ( '' === $action_id ) {
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'sitepilot-ai' ) ), false, 400, 'invalid_payload' );
		}

		$record = $this->store->consume( $action_id );
		if ( null === $record ) {
			return $this->respond( array( 'message' => __( 'This proposal has expired. Please generate the form again.', 'sitepilot-ai' ) ), false, 422, 'action_expired' );
		}
		if ( (int) $record['user_id'] !== get_current_user_id() ) {
			return $this->respond( array( 'message' => __( 'You are not allowed to approve this action.', 'sitepilot-ai' ) ), false, 403, 'permission_denied' );
		}

		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'sitepilot-ai' ) ), false, 400, 'connector_not_available' );
		}

		$plan   = (array) $record['plan'];
		$errors = $connector->validate_action( $plan );
		if ( array() !== $errors ) {
			return $this->respond( array( 'message' => implode( ' ', $errors ) ), false, 422, 'invalid_payload' );
		}

		$result = $connector->execute_action( $plan );
		if ( ( $result['status'] ?? '' ) !== 'success' ) {
			$this->logger->error( 'efb.create.failed', (string) ( $result['message'] ?? 'failed' ), array( 'code' => $result['code'] ?? 'execution_failed' ) );

			return $this->respond( array( 'message' => $result['message'] ?? __( 'Creation failed.', 'sitepilot-ai' ) ), false, 422, (string) ( $result['code'] ?? 'execution_failed' ) );
		}

		$this->logger->info( 'efb.create.executed', 'EFB form created.', array( 'form_id' => $result['form_id'] ?? null, 'title' => $plan['title'] ?? '' ) );

		return $this->respond(
			array(
				'result' => array(
					'connector' => $result['connector'] ?? '',
					'form_id'   => $result['form_id'] ?? 0,
					'edit_url'  => $result['edit_url'] ?? '',
					'shortcode' => $result['shortcode'] ?? '',
				),
			),
			true
		);
	}

	/**
	 * Answer a "how do I …?" question from the grounded EFB knowledge base.
	 */
	public function help( WP_REST_Request $request ): WP_REST_Response {
		$question = trim( (string) $request->get_param( 'question' ) );
		if ( '' === $question ) {
			return $this->respond( array( 'message' => __( 'Ask a question, e.g. "how do I make a field required?"', 'sitepilot-ai' ) ), false, 400, 'empty_question' );
		}

		$result = EfbKnowledge::answer( $question );

		if ( empty( $result['ok'] ) ) {
			return $this->respond(
				array(
					'message'     => __( 'I could not match that exactly. Try one of these topics:', 'sitepilot-ai' ),
					'suggestions' => $result['suggestions'] ?? array(),
				),
				true,
				200,
				'no_match'
			);
		}

		return $this->respond(
			array(
				'title'  => $result['title'] ?? '',
				'answer' => $result['answer'] ?? '',
			),
			true
		);
	}

	/**
	 * Translate a plain-language command into validated edit operations that the
	 * browser applies to the live Easy Form Builder structure.
	 */
	public function customize( WP_REST_Request $request ): WP_REST_Response {
		$command = trim( (string) $request->get_param( 'command' ) );
		if ( '' === $command ) {
			return $this->respond( array( 'message' => __( 'Type what you want to change.', 'sitepilot-ai' ) ), false, 400, 'empty_command' );
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond( array( 'message' => __( 'Add your AI provider API key in SitePilot AI → Settings first.', 'sitepilot-ai' ) ), false, 400, 'provider_not_configured' );
		}

		$raw_fields = (array) $request->get_param( 'fields' );
		$fields     = array();
		foreach ( $raw_fields as $f ) {
			if ( is_array( $f ) ) {
				$fields[] = array(
					'id'    => sanitize_text_field( (string) ( $f['id'] ?? '' ) ),
					'label' => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
					'type'  => sanitize_text_field( (string) ( $f['type'] ?? '' ) ),
				);
			}
		}

		try {
			$ops = ( new EfbCustomizeSkill( $this->providers->make() ) )->plan( $command, $fields );
		} catch ( ProviderException $e ) {
			return $this->respond( array( 'message' => $e->getMessage() ), false, 502, $e->error_code() );
		}

		if ( array() === $ops ) {
			return $this->respond( array( 'message' => __( 'I could not turn that into a change. Try being more specific, e.g. "make Email required" or "rename the form to Contact us".', 'sitepilot-ai' ) ), true, 200, 'no_ops' );
		}

		$this->logger->info( 'efb.customize', 'Customize command interpreted.', array( 'ops' => count( $ops ) ) );

		return $this->respond( array( 'operations' => $ops ), true );
	}

	/**
	 * Persist the live structure back into the EFB form row (called after the
	 * browser applies operations and refreshes the dropzone).
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$form_id   = (int) $request->get_param( 'form_id' );
		$structure = $request->get_param( 'structure' );

		if ( $form_id <= 0 || ! is_array( $structure ) || array() === $structure ) {
			return $this->respond( array( 'message' => __( 'Invalid save request.', 'sitepilot-ai' ) ), false, 400, 'invalid_payload' );
		}

		// Reject anything that looks like injected script markup.
		$flat = wp_json_encode( $structure );
		if ( is_string( $flat ) && false !== stripos( $flat, '<script' ) ) {
			return $this->respond( array( 'message' => __( 'Unsafe content rejected.', 'sitepilot-ai' ) ), false, 422, 'unsafe_content' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		$value = EfbFormConverter::encode_for_storage( array_values( $structure ) );
		$name  = sanitize_text_field( (string) ( $structure[0]['formName'] ?? 'Form' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update(
			$table,
			array(
				'form_name'     => $name,
				'form_structer' => $value,
			),
			array( 'form_id' => $form_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return $this->respond( array( 'message' => __( 'Could not save the form.', 'sitepilot-ai' ) ), false, 422, 'execution_failed' );
		}

		$this->logger->info( 'efb.save', 'EFB form updated.', array( 'form_id' => $form_id ) );

		return $this->respond( array( 'form_id' => $form_id ), true );
	}

	private function registry(): ConnectorRegistry {
		/** @var ConnectorRegistry $registry */
		$registry = Plugin::instance()->container()->get( ConnectorRegistry::class );

		return $registry;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return array<string, mixed>
	 */
	private function build_preview( array $plan, string $connector_name ): array {
		return array(
			'title'       => $plan['title'],
			'fields'      => array_map(
				static function ( array $f ): array {
					return array(
						'label'    => $f['label'],
						'type'     => $f['type'],
						'required' => (bool) $f['required'],
						'width'    => $f['width'] ?? 'full',
						'options'  => $f['options'],
					);
				},
				$plan['fields']
			),
			'confirmation_message' => $plan['confirmation_message'] ?? '',
			'risk_level'  => $plan['risk_level'] ?? 'medium',
			'connector'   => $connector_name,
			'field_count' => count( $plan['fields'] ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): WP_REST_Response {
		return new WP_REST_Response( array_merge( array( 'ok' => $ok, 'code' => $code ), $data ), $status );
	}
}
