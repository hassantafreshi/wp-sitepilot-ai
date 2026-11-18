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
use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbFeatureGate;
use AISAAutoPilot\Efb\EfbSaveNotes;
use AISAAutoPilot\Efb\EfbLicense;
use AISAAutoPilot\Efb\EfbPayments;
use AISAAutoPilot\Efb\EfbReplyText;
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
			$skill  = new EfbFormSkill( $this->providers->make(), EfbLicense::has_pro_features() );
			$result = $skill->plan( $prompt );
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
		$plan  = $this->finalize_plan( $split['plan'], $skill->raw_form() );

		if ( array() === ( $plan['fields'] ?? array() ) ) {
			return $this->pro_required_response( $this->pro_blocked_from_fields( $split['pro_fields'] ) );
		}
		// This path has no chat to take a link address or HTML from, so those are left out; a state or city
		// list still gets the field it follows.
		$plan['fields'] = EfbCustomizeSkill::prepare_new_form_fields( array_values( (array) $plan['fields'] ), array(), array(), EfbFormConverter::geo_labels() )['fields'];

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
		$blocked = $skill->blocked();
		if ( array() !== $blocked ) {
			$payload = $this->with_feature_gate( $payload, $blocked );
			$offers  = EfbFeatureGate::offers( $blocked );
			if ( array() !== $offers ) {
				$payload['install'] = $offers;
			}
		}

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
	 * What the feature gate held back, in the answer: the list the preview names (feature, add-on, plan, the field
	 * used instead) and the buttons that lead on (EFB's Add-ons page, EFB's own upgrade dialog for a plan).
	 *
	 * @param array<string,mixed>            $payload
	 * @param array<int,array<string,mixed>> $blocked
	 * @return array<string,mixed>
	 */
	private function with_feature_gate( array $payload, array $blocked ): array {
		if ( array() === $blocked ) {
			return $payload;
		}
		$payload['addon_required'] = EfbFeatureGate::summary( $blocked );
		$links                     = is_array( $payload['links'] ?? null ) ? $payload['links'] : array();
		foreach ( $blocked as $item ) {
			if ( in_array( $item['reason'] ?? '', array( 'addon', 'update' ), true ) ) {
				$links[] = array(
					'label' => __( 'Open EFB Add-ons', 'ai-site-assistant-autopilot' ),
					'url'   => EfbCapabilities::addons_page(),
				);
				break;
			}
		}
		foreach ( $blocked as $item ) {
			if ( 'plan' === ( $item['reason'] ?? '' ) ) {
				$links[] = array(
					/* translators: %s: Easy Form Builder plan name (Free Plus or Pro). */
					'label' => sprintf( __( 'What %s adds', 'ai-site-assistant-autopilot' ), EfbCapabilities::plan_label( (string) ( $item['package'] ?? 'pro' ) ) ),
					'url'   => EfbCapabilities::upgrade_dialog_url( (string) ( $item['feature'] ?? '' ) ),
				);
				break;
			}
		}
		if ( array() !== $links ) {
			$payload['links'] = array_values( array_unique( $links, SORT_REGULAR ) );
		}

		return $payload;
	}

	/**
	 * Install offers from several sources, each add-on once.
	 *
	 * @param array<int,array<string,mixed>> ...$lists
	 * @return array<int,array<string,mixed>>
	 */
	private function merge_offers( array ...$lists ): array {
		$out  = array();
		$seen = array();
		foreach ( $lists as $list ) {
			foreach ( $list as $offer ) {
				$code = (string) ( $offer['code'] ?? '' );
				if ( '' === $code || isset( $seen[ $code ] ) ) {
					continue;
				}
				$seen[ $code ] = true;
				$out[]         = $offer;
			}
		}

		return $out;
	}

	/**
	 * Fields the open form should get but this site cannot render: each becomes the closest field it can (with a note),
	 * or is left out. A field that only needs a higher plan stays, so EFB's own upgrade dialog answers it.
	 *
	 * @param array<int,mixed> $ops
	 * @return array{ops:array<int,mixed>, blocked:array<int,array<string,mixed>>}
	 */
	private function gate_customize_ops( array $ops ): array {
		if ( ! EfbCapabilities::available() ) {
			return array(
				'ops'     => $ops,
				'blocked' => array(),
			);
		}
		$creatable = EfbFormModel::addable_types();
		$out       = array();
		$blocked   = array();
		foreach ( $ops as $op ) {
			if ( ! is_array( $op ) || 'add_field' !== ( $op['op'] ?? '' ) || ! is_array( $op['field'] ?? null ) ) {
				$out[] = $op;
				continue;
			}
			$type  = EfbFeatureGate::resolve_type( (string) ( $op['field']['type'] ?? '' ) );
			$check = '' === $type ? null : EfbFeatureGate::check( $type, $op['field'], $creatable );
			if ( null === $check || 'plan' === $check['reason'] ) {
				$out[] = $op;
				continue;
			}
			$blocked[] = $check;
			if ( null !== $check['alternative'] ) {
				$op['field']['type'] = $check['alternative'];
				unset( $op['field']['settings'] );
				$out[] = $op;
			}
		}

		return array(
			'ops'     => $out,
			'blocked' => $blocked,
		);
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
				'dropped' => count( (array) ( $result['dropped'] ?? array() ) ),
			)
		);
		// Form package 1.1: the token that undoes this creation stays on the server, bound to the approving user.
		$form_id = (int) ( $result['form_id'] ?? 0 );
		$token   = (string) ( $result['import_token'] ?? '' );
		if ( $form_id > 0 && '' !== $token ) {
			set_transient( self::undo_key( get_current_user_id(), $form_id ), $token, DAY_IN_SECONDS );
		}

		return $this->respond(
			array(
				'result' => array(
					'connector' => $result['connector'] ?? '',
					'form_id'   => $form_id,
					'edit_url'  => $result['edit_url'] ?? '',
					'shortcode' => $result['shortcode'] ?? '',
					'can_undo'  => '' !== $token,
					// What EFB itself reported while storing it (keys of an inactive add-on, payment readiness, …).
					'notes'     => EfbSaveNotes::messages( (array) ( $result['dropped'] ?? array() ), (array) ( $result['notices'] ?? array() ), is_array( $result['payment'] ?? null ) ? $result['payment'] : null ),
					'payment'   => is_array( $result['payment'] ?? null ) ? array( 'ready' => ! empty( $result['payment']['ready'] ) ) : null,
				),
			),
			true
		);
	}

	/** The transient that holds the undo token of a form the copilot created for this user. */
	private static function undo_key( int $user_id, int $form_id ): string {
		return 'aisaautopilot_efb_undo_' . $user_id . '_' . $form_id;
	}

	/**
	 * Undo a form the copilot created a moment ago (Easy Form Builder's `Form_Package::delete()`, package 1.1): only the
	 * same user, only that form, only while its token is kept. Everything else stays in Easy Form Builder's forms list.
	 */
	public function undo( WP_REST_Request $request ): WP_REST_Response {
		$form_id = absint( $request->get_param( 'form_id' ) );
		$key     = self::undo_key( get_current_user_id(), $form_id );
		$token   = $form_id > 0 ? get_transient( $key ) : false;
		if ( ! is_string( $token ) || '' === $token ) {
			return $this->respond( array( 'message' => __( 'This form can no longer be undone here. Delete it in Easy Form Builder’s forms list if you do not want it.', 'ai-site-assistant-autopilot' ) ), false, 404, 'undo_expired' );
		}
		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( ! $connector instanceof \AISAAutoPilot\Connectors\EasyFormBuilderConnector ) {
			return $this->respond( array( 'message' => __( 'Easy Form Builder is not active.', 'ai-site-assistant-autopilot' ) ), false, 400, 'connector_not_available' );
		}
		$done = $connector->undo_create( $form_id, $token );
		if ( is_wp_error( $done ) ) {
			return $this->respond( array( 'message' => wp_strip_all_tags( $done->get_error_message() ) ), false, 422, sanitize_key( (string) $done->get_error_code() ) );
		}
		delete_transient( $key );
		$this->logger->info( 'efb.create.undone', 'EFB form created by the copilot was removed again.', array( 'form_id' => $form_id ) );

		return $this->respond(
			array(
				'message'  => __( 'Done: the new form was deleted.', 'ai-site-assistant-autopilot' ),
				'list_url' => admin_url( 'admin.php?page=Emsfb' ),
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
		// EFB's registry knows which add-ons are installed on this site; the browser's report is the fallback.
		if ( EfbCapabilities::available() ) {
			$logic                = EfbCapabilities::addon( 'AdnSMF' )['installed'];
			$addons_seen['AdnSMF'] = $logic ? 1 : 0;
			$addons_seen['AdnSS']  = EfbCapabilities::addon( 'AdnSS' )['installed'] ? 1 : 0;
		}
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
					'home_url'    => home_url( '/' ),
					'sms_addon'   => (int) ( $addons_seen['AdnSS'] ?? 0 ) >= 1,
					'form_type'   => sanitize_key( (string) $request->get_param( 'form_type' ) ),
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

		$reply  = EfbReplyText::clean( trim( (string) ( $raw['reply'] ?? '' ) ) );
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
			$gated   = $this->gate_customize_ops( $normalized_ops );
			$verbose = EfbCustomizeSkill::validate_ops_verbose(
				$gated['ops'],
				$fields,
				array(
					'logic'          => $logic,
					'steps'          => $steps,
					'tier'           => (string) $license['tier'],
					'existing_rules' => count( $logic_rules ),
					'payment'        => $pay_status,
					'has_pro'        => ! empty( $license['has_pro_features'] ),
					// Add-ons the open editor reports as running, for settings that only exist with one.
					'addons'         => array_filter( array_map( 'intval', $addons_seen ) ),
					// What the admin wrote in this conversation: an address or a phone number is only ever
					// taken from here, never from the model.
					'said'           => $this->admin_said( $message, $history ),
					'home_url'       => home_url( '/' ),
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

			$notes        = array_merge( EfbFeatureGate::messages( $gated['blocked'] ), $this->customize_notes( $dropped ) );
			$refused_some = array() !== $notes;

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
				$offers = $this->merge_offers( EfbAddons::offers( array_column( $dropped, 'reason' ) ), EfbFeatureGate::offers( $gated['blocked'] ) );
				if ( array() !== $offers ) {
					$message_payload['install'] = $offers;
				}
				$message_payload = $this->with_plan_links( $message_payload, $dropped );
				$message_payload = $this->with_feature_gate( $message_payload, $gated['blocked'] );

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
			// Rules about a field this message adds: resolved now that the field has its id.
			if ( array() !== $verbose['deferred'] ) {
				$late    = $this->resolve_deferred_rules( $verbose['deferred'], $ops, $fields, $steps, (string) $license['tier'], count( $logic_rules ) );
				$ops     = array_merge( $ops, $late['ops'] );
				$dropped = array_merge( $dropped, $late['dropped'] );
				$notes   = array_merge( $notes, $this->customize_notes( $late['dropped'] ) );
			}
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

			// A partly-applied batch must say so rather than answering a plain "Done". The model wrote its
			// reply before anything was checked, so when part of what it proposed was refused that reply
			// would announce changes that were never made.
			if ( $refused_some ) {
				$reply = __( 'I applied part of this. What I left out, and why:', 'ai-site-assistant-autopilot' );
			}
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
			$offers = $this->merge_offers( EfbAddons::offers( array_column( $dropped, 'reason' ) ), EfbFeatureGate::offers( $gated['blocked'] ) );
			if ( array() !== $offers ) {
				$payload['install'] = $offers;
			}

			return $this->respond( $this->with_feature_gate( $this->with_plan_links( $payload, $dropped ), $gated['blocked'] ), true );
		}

		if ( 'create_form' === $action && is_array( $raw['form'] ?? null ) ) {
			$validator = $this->efb_validator();
			// A payment the model planned is checked on its own (gateway, add-on, licence, currency, a given price);
			// the fields themselves go through the usual validator.
			$payment_raw   = is_array( $raw['form']['payment'] ?? null ) ? $raw['form']['payment'] : null;
			$payment_check = null === $payment_raw ? null : EfbPayments::validate_payment( $payment_raw, $pay_status, ! empty( $license['has_pro_features'] ) );
			// SPLIT: a field the site cannot render (its add-on is missing, or the plan is below it) never reaches the
			// form. EFB's registry decides; the admin is told what is needed and gets the closest field that works.
			$gate = EfbFeatureGate::new_form_fields( is_array( $raw['form']['fields'] ?? null ) ? array_values( $raw['form']['fields'] ) : array() );
			if ( array() !== $gate['blocked'] ) {
				$raw['form']['fields'] = $gate['fields'];
			}
			$result = $validator->validate( (array) $raw['form'] );
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
			$plan  = $this->finalize_plan( $split['plan'], is_array( $raw['form'] ) ? $raw['form'] : array() );
			if ( array() === ( $plan['fields'] ?? array() ) ) {
				return $this->pro_required_response( $this->pro_blocked_from_fields( $split['pro_fields'] ) );
			}
			// Links, HTML blocks and the country / state / city lists follow the same rules as in an open form.
			$prepared       = EfbCustomizeSkill::prepare_new_form_fields(
				array_values( (array) $plan['fields'] ),
				array_values( (array) ( $raw['form']['fields'] ?? array() ) ),
				array(
					'said'     => $this->admin_said( $message, $history ),
					'home_url' => home_url( '/' ),
				),
				EfbFormConverter::geo_labels()
			);
			$plan['fields'] = $prepared['fields'];
			$payment_notes  = $this->customize_notes( $prepared['dropped'] );
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
			$step_limit = EfbCapabilities::available() ? EfbCapabilities::limit( 'steps' ) : ( empty( $license['has_pro_features'] ) ? 2 : null );
			if ( is_int( $step_limit ) && $step_limit >= 1 ) {
				$breaks = 0;
				$kept   = array();
				foreach ( (array) $plan['fields'] as $field ) {
					if ( is_array( $field ) && in_array( $field['type'] ?? '', array( 'steps', 'step' ), true ) ) {
						++$breaks;
						if ( $breaks > $step_limit - 1 ) {
							continue;
						}
					}
					$kept[] = $field;
				}
				if ( $breaks > $step_limit - 1 ) {
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
			// The preview lists each field the gate changed (addon_required), with the add-on, plan and field used.
			$payload = $this->with_feature_gate( $payload, $gate['blocked'] );

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
			$offers = $this->merge_offers( EfbAddons::offers( array_column( $logic_dropped, 'reason' ) ), EfbFeatureGate::offers( $gate['blocked'] ) );
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
	 * @param array<int, array<string, mixed>>                      $fields
	 * @return array<int, array<string, mixed>>
	 */
	private function expand_add_fields( array $ops, array $fields ): array {
		$existing = array();
		foreach ( $fields as $f ) {
			$existing[] = (string) $f['id'];
			// Option ids share the form's id space (a second state list must not reuse the first one's ids).
			foreach ( (array) ( $f['options'] ?? array() ) as $option ) {
				$existing[] = (string) ( $option['id'] ?? '' );
			}
		}
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
				$op['field']         = array( 'label' => EfbPayments::label( (string) $op['payment']['gateway'] ) );
				foreach ( $items as $item ) {
					$existing[] = (string) ( $item['id_'] ?? '' );
				}
			} elseif ( 'set_form_property' === ( $op['op'] ?? '' ) && 'form_type' === ( $op['property'] ?? '' ) && 'login' === ( $op['value'] ?? '' ) ) {
				// EFB's sign-in form reads two fields with fixed ids; the ones still missing come along.
				$op['items'] = $converter->build_login_fields( $existing );
				foreach ( $op['items'] as $item ) {
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
		$license  = EfbLicense::status();
		$registry = EfbCapabilities::available();
		if ( ! $registry && ! empty( $license['is_pro'] ) ) {
			return array();
		}
		$plus = ! empty( $license['has_pro_features'] );
		// EFB's own step limit for this plan (Free: 2); null means no limit.
		$step_limit = $registry ? EfbCapabilities::limit( 'steps' ) : ( $plus ? null : 2 );

		$blocked    = array();
		$step_count = count( $steps );
		foreach ( $ops as $op ) {
			$name = (string) ( $op['op'] ?? '' );
			if ( ( $registry || ! $plus ) && 'add_field' === $name && is_array( $op['field'] ?? null ) ) {
				$type = (string) ( $op['field']['type'] ?? '' );
				if ( EfbFormModel::requires_pro_to_create( $type ) ) {
					$state     = EfbCapabilities::state( EfbCapabilities::field_key( EfbFormModel::resolve_field_type( $type ) ?? $type ) );
					$blocked[] = array(
						'kind'    => 'field',
						'type'    => $type,
						'label'   => (string) ( $op['field']['label'] ?? $type ),
						'feature' => EfbCapabilities::field_key( EfbFormModel::resolve_field_type( $type ) ?? $type ),
						'plan'    => $state['known'] ? $state['package'] : 'pro',
					);
				}
			}
			if ( null !== $step_limit && 'add_step' === $name ) {
				++$step_count;
				if ( $step_count > (int) $step_limit ) {
					$blocked[] = array(
						'kind'  => 'step_limit',
						'label' => (string) ( $op['label'] ?? 'Step' ),
					);
				}
			}
			// A setting EFB itself keeps behind a padlock on this plan: writing it would change something the
			// admin can neither see nor undo in the builder. 'plus' opens on Free Plus, 'pro' only on Pro.
			if ( 'set_field_property' === $name || 'set_form_property' === $name ) {
				$key        = (string) ( $op['property'] ?? '' );
				$definition = 'set_field_property' === $name ? EfbFormModel::field_property( $key ) : EfbFormModel::form_property( $key );
				$canonical  = 'set_field_property' === $name ? EfbFormModel::resolve_field_property( $key ) : EfbFormModel::resolve_form_property( $key );
				$needs      = EfbFormModel::plan_of( $definition, (string) ( $canonical ?? $key ) );
				if ( 'pro' === $needs && ! empty( $license['is_pro'] ) ) {
					continue;
				}
				// The registry already answered for this site's plan; the bundled catalog needs the plan applied.
				if ( ( $registry && '' !== $needs ) || 'pro' === $needs || ( 'plus' === $needs && ! $plus ) ) {
					$blocked[] = array(
						'kind'  => 'setting',
						'type'  => $key,
						'label' => (string) ( $op['target'] ?? '' ),
						'plan'  => $needs,
					);
				}
			}
		}

		return $blocked;
	}

	/**
	 * @param array<string,mixed> $plan
	 * @return array<int,array{kind:string,type?:string,label?:string}>
	 */
	private function blocked_plan_fields( array $plan ): array {
		// With EFB's registry the approval re-checks every field against the site as it is now: a plan changed or an
		// add-on switched off since the preview means the form is not created.
		if ( EfbCapabilities::available() ) {
			$blocked = array();
			foreach ( (array) ( $plan['fields'] ?? array() ) as $field ) {
				if ( ! is_array( $field ) || in_array( $field['type'] ?? '', array( 'steps', 'step' ), true ) ) {
					continue;
				}
				$check = EfbFeatureGate::check( (string) ( $field['type'] ?? '' ), $field );
				if ( null !== $check && 'builder_only' !== $check['reason'] ) {
					$blocked[] = array(
						'kind'    => 'field',
						'type'    => (string) ( $field['type'] ?? '' ),
						'label'   => (string) ( $field['label'] ?? ( $field['type'] ?? '' ) ),
						'feature' => $check['feature'],
						'reason'  => $check['reason'],
						'addon'   => $check['addon'],
						'plan'    => $check['package'],
					);
				}
			}

			return $blocked;
		}
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
	 * Build the rules that named a field added in the same message, against the open form plus the new fields.
	 *
	 * @param array<int,mixed>                 $rules
	 * @param array<int,array<string,mixed>>   $ops    Expanded operations (add_field ops carry their items).
	 * @param array<int,array<string,mixed>>   $fields
	 * @param array<int,array<string,mixed>>   $steps
	 * @return array{ops:array<int,array<string,mixed>>, dropped:array<int,array{op:string,reason:string}>}
	 */
	private function resolve_deferred_rules( array $rules, array $ops, array $fields, array $steps, string $tier, int $existing_rules ): array {
		$items = array();
		$added = 0;
		foreach ( $ops as $op ) {
			if ( 'add_logic_rule' === ( $op['op'] ?? '' ) ) {
				++$added;
			}
			if ( 'add_field' === ( $op['op'] ?? '' ) && is_array( $op['items'] ?? null ) ) {
				$items = array_merge( $items, $op['items'] );
			}
		}
		$catalog = array_merge( $fields, EfbFormConverter::logic_catalog( $items )['fields'] );
		$out     = array(
			'ops'     => array(),
			'dropped' => array(),
		);
		foreach ( $rules as $raw ) {
			$built = EfbLogicRules::build( $raw, $catalog, $steps );
			if ( empty( $built['ok'] ) || ! isset( $built['rule'] ) ) {
				$out['dropped'][] = array(
					'op'     => 'add_logic_rule',
					'reason' => 'invalid_logic_rule:' . (string) ( $built['error'] ?? 'unknown' ),
				);
				continue;
			}
			$refusal = EfbLogicRules::plan_refusal( $tier, $existing_rules + $added, count( $built['rule']['conditions']['items'] ) );
			if ( '' !== $refusal ) {
				$out['dropped'][] = array(
					'op'     => 'add_logic_rule',
					'reason' => $refusal,
				);
				continue;
			}
			++$added;
			$out['ops'][] = array(
				'op'      => 'add_logic_rule',
				'rule'    => $built['rule'],
				'summary' => (string) ( $built['summary'] ?? '' ),
				'display' => (array) ( $built['display'] ?? array() ),
			);
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
		$label   = '' !== $gateway ? EfbPayments::label( $gateway ) : '';

		switch ( $kind ) {
			case 'payment_addon_inactive':
				/* translators: %s: payment gateway name, e.g. Stripe. */
				return sprintf( __( '%s needs its Easy Form Builder add-on, which is not installed or not active. I can install it for you if you allow it; the form reloads afterwards, so save your changes first.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_addon_update':
				/* translators: %s: payment gateway name, e.g. Stripe. */
				return sprintf( __( '%s needs a newer version of its Easy Form Builder add-on (its payment code moved out of Easy Form Builder into the add-on). Update it on the Add-ons page, then ask again.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_unverified':
				/* translators: %s: payment gateway name, e.g. Stripe. */
				return sprintf( __( 'This Easy Form Builder does not report its payment add-ons, so I cannot tell whether %s would work here and added no payment. Update Easy Form Builder to 4.3.2 or newer, then ask again.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_template_missing':
				/* translators: %s: payment gateway name, e.g. Mollie. */
				return sprintf( __( 'Easy Form Builder did not give me the %s payment field, so I added no payment. Add it from the Easy Form Builder palette instead.', 'ai-site-assistant-autopilot' ), $label );
			case 'payment_requires_pro':
				return 'pro' === (string) ( $parts[2] ?? 'pro' )
					/* translators: %s: payment gateway name, e.g. Stripe. */
					? sprintf( __( 'Online payment with %s is part of Easy Form Builder Pro.', 'ai-site-assistant-autopilot' ), $label )
					/* translators: %s: payment gateway name, e.g. Stripe. */
					: sprintf( __( 'Online payment with %s needs Easy Form Builder Free Plus or Pro.', 'ai-site-assistant-autopilot' ), $label );
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
				return $this->gateway_question();
		}
	}

	/** Which gateway to use, naming the ones EFB reports as ready here (or the usual three). */
	private function gateway_question(): string {
		$ready = array();
		foreach ( EfbPayments::registry_status() as $row ) {
			if ( ! empty( $row['addon_active'] ) && ! empty( $row['plan_ok'] ) ) {
				$ready[] = (string) $row['label'];
			}
		}
		if ( array() === $ready ) {
			return __( 'Tell me which payment gateway to use. Each one is an Easy Form Builder add-on (Stripe, PayPal, ZarinPal, Mollie, Payplug, Tap, Omise, PayTabs, Paymob or Xendit); none is active on this site yet.', 'ai-site-assistant-autopilot' );
		}

		/* translators: %s: comma-separated gateway names. */
		return sprintf( __( 'Tell me which payment gateway to use: %s.', 'ai-site-assistant-autopilot' ), implode( ', ', $ready ) );
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
			$label   = EfbPayments::label( $gateway );
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
			if ( 'payment_addon_inactive' === $parts[0] || 'payment_addon_update' === $parts[0] ) {
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
			} elseif ( 'value_not_given:thank_you_redirect' === $reason ) {
				$notes[] = __( 'Tell me the full address to send visitors to after they submit, for example https://example.com/thanks, or say “home page”.', 'ai-site-assistant-autopilot' );
			} elseif ( 'value_not_given:link' === $reason ) {
				$notes[] = __( 'Tell me the address the link should open, for example https://example.com/terms.', 'ai-site-assistant-autopilot' );
			} elseif ( 'value_not_given:sms_admin_numbers' === $reason ) {
				$notes[] = __( 'Tell me the mobile numbers that should get the SMS, in international form, for example +491701234567.', 'ai-site-assistant-autopilot' );
			} elseif ( 'html_empty' === $reason ) {
				$notes[] = __( 'Tell me what the HTML block should show. I keep safe formatting (text, links, lists, images) and leave out scripts.', 'ai-site-assistant-autopilot' );
			} elseif ( 'map_exists' === $reason ) {
				$notes[] = __( 'Easy Form Builder allows one location picker per form, and this form already has one.', 'ai-site-assistant-autopilot' );
			} elseif ( 'invalid_property_value:form_type' === $reason ) {
				$notes[] = __( 'I can switch this form between an ordinary form, a survey and a sign-in form. A payment form is made by adding a payment; a registration form starts from Easy Form Builder’s own Register template.', 'ai-site-assistant-autopilot' );
			} elseif ( 'addon_inactive:AdnSS' === $reason ) {
				$notes[] = __( 'SMS notifications need Easy Form Builder’s SMS add-on, which is not installed or not active. I can install it for you if you allow it; the form reloads afterwards, so save your changes first.', 'ai-site-assistant-autopilot' );
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
			case 'date_compare_needs_date':
				return __( 'I could not add that rule: “before” and “after” need a date field and a full date, for example 2026-12-01.', 'ai-site-assistant-autopilot' );
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
			$default = $this->setting_plan_message( $blocked );
		} else {
			$default = $this->field_plan_message( $blocked );
		}

		return $this->respond(
			array(
				'type'             => 'message',
				'reply'            => null !== $reply && '' !== $reply ? $reply : $default,
				'upgrade_required' => true,
				'upgrade_reason'   => $is_step_limit ? 'step_limit' : 'pro_field',
				'upgrade_url'      => EfbLicense::upgrade_url(),
				// EFB's own upgrade dialog (F18) names the feature; the browser opens it when EFB has it.
				'upgrade_feature'  => (string) ( $blocked[0]['feature'] ?? ( $is_step_limit ? 'limit:steps' : '' ) ),
				'blocked'          => $blocked,
			),
			true
		);
	}

	/**
	 * Which plan a refused field needs, as EFB's registry says, and what the site has now.
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 */
	private function field_plan_message( array $blocked ): string {
		$names = array();
		$needs = 'free_plus';
		$addon = null;
		foreach ( $blocked as $item ) {
			if ( 'field' !== ( $item['kind'] ?? '' ) ) {
				continue;
			}
			$names[] = (string) ( $item['label'] ?? $item['type'] ?? '' );
			if ( 'pro' === ( $item['plan'] ?? 'pro' ) ) {
				$needs = 'pro';
			}
			if ( 'addon' === ( $item['reason'] ?? '' ) && ! empty( $item['addon'] ) ) {
				$addon = (string) $item['addon'];
			}
		}
		if ( ! EfbCapabilities::available() ) {
			return __( 'This field is available in the Pro version of Easy Form Builder.', 'ai-site-assistant-autopilot' );
		}
		$names = implode( ', ', array_unique( array_filter( $names ) ) );
		if ( null !== $addon ) {
			/* translators: 1: field name(s), 2: add-on name. */
			return sprintf( __( '“%1$s” needs Easy Form Builder’s %2$s add-on, which is not active on this site any more, so I did not create the form. Ask me again and I will offer the closest field.', 'ai-site-assistant-autopilot' ), $names, EfbCapabilities::addon( $addon )['name'] );
		}
		$now = EfbCapabilities::plan_label( EfbLicense::status()['tier'] );

		return 'pro' === $needs
			/* translators: 1: field name(s), 2: the site's current Easy Form Builder plan. */
			? sprintf( __( '“%1$s” needs Easy Form Builder Pro. This site is on %2$s.', 'ai-site-assistant-autopilot' ), $names, $now ) . ' ' . EfbCapabilities::plan_note( 'pro' )
			/* translators: 1: field name(s), 2: the site's current Easy Form Builder plan. */
			: sprintf( __( '“%1$s” needs Easy Form Builder Free Plus or Pro. This site is on %2$s.', 'ai-site-assistant-autopilot' ), $names, $now ) . ' ' . EfbCapabilities::plan_note( 'free_plus' );
	}

	/**
	 * One plain sentence for a setting this plan does not include: what it is, which plan has it, and
	 * that nothing was changed.
	 *
	 * @param array<int,array<string,mixed>> $blocked
	 */
	private function setting_plan_message( array $blocked ): string {
		$names = array();
		$needs = 'plus';
		foreach ( $blocked as $item ) {
			if ( 'setting' !== ( $item['kind'] ?? '' ) ) {
				continue;
			}
			$names[] = $this->setting_name( (string) ( $item['type'] ?? '' ) );
			if ( 'pro' === ( $item['plan'] ?? '' ) ) {
				$needs = 'pro';
			}
		}
		$names = implode( ', ', array_unique( array_filter( $names ) ) );
		$tier  = EfbLicense::status()['tier'];
		$now   = 'free_plus' === $tier ? __( 'Free Plus', 'ai-site-assistant-autopilot' ) : __( 'Free', 'ai-site-assistant-autopilot' );

		if ( 'pro' === $needs ) {
			/* translators: 1: setting name(s), 2: the site's current Easy Form Builder plan. */
			return sprintf( __( '“%1$s” is part of Easy Form Builder Pro. This site is on %2$s, so I left it unchanged.', 'ai-site-assistant-autopilot' ), $names, $now );
		}

		/* translators: 1: setting name(s), 2: the site's current Easy Form Builder plan. */
		return sprintf( __( '“%1$s” is part of Easy Form Builder Free Plus and Pro. This site is on %2$s, so I left it unchanged.', 'ai-site-assistant-autopilot' ), $names, $now );
	}

	/** A setting's name in the admin's words, never its storage key. */
	private function setting_name( string $key ): string {
		$names = array(
			'email_subject'             => __( 'Email subject', 'ai-site-assistant-autopilot' ),
			'thank_you_redirect'        => __( 'Redirect after submit', 'ai-site-assistant-autopilot' ),
			'loading_type'              => __( 'Loading animation', 'ai-site-assistant-autopilot' ),
			'loading_color'             => __( 'Loading animation colour', 'ai-site-assistant-autopilot' ),
			'global_mobile_hide_labels' => __( 'Mobile layout', 'ai-site-assistant-autopilot' ),
		);
		if ( isset( $names[ $key ] ) ) {
			return $names[ $key ];
		}

		return str_starts_with( $key, 'mobile_' ) ? __( 'Mobile layout', 'ai-site-assistant-autopilot' ) : ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * What the admin wrote in this conversation (this message and their earlier ones), for values that
	 * must be theirs: an address, a phone number.
	 *
	 * @param array<int,array<string,string>> $history
	 */
	private function admin_said( string $message, array $history ): string {
		$said = array( $message );
		foreach ( $history as $turn ) {
			if ( 'user' === ( $turn['role'] ?? '' ) ) {
				$said[] = (string) ( $turn['text'] ?? '' );
			}
		}

		return implode( "\n", $said );
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
					// The step number the field is on, so a rule that jumps steps can say which fields it skips.
					'step'     => preg_replace( '/[^0-9]/', '', (string) ( $f['step'] ?? '' ) ) ?? '',
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
	private function finalize_plan( array $plan, array $raw_form = array() ): array {
		$plan['confirm_to_respondent'] = EfbFormConverter::confirmation_choice( $plan, $raw_form );
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
	 * The former "save the structure" endpoint for integration consumers. It wrote Easy Form Builder's table directly,
	 * which skips EFB's own save path (its sanitizer, add-on tables, caches, versions and hooks). Easy Form Builder has
	 * no update API yet, so an open form is saved only by the owner's own Save in its editor; this answers so.
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return $this->respond(
			array( 'message' => __( 'Save the form with the Save button in the Easy Form Builder editor. AI Site Assistant AutoPilot never writes Easy Form Builder’s tables itself.', 'ai-site-assistant-autopilot' ) ),
			false,
			410,
			'use_efb_save'
		);
	}

	private function registry(): ConnectorRegistry {
		/** @var ConnectorRegistry $registry */
		$registry = Plugin::instance()->container()->get( ConnectorRegistry::class );

		return $registry;
	}

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
			'label'    => EfbPayments::label( $gateway ) . ' · ' . $currency,
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
				'gateway'  => EfbPayments::label( (string) $plan['payment']['gateway'] ),
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
			// What Easy Form Builder itself would leave out or warn about (its form package validate(); writes nothing).
			'efb_notes'            => $this->efb_preview_notes( $plan ),
			'risk_level'           => $plan['risk_level'] ?? 'medium',
			'connector'            => $connector_name,
			'field_count'          => count( array_filter( $fields, static fn ( $f ): bool => is_array( $f ) && ! in_array( $f['type'] ?? '', array( 'steps', 'step' ), true ) ) ),
		);
	}

	/**
	 * Easy Form Builder's own verdict on a planned form before approval, as sentences.
	 *
	 * @param array<string,mixed> $plan
	 * @return string[]
	 */
	private function efb_preview_notes( array $plan ): array {
		$connector = $this->registry()->get( 'easy_form_builder' );
		if ( ! $connector instanceof \AISAAutoPilot\Connectors\EasyFormBuilderConnector ) {
			return array();
		}
		if ( ! $connector->can_create() ) {
			return array( \AISAAutoPilot\Connectors\EasyFormBuilderConnector::update_message() );
		}
		$check = $connector->preview_check( $plan );
		if ( null === $check ) {
			return array();
		}
		$notes = EfbSaveNotes::messages( $check['dropped'], $check['notices'] );
		foreach ( $check['errors'] as $error ) {
			$message = trim( wp_strip_all_tags( (string) ( $error['m'] ?? '' ) ) );
			if ( '' !== $message ) {
				/* translators: %s: Easy Form Builder's own reason. */
				$notes[] = sprintf( __( 'Easy Form Builder would refuse this form: %s', 'ai-site-assistant-autopilot' ), $message );
			}
		}

		return array_values( array_unique( $notes ) );
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
