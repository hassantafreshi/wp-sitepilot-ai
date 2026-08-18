<?php
declare(strict_types=1);

namespace AISAAutoPilot\Chat;

use AISAAutoPilot\Content\KbCache;
use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Content\VectorSearch;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * RAG (Retrieval-Augmented Generation) engine.
 *
 * Answers questions like a friendly human support specialist instead of a
 * robotic template:
 *  - Searches ALL indexed content and keeps the conversation on-topic.
 *  - Asks the visitor to clarify when a question maps to several distinct pages.
 *  - Says so honestly (and can offer a human handoff) when nothing matches.
 *  - Cites real source links, in the visitor's language.
 *  - Caches answered questions and logs unanswered ones for the site owner.
 */
final class ChatEngine {

	private KnowledgeBase $kb;
	private Logger $logger;

	/** Max KB entries to include as numbered context (recency fallback). */
	private const MAX_SOURCES = 40;

	/** Top-K most relevant entries to include when semantic search is on. */
	private const VECTOR_TOP_K = 10;

	/** Max characters of context to send to the AI. */
	private const MAX_CONTEXT_CHARS = 12000;

	public function __construct( ?KnowledgeBase $kb = null, ?Logger $logger = null ) {
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Ask a question using RAG.
	 *
	 * @param string $question The user's current question.
	 * @param string $history  Optional formatted prior turns ("User: ...\nAssistant: ...").
	 * @return array{
	 *   answer:string,
	 *   sources:array<int,array{post_id:int,title:string,url:string,relevance:float}>,
	 *   status:string,
	 *   clarify_options:array<int,string>,
	 *   handoff_offer:bool,
	 *   cached:bool,
	 *   context:string,
	 *   usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
	 * }
	 */
	public function ask( string $question, string $history = '' ): array {
		$manager = new ProviderManager();
		if ( ! $manager->is_configured() ) {
			return $this->result(
				__( 'AI provider is not configured. Please add your API key in Settings.', 'ai-site-assistant-autopilot' ),
				array(),
				'not_configured'
			);
		}

		$is_followup = '' !== trim( $history );

		// 1) Answer cache — only for stand-alone questions (follow-ups depend on history).
		$cache = new KbCache();
		if ( ! $is_followup ) {
			$hit = $cache->get( $question );
			if ( null !== $hit && '' !== trim( $hit['answer'] ) ) {
				$res           = $this->result( $hit['answer'], $hit['sources'], 'answered' );
				$res['cached'] = true;
				return $res;
			}
		}

		try {
			// 2) Retrieve context — semantic (embeddings) when available, else recency.
			$built   = $this->retrieve( $question );
			$context = $built['context'];
			$map     = $built['map'];

			if ( '' === trim( $context ) ) {
				$gap = $this->offer_handoff();
				( new KbGaps() )->record( $question );
				return $this->result(
					$this->not_found_message( $gap ),
					array(),
					'not_found',
					array(),
					$gap
				);
			}

			// 3) Ask the model for a structured decision (answer / clarify / not found).
			$provider = $manager->make();
			$system   = $this->build_system_prompt( $context );
			$user     = $this->build_user_prompt( $question, $history );

			$decision = null;
			try {
				$decision = $provider->generate_structured_output( $system, $user );
			} catch ( \Throwable $e ) {
				// Fallback: some providers dislike JSON mode — degrade to plain text.
				$this->logger->error( 'chat_rag_structured', $e->getMessage() );
				$plain = $provider->generate_text( $this->build_plain_prompt( $context ), $user );
				return $this->finalise_plain( $question, $plain, $map, $cache, $is_followup );
			}

			return $this->finalise( $question, $decision, $map, $cache, $is_followup );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'chat_rag', $e->getMessage() );

			return $this->result(
				__( 'An error occurred while processing your question. Please try again.', 'ai-site-assistant-autopilot' ),
				array(),
				'error'
			);
		}
	}

	/**
	 * Build the numbered context + reference map for a question.
	 *
	 * Prefers semantic (embeddings) retrieval — top-K most relevant entries —
	 * and falls back to recency-based context when embeddings are off/unavailable.
	 *
	 * @return array{context:string, map:array<int,array{post_id:int,title:string}>}
	 */
	private function retrieve( string $question ): array {
		try {
			$vector = new VectorSearch( $this->kb );
			if ( $vector->is_ready() ) {
				$entries = $vector->search( $question, self::VECTOR_TOP_K );
				if ( ! empty( $entries ) ) {
					return $this->kb->build_context_refs( $entries, self::MAX_CONTEXT_CHARS );
				}
			}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'chat_retrieve', $e->getMessage() );
		}

		// Fallback: most-recent indexed content.
		return $this->kb->get_context_with_refs( self::MAX_SOURCES, self::MAX_CONTEXT_CHARS );
	}

	/**
	 * Turn the model's structured decision into a final result.
	 *
	 * @param array<string,mixed>                        $decision
	 * @param array<int,array{post_id:int,title:string}> $map
	 */
	private function finalise( string $question, array $decision, array $map, KbCache $cache, bool $is_followup ): array {
		$status = strtolower( (string) ( $decision['status'] ?? 'answered' ) );
		$answer = trim( (string) ( $decision['answer'] ?? '' ) );

		if ( 'clarify' === $status ) {
			$options = array();
			if ( isset( $decision['clarify_options'] ) && is_array( $decision['clarify_options'] ) ) {
				foreach ( $decision['clarify_options'] as $opt ) {
					$opt = trim( wp_strip_all_tags( (string) $opt ) );
					if ( '' !== $opt ) {
						$options[] = mb_substr( $opt, 0, 120 );
					}
				}
			}
			$options = array_slice( array_values( array_unique( $options ) ), 0, 5 );

			if ( '' === $answer ) {
				$answer = __( 'I found a few different things that could match. Which one did you mean?', 'ai-site-assistant-autopilot' );
			}

			return $this->result( $answer, $this->map_sources( $decision, $map ), 'clarify', $options );
		}

		if ( 'not_found' === $status || '' === $answer ) {
			$gap = $this->offer_handoff();
			( new KbGaps() )->record( $question );

			$message = '' !== $answer ? $answer : $this->not_found_message( $gap );
			if ( $gap && '' !== $answer ) {
				$message .= "\n\n" . $this->handoff_line();
			}

			return $this->result( $message, array(), 'not_found', array(), $gap );
		}

		// Answered.
		$sources = $this->map_sources( $decision, $map );

		if ( empty( $sources ) ) {
			// Model answered but cited nothing — best-effort keyword fallback.
			$sources = $this->fallback_sources( $question );
		}

		// Cache stand-alone answers so the next identical question skips retrieval.
		if ( ! $is_followup ) {
			$cache->put( $question, $answer, $sources );
		}

		return $this->result( $answer, $sources, 'answered' );
	}

	/**
	 * Finalise a plain-text fallback answer (no structured decision available).
	 *
	 * @param array{content:string,usage:array}          $plain
	 * @param array<int,array{post_id:int,title:string}> $map
	 */
	private function finalise_plain( string $question, array $plain, array $map, KbCache $cache, bool $is_followup ): array {
		$answer  = trim( (string) ( $plain['content'] ?? '' ) );
		$sources = $this->parse_inline_sources( $answer, $map );
		if ( empty( $sources ) ) {
			$sources = $this->fallback_sources( $question );
		}
		// Strip a trailing "SOURCES: ..." line if the model added one.
		$answer = trim( (string) preg_replace( '/\n*\s*SOURCES:\s*[^\n]*\s*$/i', '', $answer ) );

		if ( '' === $answer ) {
			( new KbGaps() )->record( $question );
			$gap = $this->offer_handoff();
			return $this->result( $this->not_found_message( $gap ), array(), 'not_found', array(), $gap );
		}

		if ( empty( $sources ) ) {
			( new KbGaps() )->record( $question );
		} elseif ( ! $is_followup ) {
			$cache->put( $question, $answer, $sources );
		}

		$res          = $this->result( $answer, $sources, 'answered' );
		$res['usage'] = isset( $plain['usage'] ) && is_array( $plain['usage'] ) ? $plain['usage'] : $res['usage'];
		return $res;
	}

	/**
	 * Map the model-cited reference numbers back to real posts/links.
	 *
	 * @param array<string,mixed>                        $decision
	 * @param array<int,array{post_id:int,title:string}> $map
	 * @return array<int,array{post_id:int,title:string,url:string,relevance:float}>
	 */
	private function map_sources( array $decision, array $map ): array {
		$refs = array();
		if ( isset( $decision['sources'] ) && is_array( $decision['sources'] ) ) {
			foreach ( $decision['sources'] as $ref ) {
				$refs[] = (int) $ref;
			}
		}

		$sources = array();
		$seen    = array();
		foreach ( $refs as $n ) {
			if ( ! isset( $map[ $n ] ) || isset( $seen[ $map[ $n ]['post_id'] ] ) ) {
				continue;
			}
			$pid          = $map[ $n ]['post_id'];
			$seen[ $pid ] = true;
			$sources[]    = array(
				'post_id'   => $pid,
				'title'     => $map[ $n ]['title'],
				'url'       => (string) get_permalink( $pid ),
				'relevance' => 1.0,
			);
		}

		return $sources;
	}

	/**
	 * Parse a trailing "SOURCES: 1, 3" line from a plain-text answer.
	 *
	 * @param array<int,array{post_id:int,title:string}> $map
	 * @return array<int,array{post_id:int,title:string,url:string,relevance:float}>
	 */
	private function parse_inline_sources( string $answer, array $map ): array {
		if ( ! preg_match( '/SOURCES:\s*([^\n]*)\s*$/i', $answer, $m ) ) {
			return array();
		}
		if ( false !== stripos( $m[1], 'none' ) ) {
			return array();
		}
		preg_match_all( '/\d+/', $m[1], $nums );

		return $this->map_sources( array( 'sources' => $nums[0] ), $map );
	}

	/**
	 * Keyword-search fallback for sources when the model cites none.
	 *
	 * @return array<int,array{post_id:int,title:string,url:string,relevance:float}>
	 */
	private function fallback_sources( string $question ): array {
		$results = $this->kb->search( $question, 3 );
		$sources = array();
		foreach ( $results as $r ) {
			$pid       = (int) $r['post_id'];
			$sources[] = array(
				'post_id'   => $pid,
				'title'     => html_entity_decode( $r['title'] ?? '', ENT_QUOTES, 'UTF-8' ),
				'url'       => (string) get_permalink( $pid ),
				'relevance' => round( (float) ( $r['relevance'] ?? 1.0 ), 2 ),
			);
		}

		return $sources;
	}

	private function build_system_prompt( string $context ): string {
		return 'You are AISAAutoPilot, a warm, knowledgeable support specialist for this website. '
			. "You speak like a helpful human expert — friendly, clear and genuinely useful — never robotic.\n\n"
			. "You are given numbered KNOWLEDGE entries drawn from the site's own content. "
			. "Decide how to respond and reply with a JSON object using EXACTLY these keys:\n"
			. '- "status": one of "answered", "clarify", "not_found".' . "\n"
			. '- "answer": your reply text in Markdown (see writing rules). For "clarify", a short friendly question. For "not_found", a brief honest note.' . "\n"
			. '- "clarify_options": array of short labels (max 5) naming the distinct topics the user might mean. Only for status "clarify"; otherwise [].' . "\n"
			. '- "sources": array of the KNOWLEDGE entry numbers you actually used (e.g. [1,3]). [] if none.' . "\n\n"
			. "Decision rules:\n"
			. "- Use \"answered\" when the knowledge clearly covers the question. Base the answer ONLY on the knowledge; never invent specifics.\n"
			. "- Use \"clarify\" when the question could refer to several DIFFERENT pages/topics — briefly ask which one they mean and list them in clarify_options.\n"
			. "- Use \"not_found\" only when nothing in the knowledge is relevant. Don't force an answer.\n\n"
			. "Writing rules for \"answer\":\n"
			. "- Reply in the SAME language the user writes in.\n"
			. "- Sound human and helpful. Start with a short natural sentence, then explain.\n"
			. "- For \"how do I…\" questions, give concrete numbered steps — not a vague summary.\n"
			. "- Use light Markdown: short paragraphs, **bold** for key terms, numbered/bulleted lists.\n"
			. "- Consider the conversation so far and stay on the SAME topic as the user's follow-up.\n"
			. "- NEVER write meta phrases like \"I used post X\" or \"based on the provided content\". Sources are shown to the user automatically.\n\n"
			. "KNOWLEDGE:\n" . $context;
	}

	/**
	 * Plain-text prompt used only if the provider fails JSON mode.
	 */
	private function build_plain_prompt( string $context ): string {
		return "You are AISAAutoPilot, a warm, knowledgeable human-like support specialist for this website.\n"
			. "Answer using ONLY the numbered KNOWLEDGE below, in the SAME language as the user.\n"
			. "Be friendly and clear; for how-to questions give concrete numbered steps. Use light Markdown.\n"
			. "If nothing is relevant, say so honestly. Never write \"I used post X\".\n"
			. "On the VERY LAST line output exactly: SOURCES: <comma-separated entry numbers you used, or none>\n\n"
			. "KNOWLEDGE:\n" . $context;
	}

	private function build_user_prompt( string $question, string $history ): string {
		if ( '' !== trim( $history ) ) {
			return "Conversation so far:\n" . trim( $history ) . "\n\nUser's new message: " . $question;
		}

		return $question;
	}

	/**
	 * Whether a human handoff is enabled and has at least one configured channel.
	 */
	private function offer_handoff(): bool {
		$w = Settings::get( 'widget', array() );
		if ( ! is_array( $w ) || empty( $w['handoff_enabled'] ) ) {
			return false;
		}

		$email         = sanitize_email( (string) ( $w['handoff_email_to'] ?? get_option( 'admin_email', '' ) ) );
		$has_email    = ! empty( $w['handoff_email_enabled'] ) && '' !== $email && is_email( $email );
		$has_telegram = Settings::has_handoff_secret( 'handoff_telegram_token' ) && ! empty( $w['handoff_telegram_chat'] );
		$has_twilio   = Settings::has_handoff_secret( 'handoff_twilio_sid' ) && Settings::has_handoff_secret( 'handoff_twilio_token' );

		return $has_email || $has_telegram || $has_twilio;
	}

	private function not_found_message( bool $handoff ): string {
		$base = __( "I looked through the site's content but couldn't find a clear answer to that yet.", 'ai-site-assistant-autopilot' );
		if ( $handoff ) {
			return $base . "\n\n" . $this->handoff_line();
		}

		return $base . ' ' . __( 'Could you try rephrasing, or asking about something else?', 'ai-site-assistant-autopilot' );
	}

	private function handoff_line(): string {
		return __( 'Would you like me to connect you with a member of our team?', 'ai-site-assistant-autopilot' );
	}

	/**
	 * Uniform result envelope.
	 *
	 * @param array<int,array<string,mixed>> $sources
	 * @param array<int,string>              $clarify_options
	 * @return array<string,mixed>
	 */
	private function result( string $answer, array $sources, string $status, array $clarify_options = array(), bool $handoff_offer = false ): array {
		return array(
			'answer'          => $answer,
			'sources'         => $sources,
			'status'          => $status,
			'clarify_options' => $clarify_options,
			'handoff_offer'   => $handoff_offer,
			'cached'          => false,
			'context'         => '',
			'usage'           => array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			),
		);
	}
}
