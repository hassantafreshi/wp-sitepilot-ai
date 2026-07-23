<?php
declare(strict_types=1);

namespace SitePilot\Chat;

use SitePilot\Content\KnowledgeBase;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;

/**
 * RAG (Retrieval-Augmented Generation) engine.
 *
 * Searches the Knowledge Base for relevant content, builds a context-rich
 * prompt, sends it to the AI provider, and returns the answer with citations.
 */
final class ChatEngine {

	private KnowledgeBase $kb;
	private Logger $logger;

	/** Max KB entries to include as context. */
	private const MAX_SOURCES = 8;

	/** Max characters of context to send to the AI. */
	private const MAX_CONTEXT_CHARS = 10000;

	public function __construct( ?KnowledgeBase $kb = null, ?Logger $logger = null ) {
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Ask a question using RAG.
	 *
	 * @param string $question The user's question.
	 * @param string $context_override Optional pre-built context (from conversation history).
	 * @return array{answer:string, sources:array<int,array{post_id:int,title:string,url:string,relevance:float}>,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}}
	 */
	public function ask( string $question, string $context_override = '' ): array {
		$manager = new ProviderManager();
		if ( ! $manager->is_configured() ) {
			return array(
				'answer'  => __( 'AI provider is not configured. Please add your API key in Settings.', 'sitepilot-ai' ),
				'sources' => array(),
				'usage'   => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ),
			);
		}

		try {
			// 1. Search KB for relevant content.
			$search_results = $this->kb->search( $question, self::MAX_SOURCES );

			// 2. Build context from KB search results.
			$sources  = array();
			$context  = '';
			$context .= '__محتویات وبسایت (برای پاسخ‌دهی):__' . "\n\n";

			foreach ( $search_results as $result ) {
				$entry = $context . sprintf(
					"[%s — %s]\nخلاصه: %s\nکلمات کلیدی: %s\nدسته‌بندی: %s\n\n",
					$result['post_type'],
					$result['title'],
					$result['summary'],
					$result['keywords'],
					$result['category']
				);

				if ( mb_strlen( $entry ) > self::MAX_CONTEXT_CHARS - mb_strlen( $context ) ) {
					break;
				}
				$context = $entry;

				$sources[] = array(
					'post_id'   => (int) $result['post_id'],
					'title'     => html_entity_decode( $result['title'], ENT_QUOTES, 'UTF-8' ),
					'url'       => (string) get_permalink( (int) $result['post_id'] ),
					'relevance' => round( (float) ( $result['relevance'] ?? 1.0 ), 2 ),
				);
			}

			if ( '' === trim( $context ) ) {
				// No KB results — try direct AI answer without context.
				$context = __( 'No specific content found. Answer generally if possible.', 'sitepilot-ai' );
			}

			// 3. Build system prompt.
			$system_prompt = "You are SitePilot AI, a helpful WordPress website assistant.\n\n"
				. "Your task is to answer the user's question based ONLY on the website content provided below.\n\n"
				. $context . "\n\n"
				. "Rules:\n"
				. "- Respond in the same language as the user's question.\n"
				. "- Be concise, accurate, and helpful.\n"
				. "- If the answer is not in the provided content, say so honestly.\n"
				. "- At the end, list the sources you used (titles only).\n";

			// Use context override if provided (for conversation history).
			$user_prompt = $context_override ?: $question;

			// 4. Send to AI.
			$provider = $manager->make();
			$result   = $provider->generate_text( $system_prompt, $user_prompt );

			return array(
				'answer'  => $result['content'],
				'sources' => $sources,
				'usage'   => $result['usage'],
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'chat_rag', $e->getMessage() );

			return array(
				'answer'  => __( 'An error occurred while processing your question. Please try again.', 'sitepilot-ai' ),
				'sources' => array(),
				'usage'   => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ),
				'error'   => $e->getMessage(),
			);
		}
	}
}
