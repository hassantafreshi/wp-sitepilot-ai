<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Execution\ActionExecutor;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ModelCatalogInterface;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Skills\CreateFormSkill;
use AISAAutoPilot\Skills\FormPlanEditor;
use AISAAutoPilot\Skills\FormPlanValidator;
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
		if ( ! Settings::get( 'form_composer_enabled', true ) ) {
			return $this->respond( array( 'message' => __( 'Form Studio is turned off in Integrations.', 'ai-site-assistant-autopilot' ) ), false, 403, 'integration_disabled' );
		}
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

		// A change to the plan already on the canvas: the server holds the full plan, so the model
		// gets every detail of it (widths, hints, order) and not a summary the browser rebuilt.
		$revise_of = sanitize_text_field( (string) $request->get_param( 'revise_of' ) );
		$base      = null;
		if ( '' !== $revise_of ) {
			$base = $this->own_plan( $revise_of );
			if ( null === $base ) {
				return $this->respond( array( 'message' => __( 'That draft has expired. Describe the form again to start a new one.', 'ai-site-assistant-autopilot' ) ), false, 410, 'plan_expired' );
			}
		}
		$selected_key = $this->field_key( (string) $request->get_param( 'selected' ), $base );

		try {
			$result = null === $base ? $skill->plan( $prompt ) : $skill->revise( $base, $prompt, $selected_key );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'plan.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );

			return $this->respond( array( 'message' => $e->user_message() ), false, $e->status_code(), $e->error_code() );
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

		return $this->finish_plan( $result->plan, $connector, 'plan.created', null !== $base );
	}

	/**
	 * Deterministic edits to the plan on the canvas (width, required, text, order, remove). No AI
	 * call, so a click costs nothing; the result is validated like any other plan.
	 */
	public function edit( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'form_composer_enabled', true ) ) {
			return $this->respond( array( 'message' => __( 'Form Studio is turned off in Integrations.', 'ai-site-assistant-autopilot' ) ), false, 403, 'integration_disabled' );
		}
		$base = $this->own_plan( sanitize_text_field( (string) $request->get_param( 'action_id' ) ) );
		if ( null === $base ) {
			return $this->respond( array( 'message' => __( 'That draft has expired. Describe the form again to start a new one.', 'ai-site-assistant-autopilot' ) ), false, 410, 'plan_expired' );
		}
		$ops = $request->get_param( 'ops' );
		if ( ! is_array( $ops ) || array() === $ops || count( $ops ) > 20 ) {
			return $this->respond( array( 'message' => __( 'Nothing to change.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}

		$edited = FormPlanEditor::apply( $base, $ops );
		if ( null === $edited ) {
			return $this->respond( array( 'message' => __( 'That change could not be applied to the form.', 'ai-site-assistant-autopilot' ) ), false, 422, 'edit_failed' );
		}
		$result = ( new FormPlanValidator() )->validate( $edited );
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
		$connector = $this->registry()->get( 'native_form' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'AISAAutoPilot Forms is not available on this site.', 'ai-site-assistant-autopilot' ) ), false, 400, 'connector_not_available' );
		}

		return $this->finish_plan( $result->plan, $connector, 'plan.edited', true );
	}

	/**
	 * The stored plan of an unexpired proposal this administrator made for native forms.
	 *
	 * @return array<string,mixed>|null
	 */
	private function own_plan( string $action_id ): ?array {
		if ( '' === $action_id ) {
			return null;
		}
		$record = $this->store->get( $action_id );
		if ( null === $record || (int) ( $record['user_id'] ?? 0 ) !== get_current_user_id() || 'native_form' !== ( $record['connector_id'] ?? '' ) ) {
			return null;
		}

		return is_array( $record['plan'] ?? null ) ? $record['plan'] : null;
	}

	/**
	 * A selected field is only passed on when it really is a field of the plan.
	 *
	 * @param array<string,mixed>|null $plan
	 */
	private function field_key( string $key, ?array $plan ): ?string {
		if ( null === $plan || '' === $key ) {
			return null;
		}
		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( is_array( $field ) && ( $field['key'] ?? '' ) === $key ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Store a validated plan as a pending proposal and answer with its preview.
	 *
	 * @param array<string,mixed> $plan
	 */
	private function finish_plan( array $plan, \AISAAutoPilot\Connectors\ConnectorInterface $connector, string $event, bool $revised ): WP_REST_Response {
		$notification_email = Settings::form_notification_email();
		if ( '' === $notification_email ) {
			return $this->respond(
				array( 'message' => __( 'Set a valid AISAAutoPilot Forms notification email in Settings before creating a form.', 'ai-site-assistant-autopilot' ) ),
				false,
				400,
				'notification_email_not_configured'
			);
		}

		$plan['notification_email'] = $notification_email;

		$default_status = 'publish' === Settings::get( 'default_form_status', 'draft' ) ? 'publish' : 'draft';
		$action_id      = $this->store->create(
			$plan,
			$connector->get_id(),
			array( 'status' => $default_status )
		);
		$this->logger->info(
			$event,
			'Form plan generated.',
			array(
				'connector'   => $connector->get_id(),
				'field_count' => count( $plan['fields'] ),
				'revised'     => $revised,
			)
		);
		return $this->respond(
			array(
				'action_id'  => $action_id,
				'expires_at' => time() + ActionStore::ttl(),
				'preview'    => $this->build_preview( $plan, $connector->get_name(), $default_status ),
			),
			true
		);
	}

	/**
	 * Approve + execute a stored plan.
	 */
	public function execute( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'form_composer_enabled', true ) ) {
			return $this->respond( array( 'message' => __( 'Form Studio is turned off in Integrations.', 'ai-site-assistant-autopilot' ) ), false, 403, 'integration_disabled' );
		}
		$action_id = sanitize_text_field( (string) $request->get_param( 'action_id' ) );
		if ( '' === $action_id ) {
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}

		$executor = new ActionExecutor( $this->registry() );
		$result   = $executor->approve_and_execute( $action_id, 'native_form' );

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
		$config   = $this->requested_config( $request );
		$provider = ProviderManager::build( $config );
		$health   = $provider->health_check();

		// A test may use a key that has not been saved yet. Bind a successful test
		// to this administrator and promote it only when the same configuration is
		// saved; an unsuccessful draft test must not unset a working live provider.
		\AISAAutoPilot\Admin\SettingsPage::record_provider_test( $config, (bool) $health['ok'] );

		$diagnostic = sanitize_key( (string) ( $health['code'] ?? ( $health['ok'] ? 'connected' : 'health_failed' ) ) );
		return $this->respond(
			array(
				'message'    => $health['message'],
				'diagnostic' => $diagnostic,
			),
			(bool) $health['ok'],
			$health['ok'] ? 200 : 400,
			$diagnostic
		);
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

		$key       = (string) $request->get_param( 'api_key' );
		$key_mode  = sanitize_key( (string) $request->get_param( 'key_management' ) );
		$clear_key = in_array( $request->get_param( 'clear_api_key' ), array( true, 1, '1' ), true );
		if ( '' === trim( $key ) && ! $clear_key ) {
			$key = 'wp_connectors' === $key_mode ? WpConnectors::get_api_key() : WpConnectors::get_effective_api_key();
		}

		return array(
			'provider'    => $provider,
			// A missing parameter means "use the saved configuration". An explicit
			// empty field must remain empty so a setup test reports invalid_config
			// instead of silently testing the previous provider endpoint.
			'base_url'    => esc_url_raw( (string) ( $request->has_param( 'base_url' ) ? $request->get_param( 'base_url' ) : Settings::get( 'base_url' ) ) ),
			'model'       => sanitize_text_field( (string) ( $request->has_param( 'model' ) ? $request->get_param( 'model' ) : Settings::get( 'model' ) ) ),
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
	private function build_preview( array $plan, string $connector_name, string $default_status ): array {
		return array(
			'title'                => $plan['title'],
			'description'          => $plan['description'],
			'fields'               => array_map(
				static function ( array $f ): array {
					return array(
						'key'         => $f['key'],
						'label'       => $f['label'],
						'type'        => $f['type'],
						'required'    => (bool) $f['required'],
						'placeholder' => $f['placeholder'] ?? '',
						'help'        => $f['help'] ?? '',
						'width'       => $f['width'] ?? 'full',
						'options'     => $f['options'],
						'settings'    => $f['settings'] ?? array(),
					);
				},
				$plan['fields']
			),
			'submit_button'        => $plan['submit_button'],
			'confirmation_message' => $plan['confirmation_message'],
			'risk_level'           => $plan['risk_level'],
			'notes'                => $plan['notes'],
			'needs_efb'            => array_values( (array) ( $plan['needs_efb'] ?? array() ) ),
			'connector'            => $connector_name,
			'notification_email'   => $this->mask_email( (string) ( $plan['notification_email'] ?? '' ) ),
			'field_count'          => count( $plan['fields'] ),
			'default_status'       => 'publish' === $default_status ? 'publish' : 'draft',
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
