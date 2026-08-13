<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Plugin;
use SitePilot\Efb\EfbChatSkill;
use SitePilot\Efb\EfbCustomizeSkill;
use SitePilot\Efb\EfbFormConverter;
use SitePilot\Efb\EfbLicense;
use SitePilot\Efb\EfbFormModel;
use SitePilot\Efb\EfbFormSkill;
use SitePilot\Efb\EfbKnowledge;
use SitePilot\Execution\ActionStore;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderException;
use SitePilot\Providers\ProviderManager;
use SitePilot\Skills\FormPlanValidator;
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
			$result = ( new EfbFormSkill( $this->providers->make(), EfbLicense::has_pro_features() ) )->plan( $prompt );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'efb.plan.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );

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

		// Build what this licence can actually render, and say plainly what a
		// Free Plus or Pro licence would have added instead of failing outright.
		$split = EfbFormModel::split_pro_fields( $result->plan, EfbLicense::has_pro_features() );
		$plan  = $split['plan'];

		if ( array() === ( $plan['fields'] ?? array() ) ) {
			return $this->pro_required_response( $this->pro_blocked_from_fields( $split['pro_fields'] ) );
		}

		$action_id = $this->store->create( $plan, 'easy_form_builder' );
		$this->logger->info(
			'efb.plan.created',
			'EFB form plan generated.',
			array(
				'title'       => $plan['title'],
				'pro_omitted' => $split['pro_fields'],
			)
		);

		$payload = array(
			'action_id'  => $action_id,
			'expires_at' => time() + ActionStore::ttl(),
			'preview'    => $this->build_preview( $plan, $connector->get_name() ),
		);
		$payload = $this->with_pro_upsell( $payload, $split['pro_fields'] );

		return $this->respond( $payload, true );
	}

	/**
	 * Attach the "this needed Pro" notice to an otherwise successful response, so
	 * the admin gets their form AND knows what a licence would add.
	 *
	 * @param array<string,mixed>                        $payload
	 * @param array<int,array{type:string,label:string}> $pro_fields
	 * @return array<string,mixed>
	 */
	private function with_pro_upsell( array $payload, array $pro_fields ): array {
		if ( array() === $pro_fields ) {
			return $payload;
		}

		$names = array();
		foreach ( $pro_fields as $field ) {
			$definition = EfbFormModel::field_type( $field['type'] );
			$names[]    = sprintf( '%s (%s)', $field['label'], (string) ( $definition['label'] ?? $field['type'] ) );
		}

		$payload['pro_omitted']      = $pro_fields;
		$payload['upgrade_required'] = true;
		$payload['upgrade_reason']   = 'pro_field';
		$payload['upgrade_url']      = EfbLicense::upgrade_url();
		$payload['errors']           = array(
			sprintf(
				/* translators: %s: comma-separated field names. */
				_n(
					'I left out %s — that field needs Easy Form Builder Free Plus or Pro. Everything else is ready.',
					'I left out %s — those fields need Easy Form Builder Free Plus or Pro. Everything else is ready.',
					count( $names ),
					'sitepilot-ai'
				),
				implode( '، ', $names )
			),
		);

		return $payload;
	}

	/**
	 * @param array<int,array{type:string,label:string}> $pro_fields
	 * @return array<int,array{kind:string,type:string,label:string}>
	 */
	private function pro_blocked_from_fields( array $pro_fields ): array {
		$blocked = array();
		foreach ( $pro_fields as $field ) {
			$blocked[] = array(
				'kind'  => 'field',
				'type'  => $field['type'],
				'label' => $field['label'],
			);
		}

		return $blocked;
	}

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$action_id = sanitize_text_field( (string) $request->get_param( 'action_id' ) );
		if ( '' === $action_id ) {
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'sitepilot-ai' ) ), false, 400, 'invalid_payload' );
		}

		$claim = $this->store->claim( $action_id, get_current_user_id() );
		if ( 'missing' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'This proposal has expired. Please generate the form again.', 'sitepilot-ai' ) ), false, 422, 'action_expired' );
		}
		if ( 'forbidden' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'You are not allowed to approve this action.', 'sitepilot-ai' ) ), false, 403, 'permission_denied' );
		}
		if ( 'already_claimed' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'This proposal is already being processed or has already been used.', 'sitepilot-ai' ) ), false, 422, 'action_already_processed' );
		}
		$record    = (array) ( $claim['record'] ?? array() );
		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'sitepilot-ai' ) ), false, 400, 'connector_not_available' );
		}

		$plan    = (array) $record['plan'];
		$blocked = $this->blocked_plan_fields( $plan );
		if ( array() !== $blocked ) {
			return $this->pro_required_response( $blocked );
		}
		$errors = $connector->validate_action( $plan );
		if ( array() !== $errors ) {
			return $this->respond( array( 'message' => implode( ' ', $errors ) ), false, 422, 'invalid_payload' );
		}

		$result = $connector->execute_action( $plan );
		if ( ( $result['status'] ?? '' ) !== 'success' ) {
			$this->logger->error( 'efb.create.failed', (string) ( $result['message'] ?? 'failed' ), array( 'code' => $result['code'] ?? 'execution_failed' ) );

			return $this->respond( array( 'message' => $result['message'] ?? __( 'Creation failed.', 'sitepilot-ai' ) ), false, 422, (string) ( $result['code'] ?? 'execution_failed' ) );
		}

		$this->logger->info(
			'efb.create.executed',
			'EFB form created.',
			array(
				'form_id' => $result['form_id'] ?? null,
				'title'   => $plan['title'] ?? '',
			)
		);

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
	 * Unified conversational endpoint (ChatGPT-style single thread). Decides intent
	 * and returns a typed response: message | preview | ops | help.
	 */
	public function chat( WP_REST_Request $request ): WP_REST_Response {
		$trace_id = $this->trace_id( $request->get_param( 'trace_id' ) );
		$message  = trim( (string) $request->get_param( 'message' ) );
		if ( '' === $message ) {
			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => __( 'Tell me what form you need, ask me to change the open form, or ask how to do something.', 'sitepilot-ai' ),
				),
				true
			);
		}
		if ( mb_strlen( $message ) > 2000 ) {
			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => __( 'That message is a bit long — please shorten it.', 'sitepilot-ai' ),
				),
				true
			);
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => __( 'Add your AI provider API key in SitePilot AI → Settings first.', 'sitepilot-ai' ),
				),
				false,
				400,
				'provider_not_configured'
			);
		}

		$has_form = (bool) $request->get_param( 'has_form' );
		$fields   = $this->clean_fields( (array) $request->get_param( 'fields' ) );
		$steps    = $this->clean_steps( (array) $request->get_param( 'steps' ) );
		$history  = $this->clean_history( (array) $request->get_param( 'history' ) );
		$license  = EfbLicense::status();
		$this->logger->info(
			'efb.chat.received',
			'Chat request received.',
			array(
				'trace_id'      => $trace_id,
				'message'       => mb_substr( $message, 0, 200 ),
				'has_form'      => $has_form,
				'field_count'   => count( $fields ),
				'step_count'    => count( $steps ),
				'plan_tier'     => $license['tier'],
				'history_count' => count( $history ),
			)
		);

		try {
			$raw = ( new EfbChatSkill( $this->providers->make() ) )->respond( $message, $history, $has_form, $fields, $steps, $license );
		} catch ( ProviderException $e ) {
			$this->logger->error(
				'efb.chat.provider_error',
				$e->getMessage(),
				array(
					'trace_id' => $trace_id,
					'code'     => $e->error_code(),
					'message'  => mb_substr( $message, 0, 200 ),
				)
			);

			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => $e->user_message(),
				),
				false,
				502,
				$e->error_code()
			);
		}

		$reply  = trim( (string) ( $raw['reply'] ?? '' ) );
		$action = (string) ( $raw['action'] ?? 'chat' );

		// Trace every turn: what the user asked, what the AI decided, raw op names.
		$raw_ops        = is_array( $raw['operations'] ?? null ) ? $raw['operations'] : array();
		$normalized_ops = $this->normalize_structural_ops( $raw_ops, $message );
		$this->logger->info(
			'efb.chat.turn',
			'Chat turn decided.',
			array(
				'trace_id'       => $trace_id,
				'message'        => mb_substr( $message, 0, 200 ),
				'action'         => $action,
				'has_form'       => $has_form,
				'fields'         => count( $fields ),
				'raw_ops'        => array_map(
					static fn ( $o ) => is_array( $o ) ? (string) ( $o['op'] ?? '?' ) : '?',
					$raw_ops
				),
				'normalized_ops' => array_map(
					static fn ( $o ) => is_array( $o ) ? (string) ( $o['op'] ?? '?' ) : '?',
					$normalized_ops
				),
			)
		);

		if ( 'answer_help' === $action ) {
			$topic  = trim( (string) ( $raw['help_topic'] ?? '' ) );
			$result = EfbKnowledge::answer( '' !== $topic ? $topic : $message );
			if ( empty( $result['ok'] ) ) {
				return $this->respond(
					array(
						'type'        => 'help',
						'reply'       => '' !== $reply ? $reply : __( 'Here are topics I can walk you through:', 'sitepilot-ai' ),
						'suggestions' => $result['suggestions'] ?? array(),
					),
					true
				);
			}
			return $this->respond(
				array(
					'type'   => 'help',
					'reply'  => $reply,
					'title'  => $result['title'] ?? '',
					'answer' => $result['answer'] ?? '',
				),
				true
			);
		}

		if ( 'customize' === $action ) {
			$verbose = EfbCustomizeSkill::validate_ops_verbose( $normalized_ops, $fields );
			$ops     = $verbose['ops'];
			$dropped = $verbose['dropped'];

			if ( array() !== $dropped ) {
				$this->logger->error(
					'efb.chat.ops_dropped',
					'Some operations were rejected by validation.',
					array(
						'trace_id' => $trace_id,
						'dropped'  => $dropped,
						'message'  => mb_substr( $message, 0, 200 ),
					)
				);
			}

			$notes = $this->customize_notes( $dropped );

			// A Pro-only field is rejected during validation, so it never reaches
			// the Pro check below. Answer with the real upgrade prompt instead of a
			// generic failure that reads like the admin phrased it wrong.
			$pro_blocked = $this->pro_fields_in_dropped( $dropped );
			if ( array() !== $pro_blocked && array() === $ops ) {
				$this->logger->info(
					'efb.chat.pro_blocked',
					'A Pro-only field was requested.',
					array(
						'trace_id' => $trace_id,
						'blocked'  => $pro_blocked,
						'license'  => $license,
					)
				);

				return $this->pro_required_response( $pro_blocked, $reply );
			}

			if ( array() === $ops ) {
				// Never pretend success: explain what was rejected and why.
				return $this->respond(
					array(
						'type'   => 'message',
						'reply'  => '' !== $reply ? $reply : __( 'I could not turn that into a change. Try e.g. "add a mobile field" or "make Email required".', 'sitepilot-ai' ),
						'errors' => $notes,
					),
					true
				);
			}

			$ops     = $this->bind_new_step_references( $ops );
			$blocked = $this->blocked_customize_operations( $ops, $steps );
			if ( array() !== $blocked ) {
				$this->logger->info(
					'efb.chat.pro_blocked',
					'EFB plan blocked a requested operation.',
					array(
						'trace_id' => $trace_id,
						'blocked'  => $blocked,
						'license'  => $license,
					)
				);

				return $this->pro_required_response( $blocked );
			}

			$ops = $this->expand_add_fields( $ops, $fields );

			$this->logger->info(
				'efb.chat.customize',
				'Customize ops ready.',
				array(
					'trace_id' => $trace_id,
					'ops'      => $ops,
				)
			);

			// A partly-applied batch must say so rather than answering a plain "Done".
			$payload = array(
				'type'       => 'ops',
				'reply'      => '' !== $reply ? $reply : __( 'Done — applying your change.', 'sitepilot-ai' ),
				'operations' => $ops,
			);
			if ( array() !== $notes ) {
				$payload['errors'] = $notes;
			}

			return $this->respond( $payload, true );
		}

		if ( 'create_form' === $action && is_array( $raw['form'] ?? null ) ) {
			$validator = $this->efb_validator();
			$result    = $validator->validate( (array) $raw['form'] );
			if ( ! $result->ok ) {
				return $this->respond(
					array(
						'type'   => 'message',
						'reply'  => '' !== $reply ? $reply : __( 'I could not build a safe form from that. Could you add a bit more detail?', 'sitepilot-ai' ),
						'errors' => $result->errors,
					),
					true
				);
			}
			$split = EfbFormModel::split_pro_fields( $result->plan, EfbLicense::has_pro_features() );
			$plan  = $split['plan'];
			if ( array() === ( $plan['fields'] ?? array() ) ) {
				return $this->pro_required_response( $this->pro_blocked_from_fields( $split['pro_fields'] ) );
			}
			$connector = $this->registry()->get( 'easy_form_builder' );
			$name      = ( null !== $connector ) ? $connector->get_name() : 'Easy Form Builder';
			$action_id = $this->store->create( $plan, 'easy_form_builder' );
			$this->logger->info(
				'efb.chat.plan',
				'Form planned via chat.',
				array(
					'trace_id'    => $trace_id,
					'title'       => $plan['title'],
					'pro_omitted' => $split['pro_fields'],
				)
			);

			$payload = array(
				'type'      => 'preview',
				'reply'     => '' !== $reply ? $reply : __( 'Here is the form I designed — review it and approve to create it.', 'sitepilot-ai' ),
				'action_id' => $action_id,
				'preview'   => $this->build_preview( $plan, $name ),
			);

			return $this->respond( $this->with_pro_upsell( $payload, $split['pro_fields'] ), true );
		}

		// ask / chat / fallback.
		return $this->respond(
			array(
				'type'  => 'message',
				'reply' => '' !== $reply ? $reply : __( 'How can I help with your form?', 'sitepilot-ai' ),
			),
			true
		);
	}

	/**
	 * Server-side expansion of add_field ops: build the full EFB field (+ option)
	 * items from the model templates, so the browser only splices them in.
	 *
	 * @param array<int, array<string, mixed>>                      $ops
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 * @return array<int, array<string, mixed>>
	 */
	private function expand_add_fields( array $ops, array $fields ): array {
		$existing  = array_map( static fn ( $f ) => (string) $f['id'], $fields );
		$converter = new EfbFormConverter();

		foreach ( $ops as &$op ) {
			if ( 'add_field' === ( $op['op'] ?? '' ) && is_array( $op['field'] ?? null ) ) {
				$items       = $converter->build_field( $op['field'], $existing );
				$op['items'] = $items;
				foreach ( $items as $item ) {
					$existing[] = (string) ( $item['id_'] ?? '' );
				}
			} elseif ( 'add_step' === ( $op['op'] ?? '' ) ) {
				$op['item'] = $converter->build_step( (string) ( $op['label'] ?? 'Step' ) );
			}
		}
		unset( $op );

		return $ops;
	}

	/**
	 * Receive a client-side apply report so the Logs page shows the full story:
	 * which operations were applied in the browser and which failed.
	 */
	public function client_log( WP_REST_Request $request ): WP_REST_Response {
		$event               = sanitize_key( (string) $request->get_param( 'event' ) );
		$msg                 = sanitize_text_field( mb_substr( (string) $request->get_param( 'message' ), 0, 300 ) );
		$context             = $this->clean_trace_context( $request->get_param( 'context' ) );
		$context['trace_id'] = $this->trace_id( $request->get_param( 'trace_id' ) );

		if ( '' === $event ) {
			return $this->respond( array( 'message' => 'missing event' ), false, 400, 'invalid_payload' );
		}

		$failed = is_array( $context['failed'] ?? null ) ? $context['failed'] : array();
		if ( array() !== $failed ) {
			$this->logger->error( 'efb.client.' . $event, '' !== $msg ? $msg : 'Client reported failures.', $context );
		} else {
			$this->logger->info( 'efb.client.' . $event, '' !== $msg ? $msg : 'Client report.', $context );
		}

		return $this->respond( array(), true );
	}

	private function efb_validator(): FormPlanValidator {
		$limits = EfbFormModel::limits();

		return new FormPlanValidator(
			EfbFormModel::designable_types( EfbLicense::has_pro_features() ),
			EfbFormModel::option_types(),
			EfbFormModel::forbidden_types(),
			EfbFormModel::aliases(),
			(int) ( $limits['max_fields'] ?? 30 )
		);
	}

	/**
	 * Correct a known model failure mode before validation: a request for a real
	 * EFB Step was previously represented as a display Heading. The conversion is
	 * deliberately narrow so an explicit request for a heading remains a heading.
	 *
	 * @param array<int,mixed> $ops
	 * @return array<int,mixed>
	 */
	private function normalize_structural_ops( array $ops, string $message ): array {
		$asks_for_step    = (bool) preg_match( '/(?:\\bstep(?:s)?\\b|گام|مرحله)/iu', $message );
		$asks_for_heading = (bool) preg_match( '/(?:\\bheading\\b|عنوان|سرتیتر)/iu', $message );
		if ( ! $asks_for_step || $asks_for_heading ) {
			return $ops;
		}

		foreach ( $ops as &$op ) {
			if ( ! is_array( $op ) || 'add_field' !== ( $op['op'] ?? '' ) || ! is_array( $op['field'] ?? null ) ) {
				continue;
			}
			$type  = strtolower( trim( (string) ( $op['field']['type'] ?? '' ) ) );
			$label = (string) ( $op['field']['label'] ?? '' );
			if ( 'heading' === $type && ( '' === $label || (bool) preg_match( '/(?:\\bstep(?:s)?\\b|گام|مرحله)/iu', $label ) ) ) {
				$op = array(
					'op'    => 'add_step',
					'label' => '' !== trim( $label ) ? $label : 'Step',
				);
			}
		}
		unset( $op );

		return $ops;
	}

	/**
	 * Give new steps stable per-response references and attach following fields
	 * when the model omitted the reference. This preserves operation order and
	 * lets the browser place "Address" in the Step just created above it.
	 *
	 * @param array<int,array<string,mixed>> $ops
	 * @return array<int,array<string,mixed>>
	 */
	private function bind_new_step_references( array $ops ): array {
		$active_ref = '';
		$number     = 0;
		foreach ( $ops as &$op ) {
			if ( 'add_step' === ( $op['op'] ?? '' ) ) {
				++$number;
				$ref            = sanitize_key( (string) ( $op['step_ref'] ?? '' ) );
				$active_ref     = '' !== $ref ? $ref : 'new_step_' . $number;
				$op['step_ref'] = $active_ref;
			} elseif ( 'add_field' === ( $op['op'] ?? '' ) && '' !== $active_ref && empty( $op['step_ref'] ) ) {
				$op['step_ref'] = $active_ref;
			}
		}
		unset( $op );

		return $ops;
	}

	/**
	 * @param array<int,array<string,mixed>>           $ops
	 * @param array<int,array{id:string,label:string}> $steps
	 * @return array<int,array{kind:string,type?:string,label?:string}>
	 */
	private function blocked_customize_operations( array $ops, array $steps ): array {
		$license = EfbLicense::status();
		if ( ! empty( $license['has_pro_features'] ) ) {
			return array();
		}

		$blocked    = array();
		$step_count = count( $steps );
		foreach ( $ops as $op ) {
			if ( 'add_field' === ( $op['op'] ?? '' ) && is_array( $op['field'] ?? null ) ) {
				$type = (string) ( $op['field']['type'] ?? '' );
				if ( EfbFormModel::requires_pro_to_create( $type ) ) {
					$blocked[] = array(
						'kind'  => 'field',
						'type'  => $type,
						'label' => (string) ( $op['field']['label'] ?? $type ),
					);
				}
			}
			if ( 'add_step' === ( $op['op'] ?? '' ) ) {
				++$step_count;
				if ( $step_count > 2 ) {
					$blocked[] = array(
						'kind'  => 'step_limit',
						'label' => (string) ( $op['label'] ?? 'Step' ),
					);
				}
			}
			// EFB padlocks the whole mobile responsive block on Free, so writing
			// those keys would move settings the admin can neither see nor undo.
			if ( 'set_field_property' === ( $op['op'] ?? '' ) && EfbFormModel::requires_pro_property( (string) ( $op['property'] ?? '' ) ) ) {
				$blocked[] = array(
					'kind'  => 'setting',
					'type'  => (string) $op['property'],
					'label' => (string) ( $op['target'] ?? '' ),
				);
			}
			if ( 'set_form_property' === ( $op['op'] ?? '' ) && EfbFormModel::requires_pro_form_property( (string) ( $op['property'] ?? '' ) ) ) {
				$blocked[] = array(
					'kind'  => 'setting',
					'type'  => (string) $op['property'],
					'label' => '',
				);
			}
		}

		return $blocked;
	}

	/**
	 * @param array<string,mixed> $plan
	 * @return array<int,array{kind:string,type?:string,label?:string}>
	 */
	private function blocked_plan_fields( array $plan ): array {
		if ( EfbLicense::has_pro_features() ) {
			return array();
		}

		$blocked = array();
		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( is_array( $field ) && EfbFormModel::requires_pro_field( (string) ( $field['type'] ?? '' ) ) ) {
				$blocked[] = array(
					'kind'  => 'field',
					'type'  => (string) ( $field['type'] ?? '' ),
					'label' => (string) ( $field['label'] ?? ( $field['type'] ?? '' ) ),
				);
			}
		}

		return $blocked;
	}

	/**
	 * Pro-only field types the validator threw out before the Pro gate could see
	 * them, shaped like blocked_customize_operations() so the same response works.
	 *
	 * @param array<int,array{op:string,reason:string}> $dropped
	 * @return array<int,array{kind:string,type:string,label:string}>
	 */
	private function pro_fields_in_dropped( array $dropped ): array {
		$blocked = array();
		foreach ( $dropped as $d ) {
			$reason = (string) ( $d['reason'] ?? '' );
			if ( ! str_starts_with( $reason, 'recognized_but_not_creatable:' ) ) {
				continue;
			}
			$type = substr( $reason, strpos( $reason, ':' ) + 1 );
			if ( '' !== $type && EfbFormModel::requires_pro_field( $type ) ) {
				$definition = EfbFormModel::field_type( $type );
				$blocked[]  = array(
					'kind'  => 'field',
					'type'  => $type,
					'label' => (string) ( $definition['label'] ?? $type ),
				);
			}
		}

		return $blocked;
	}

	/**
	 * Turn validation rejections into sentences for the admin. Used whether the
	 * batch was refused outright or only partly applied — either way the reply
	 * has to account for what did not happen.
	 *
	 * @param array<int,array{op:string,reason:string}> $dropped
	 * @return string[]
	 */
	private function customize_notes( array $dropped ): array {
		$notes = array();
		foreach ( $dropped as $d ) {
			$reason = (string) ( $d['reason'] ?? '' );
			if ( str_starts_with( $reason, 'unsupported_type:' ) || str_starts_with( $reason, 'forbidden_type:' ) ) {
				/* translators: %s: field type. */
				$notes[] = sprintf( __( 'Field type "%s" is not supported in Easy Form Builder.', 'sitepilot-ai' ), substr( $reason, strpos( $reason, ':' ) + 1 ) );
			} elseif ( str_starts_with( $reason, 'choice_needs_options:' ) ) {
				$notes[] = __( 'A choice field needs at least one option — tell me the options.', 'sitepilot-ai' );
			} elseif ( str_starts_with( $reason, 'recognized_but_not_creatable:' ) ) {
				$type       = substr( $reason, strpos( $reason, ':' ) + 1 );
				$definition = EfbFormModel::field_type( $type );
				/* translators: %s: field name. */
				$notes[] = sprintf( __( 'I can configure a "%s" field but not add one — drag it in from the Easy Form Builder palette, then ask me again.', 'sitepilot-ai' ), (string) ( $definition['label'] ?? $type ) );
			} elseif ( str_starts_with( $reason, 'operation_limit_reached:' ) ) {
				$parts   = explode( ':', $reason );
				$skipped = (int) ( $parts[2] ?? 0 );
				$notes[] = sprintf(
					/* translators: 1: number of applied operations, 2: number of skipped operations. */
					__( 'That was too many changes at once: I applied the first %1$d and skipped %2$d. Ask again for the rest.', 'sitepilot-ai' ),
					(int) ( $parts[1] ?? 0 ),
					$skipped
				);
			}
		}

		return array_values( array_unique( $notes ) );
	}

	/**
	 * Uses EFB's own notion of a locked Pro field. The browser turns this into
	 * the exact EFB `pro_show_efb()` dialog; the URL is the official fallback.
	 *
	 * @param array<int,array{kind:string,type?:string,label?:string}> $blocked
	 */
	private function pro_required_response( array $blocked, ?string $reply = null ): WP_REST_Response {
		$is_step_limit = (bool) array_filter( $blocked, static fn ( array $item ): bool => 'step_limit' === ( $item['kind'] ?? '' ) );
		$is_setting    = ! $is_step_limit && (bool) array_filter( $blocked, static fn ( array $item ): bool => 'setting' === ( $item['kind'] ?? '' ) );
		if ( $is_step_limit ) {
			$default = __( 'Easy Form Builder Free supports up to two steps. Upgrade to add more steps.', 'sitepilot-ai' );
		} elseif ( $is_setting ) {
			$default = __( 'The mobile layout settings are part of the Pro version of Easy Form Builder.', 'sitepilot-ai' );
		} else {
			$default = __( 'This field is available in the Pro version of Easy Form Builder.', 'sitepilot-ai' );
		}

		return $this->respond(
			array(
				'type'             => 'message',
				'reply'            => null !== $reply && '' !== $reply ? $reply : $default,
				'upgrade_required' => true,
				'upgrade_reason'   => $is_step_limit ? 'step_limit' : 'pro_field',
				'upgrade_url'      => EfbLicense::upgrade_url(),
				'blocked'          => $blocked,
			),
			true
		);
	}

	/**
	 * @param array<mixed> $raw
	 * @return array<int, array{id:string,label:string,type:string}>
	 */
	private function clean_fields( array $raw ): array {
		$out = array();
		foreach ( $raw as $f ) {
			if ( is_array( $f ) ) {
				$out[] = array(
					'id'    => sanitize_text_field( (string) ( $f['id'] ?? '' ) ),
					'label' => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
					'type'  => sanitize_text_field( (string) ( $f['type'] ?? '' ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * @param array<mixed> $raw
	 * @return array<int,array{id:string,label:string}>
	 */
	private function clean_steps( array $raw ): array {
		$out = array();
		foreach ( array_slice( $raw, 0, 30 ) as $step ) {
			if ( is_array( $step ) ) {
				$id = sanitize_text_field( (string) ( $step['id'] ?? '' ) );
				if ( '' !== $id ) {
					$out[] = array(
						'id'    => $id,
						'label' => sanitize_text_field( (string) ( $step['label'] ?? '' ) ),
					);
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<mixed> $raw
	 * @return array<int, array{role:string,text:string}>
	 */
	private function clean_history( array $raw ): array {
		$out = array();
		foreach ( array_slice( $raw, -10 ) as $h ) {
			if ( is_array( $h ) ) {
				$out[] = array(
					'role' => ( ( $h['role'] ?? '' ) === 'user' ) ? 'user' : 'assistant',
					'text' => mb_substr( sanitize_text_field( (string) ( $h['text'] ?? '' ) ), 0, 400 ),
				);
			}
		}

		return $out;
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
			return $this->respond( array( 'message' => $e->user_message() ), false, 502, $e->error_code() );
		}

		if ( array() === $ops ) {
			return $this->respond( array( 'message' => __( 'I could not turn that into a change. Try being more specific, e.g. "make Email required" or "rename the form to Contact us".', 'sitepilot-ai' ) ), true, 200, 'no_ops' );
		}

		$ops     = $this->bind_new_step_references( $ops );
		$blocked = $this->blocked_customize_operations( $ops, array() );
		if ( array() !== $blocked ) {
			return $this->pro_required_response( $blocked );
		}

		$ops = $this->expand_add_fields( $ops, $fields );

		$this->logger->info( 'efb.customize', 'Customize command interpreted.', array( 'ops' => count( $ops ) ) );

		return $this->respond( array( 'operations' => $ops ), true );
	}

	/**
	 * Explicit persistence endpoint for integration consumers. The SitePilot EFB
	 * chat deliberately does not call this after applying an AI proposal: it
	 * leaves the changed valj_efb state on the canvas for the administrator to
	 * review and save through EFB's normal Save control.
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
			'title'                => $plan['title'],
			'fields'               => array_map(
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
			'risk_level'           => $plan['risk_level'] ?? 'medium',
			'connector'            => $connector_name,
			'field_count'          => count( $plan['fields'] ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): WP_REST_Response {
		return new WP_REST_Response(
			array_merge(
				array(
					'ok'   => $ok,
					'code' => $code,
				),
				$data
			),
			$status
		);
	}

	/** @param mixed $value */
	private function trace_id( $value ): string {
		$trace = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ) ?? '';
		if ( '' === $trace ) {
			$trace = substr( wp_generate_uuid4(), 0, 12 );
		}

		return substr( $trace, 0, 64 );
	}

	/**
	 * Keep browser diagnostics useful but bounded. The client is authenticated,
	 * yet its payload is still untrusted and must not turn the option log into an
	 * unbounded arbitrary-data store.
	 *
	 * @param mixed $value
	 * @return array<string|int, mixed>
	 */
	private function clean_trace_context( $value, int $depth = 0 ): array {
		if ( ! is_array( $value ) || $depth > 3 ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $value, 0, 20, true ) as $key => $item ) {
			$clean_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
			if ( is_array( $item ) ) {
				$out[ $clean_key ] = $this->clean_trace_context( $item, $depth + 1 );
			} elseif ( is_scalar( $item ) || null === $item ) {
				$out[ $clean_key ] = mb_substr( sanitize_text_field( (string) $item ), 0, 500 );
			}
		}

		return $out;
	}
}
