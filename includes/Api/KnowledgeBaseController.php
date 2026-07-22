<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Content\ContentIndexer;
use SitePilot\Content\KnowledgeBase;
use SitePilot\Providers\ProviderManager;
use SitePilot\Usage\UsageTracker;

/**
 * REST controller for Knowledge Base and Usage endpoints.
 */
final class KnowledgeBaseController {

	/**
	 * POST /kb/index/batch — Process one batch of content indexing.
	 */
	public function index_batch( \WP_REST_Request $request ): \WP_REST_Response {
		$offset  = absint( $request->get_param( 'offset' ) );
		$indexer = new ContentIndexer();
		$result  = $indexer->process_batch( $offset );

		if ( isset( $result['error'] ) ) {
			return $this->respond( array( 'message' => $result['error'] ), false, 400, 'not_configured' );
		}

		return $this->respond( $result, true );
	}

	/**
	 * POST /kb/truncate — Clear all KB entries (before full re-index).
	 */
	public function truncate( \WP_REST_Request $request ): \WP_REST_Response {
		$kb = new KnowledgeBase();
		$kb->truncate();

		return $this->respond( array( 'message' => __( 'Knowledge base cleared.', 'sitepilot-ai' ) ), true );
	}

	/**
	 * GET /kb/list — List KB entries with pagination and search.
	 */
	public function list_entries( \WP_REST_Request $request ): \WP_REST_Response {
		$kb        = new KnowledgeBase();
		$page      = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page  = 20;
		$search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$post_type = sanitize_text_field( (string) $request->get_param( 'post_type' ) );

		$result = $kb->get_all( $page, $per_page, $search, $post_type );
		$pages  = max( 1, (int) ceil( $result['total'] / $per_page ) );

		return $this->respond( array(
			'items' => $result['items'],
			'total' => $result['total'],
			'page'  => $page,
			'pages' => $pages,
		), true );
	}

	/**
	 * GET /kb/stats — Get KB statistics.
	 */
	public function stats( \WP_REST_Request $request ): \WP_REST_Response {
		$kb    = new KnowledgeBase();
		$stats = $kb->get_stats();

		return $this->respond( array( 'stats' => $stats ), true );
	}

	/**
	 * POST /kb/delete — Remove a single KB entry.
	 */
	public function delete_entry( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return $this->respond( array( 'message' => __( 'Invalid post ID.', 'sitepilot-ai' ) ), false, 400, 'invalid_id' );
		}

		$kb     = new KnowledgeBase();
		$result = $kb->delete_by_post_id( $post_id );

		return $this->respond(
			array( 'message' => $result ? __( 'Entry removed.', 'sitepilot-ai' ) : __( 'Entry not found.', 'sitepilot-ai' ) ),
			$result
		);
	}

	/**
	 * POST /kb/chat — Ask a question against the knowledge base (RAG).
	 */
	public function chat( \WP_REST_Request $request ): \WP_REST_Response {
		$question = sanitize_text_field( (string) $request->get_param( 'question' ) );
		if ( '' === trim( $question ) ) {
			return $this->respond( array( 'message' => __( 'Please enter a question.', 'sitepilot-ai' ) ), false, 400, 'empty_question' );
		}

		$provider_manager = new ProviderManager();
		if ( ! $provider_manager->is_configured() ) {
			return $this->respond( array( 'message' => __( 'AI provider is not configured.', 'sitepilot-ai' ) ), false, 400, 'not_configured' );
		}

		$kb      = new KnowledgeBase();
		$context = $kb->get_context_for_ai( 50, 12000 );

		if ( '' === $context ) {
			return $this->respond( array(
				'message' => __( 'The knowledge base is empty. Please index your content first.', 'sitepilot-ai' ),
			), false, 400, 'empty_kb' );
		}

		$system_prompt = "You are SitePilot AI, a helpful assistant for a WordPress website.\n"
			. "Answer the user's question based ONLY on the website content provided below.\n"
			. "If the answer is not in the content, say so honestly.\n"
			. "Respond in the same language as the user's question.\n"
			. "Be concise, helpful, and accurate.\n\n"
			. "Website content:\n" . $context;

		try {
			$provider = $provider_manager->make();
			$result   = $provider->generate_text( $system_prompt, $question );

			return $this->respond( array(
				'answer' => $result['content'],
				'usage'  => $result['usage'],
			), true );

		} catch ( \Throwable $e ) {
			return $this->respond( array( 'message' => $e->getMessage() ), false, 500, 'ai_error' );
		}
	}

	/**
	 * GET /usage/report — Get usage statistics and credit info.
	 */
	public function usage_report( \WP_REST_Request $request ): \WP_REST_Response {
		$tracker = new UsageTracker();
		$report  = $tracker->get_report();

		return $this->respond( array( 'report' => $report ), true );
	}

	/**
	 * POST /usage/budget — Set the monthly token budget.
	 */
	public function set_budget( \WP_REST_Request $request ): \WP_REST_Response {
		$budget  = absint( $request->get_param( 'budget' ) );
		$tracker = new UsageTracker();
		$tracker->set_budget( $budget );

		return $this->respond( array(
			'message' => __( 'Budget updated.', 'sitepilot-ai' ),
			'budget'  => $budget,
		), true );
	}

	/**
	 * POST /usage/reset — Reset usage counters.
	 */
	public function reset_usage( \WP_REST_Request $request ): \WP_REST_Response {
		$tracker = new UsageTracker();
		$tracker->reset();

		return $this->respond( array( 'message' => __( 'Usage data reset.', 'sitepilot-ai' ) ), true );
	}

	/**
	 * Uniform response envelope.
	 *
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): \WP_REST_Response {
		$payload = array_merge( array( 'ok' => $ok, 'code' => $code ), $data );

		return new \WP_REST_Response( $payload, $status );
	}
}
