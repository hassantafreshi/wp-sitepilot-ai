<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Efb\EfbChatSkill;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbAddons;
use AISAAutoPilot\Efb\EfbLicense;
use AISAAutoPilot\Efb\EfbPayments;
use AISAAutoPilot\Efb\EfbFormModel;
use AISAAutoPilot\Efb\EfbFormReview;
use AISAAutoPilot\Efb\EfbFormSkill;
use AISAAutoPilot\Efb\EfbKnowledge;
use AISAAutoPilot\Efb\EfbLogicRules;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Skills\FormPlanValidator;
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

		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'ai-site-assistant-autopilot' ) ), false, 400, 'connector_not_available' );
		}

		try {
			$result = ( new EfbFormSkill( $this->providers->make(), EfbLicense::has_pro_features() ) )->plan( $prompt );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'efb.plan.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );

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

		// Build what this licence can actually render, and say plainly what a
		// Free Plus or Pro licence would have added instead of failing outright.
		$split = EfbFormModel::split_pro_fields( $result->plan, EfbLicense::has_pro_features() );
		$plan  = $this->finalize_plan( $split['plan'] );

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
					'ai-site-assistant-autopilot'
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
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}

		$claim = $this->store->claim( $action_id, get_current_user_id(), 'easy_form_builder' );
		if ( 'missing' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'This proposal has expired. Please generate the form again.', 'ai-site-assistant-autopilot' ) ), false, 422, 'action_expired' );
		}
		if ( 'forbidden' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'You are not allowed to approve this action.', 'ai-site-assistant-autopilot' ) ), false, 403, 'permission_denied' );
		}
		if ( 'already_claimed' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'This proposal is already being processed or has already been used.', 'ai-site-assistant-autopilot' ) ), false, 422, 'action_already_processed' );
		}
		if ( 'wrong_connector' === $claim['status'] ) {
			return $this->respond( array( 'message' => __( 'This approval does not belong to Easy Form Builder.', 'ai-site-assistant-autopilot' ) ), false, 422, 'invalid_action' );
		}
		$record    = (array) ( $claim['record'] ?? array() );
		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( null === $connector || ! $connector->is_available() ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'ai-site-assistant-autopilot' ) ), false, 400, 'connector_not_available' );
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

			return $this->respond( array( 'message' => $result['message'] ?? __( 'Creation failed.', 'ai-site-assistant-autopilot' ) ), false, 422, (string) ( $result['code'] ?? 'execution_failed' ) );
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
					'reply' => __( 'Tell me what form you need, ask me to change the open form, or ask how to do something.', 'ai-site-assistant-autopilot' ),
				),
				true
			);
		}
		if ( mb_strlen( $message ) > 2000 ) {
			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => __( 'That message is a bit long — please shorten it.', 'ai-site-assistant-autopilot' ),
				),
				true
			);
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond(
				array(
					'type'  => 'message',
					'reply' => __( 'Add your AI provider API key in AI Site Assistant AutoPilot → Settings first.', 'ai-site-assistant-autopilot' ),
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
		$selected = $this->selected_field( $request->get_param( 'selected_field' ), $fields );
		$license  = EfbLicense::status();
		// The browser reports whether EFB loaded its Conditional Logic add-on on
		// this form; rules it proposes are still re-validated by EFB on Save.
		// An open form reports its own add-on state; with no form open, the editor's add-on list says it.
		$addons_seen = is_array( $request->get_param( 'addons' ) ) ? (array) $request->get_param( 'addons' ) : array();
		$logic       = $has_form ? (bool) $request->get_param( 'logic_addon' ) : (int) ( $addons_seen['AdnSMF'] ?? 0 ) >= 1;
		$logic_rules = $this->clean_logic_rules( $request->get_param( 'logic_rules' ) );
		$captcha     = $request->get_param( 'captcha' );
		$shield      = (bool) $request->get_param( 'spam_shield' );
		$explained   = EfbLogicRules::explain( $logic_rules, $fields, $steps );
		// Which payment gateways are switched on in the open editor (on or off, nothing else) and whether
		// their keys were saved at all; the keys themselves are never read into a variable here.
		$pay_status = EfbPayments::status( $request->get_param( 'addons' ) );
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
				'selected_id'   => null !== $selected ? $selected['id'] : '',
			)
		);

		try {
			$raw = ( new EfbChatSkill( $this->providers->make() ) )->respond(
				$message,
				$history,
				$has_form,
				$fields,
				$steps,
				$license,
				$selected,
				array(
					'logic'       => $logic,
					'logic_rules' => $explained,
					'payment'     => $pay_status,
				)
			);
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
				$e->status_code(),
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
						'reply'       => '' !== $reply ? $reply : __( 'Here are topics I can walk you through:', 'ai-site-assistant-autopilot' ),
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

		if ( 'review' === $action && $has_form ) {
			return $this->review_response(
				$reply,
				$fields,
				array(
					'steps'   => max( 1, count( $steps ) ),
					'captcha' => null === $captcha ? null : (bool) $captcha,
					'shield'  => $shield,
				),
				$steps,
				$logic_rules
			);
		}

		if ( 'explain_logic' === $action && $has_form ) {
			$conflicts = EfbLogicRules::conflicts( $logic_rules );

			return $this->respond(
				array(
					'type'   => 'list',
					'reply'  => array() !== $explained
						? ( '' !== $reply ? $reply : __( 'Here is what this form’s conditional logic does:', 'ai-site-assistant-autopilot' ) )
						: __( 'This form has no conditional logic rules yet.', 'ai-site-assistant-autopilot' ),
					'items'  => array_map(
						static fn ( string $line ): array => array(
							'severity' => 'info',
							'message'  => $line,
						),
						$explained
					),
					'errors' => $this->conflict_notes( $conflicts, $fields ),
				),
				true
			);
		}

		if ( 'customize' === $action ) {
			$verbose = EfbCustomizeSkill::validate_ops_verbose(
				$normalized_ops,
				$fields,
				array(
					'logic'          => $logic,
					'steps'          => $steps,
					'tier'           => (string) $license['tier'],
					'existing_rules' => count( $logic_rules ),
					'payment'        => $pay_status,
					'has_pro'        => ! empty( $license['has_pro_features'] ),
				)
			);
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
				// Never pretend success: explain what was rejected and why. The model
				// wrote its reply before anything was checked, so once every operation
				// it proposed is refused that reply would announce a change never made.
				if ( array() !== $dropped ) {
					$reply = __( 'I did not change the form.', 'ai-site-assistant-autopilot' );
				}

				$message_payload = array(
					'type'   => 'message',
					'reply'  => '' !== $reply ? $reply : __( 'I could not turn that into a change. Try e.g. "add a mobile field" or "make Email required".', 'ai-site-assistant-autopilot' ),
					'errors' => $notes,
				);
				$pay_links       = $this->payment_links( $dropped, array(), $pay_status );
				if ( array() !== $pay_links ) {
					$message_payload['links'] = $pay_links;
				}
				$offers = EfbAddons::offers( array_column( $dropped, 'reason' ) );
				if ( array() !== $offers ) {
					$message_payload['install'] = $offers;
				}
				$message_payload = $this->with_plan_links( $message_payload, $dropped );

				return $this->respond( $message_payload, true );
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
			// A payment goes in even when its keys are not saved yet; say what is still missing.
			$notes = array_merge( $notes, $this->payment_follow_up( $ops, $pay_status ) );

			// A new rule that drives a field the opposite way to an existing rule
			// on the same condition is applied, but never silently.
			$new_rules = array();
			foreach ( $ops as $op ) {
				if ( 'add_logic_rule' === ( $op['op'] ?? '' ) ) {
					$new_rules[] = (array) $op['rule'];
				}
			}
			if ( array() !== $new_rules ) {
				$notes = array_merge( $notes, $this->conflict_notes( EfbLogicRules::conflicts( array_merge( $logic_rules, $new_rules ) ), $fields ) );
			}

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
				'reply'      => '' !== $reply ? $reply : __( 'Done — applying your change.', 'ai-site-assistant-autopilot' ),
				'operations' => $ops,
			);
			if ( array() !== $notes ) {
				$payload['errors'] = $notes;
			}
			$pay_links = $this->payment_links( $dropped, $ops, $pay_status );
			if ( array() !== $pay_links ) {
				$payload['links'] = $pay_links;
			}
			$offers = EfbAddons::offers( array_column( $dropped, 'reason' ) );
			if ( array() !== $offers ) {
				$payload['install'] = $offers;
			}

			return $this->respond( $this->with_plan_links( $payload, $dropped ), true );
		}

		if ( 'create_form' === $action && is_array( $raw['form'] ?? null ) ) {
			$validator = $this->efb_validator();
			// A payment the model planned is checked on its own (gateway, add-on, licence, currency, a given price);
			// the fields themselves go through the usual validator.
			$payment_raw   = is_array( $raw['form']['payment'] ?? null ) ? $raw['form']['payment'] : null;
			$payment_check = null === $payment_raw ? null : EfbPayments::validate_payment( $payment_raw, $pay_status, ! empty( $license['has_pro_features'] ) );
			$result        = $validator->validate( (array) $raw['form'] );
			if ( ! $result->ok ) {
				// The model's reply describes the form it meant to build; none was
				// built, so that reply must not be shown as if one had been.
				return $this->respond(
					array(
						'type'   => 'message',
						'reply'  => __( 'I could not build a safe form from that. Could you add a bit more detail?', 'ai-site-assistant-autopilot' ),
						'errors' => $result->errors,
					),
					true
				);
			}
			$split = EfbFormModel::split_pro_fields( $result->plan, EfbLicense::has_pro_features() );
			$plan  = $this->finalize_plan( $split['plan'] );
			if ( array() === ( $plan['fields'] ?? array() ) ) {
				return $this->pro_required_response( $this->pro_blocked_from_fields( $split['pro_fields'] ) );
			}
			$payment_notes = array();
			if ( null !== $payment_check ) {
				if ( $payment_check['ok'] ) {
					$plan['payment'] = $payment_check['payment'];
					$payment_notes   = array_merge(
						$payment_notes,
						$this->payment_follow_up(
							array(
								array(
									'op'      => 'add_payment',
									'payment' => $payment_check['payment'],
								),
							),
							$pay_status,
							false
						)
					);
				} else {
					$payment_notes[] = $this->payment_note( (string) ( $payment_check['reason'] ?? '' ) );
				}
			}
			// EFB Free renders two pages at most: later page breaks are folded into page two, and the admin is told
			// how to get more (Free Plus or Pro) or that the form now fits.
			$step_links = array();
			if ( empty( $license['has_pro_features'] ) ) {
				$breaks = 0;
				$kept   = array();
				foreach ( (array) $plan['fields'] as $field ) {
					if ( is_array( $field ) && in_array( $field['type'] ?? '', array( 'steps', 'step' ), true ) ) {
						++$breaks;
						if ( $breaks > 1 ) {
							continue;
						}
					}
					$kept[] = $field;
				}
				if ( $breaks > 1 ) {
					$plan['fields']  = $kept;
					$payment_notes[] = sprintf(
						/* translators: %d: number of pages the request asked for. */
						__( 'You asked for %d pages, but Easy Form Builder Free shows two at most, so the later pages are on page two. Buy Free Plus or Pro for more pages, or keep it as it is.', 'ai-site-assistant-autopilot' ),
						$breaks + 1
					);
					$step_links = array(
						array(
							'label' => __( 'See EFB Pro', 'ai-site-assistant-autopilot' ),
							'url'   => EfbLicense::upgrade_url(),
						),
					);
				}
			}

			// Conditional logic planned with the form: resolved against the ids this plan will be saved with,
			// within the add-on's plan limits, or explained and offered instead.
			$logic_dropped = array();
			if ( is_array( $raw['form']['logic'] ?? null ) && array() !== $raw['form']['logic'] ) {
				$built         = $this->plan_logic( $plan, array_values( (array) $raw['form']['logic'] ), $logic, (string) $license['tier'] );
				$logic_dropped = $built['dropped'];
				if ( array() !== $built['rules'] ) {
					$plan['logic_rules'] = $built['rules'];
				}
				$payment_notes = array_merge( $payment_notes, $this->customize_notes( $logic_dropped ) );
			}
			if ( null !== $payment_check && ! $payment_check['ok'] ) {
				$logic_dropped[] = array(
					'op'     => 'add_payment',
					'reason' => (string) ( $payment_check['reason'] ?? '' ),
				);
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
				'reply'     => '' !== $reply ? $reply : __( 'Here is the form I designed — review it and approve to create it.', 'ai-site-assistant-autopilot' ),
				'action_id' => $action_id,
				'preview'   => $this->build_preview( $plan, $name, $shield ),
			);

			if ( array() !== $payment_notes ) {
				$payload['errors'] = $payment_notes;
			}
			$pay_links = $this->payment_links(
				null !== $payment_check && ! $payment_check['ok'] ? array(
					array(
						'op'     => 'add_payment',
						'reason' => (string) ( $payment_check['reason'] ?? '' ),
					),
				) : array(),
				isset( $plan['payment'] ) ? array(
					array(
						'op'      => 'add_payment',
						'payment' => $plan['payment'],
					),
				) : array(),
				$pay_status
			);
			if ( array() !== $pay_links ) {
				$payload['links'] = $pay_links;
			}
			$offers = EfbAddons::offers( array_column( $logic_dropped, 'reason' ) );
			if ( array() !== $offers ) {
				$payload['install'] = $offers;
			}
			$payload = $this->with_plan_links( $payload, $logic_dropped );
			if ( array() !== $step_links ) {
				$payload['links'] = array_values( array_unique( array_merge( is_array( $payload['links'] ?? null ) ? $payload['links'] : array(), $step_links ), SORT_REGULAR ) );
			}

			return $this->respond( $this->with_pro_upsell( $payload, $split['pro_fields'] ), true );
		}

		// ask / chat / fallback.
		return $this->respond(
			array(
				'type'  => 'message',
				'reply' => '' !== $reply ? $reply : __( 'How can I help with your form?', 'ai-site-assistant-autopilot' ),
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
				if ( 'trmCheckbox' === ( $op['field']['type'] ?? '' ) ) {
					$op['field']['privacy_link'] = $this->privacy_link();
				}
				$items       = $converter->build_field( $op['field'], $existing );
				$op['items'] = $items;
				foreach ( $items as $item ) {
					$existing[] = (string) ( $item['id_'] ?? '' );
				}
			} elseif ( 'add_payment' === ( $op['op'] ?? '' ) && is_array( $op['payment'] ?? null ) ) {
				$items       = $converter->build_payment( $op['payment'], $existing, empty( $op['has_gateway'] ) );
				$op['items'] = $items;
				// The same form-level settings EFB's own builder writes when its gateway field is added.
				$op['form_settings'] = EfbPayments::form_settings( (string) $op['payment']['gateway'], (string) $op['payment']['currency'] );
				$op['field']         = array( 'label' => (string) ( EfbPayments::GATEWAYS[ $op['payment']['gateway'] ]['label'] ?? '' ) );
				foreach ( $items as $item ) {
					$existing[] = (string) ( $item['id_'] ?? '' );
				}
			} elseif ( 'add_step' === ( $op['op'] ?? '' ) ) {
				$op['item'] = $converter->build_step( (string) ( $op['label'] ?? SiteLanguage::for_site( static fn (): string => __( 'Step', 'ai-site-assistant-autopilot' ) ) ) );
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
	 * Resolve the logic a new form was planned with against the ids it will be saved with.
	 *
	 * @param array<string,mixed> $plan
	 * @param array<int,mixed>    $raw_rules
	 * @return array{rules:array<int,array<string,mixed>>, dropped:array<int,array{op:string,reason:string}>, summaries:string[]}
	 */
	private function plan_logic( array $plan, array $raw_rules, bool $addon_active, string $tier ): array {
		$out = array(
			'rules'     => array(),
			'dropped'   => array(),
			'summaries' => array(),
		);
		if ( ! $addon_active ) {
			$out['dropped'][] = array(
				'op'     => 'add_logic_rule',
				'reason' => 'logic_addon_inactive',
			);
			return $out;
		}
		$catalog = EfbFormConverter::logic_catalog( ( new EfbFormConverter() )->convert( $plan ) );
		foreach ( array_slice( $raw_rules, 0, 12 ) as $raw ) {
			$conditions = is_array( $raw['when'] ?? null ) ? count( (array) $raw['when'] ) : 0;
			$refusal    = EfbLogicRules::plan_refusal( $tier, count( $out['rules'] ), $conditions );
			if ( '' !== $refusal ) {
				$out['dropped'][] = array(
					'op'     => 'add_logic_rule',
					'reason' => $refusal,
				);
				continue;
			}
			$built = EfbLogicRules::build( $raw, $catalog['fields'], $catalog['steps'] );
			if ( empty( $built['ok'] ) ) {
				$out['dropped'][] = array(
					'op'     => 'add_logic_rule',
					'reason' => 'invalid_logic_rule:' . (string) ( $built['error'] ?? '' ),
				);
				continue;
			}
			$out['rules'][]     = $built['rule'];
			$out['summaries'][] = (string) ( $built['summary'] ?? '' );
		}

		return $out;
	}

	/**
	 * A plan limit in the way gets the way past it as a button: EFB Pro.
	 *
	 * @param array<string,mixed>                       $payload
	 * @param array<int,array{op:string,reason:string}> $dropped
	 * @return array<string,mixed>
	 */
	private function with_plan_links( array $payload, array $dropped ): array {
		foreach ( $dropped as $d ) {
			$reason = (string) ( $d['reason'] ?? '' );
			if ( 'logic_needs_plan' === $reason || str_starts_with( $reason, 'logic_rule_limit:' ) || str_starts_with( $reason, 'logic_condition_limit:' ) ) {
				$links            = is_array( $payload['links'] ?? null ) ? $payload['links'] : array();
				$links[]          = array(
					'label' => __( 'See EFB Pro', 'ai-site-assistant-autopilot' ),
					'url'   => EfbLicense::upgrade_url(),
				);
				$payload['links'] = array_values( array_unique( $links, SORT_REGULAR ) );
				break;
			}
		}

		return $payload;
	}

	/**
	 * @param array<string,mixed> $plan
	 * @return string[]
	 */
	private function explain_planned_logic( array $plan ): array {
		$catalog = EfbFormConverter::logic_catalog( ( new EfbFormConverter() )->convert( $plan ) );

		return EfbLogicRules::explain( (array) $plan['logic_rules'], $catalog['fields'], $catalog['steps'] );
	}

	/** Plain-language explanation of why a payment request was not applied. */
	private function payment_note( string $reason ): string {
		$parts   = explode( ':', $reason );
		$kind    = $parts[0];
		$gateway = (string) ( $parts[1] ?? '' );
		$label   = (string) ( EfbPayments::GATEWAYS[ $gateway ]['label'] ?? '' );

		switch ( $kind ) {
			case 'payment_addon_inactive':
				/* translators: %s: payment gateway name, e.g. Stripe. */
				return sprintf( __( '%s needs its Easy Form Builder add-on. Turn it on under Easy Form Builder → Add-ons, reload this form and ask me again.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_requires_pro':
				/* translators: %s: payment gateway name, e.g. Stripe. */
				return sprintf( __( 'Online payment with %s is part of Easy Form Builder Pro.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_currency_unsupported':
				/* translators: 1: payment gateway name, 2: currency code. */
				return sprintf( __( '%1$s cannot take payments in %2$s. Tell me another currency.', 'ai-site-assistant-autopilot' ), $label, strtoupper( (string) ( $parts[2] ?? '' ) ) );
			case 'payment_needs_price':
				return __( 'I need the price before I can add payment: how much is it, or which options cost how much, and in which currency? I never guess a price.', 'ai-site-assistant-autopilot' );
			case 'payment_gateway_conflict':
				/* translators: %s: payment gateway name already used by the form. */
				return sprintf( __( 'This form already takes payment through %s, and a form can use only one gateway. Remove that payment field first, or keep it.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_duplicate':
				return __( 'I added one payment to the form; a second one in the same message was left out.', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Tell me which payment gateway to use: Stripe, PayPal or ZarinPal.', 'ai-site-assistant-autopilot' );
		}
	}

	/**
	 * What is still missing after a payment was added: a gateway's keys. Never says what a key is.
	 *
	 * @param array<int,array<string,mixed>>             $ops
	 * @param array<string,array<string,mixed>>          $status
	 * @return string[]
	 */
	private function payment_follow_up( array $ops, array $status, bool $live = true ): array {
		$notes = array();
		foreach ( $ops as $op ) {
			if ( 'add_payment' !== ( $op['op'] ?? '' ) ) {
				continue;
			}
			$gateway = (string) ( $op['payment']['gateway'] ?? '' );
			$label   = (string) ( EfbPayments::GATEWAYS[ $gateway ]['label'] ?? '' );
			if ( false === ( $status[ $gateway ]['keys_saved'] ?? null ) ) {
				/* translators: %s: payment gateway name, e.g. Stripe. */
				$notes[] = sprintf( __( '%s needs your account keys before this can take a real payment. Add them in its Easy Form Builder settings page; I never see or change keys.', 'ai-site-assistant-autopilot' ), $label );
			}
			$notes[] = $live
				? __( 'Prices and the currency are in the form now. Check them, then save the form in Easy Form Builder.', 'ai-site-assistant-autopilot' )
				: __( 'The prices and the currency are shown in the preview. Check them before you create the form.', 'ai-site-assistant-autopilot' );
		}

		return $notes;
	}

	/**
	 * Buttons that go with a payment answer: the add-on page, the gateway's settings, or the upgrade page.
	 *
	 * @param array<int,array{op:string,reason:string}> $dropped
	 * @param array<int,array<string,mixed>>            $ops
	 * @param array<string,array<string,mixed>>         $status
	 * @return array<int,array{label:string,url:string}>
	 */
	private function payment_links( array $dropped, array $ops, array $status ): array {
		$links = array();
		foreach ( $dropped as $d ) {
			$parts = explode( ':', (string) ( $d['reason'] ?? '' ) );
			if ( 'payment_addon_inactive' === $parts[0] ) {
				$links[] = array(
					'label' => __( 'Open EFB Add-ons', 'ai-site-assistant-autopilot' ),
					'url'   => admin_url( 'admin.php?page=Emsfb_addon' ),
				);
			} elseif ( 'payment_requires_pro' === $parts[0] ) {
				$links[] = array(
					'label' => __( 'See EFB Pro', 'ai-site-assistant-autopilot' ),
					'url'   => EfbLicense::upgrade_url(),
				);
			}
		}
		foreach ( $ops as $op ) {
			if ( 'add_payment' !== ( $op['op'] ?? '' ) ) {
				continue;
			}
			$gateway = (string) ( $op['payment']['gateway'] ?? '' );
			if ( false === ( $status[ $gateway ]['keys_saved'] ?? null ) && ! empty( $status[ $gateway ]['page'] ) ) {
				$links[] = array(
					/* translators: %s: payment gateway name, e.g. Stripe. */
					'label' => sprintf( __( 'Open %s settings', 'ai-site-assistant-autopilot' ), (string) ( $status[ $gateway ]['label'] ?? '' ) ),
					'url'   => admin_url( 'admin.php?page=' . (string) $status[ $gateway ]['page'] ),
				);
			}
		}

		return array_values( array_unique( $links, SORT_REGULAR ) );
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
				$notes[] = sprintf( __( 'Field type "%s" is not supported in Easy Form Builder.', 'ai-site-assistant-autopilot' ), substr( $reason, strpos( $reason, ':' ) + 1 ) );
			} elseif ( str_starts_with( $reason, 'choice_needs_options:' ) ) {
				$notes[] = __( 'A choice field needs at least one option — tell me the options.', 'ai-site-assistant-autopilot' );
			} elseif ( str_starts_with( $reason, 'recognized_but_not_creatable:' ) ) {
				$type       = substr( $reason, strpos( $reason, ':' ) + 1 );
				$definition = EfbFormModel::field_type( $type );
				/* translators: %s: field name. */
				$notes[] = sprintf( __( 'I can configure a "%s" field but not add one — drag it in from the Easy Form Builder palette, then ask me again.', 'ai-site-assistant-autopilot' ), (string) ( $definition['label'] ?? $type ) );
			} elseif ( str_starts_with( $reason, 'payment_' ) ) {
				$notes[] = $this->payment_note( $reason );
			} elseif ( 'logic_addon_inactive' === $reason ) {
				$notes[] = __( 'Conditional logic needs Easy Form Builder’s Conditional Logic add-on, which is not installed or not active. I can install it for you if you allow it; the form reloads afterwards, so save your changes first.', 'ai-site-assistant-autopilot' );
			} elseif ( 'logic_needs_plan' === $reason ) {
				$notes[] = __( 'Conditional logic rules need Easy Form Builder Free Plus or Pro. Upgrade, or change the form so it does not depend on logic: for example ask every question, or use a separate form for each case.', 'ai-site-assistant-autopilot' );
			} elseif ( str_starts_with( $reason, 'logic_rule_limit:' ) ) {
				/* translators: %d: rule limit. */
				$notes[] = sprintf( __( 'Easy Form Builder Free Plus allows %d conditional logic rules per form, so the rest were left out. Buy Pro for unlimited rules, or change the form: combine rules that show the same fields, move a branch to its own step, or split it into separate forms.', 'ai-site-assistant-autopilot' ), (int) substr( $reason, strlen( 'logic_rule_limit:' ) ) );
			} elseif ( str_starts_with( $reason, 'logic_condition_limit:' ) ) {
				/* translators: %d: condition limit. */
				$notes[] = sprintf( __( 'Easy Form Builder Free Plus allows %d conditions in one rule. Buy Pro, or split the rule into simpler rules.', 'ai-site-assistant-autopilot' ), (int) substr( $reason, strlen( 'logic_condition_limit:' ) ) );
			} elseif ( str_starts_with( $reason, 'invalid_logic_rule:' ) ) {
				$notes[] = $this->logic_rule_note( substr( $reason, strlen( 'invalid_logic_rule:' ) ) );
			} elseif ( str_starts_with( $reason, 'operation_limit_reached:' ) ) {
				$parts   = explode( ':', $reason );
				$skipped = (int) ( $parts[2] ?? 0 );
				$notes[] = sprintf(
					/* translators: 1: number of applied operations, 2: number of skipped operations. */
					__( 'That was too many changes at once: I applied the first %1$d and skipped %2$d. Ask again for the rest.', 'ai-site-assistant-autopilot' ),
					(int) ( $parts[1] ?? 0 ),
					$skipped
				);
			}
		}

		return array_values( array_unique( $notes ) );
	}

	/**
	 * Why a proposed rule was refused, in words the admin can act on.
	 */
	private function logic_rule_note( string $error ): string {
		$parts  = explode( ':', $error );
		$detail = (string) ( $parts[1] ?? '' );

		switch ( $parts[0] ) {
			case 'unknown_condition_field':
			case 'unknown_action_target':
				/* translators: %s: field name the AI referred to. */
				return sprintf( __( 'I could not add that rule: this form has no field called “%s”.', 'ai-site-assistant-autopilot' ), $detail );
			case 'unknown_option':
				/* translators: %s: option text. */
				return sprintf( __( 'I could not add that rule: “%s” is not one of that field’s options.', 'ai-site-assistant-autopilot' ), (string) ( $parts[2] ?? '' ) );
			case 'unknown_step':
				/* translators: %s: step name or number. */
				return sprintf( __( 'I could not add that rule: this form has no step “%s”.', 'ai-site-assistant-autopilot' ), $detail );
			case 'action_targets_its_own_condition':
				return __( 'I could not add that rule: a field cannot show, hide or require itself.', 'ai-site-assistant-autopilot' );
			case 'contradictory_actions':
				return __( 'I could not add that rule: it would show and hide (or require and un-require) the same field at once.', 'ai-site-assistant-autopilot' );
			default:
				return __( 'I could not turn that into a valid conditional logic rule. Name the field, the answer and what should happen.', 'ai-site-assistant-autopilot' );
		}
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
			$default = __( 'Easy Form Builder Free supports up to two steps. Buy Free Plus or Pro for more, or change the form: merge pages so it fits in two, or make it a single page.', 'ai-site-assistant-autopilot' );
		} elseif ( $is_setting ) {
			$default = __( 'The mobile layout settings are part of the Pro version of Easy Form Builder.', 'ai-site-assistant-autopilot' );
		} else {
			$default = __( 'This field is available in the Pro version of Easy Form Builder.', 'ai-site-assistant-autopilot' );
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
	 * @return array<int, array{id:string,label:string,type:string,required:bool,options:array<int,array{id:string,value:string}>}>
	 */
	private function clean_fields( array $raw ): array {
		$out = array();
		foreach ( array_slice( $raw, 0, 200 ) as $f ) {
			if ( is_array( $f ) ) {
				$options = array();
				foreach ( array_slice( is_array( $f['options'] ?? null ) ? $f['options'] : array(), 0, 60 ) as $option ) {
					if ( is_array( $option ) && '' !== (string) ( $option['id'] ?? '' ) ) {
						$options[] = array(
							'id'    => sanitize_text_field( (string) $option['id'] ),
							'value' => mb_substr( sanitize_text_field( (string) ( $option['value'] ?? '' ) ), 0, 120 ),
						);
					}
				}
				$out[] = array(
					'id'       => sanitize_text_field( (string) ( $f['id'] ?? '' ) ),
					'label'    => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
					'type'     => sanitize_text_field( (string) ( $f['type'] ?? '' ) ),
					'required' => ! empty( $f['required'] ) && 'false' !== $f['required'],
					'options'  => $options,
				);
			}
		}

		return $out;
	}

	/**
	 * The open form's logic_rules as the browser read them: bounded and reduced
	 * to scalars. They are only read (explained, checked for conflicts), never
	 * written back from here.
	 *
	 * @param mixed $raw
	 * @return array<int, array<string, mixed>>
	 */
	private function clean_logic_rules( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = static function ( $value, int $depth ) use ( &$clean ) {
			if ( is_array( $value ) ) {
				if ( $depth > 5 ) {
					return array();
				}
				$out = array();
				foreach ( array_slice( $value, 0, 40, true ) as $key => $item ) {
					$out[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = $clean( $item, $depth + 1 );
				}

				return $out;
			}

			return is_bool( $value ) ? $value : mb_substr( sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) ), 0, 160 );
		};

		return array_values( array_filter( (array) $clean( array_slice( $raw, 0, 30 ), 0 ), 'is_array' ) );
	}

	/**
	 * Add what the server, not the AI, decides: the privacy page linked from a
	 * consent checkbox and the address that receives admin notifications.
	 *
	 * @param array<string, mixed> $plan
	 * @return array<string, mixed>
	 */
	private function finalize_plan( array $plan ): array {
		foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
			if ( is_array( $field ) && 'trmCheckbox' === ( $field['type'] ?? '' ) ) {
				$plan['privacy_link'] = $this->privacy_link();
				break;
			}
		}

		$admin                = sanitize_email( (string) get_option( 'admin_email', '' ) );
		$plan['notify_email'] = ! empty( $plan['notify_admin'] ) && '' !== $admin && is_email( $admin ) ? $admin : '';

		return $plan;
	}

	/**
	 * The site's published privacy policy page, if it has one.
	 *
	 * @return array{url?:string,label?:string}
	 */
	private function privacy_link(): array {
		$url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		if ( '' === $url ) {
			return array();
		}
		$page = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		return array(
			'url'   => esc_url_raw( $url ),
			'label' => $page > 0 ? wp_strip_all_tags( get_the_title( $page ) ) : '',
		);
	}

	/**
	 * "Review this form": deterministic checks plus ready-to-apply fixes. A fix
	 * goes through the same validation and Pro gate as any chat edit.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<string, mixed>             $context
	 * @param array<int, array<string, mixed>> $steps
	 * @param array<int, array<string, mixed>> $logic_rules
	 */
	private function review_response( string $reply, array $fields, array $context, array $steps, array $logic_rules ): WP_REST_Response {
		$items = array();
		foreach ( EfbFormReview::review( $fields, $context ) as $finding ) {
			$item = array(
				'code'     => $finding['code'],
				'severity' => $finding['severity'],
				'message'  => $finding['message'],
			);
			if ( isset( $finding['fix'] ) ) {
				$fix = EfbCustomizeSkill::validate_ops_verbose( array( $finding['fix'] ), $fields )['ops'];
				if ( array() !== $fix && array() === $this->blocked_customize_operations( $fix, $steps ) ) {
					$item['operations'] = $this->expand_add_fields( $fix, $fields );
				}
			}
			$items[] = $item;
		}

		foreach ( $this->conflict_notes( EfbLogicRules::conflicts( $logic_rules ), $fields ) as $note ) {
			$items[] = array(
				'code'     => 'logic_conflict',
				'severity' => 'warning',
				'message'  => $note,
			);
		}

		return $this->respond(
			array(
				'type'  => 'list',
				'reply' => array() !== $items
					? ( '' !== $reply ? $reply : __( 'I checked the form. Here is what I would improve:', 'ai-site-assistant-autopilot' ) )
					: __( 'I checked the form and found nothing to fix.', 'ai-site-assistant-autopilot' ),
				'items' => $items,
			),
			true
		);
	}

	/**
	 * @param string[]                         $targets
	 * @param array<int, array<string, mixed>> $fields
	 * @return string[]
	 */
	private function conflict_notes( array $targets, array $fields ): array {
		$notes = array();
		foreach ( $targets as $target ) {
			$label = $target;
			foreach ( $fields as $field ) {
				if ( (string) ( $field['id'] ?? '' ) === $target ) {
					$label = (string) $field['label'];
				}
			}
			/* translators: %s: field label. */
			$notes[] = sprintf( __( 'Two rules on the same condition both show and hide (or require and un-require) “%s”; only the last one wins. Check the Conditional Logic panel.', 'ai-site-assistant-autopilot' ), $label );
		}

		return $notes;
	}

	/**
	 * Resolve client selection against the current, sanitized field catalogue.
	 * The client only supplies an ID; label and type always come from live fields.
	 *
	 * @param mixed                                                    $raw
	 * @param array<int, array{id:string,label:string,type:string}>     $fields
	 * @return array{id:string,label:string,type:string}|null
	 */
	private function selected_field( $raw, array $fields ): ?array {
		$id = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
		if ( '' === $id ) {
			return null;
		}

		foreach ( $fields as $field ) {
			if ( hash_equals( $field['id'], $id ) ) {
				return $field;
			}
		}

		return null;
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
			return $this->respond( array( 'message' => __( 'Ask a question, e.g. "how do I make a field required?"', 'ai-site-assistant-autopilot' ) ), false, 400, 'empty_question' );
		}

		$result = EfbKnowledge::answer( $question );

		if ( empty( $result['ok'] ) ) {
			return $this->respond(
				array(
					'message'     => __( 'I could not match that exactly. Try one of these topics:', 'ai-site-assistant-autopilot' ),
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
			return $this->respond( array( 'message' => __( 'Type what you want to change.', 'ai-site-assistant-autopilot' ) ), false, 400, 'empty_command' );
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond( array( 'message' => __( 'Add your AI provider API key in AI Site Assistant AutoPilot → Settings first.', 'ai-site-assistant-autopilot' ) ), false, 400, 'provider_not_configured' );
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
			return $this->respond( array( 'message' => $e->user_message() ), false, $e->status_code(), $e->error_code() );
		}

		if ( array() === $ops ) {
			return $this->respond( array( 'message' => __( 'I could not turn that into a change. Try being more specific, e.g. "make Email required" or "rename the form to Contact us".', 'ai-site-assistant-autopilot' ) ), true, 200, 'no_ops' );
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
	 * Explicit persistence endpoint for integration consumers. The AISAAutoPilot EFB
	 * chat deliberately does not call this after applying an AI proposal: it
	 * leaves the changed valj_efb state on the canvas for the administrator to
	 * review and save through EFB's normal Save control.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$form_id   = (int) $request->get_param( 'form_id' );
		$structure = $request->get_param( 'structure' );

		if ( $form_id <= 0 || ! is_array( $structure ) || array() === $structure ) {
			return $this->respond( array( 'message' => __( 'Invalid save request.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}

		// Reject anything that looks like injected script markup.
		$flat = wp_json_encode( $structure );
		if ( is_string( $flat ) && false !== stripos( $flat, '<script' ) ) {
			return $this->respond( array( 'message' => __( 'Unsafe content rejected.', 'ai-site-assistant-autopilot' ) ), false, 422, 'unsafe_content' );
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
			return $this->respond( array( 'message' => __( 'Could not save the form.', 'ai-site-assistant-autopilot' ) ), false, 422, 'execution_failed' );
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
	/**
	 * The payment as rows of the preview, with every price in plain sight so approving the form is approving the prices.
	 *
	 * @param array<string,mixed> $payment
	 * @return array<int,array<string,mixed>>
	 */
	private function payment_preview_rows( array $payment ): array {
		if ( array() === $payment ) {
			return array();
		}
		$currency = strtoupper( (string) ( $payment['currency'] ?? '' ) );
		$rows     = array();
		$items    = is_array( $payment['items'] ?? null ) ? $payment['items'] : array();
		if ( array() !== $items ) {
			$rows[] = array(
				'label'    => '' !== (string) ( $payment['label'] ?? '' ) ? (string) $payment['label'] : __( 'Choose an option', 'ai-site-assistant-autopilot' ),
				'type'     => (string) ( EfbPayments::CHOICE_FIELDS[ (string) ( $payment['choice_type'] ?? 'radio' ) ] ?? 'payRadio' ),
				'required' => true,
				'width'    => 'full',
				'help'     => '',
				'options'  => array_map( static fn ( array $item ): string => $item['label'] . ' — ' . rtrim( rtrim( number_format( (float) $item['price'], 2, '.', '' ), '0' ), '.' ) . ' ' . $currency, $items ),
			);
		} else {
			$rows[] = array(
				'label'    => (string) ( $payment['amount_label'] ?? '' ),
				'type'     => 'prcfld',
				'required' => true,
				'width'    => 'full',
				'help'     => '',
				'options'  => array(),
			);
		}
		$gateway = (string) ( $payment['gateway'] ?? '' );
		$rows[]  = array(
			'label'    => (string) ( EfbPayments::GATEWAYS[ $gateway ]['label'] ?? '' ) . ' · ' . $currency,
			'type'     => (string) ( EfbPayments::gateway_field( $gateway ) ?? 'stripe' ),
			'required' => true,
			'width'    => 'full',
			'help'     => '',
			'options'  => array(),
		);

		return $rows;
	}

	private function build_preview( array $plan, string $connector_name, bool $shield = false ): array {
		$fields    = (array) $plan['fields'];
		$has_email = (bool) array_filter( $fields, static fn ( $f ): bool => is_array( $f ) && 'email' === ( $f['type'] ?? '' ) );
		$pay_rows  = $this->payment_preview_rows( is_array( $plan['payment'] ?? null ) ? $plan['payment'] : array() );
		$steps     = 1 + count( array_filter( $fields, static fn ( $f ): bool => is_array( $f ) && in_array( $f['type'] ?? '', array( 'steps', 'step' ), true ) ) );

		return array(
			'title'                => $plan['title'],
			'fields'               => array_merge(
				array_map(
					static function ( array $f ): array {
						return array(
							'label'    => $f['label'],
							'type'     => $f['type'],
							'required' => (bool) $f['required'],
							'width'    => $f['width'] ?? 'full',
							'help'     => (string) ( $f['help'] ?? '' ),
							'options'  => $f['options'],
						);
					},
					$fields
				),
				$pay_rows
			),
			// The rules planned with the form, in the same plain sentences "explain the logic" uses.
			'logic'                => is_array( $plan['logic_rules'] ?? null ) && array() !== $plan['logic_rules'] ? $this->explain_planned_logic( $plan ) : array(),
			'payment'              => is_array( $plan['payment'] ?? null ) ? array(
				'gateway'  => (string) ( EfbPayments::GATEWAYS[ $plan['payment']['gateway'] ]['label'] ?? '' ),
				'currency' => strtoupper( (string) $plan['payment']['currency'] ),
			) : null,
			'submit_button'        => (string) ( $plan['submit_button'] ?? '' ),
			'confirmation_message' => $plan['confirmation_message'] ?? '',
			// Shown before approval so the admin sees exactly who will be emailed.
			'notifications'        => array(
				'admin'      => (string) ( $plan['notify_email'] ?? '' ),
				'respondent' => ! empty( $plan['confirm_to_respondent'] ) && $has_email,
			),
			'steps'                => $steps,
			'suggestions'          => array_map(
				static fn ( array $finding ): array => array(
					'severity' => $finding['severity'],
					'message'  => $finding['message'],
				),
				EfbFormReview::review(
					$fields,
					array(
						'steps'  => $steps,
						'shield' => $shield,
					)
				)
			),
			'risk_level'           => $plan['risk_level'] ?? 'medium',
			'connector'            => $connector_name,
			'field_count'          => count( array_filter( $fields, static fn ( $f ): bool => is_array( $f ) && ! in_array( $f['type'] ?? '', array( 'steps', 'step' ), true ) ) ),
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
