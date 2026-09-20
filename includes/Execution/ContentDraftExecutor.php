<?php
declare(strict_types=1);

namespace AISAAutoPilot\Execution;

use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Skills\ContentPlanValidator;

/** Creates only an approved WordPress draft from a one-time Content Studio plan. */
final class ContentDraftExecutor {

	private ActionStore $store;
	private Logger $logger;
	private ContentPlanValidator $validator;

	public function __construct( ?ActionStore $store = null, ?Logger $logger = null, ?ContentPlanValidator $validator = null ) {
		$this->store     = $store ?? new ActionStore();
		$this->logger    = $logger ?? new Logger();
		$this->validator = $validator ?? new ContentPlanValidator();
	}

	/** @return array<string,mixed> */
	public function approve_and_execute( string $action_id ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this draft.', 'ai-site-assistant-autopilot' ) );
		}
		$claim = $this->store->claim( $action_id, get_current_user_id(), 'content_draft' );
		if ( 'missing' === $claim['status'] ) {
			return $this->failure( 'action_expired', __( 'This draft proposal has expired. Generate it again.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 'forbidden' === $claim['status'] ) {
			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this draft.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 'already_claimed' === $claim['status'] ) {
			return $this->failure( 'action_already_processed', __( 'This draft has already been processed.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 'wrong_connector' === $claim['status'] ) {
			return $this->failure( 'invalid_action', __( 'This approval does not belong to Content Studio.', 'ai-site-assistant-autopilot' ) );
		}

		$record = (array) ( $claim['record'] ?? array() );
		if ( 'content_draft' !== (string) ( $record['connector_id'] ?? '' ) ) {
			return $this->failure( 'invalid_action', __( 'This approval does not belong to Content Studio.', 'ai-site-assistant-autopilot' ) );
		}
		$validated = $this->validator->validate( (array) ( $record['plan'] ?? array() ) );
		if ( ! $validated->ok ) {
			$this->logger->error( 'content.invalid', 'Content plan failed validation at execution.', array( 'action_id' => $action_id ) );
			return $this->failure( 'invalid_payload', implode( ' ', $validated->errors ) );
		}
		$plan    = $validated->plan;
		$post    = array(
			'post_type'    => $plan['post_type'],
			'post_status'  => 'draft',
			'post_title'   => $plan['title'],
			'post_name'    => $plan['slug'],
			'post_excerpt' => $plan['excerpt'],
			'post_content' => $plan['body'],
			'post_author'  => get_current_user_id(),
		);
		$post_id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $post_id ) || ! is_numeric( $post_id ) ) {
			$message = is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'WordPress could not create the draft.', 'ai-site-assistant-autopilot' );
			$this->logger->error( 'content.create_failed', $message, array( 'action_id' => $action_id ) );
			return $this->failure( 'draft_create_failed', $message );
		}

		$meta = (string) ( $plan['meta_description'] ?? '' );
		if ( '' !== $meta ) {
			update_post_meta( (int) $post_id, '_aisaautopilot_meta_description', $meta );
		}
		update_post_meta( (int) $post_id, '_aisaautopilot_generated', '1' );
		// A draft written from an unanswered question is linked to it, so the gap offers "Open draft".
		$source_gap = (string) ( $record['execution_context']['source_gap'] ?? '' );
		if ( '' !== $source_gap && ( new KbGaps() )->link_draft( $source_gap, (int) $post_id ) ) {
			update_post_meta( (int) $post_id, '_aisaautopilot_source_gap', $source_gap );
		}
		$this->logger->info(
			'content.draft_created',
			'Content Studio created a WordPress draft.',
			array(
				'action_id' => $action_id,
				'post_id'   => (int) $post_id,
				'post_type' => $plan['post_type'],
			)
		);

		return array(
			'status'   => 'success',
			'post_id'  => (int) $post_id,
			'edit_url' => (string) get_edit_post_link( (int) $post_id, 'raw' ),
			'is_draft' => true,
		);
	}

	/** @return array{status:string,code:string,message:string} */
	private function failure( string $code, string $message ): array {
		return array(
			'status'  => 'failed',
			'code'    => $code,
			'message' => $message,
		);
	}
}
