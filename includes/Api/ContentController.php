<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Execution\ContentDraftExecutor;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Skills\ContentDraftSkill;
use AISAAutoPilot\Usage\UsageTracker;
use WP_REST_Request;
use WP_REST_Response;

/** REST surface for approval-gated post and page drafts. */
final class ContentController {

	private ProviderManager $providers;
	private ActionStore $store;
	private Logger $logger;

	public function __construct() {
		$this->providers = new ProviderManager();
		$this->store     = new ActionStore();
		$this->logger    = new Logger();
	}

	public function plan( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'content_studio_enabled', true ) ) {
			return $this->respond( array( 'message' => __( 'Content Studio is turned off in Integrations.', 'ai-site-assistant-autopilot' ) ), false, 403, 'integration_disabled' );
		}
		$prompt = trim( (string) $request->get_param( 'prompt' ) );
		if ( '' === $prompt || mb_strlen( $prompt ) > 6000 ) {
			return $this->respond( array( 'message' => __( 'Describe the post or page you want to draft in 6,000 characters or fewer.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_prompt' );
		}
		// "Write it" on an unanswered question passes its hash. It must name a question that is still open;
		// the link to the draft is made from the stored proposal after approval, never from the client.
		$source_gap = (string) $request->get_param( 'source_gap' );
		if ( '' !== $source_gap && null === ( new KbGaps() )->get( $source_gap ) ) {
			return $this->respond( array( 'message' => __( 'The unanswered question this draft was for is no longer in the list. Open Write again to draft without linking it.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_source_gap' );
		}
		if ( ( new UsageTracker() )->is_exhausted() ) {
			$this->logger->info( 'content.usage_cap_reached', 'Content planning was blocked because the token budget is exhausted.' );
			return $this->respond( array( 'message' => __( 'Your AI usage cap has been reached. Increase or reset the budget before drafting content.', 'ai-site-assistant-autopilot' ) ), false, 429, 'usage_cap_reached' );
		}
		if ( ! $this->providers->is_configured() ) {
			return $this->respond( array( 'message' => __( 'Connect an AI provider before drafting content.', 'ai-site-assistant-autopilot' ) ), false, 400, 'provider_not_configured' );
		}
		try {
			$result = ( new ContentDraftSkill( $this->providers->make() ) )->plan( $prompt );
		} catch ( ProviderException $e ) {
			$this->logger->error( 'content.provider_error', $e->getMessage(), array( 'code' => $e->error_code() ) );
			return $this->respond( array( 'message' => $e->user_message() ), false, $e->status_code(), $e->error_code() );
		}
		if ( ! $result->ok ) {
			return $this->respond(
				array(
					'message' => __( 'The content proposal was not safe or complete.', 'ai-site-assistant-autopilot' ),
					'errors'  => $result->errors,
				),
				false,
				422,
				'validation_failed'
			);
		}
		$action_id = $this->store->create( $result->plan, 'content_draft', array( 'source_gap' => $source_gap ) );
		$this->logger->info(
			'content.plan_created',
			'Content Studio plan created.',
			array(
				'action_id' => $action_id,
				'post_type' => $result->plan['post_type'],
			)
		);
		return $this->respond(
			array(
				'action_id'  => $action_id,
				'expires_at' => time() + ActionStore::ttl(),
				'preview'    => array(
					'post_type'        => $result->plan['post_type'],
					'title'            => $result->plan['title'],
					'excerpt'          => $result->plan['excerpt'],
					'body'             => $result->plan['body'],
					'meta_description' => $result->plan['meta_description'],
					'slug'             => $result->plan['slug'],
					'risk_level'       => $result->plan['risk_level'],
					'notes'            => $result->plan['notes'],
					'status'           => 'draft',
				),
			),
			true
		);
	}

	public function execute( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Settings::get( 'content_studio_enabled', true ) ) {
			return $this->respond( array( 'message' => __( 'Content Studio is turned off in Integrations.', 'ai-site-assistant-autopilot' ) ), false, 403, 'integration_disabled' );
		}
		$action_id = sanitize_text_field( (string) $request->get_param( 'action_id' ) );
		if ( '' === $action_id ) {
			return $this->respond( array( 'message' => __( 'Missing action reference.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_payload' );
		}
		$result = ( new ContentDraftExecutor( $this->store, $this->logger ) )->approve_and_execute( $action_id );
		if ( 'success' !== ( $result['status'] ?? '' ) ) {
			return $this->respond( array( 'message' => $result['message'] ?? __( 'Draft creation failed.', 'ai-site-assistant-autopilot' ) ), false, 422, (string) ( $result['code'] ?? 'execution_failed' ) );
		}
		return $this->respond( array( 'result' => $result ), true );
	}

	/** @param array<string,mixed> $data */
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
}
