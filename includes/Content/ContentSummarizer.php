<?php
declare(strict_types=1);

namespace SitePilot\Content;

use SitePilot\Providers\ProviderInterface;
use SitePilot\Providers\ProviderException;

/**
 * Sends raw post content to the AI provider and receives a structured
 * JSON summary: title, summary, keywords, category.
 */
final class ContentSummarizer {

	private ProviderInterface $provider;

	/** Max characters of raw content sent to the AI (keeps token cost bounded). */
	private const MAX_CONTENT_CHARS = 8000;

	public function __construct( ProviderInterface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Summarise a single post into a structured KB entry.
	 *
	 * @param \WP_Post $post
	 * @return array{title:string, summary:string, keywords:string, category:string, token_count:int}
	 * @throws ProviderException
	 */
	public function summarize( \WP_Post $post ): array {
		$raw_content = $this->extract_text( $post );
		$title       = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$post_type   = get_post_type_object( $post->post_type );
		$type_label  = $post_type ? $post_type->labels->singular_name : $post->post_type;

		// Truncate very long content to keep token cost predictable.
		if ( mb_strlen( $raw_content ) > self::MAX_CONTENT_CHARS ) {
			$raw_content = mb_substr( $raw_content, 0, self::MAX_CONTENT_CHARS ) . "\xE2\x80\xA6";
		}

		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $title, $type_label, $raw_content, $post );

		$result = $this->provider->generate_structured_output( $system_prompt, $user_prompt );

		return array(
			'title'       => sanitize_text_field( $result['title'] ?? $title ),
			'summary'     => sanitize_textarea_field( $result['summary'] ?? '' ),
			'keywords'    => sanitize_text_field( $result['keywords'] ?? '' ),
			'category'    => sanitize_text_field( $result['category'] ?? '' ),
			'token_count' => absint( $result['token_count'] ?? 0 ),
		);
	}

	/**
	 * Extract clean text from a post (strip shortcodes, blocks, HTML).
	 */
	private function extract_text( \WP_Post $post ): string {
		$content = $post->post_content;

		// Remove shortcodes.
		$content = strip_shortcodes( $content );

		// Remove Gutenberg block comments.
		$content = (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $content );

		// Strip HTML tags.
		$content = wp_strip_all_tags( $content, true );

		// Collapse whitespace.
		$content = (string) preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	private function build_system_prompt(): string {
		return "You are a content analysis assistant for a WordPress website knowledge base.\n"
			. "Given a post's title, type, and content, produce a JSON object with exactly these keys:\n"
			. '- "title": A clean, concise title (string, max 200 chars)' . "\n"
			. '- "summary": A 2-4 sentence summary capturing the main points (string, max 500 chars)' . "\n"
			. '- "keywords": Comma-separated key terms, 3-8 keywords (string, max 300 chars)' . "\n"
			. '- "category": A single best-fit category label (string, max 50 chars)' . "\n"
			. '- "token_count": Estimated token count of the original content (integer)' . "\n\n"
			. "Rules:\n"
			. "- Respond ONLY with valid JSON. No markdown, no explanation.\n"
			. "- Write the summary in the SAME LANGUAGE as the original content.\n"
			. "- Be factual and concise. Do not add information not present in the content.\n"
			. '- If the content is empty or too short, set summary to "No content available."';
	}

	private function build_user_prompt( string $title, string $type_label, string $content, \WP_Post $post ): string {
		$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		$cat_str    = is_array( $categories ) && ! empty( $categories ) ? implode( ', ', $categories ) : 'Uncategorized';

		return sprintf(
			"Post type: %s\nTitle: %s\nCategories: %s\nDate: %s\n\nContent:\n%s",
			$type_label,
			$title,
			$cat_str,
			get_the_date( 'Y-m-d', $post ),
			$content
		);
	}
}
