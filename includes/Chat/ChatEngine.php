<?php
declare(strict_types=1);

namespace SitePilot\Chat;

use SitePilot\Content\KnowledgeBase;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;

/**
 * RAG (Retrieval-Augmented Generation) engine.
 *
 * Two-phase approach:
 * 1. Build comprehensive context from ALL KB content (get_context_for_ai)
 * 2. Search KB for specific relevant entries → source citations
 * 3. Send full context + specific sources to AI
 */
final class ChatEngine {

	private KnowledgeBase $kb;
	private Logger $logger;

	/** Max KB entries to include as context. */
	private const MAX_SOURCES = 10;

	/** Max characters of context to send to the AI. */
	private const MAX_CONTEXT_CHARS = 12000;

	public function __construct( ?KnowledgeBase $kb = null, ?Logger $logger = null ) {
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Ask a question using RAG.
	 *
	 * @param string $question        The user's question.
	 * @param string $context_override Optional pre-built context (from conversation history).
	 * @return array{answer:string, sources:array<int,array{post_id:int,title:string,url:string,relevance:float}>, context:string, usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}}
	 */
	public function ask( string $question, string $context_override = '' ): array {
		$manager = new ProviderManager();
		if ( ! $manager->is_configured() ) {
			return array(
				'answer'  => __( 'AI provider is not configured. Please add your API key in Settings.', 'sitepilot-ai' ),
				'sources' => array(),
				'context' => '',
				'usage'   => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ),
			);
		}

		try {
			// Phase 1: Get comprehensive context from ALL KB content.
			$kb_context = $this->kb->get_context_for_ai( 50, self::MAX_CONTEXT_CHARS );

			// Phase 2: Search for specific relevant entries for source citations.
			$search_results = $this->kb->search( $question, self::MAX_SOURCES );
			$sources        = array();

			foreach ( $search_results as $result ) {
				$sources[] = array(
					'post_id'   => (int) $result['post_id'],
					'title'     => html_entity_decode( $result['title'] ?? '', ENT_QUOTES, 'UTF-8' ),
					'url'       => (string) get_permalink( (int) $result['post_id'] ),
					'relevance' => round( (float) ( $result['relevance'] ?? 1.0 ), 2 ),
				);
			}

			// Phase 3: Build the system prompt with context.
			$context_for_ai = '';
			if ( '' !== trim( $kb_context ) ) {
				$context_for_ai = "Website content (use this to answer):\n" . $kb_context;
			}

			$system_prompt = "You are SitePilot AI, a helpful WordPress website assistant.\n"
				. "Answer the user's question based on the website content below.\n"
				. "If the content doesn't have the answer, say so honestly.\n"
				. "Be concise and helpful.\n\n"
				. $context_for_ai . "\n\n"
				. "At the end, if you used specific sources, mention them by name.\n";

			// Use context override if provided (for conversation history).
			$user_prompt = $context_override ?: $question;

			// Phase 4: Send to AI provider.
			$provider = $manager->make();
			$result   = $provider->generate_text( $system_prompt, $user_prompt );

			return array(
				'answer'  => $result['content'],
				'sources' => $sources,
				'context' => $kb_context,
				'usage'   => $result['usage'],
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'chat_rag', $e->getMessage() );

			return array(
				'answer'  => __( 'An error occurred while processing your question. Please try again.', 'sitepilot-ai' ),
				'sources' => array(),
				'context' => '',
				'usage'   => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ),
				'error'   => $e->getMessage(),
			);
		}
	}
}
