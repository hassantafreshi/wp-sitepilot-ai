<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Admin\ContentStudioNavigation;
use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\KnowledgeSearch;
use AISAAutoPilot\Content\SavedAnswers;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

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

		$post_types = $request->get_param( 'post_types' );
		$categories = $request->get_param( 'categories' );
		$filters    = array(
			'post_types' => is_array( $post_types ) ? array_map( 'sanitize_key', $post_types ) : array(),
			'categories' => is_array( $categories ) ? array_map( 'absint', $categories ) : array(),
		);

		$result = $indexer->process_batch( $offset, $filters );

		if ( 'usage_cap_reached' === (string) ( $result['code'] ?? '' ) ) {
			return $this->respond( $result, false, 429, 'usage_cap_reached' );
		}

		if ( isset( $result['error'] ) ) {
			return $this->respond( array( 'message' => $result['error'] ), false, 400, (string) ( $result['code'] ?? 'not_configured' ) );
		}

		return $this->respond( $result, true );
	}

	/**
	 * POST /kb/truncate — Clear all KB entries (before full re-index).
	 */
	public function truncate( \WP_REST_Request $request ): \WP_REST_Response {
		// Indexing text makes no AI request; only semantic search's embeddings count against the cap.
		if ( ( new ProviderManager() )->embeddings_enabled() && ( new UsageTracker() )->is_exhausted() ) {
			return $this->respond(
				array(
					'message' => __( 'The local AI usage cap has been reached. Raise or reset the token cap before starting a full re-index so existing knowledge is not cleared without a replacement pass.', 'ai-site-assistant-autopilot' ),
				),
				false,
				429,
				'usage_cap_reached'
			);
		}

		$kb = new KnowledgeBase();
		$kb->truncate();

		return $this->respond( array( 'message' => __( 'Knowledge base cleared.', 'ai-site-assistant-autopilot' ) ), true );
	}

	/**
	 * GET /kb/list — List KB entries with pagination and search.
	 */
	public function list_entries( \WP_REST_Request $request ): \WP_REST_Response {
		$kb           = new KnowledgeBase();
		$page         = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page     = 20;
		$search       = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$post_type    = sanitize_text_field( (string) $request->get_param( 'post_type' ) );
		$content_type = sanitize_text_field( (string) $request->get_param( 'content_type' ) );

		$result = $kb->get_all( $page, $per_page, $search, $post_type, $content_type );
		$pages  = max( 1, (int) ceil( $result['total'] / $per_page ) );

		return $this->respond(
			array(
				'items' => $result['items'],
				'total' => $result['total'],
				'page'  => $page,
				'pages' => $pages,
			),
			true
		);
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
			return $this->respond( array( 'message' => __( 'Invalid post ID.', 'ai-site-assistant-autopilot' ) ), false, 400, 'invalid_id' );
		}

		$kb     = new KnowledgeBase();
		$result = $kb->delete_by_post_id( $post_id );

		return $this->respond(
			array( 'message' => $result ? __( 'Entry removed.', 'ai-site-assistant-autopilot' ) : __( 'Entry not found.', 'ai-site-assistant-autopilot' ) ),
			$result
		);
	}

	/**
	 * POST /kb/chat — Ask a question against the knowledge base (RAG).
	 */
	public function chat( \WP_REST_Request $request ): \WP_REST_Response {
		$question = sanitize_text_field( (string) $request->get_param( 'question' ) );
		if ( '' === trim( $question ) ) {
			return $this->respond( array( 'message' => __( 'Please enter a question.', 'ai-site-assistant-autopilot' ) ), false, 400, 'empty_question' );
		}

		$provider_manager = new ProviderManager();
		if ( ! $provider_manager->is_configured() ) {
			return $this->respond( array( 'message' => __( 'AI provider is not configured.', 'ai-site-assistant-autopilot' ) ), false, 400, 'not_configured' );
		}

		$kb     = new KnowledgeBase();
		$search = ( new KnowledgeSearch( null, $kb ) )->retrieve( $question );
		if ( in_array( $search['reason'], array( 'not_ready', 'empty_index' ), true ) ) {
			// The passage index is still being built: the post summaries are the best there is.
			$context = $kb->get_context_for_ai( 50, 12000 );
		} elseif ( ! $search['found'] ) {
			// Nothing on the site relates to the question, so no AI request is made.
			return $this->respond(
				array(
					'answer' => ChatEngine::default_not_found_message( false ),
					'usage'  => array(),
					'status' => 'not_found',
				),
				true
			);
		} else {
			$context = $search['context'];
		}

		if ( '' === $context ) {
			return $this->respond(
				array(
					'message' => __( 'The knowledge base is empty. Please index your content first.', 'ai-site-assistant-autopilot' ),
				),
				false,
				400,
				'empty_kb'
			);
		}

		$system_prompt = "You are AI Site Assistant AutoPilot, a helpful assistant for a WordPress website.\n"
			. "Answer the user's question based ONLY on the website content provided below.\n"
			. "If the answer is not in the content, say so honestly.\n"
			. "Quote exact figures (prices, times, dates, amounts) as written.\n"
			. "Respond in the same language as the user's question.\n"
			. "Be concise, helpful, and accurate.\n\n"
			. "Website content:\n" . $context;

		try {
			$provider = $provider_manager->make();
			$result   = $provider->generate_text( $system_prompt, $question );

			return $this->respond(
				array(
					'answer' => $result['content'],
					'usage'  => $result['usage'],
				),
				true
			);

		} catch ( ProviderException $e ) {
			return $this->respond( array( 'message' => $e->user_message() ), false, $e->status_code(), $e->error_code() );
		} catch ( \Throwable $e ) {
			return $this->respond( array( 'message' => __( 'The AI provider could not answer this question. Please try again.', 'ai-site-assistant-autopilot' ) ), false, 502, 'ai_error' );
		}
	}

	/**
	 * GET /kb/gaps — List questions the KB could not answer (content gaps).
	 */
	public function gaps( \WP_REST_Request $request ): \WP_REST_Response {
		$gaps   = new KbGaps();
		$window = in_array( (string) $request->get_param( 'window' ), KbGaps::WINDOWS, true ) ? (string) $request->get_param( 'window' ) : 'all';
		$items  = $gaps->open_list( 50, $window );
		foreach ( $items as &$item ) {
			// "Write it" only opens Content Studio with an editable brief; nothing is generated until the administrator asks.
			$item['write_url'] = ContentStudioNavigation::write_url(
				sprintf(
					/* translators: %s: a question a site visitor asked that the site could not answer. */
					__( 'Write a help article that answers this question from a site visitor: %s', 'ai-site-assistant-autopilot' ),
					(string) $item['question']
				),
				(string) $item['hash']
			);
		}
		unset( $item );

		return $this->respond(
			array(
				'items'  => $items,
				'total'  => $gaps->open_count( $window ),
				'window' => $window,
			),
			true
		);
	}

	/**
	 * GET /kb/answers — Answers the site owner saved for visitor questions.
	 */
	public function answers(): \WP_REST_Response {
		return $this->respond(
			array(
				'items' => ( new SavedAnswers() )->list(),
				'limit' => SavedAnswers::MAX_KEEP,
			),
			true
		);
	}

	/**
	 * POST /kb/answers — Save an answer. With `gap`, the question comes from that unanswered question, which is
	 * dismissed once the answer is saved; with `id`, an existing answer is edited.
	 */
	public function answers_save( \WP_REST_Request $request ): \WP_REST_Response {
		$gap_hash = sanitize_key( (string) $request->get_param( 'gap' ) );
		$question = (string) $request->get_param( 'question' );
		$gaps     = new KbGaps();
		if ( '' !== $gap_hash ) {
			$gap = $gaps->get( $gap_hash );
			if ( null === $gap ) {
				return $this->respond( array( 'message' => __( 'This question is no longer in the list. Reload the page and try again.', 'ai-site-assistant-autopilot' ) ), false, 404, 'gap_not_found' );
			}
			$question = $gap['question'];
		}

		$result = ( new SavedAnswers() )->save( $question, (string) $request->get_param( 'answer' ), sanitize_key( (string) $request->get_param( 'id' ) ) );
		if ( ! $result['ok'] ) {
			$messages = array(
				'invalid_question'   => __( 'Enter the question visitors ask.', 'ai-site-assistant-autopilot' ),
				/* translators: %s: maximum number of characters in a saved answer. */
				'invalid_answer'     => sprintf( __( 'Write an answer of up to %s characters.', 'ai-site-assistant-autopilot' ), number_format_i18n( SavedAnswers::ANSWER_MAX ) ),
				'duplicate_question' => __( 'Another saved answer already covers this question.', 'ai-site-assistant-autopilot' ),
				/* translators: %s: maximum number of saved answers. */
				'saved_answers_full' => sprintf( __( 'You can keep up to %s saved answers. Delete one before adding another.', 'ai-site-assistant-autopilot' ), number_format_i18n( SavedAnswers::MAX_KEEP ) ),
			);
			return $this->respond( array( 'message' => $messages[ $result['code'] ] ?? __( 'Could not save the answer.', 'ai-site-assistant-autopilot' ) ), false, 400, $result['code'] );
		}
		if ( '' !== $gap_hash ) {
			$gaps->dismiss( $gap_hash );
		}

		return $this->respond(
			array(
				'item'          => $result['item'],
				'gap_dismissed' => '' !== $gap_hash,
			),
			true,
			200,
			$result['code']
		);
	}

	/**
	 * POST /kb/answers/delete — Delete one saved answer.
	 */
	public function answers_delete( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! ( new SavedAnswers() )->delete( sanitize_key( (string) $request->get_param( 'id' ) ) ) ) {
			return $this->respond( array( 'message' => __( 'This saved answer no longer exists.', 'ai-site-assistant-autopilot' ) ), false, 404, 'not_found' );
		}
		return $this->respond( array( 'message' => __( 'Saved answer deleted.', 'ai-site-assistant-autopilot' ) ), true );
	}

	/**
	 * POST /kb/gaps/dismiss — Dismiss one gap, or clear all when hash is empty.
	 */
	public function gaps_dismiss( \WP_REST_Request $request ): \WP_REST_Response {
		$hash = sanitize_text_field( (string) $request->get_param( 'hash' ) );
		$gaps = new KbGaps();

		if ( '' === $hash ) {
			$gaps->clear();
		} else {
			$gaps->dismiss( $hash );
		}

		return $this->respond( array( 'message' => __( 'Updated.', 'ai-site-assistant-autopilot' ) ), true );
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
		$budget = $this->requested_budget( $request );
		if ( null === $budget ) {
			return $this->respond(
				array( 'message' => __( 'Enter a whole-number token cap between 0 and 1,000,000,000.', 'ai-site-assistant-autopilot' ) ),
				false,
				400,
				'invalid_budget'
			);
		}

		$tracker = new UsageTracker();
		$tracker->set_budget( $budget );

		return $this->respond(
			array(
				'message' => __( 'Budget updated.', 'ai-site-assistant-autopilot' ),
				'budget'  => $budget,
			),
			true
		);
	}

	/**
	 * Validate the mutable budget at the API boundary. A negative whole number
	 * preserves the established compatibility behaviour of disabling the cap;
	 * malformed, missing, fractional and compound values must never reset it.
	 */
	private function requested_budget( \WP_REST_Request $request ): ?int {
		if ( ! $request->has_param( 'budget' ) ) {
			return null;
		}

		$value = $request->get_param( 'budget' );
		if ( is_int( $value ) ) {
			$raw = (string) $value;
		} elseif ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/D', $value ) ) {
			$raw = $value;
		} else {
			return null;
		}

		$negative = str_starts_with( $raw, '-' );
		$digits   = ltrim( $negative ? substr( $raw, 1 ) : $raw, '0' );
		$digits   = '' === $digits ? '0' : $digits;
		$maximum  = (string) UsageTracker::MAX_BUDGET;
		if ( strlen( $digits ) > strlen( $maximum ) || ( strlen( $digits ) === strlen( $maximum ) && $digits > $maximum ) ) {
			return null;
		}

		return $negative ? 0 : (int) $digits;
	}

	/**
	 * POST /usage/reset — Reset usage counters.
	 */
	public function reset_usage( \WP_REST_Request $request ): \WP_REST_Response {
		$tracker = new UsageTracker();
		$tracker->reset();

		return $this->respond( array( 'message' => __( 'Usage data reset.', 'ai-site-assistant-autopilot' ) ), true );
	}

	/**
	 * Uniform response envelope.
	 *
	 * @param array<string, mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200, string $code = 'ok' ): \WP_REST_Response {
		$payload = array_merge(
			array(
				'ok'   => $ok,
				'code' => $code,
			),
			$data
		);

		return new \WP_REST_Response( $payload, $status );
	}
}
