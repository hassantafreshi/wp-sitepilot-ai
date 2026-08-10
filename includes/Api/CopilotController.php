<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Plugin;
use SitePilot\Core\Settings;
use SitePilot\Execution\ActionExecutor;
use SitePilot\Execution\ActionStore;
use SitePilot\Logging\Logger;
use SitePilot\Providers\OpenAiCompatibleProvider;
use SitePilot\Providers\ProviderException;
use SitePilot\Providers\ProviderManager;
use SitePilot\Skills\CreateFormSkill;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles the Admin Copilot REST endpoints: plan, execute, and provider test.
 */
final class CopilotController {

	private ProviderManager $providers;
	private ActionStore $store;
	private Logger $logger;

	public function __construct() {
		$this->providers = new ProviderManager();
		$this->store     = new ActionStore();
		$this->logger    = new Logger();
	}

	/**
	 * Generate a validated form plan and store it as a pending action.
	 */
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

		$skill = new CreateFormSkill( $this->providers->make() );

		try {
			$result = $skill->plan( $prompt );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'plan.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );

			return $this->respond( array( 'message' => $e->user_message() ), false, 502, $e->error_code() );
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

		$connector = $this->registry()->resolve( 'form.create' );
		if ( null === $connector ) {
			return $this->respond( array( 'message' => __( 'No form connector is available.', 'sitepilot-ai' ) ), false, 400, 'connector_not_available' );
		}

		$action_id = $this->store->create( $result->plan, $connector->get_id() );
		$this->logger->info( 'plan.created', 'Form plan generated.', array( 'title' => $result->plan['title'] ) );

		return $this->respond(
			array(
				'action_id' => $action_id,
				'preview'   => $this->build_preview( $result->plan, $connector->get_name() ),
			),
			true
		);
	}

	/**
	 * Approve + execute a stored plan.
	 */
	public function execute( WP_REST_Request $request ): WP_REST_Response {
		$action_id = sanitize_text_field( (string) $request->get_param( 'action_id' ) );
		if ( '' === $action_id ) {
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'sitepilot-ai' ) ), false, 400, 'invalid_payload' );
		}

		$executor = new ActionExecutor( $this->registry() );
		$result   = $executor->approve_and_execute( $action_id );

		if ( ( $result['status'] ?? '' ) !== 'success' ) {
			return $this->respond(
				array( 'message' => $result['message'] ?? __( 'Execution failed.', 'sitepilot-ai' ) ),
				false,
				422,
				(string) ( $result['code'] ?? 'execution_failed' )
			);
		}

		return $this->respond(
			array(
				'result' => array(
					'connector' => $result['connector'] ?? '',
					'form_id'   => $result['form_id'] ?? 0,
					'edit_url'  => $result['edit_url'] ?? '',
					'shortcode' => $result['shortcode'] ?? '',
					'is_draft'  => (bool) ( $result['is_draft'] ?? true ),
				),
			),
			true
		);
	}

	/**
	 * Test the AI provider connection. Accepts an optional not-yet-saved key so
	 * the user can verify before saving. The key is used transiently only.
	 */
	public function test( WP_REST_Request $request ): WP_REST_Response {
		$base_url = esc_url_raw( (string) ( $request->get_param( 'base_url' ) ?: Settings::get( 'base_url' ) ) );
		$model    = sanitize_text_field( (string) ( $request->get_param( 'model' ) ?: Settings::get( 'model' ) ) );
		$key      = (string) $request->get_param( 'api_key' );
		if ( '' === trim( $key ) ) {
			$key = Settings::get_api_key();
		}

		$provider = new OpenAiCompatibleProvider( $base_url, $model, $key, 0.0, 20 );
		$health   = $provider->health_check();

		return $this->respond( array( 'message' => $health['message'] ), (bool) $health['ok'], $health['ok'] ? 200 : 400, $health['ok'] ? 'ok' : 'health_failed' );
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
			'title'                => $plan['title'],
			'description'          => $plan['description'],
			'fields'               => array_map(
				static function ( array $f ): array {
					return array(
						'label'    => $f['label'],
						'type'     => $f['type'],
						'required' => (bool) $f['required'],
						'options'  => $f['options'],
					);
				},
				$plan['fields']
			),
			'submit_button'        => $plan['submit_button'],
			'confirmation_message' => $plan['confirmation_message'],
			'risk_level'           => $plan['risk_level'],
			'notes'                => $plan['notes'],
			'connector'            => $connector_name,
			'field_count'          => count( $plan['fields'] ),
			'default_status'       => Settings::get( 'default_form_status', 'draft' ),
		);
	}

	/**
	 * Uniform response envelope.
	 *
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): WP_REST_Response {
		$payload = array_merge( array( 'ok' => $ok, 'code' => $code ), $data );

		return new WP_REST_Response( $payload, $status );
	}
}
