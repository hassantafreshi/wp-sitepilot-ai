<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionExecutor;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ModelCatalogInterface;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Skills\CreateFormSkill;
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
			return $this->respond( array( 'message' => __( 'Please describe the form you want.', 'ai-site-assistant-autopilot' ) ), false, 400, 'empty_prompt' );
		}
		if ( mb_strlen( $prompt ) > 2000 ) {
			return $this->respond( array( 'message' => __( 'Your request is too long.', 'ai-site-assistant-autopilot' ) ), false, 400, 'prompt_too_long' );
		}

		if ( ! $this->providers->is_configured() ) {
			return $this->respond(
				array( 'message' => __( 'Add your AI provider API key in AI Site Assistant AutoPilot → Settings first.', 'ai-site-assistant-autopilot' ) ),
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
					'message' => __( 'The form plan was not safe or complete.', 'ai-site-assistant-autopilot' ),
					'errors'  => $result->errors,
				),
				false,
				422,
				'validation_failed'
			);
		}

		/*
		* This endpoint is the independent AISAAutoPilot Forms product. It never
		* auto-switches to Easy Form Builder: EFB has its own advanced route and
		* editor so the field/data contract remains predictable here.
		*/
		$connector = $this->registry()->get( 'native_form' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'AISAAutoPilot Forms is not available on this site.', 'ai-site-assistant-autopilot' ) ), false, 400, 'connector_not_available' );
		}

		$notification_email = Settings::form_notification_email();
		if ( '' === $notification_email ) {
			return $this->respond(
				array( 'message' => __( 'Set a valid AISAAutoPilot Forms notification email in Settings before creating a form.', 'ai-site-assistant-autopilot' ) ),
				false,
				400,
				'notification_email_not_configured'
			);
		}

		$plan                       = $result->plan;
		$plan['notification_email'] = $notification_email;

		$action_id = $this->store->create( $plan, $connector->get_id() );
		$this->logger->info(
			'plan.created',
			'Form plan generated.',
			array(
				'connector'   => $connector->get_id(),
				'field_count' => count( $plan['fields'] ),
			)
		);
		return $this->respond(
			array(
				'action_id'  => $action_id,
				'expires_at' => time() + ActionStore::ttl(),
				'preview'    => $this->build_preview( $plan, $connector->get_name() ),
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
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}

		$executor = new ActionExecutor( $this->registry() );
		$result   = $executor->approve_and_execute( $action_id );

		if ( ( $result['status'] ?? '' ) !== 'success' ) {
			return $this->respond(
				array( 'message' => $result['message'] ?? __( 'Execution failed.', 'ai-site-assistant-autopilot' ) ),
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
		$provider = ProviderManager::build( $this->requested_config( $request ) );
		$health   = $provider->health_check();

		// The Connections screen shows when the dependency report was last refreshed.
		\AISAAutoPilot\Admin\ConnectionsPage::stamp_diagnostics();

		// Settings ticks off "Test connection" only on a real success, and clears
		// the mark again whenever the endpoint, model or key changes.
		if ( $health['ok'] ) {
			update_option( \AISAAutoPilot\Admin\SettingsPage::VERIFIED_OPTION, time(), false );
		} else {
			delete_option( \AISAAutoPilot\Admin\SettingsPage::VERIFIED_OPTION );
		}

		return $this->respond( array( 'message' => $health['message'] ), (bool) $health['ok'], $health['ok'] ? 200 : 400, $health['ok'] ? 'ok' : 'health_failed' );
	}

	/**
	 * Ask the configured endpoint which models the key can use, so the Settings
	 * dropdown reflects the provider's catalogue today rather than the day the
	 * plugin was released.
	 */
	public function models( WP_REST_Request $request ): WP_REST_Response {
		$provider = ProviderManager::build( $this->requested_config( $request ) );

		if ( ! $provider instanceof ModelCatalogInterface ) {
			return $this->respond( array( 'message' => __( 'This provider does not publish a model list.', 'ai-site-assistant-autopilot' ) ), false, 400, 'models_unsupported' );
		}

		try {
			$models = $provider->list_models();
		} catch ( ProviderException $e ) {
			return $this->respond( array( 'message' => $e->getMessage() ), false, 400, $e->error_code() );
		}

		return $this->respond(
			array(
				'models'  => $models,
				'message' => sprintf(
					/* translators: %s: number of models returned by the provider. */
					__( '%s models loaded from the provider.', 'ai-site-assistant-autopilot' ),
					number_format_i18n( count( $models ) )
				),
			),
			true,
			200,
			'ok'
		);
	}

	/**
	 * The endpoint under test: whatever the administrator has typed into the
	 * Settings form, falling back to what is already saved.
	 *
	 * @return array<string, mixed>
	 */
	private function requested_config( WP_REST_Request $request ): array {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		if ( '' === $provider || ! array_key_exists( $provider, Settings::presets() ) ) {
			$provider = (string) Settings::get( 'provider', '' );
		}

		$key = (string) $request->get_param( 'api_key' );
		if ( '' === trim( $key ) ) {
			$key = Settings::get_api_key();
		}

		return array(
			'provider'    => $provider,
			'base_url'    => esc_url_raw( (string) ( $request->get_param( 'base_url' ) ?: Settings::get( 'base_url' ) ) ),
			'model'       => sanitize_text_field( (string) ( $request->get_param( 'model' ) ?: Settings::get( 'model' ) ) ),
			'api_key'     => $key,
			'temperature' => 0.0,
			'timeout'     => 20,
		);
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
			'notification_email'   => $this->mask_email( (string) ( $plan['notification_email'] ?? '' ) ),
			'field_count'          => count( $plan['fields'] ),
			'default_status'       => Settings::get( 'default_form_status', 'draft' ),
		);
	}

	private function mask_email( string $email ): string {

		$at = strpos( $email, '@' );
		if ( false === $at ) {
			return '';
		}
		$local = substr( $email, 0, $at );
		return ( strlen( $local ) > 1 ? substr( $local, 0, 1 ) . '***' : '*' ) . substr( $email, $at );
	}
	/**
	 * Uniform response envelope.
	 *
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): WP_REST_Response {
		$payload = array_merge(
			array(
				'ok'   => $ok,
				'code' => $code,
			),
			$data
		);

		return new WP_REST_Response( $payload, $status );
	}
}
