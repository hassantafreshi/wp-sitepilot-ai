<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * Batch content indexer.
 *
 * Reads posts, pages and enabled post types, splits each into passages of its own text
 * (product data included) and stores them in the passage index. No AI request is made
 * to index a page: indexing is free, complete and fast. Only when semantic search is
 * switched on does each page also get an embedding, which uses the AI provider.
 *
 * Designed for AJAX-driven progress: the admin UI calls /kb/index/batch repeatedly.
 */
final class ContentIndexer {

	/** Posts per AJAX batch when only text is indexed. */
	public const BATCH_SIZE = 25;

	/** Posts per batch when every post also needs an embedding request. */
	public const EMBED_BATCH_SIZE = 4;

	/** Bumped when extraction or chunking changes, so every post is re-indexed once. */
	public const FORMAT = 'chunks-1';

	/** Post types eligible for indexing. */
	private const INDEXABLE_TYPES = array( 'post', 'page' );

	private KnowledgeBase $kb;
	private KnowledgeIndex $index;
	private Logger $logger;

	public function __construct( ?KnowledgeBase $kb = null, ?Logger $logger = null, ?KnowledgeIndex $index = null ) {
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
		$this->index  = $index ?? new KnowledgeIndex();
	}

	/**
	 * Get the total number of indexable posts, optionally narrowed by filters.
	 *
	 * @param array{post_types?:array<int,string>, categories?:array<int,int>} $filters
	 */
	public function get_total_posts( array $filters = array() ): int {
		$types = $this->resolve_types( $filters );
		$cats  = $this->resolve_categories( $filters );

		if ( empty( $cats ) ) {
			$count = 0;
			foreach ( $types as $type ) {
				$counts = wp_count_posts( $type );
				$count += (int) ( $counts->publish ?? 0 );
			}

			return $count;
		}

		// Category filter needs a real query to count accurately.
		$query = new \WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'category__in'   => $cats,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Get the number of posts already indexed.
	 */
	public function get_indexed_count(): int {
		$stats = $this->kb->get_stats();

		return $stats['indexed'];
	}

	/**
	 * Process one batch of posts.
	 *
	 * @param int                                                              $offset Starting offset in the post list.
	 * @param array{post_types?:array<int,string>, categories?:array<int,int>} $filters
	 * @return array{
	 *   done: bool,
	 *   processed: int,
	 *   total: int,
	 *   offset: int,
	 *   items: array<int, array{post_id:int, title:string, status:string, message:string}>,
	 *   percent: int,
	 *   paused?: bool,
	 *   code?: string,
	 *   message?: string,
	 *   error?: string
	 * }
	 */
	public function process_batch( int $offset = 0, array $filters = array() ): array {
		$this->ensure_tables();

		$embed     = $this->embedding_context();
		$total     = $this->get_total_posts( $filters );
		$types     = $this->resolve_types( $filters );
		$cats      = $this->resolve_categories( $filters );
		$items     = array();
		$processed = 0;

		$query_args = array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => null !== $embed ? self::EMBED_BATCH_SIZE : self::BATCH_SIZE,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( ! empty( $cats ) ) {
			$query_args['category__in'] = $cats;
		}

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			return array(
				'done'      => true,
				'processed' => 0,
				'total'     => $total,
				'offset'    => $offset,
				'items'     => array(),
				'percent'   => 100,
			);
		}

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$title   = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );

			try {
				$outcome = $this->index_post( $post, $embed );
			} catch ( ProviderException $e ) {
				if ( 'usage_cap_reached' === $e->error_code() ) {
					// Keep the cursor on this post: its text is stored, and the resumed pass
					// adds the embedding that could not be requested.
					return $this->paused_result( $offset, $processed, $total, $items );
				}
				$outcome = array(
					'status'  => 'error',
					'message' => $e->getMessage(),
				);
			} catch ( \Throwable $e ) {
				$this->logger->error( 'kb_index', sprintf( 'Unexpected error post %d: %s', $post_id, $e->getMessage() ) );
				$outcome = array(
					'status'  => 'error',
					'message' => __( 'Unexpected error during indexing.', 'ai-site-assistant-autopilot' ),
				);
			}

			$items[] = array(
				'post_id' => $post_id,
				'title'   => $title,
				'status'  => $outcome['status'],
				'message' => $this->item_message( $outcome, $title ),
			);
			++$processed;
		}

		$new_offset = $offset + $processed;
		$percent    = $total > 0 ? min( 100, (int) round( ( $new_offset / $total ) * 100 ) ) : 100;

		return array(
			'done'      => $new_offset >= $total,
			'processed' => $processed,
			'total'     => $total,
			'offset'    => $new_offset,
			'items'     => $items,
			'percent'   => $percent,
		);
	}

	/**
	 * Index a single post (used by auto-refresh hooks).
	 *
	 * @return array{status:string, message:string, code?:string}
	 */
	public function index_single( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Post not found or not published.', 'ai-site-assistant-autopilot' ),
			);
		}

		if ( ! in_array( $post->post_type, $this->get_indexable_types(), true ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Post type is not indexable.', 'ai-site-assistant-autopilot' ),
			);
		}

		$this->ensure_tables();

		try {
			$outcome = $this->index_post( $post, $this->embedding_context() );
			if ( 'indexed' === $outcome['status'] ) {
				$this->logger->info( 'kb_auto_refresh', sprintf( 'Auto-indexed post %d', $post_id ) );
			}

			return array(
				'status'  => $outcome['status'],
				'message' => $this->item_message( $outcome, html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ),
			);
		} catch ( ProviderException $e ) {
			if ( 'usage_cap_reached' === $e->error_code() ) {
				return array(
					'status'  => 'paused',
					'code'    => 'usage_cap_reached',
					'message' => $e->user_message(),
				);
			}

			$this->logger->error( 'kb_auto_refresh', sprintf( 'Auto-index failed post %d: %s', $post_id, $e->getMessage() ) );

			return array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		} catch ( \Throwable $e ) {
			$this->logger->error( 'kb_auto_refresh', sprintf( 'Auto-index failed post %d: %s', $post_id, $e->getMessage() ) );

			return array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Remove a post from the knowledge index (deleted, trashed, unpublished or protected).
	 */
	public function remove_post( int $post_id ): void {
		$removed = $this->index->tables_exist() ? $this->index->delete_post( $post_id ) : 0;
		if ( $this->kb->delete_by_post_id( $post_id ) || $removed > 0 ) {
			$this->logger->info( 'kb_remove', sprintf( 'Removed post %d from KB', $post_id ) );
		}
	}

	/**
	 * Index one post: passages, the post row for the admin screens, and (optionally) an embedding.
	 *
	 * @param array{provider:\AISAAutoPilot\Providers\ProviderInterface, model:string}|null $embed
	 * @return array{status:string, reason:string, passages?:int}
	 * @throws ProviderException When the usage cap stops an embedding request.
	 */
	private function index_post( \WP_Post $post, ?array $embed ): array {
		$post_id = (int) $post->ID;

		if ( ! KnowledgeExtractor::is_indexable( $post ) ) {
			$this->remove_post( $post_id );
			return array(
				'status' => 'skipped',
				'reason' => 'protected',
			);
		}

		$doc      = KnowledgeExtractor::extract( $post );
		$hash     = $this->compute_hash( $post, $doc );
		$existing = $this->kb->get_by_post_id( $post_id );

		if ( $existing && $existing['content_hash'] === $hash && $this->index->count_for_post( $post_id ) > 0 ) {
			// Unchanged: only add a missing embedding, so switching semantic search on needs no full re-index.
			if ( null !== $embed && ( empty( $existing['embedding'] ) || ( $existing['embedding_model'] ?? '' ) !== $embed['model'] )
				&& $this->embed( $embed, $post_id, (string) $existing['title'], (string) $existing['summary'] ) ) {
				return array(
					'status' => 'indexed',
					'reason' => 'embedded',
				);
			}

			return array(
				'status' => 'skipped',
				'reason' => 'unchanged',
			);
		}

		$chunks = KnowledgeChunker::chunk( $doc['sections'], $doc['facts'], $doc['excerpt'] );
		$stored = $this->index->replace_post( $post_id, $doc['title'], $chunks );
		$body   = trim( implode( "\n", array_column( $chunks, 'text' ) ) );

		$this->kb->upsert(
			array(
				'post_id'      => $post_id,
				'post_type'    => $post->post_type,
				'title'        => $doc['title'],
				'summary'      => $this->lead( $body ),
				'keywords'     => $this->keywords( $doc['title'] . "\n" . $body ),
				'category'     => $this->category( $post ),
				'content_type' => $this->content_type( $post, $doc ),
				'content_hash' => $hash,
				'token_count'  => (int) ceil( mb_strlen( $body ) / 4 ),
			)
		);

		if ( null !== $embed ) {
			$this->embed( $embed, $post_id, $doc['title'], $this->lead( $body ) );
		}

		return array(
			'status'   => 'indexed',
			'reason'   => 'indexed',
			'passages' => $stored,
		);
	}

	/**
	 * @param array{status:string, reason?:string, message?:string, passages?:int} $outcome
	 */
	private function item_message( array $outcome, string $title ): string {
		if ( isset( $outcome['message'] ) ) {
			return 'error' === $outcome['status']
				/* translators: 1: post title, 2: error message. */
				? sprintf( __( 'Failed to index "%1$s": %2$s', 'ai-site-assistant-autopilot' ), $title, $outcome['message'] )
				: (string) $outcome['message'];
		}
		switch ( $outcome['reason'] ?? '' ) {
			case 'protected':
				return __( 'Password-protected or private: not indexed, so the assistant never quotes it.', 'ai-site-assistant-autopilot' );
			case 'unchanged':
				return __( 'Unchanged — skipped.', 'ai-site-assistant-autopilot' );
			case 'embedded':
				return __( 'Added semantic embedding.', 'ai-site-assistant-autopilot' );
			default:
				return sprintf(
					/* translators: 1: post title, 2: number of passages. */
					_n( 'Indexed "%1$s" (%2$d passage).', 'Indexed "%1$s" (%2$d passages).', max( 1, (int) ( $outcome['passages'] ?? 1 ) ), 'ai-site-assistant-autopilot' ),
					$title,
					max( 1, (int) ( $outcome['passages'] ?? 1 ) )
				);
		}
	}

	/**
	 * The provider and model for embeddings, or null when semantic search is off or cannot run.
	 *
	 * @return array{provider:\AISAAutoPilot\Providers\ProviderInterface, model:string}|null
	 */
	private function embedding_context(): ?array {
		$manager = new ProviderManager();
		if ( ! $manager->embeddings_enabled() || ! $manager->is_configured() ) {
			return null;
		}

		return array(
			'provider' => $manager->make(),
			'model'    => $manager->embedding_model(),
		);
	}

	/**
	 * Store an embedding for one post. Best-effort, except a reached usage cap, which stops the batch.
	 *
	 * @param array{provider:\AISAAutoPilot\Providers\ProviderInterface, model:string} $embed
	 * @throws ProviderException When the usage cap is reached.
	 */
	private function embed( array $embed, int $post_id, string $title, string $lead ): bool {
		$text = trim( $title . "\n" . $lead );
		if ( '' === $text ) {
			return false;
		}
		if ( ( new UsageTracker() )->is_exhausted() ) {
			throw new ProviderException( 'usage_cap_reached', __( 'The local AI usage cap has been reached. Raise or reset the token cap in Usage & Cost, then resume this indexing pass.', 'ai-site-assistant-autopilot' ) );
		}

		try {
			$result = $embed['provider']->generate_embedding( $text );
			return $this->kb->update_embedding( $post_id, $result['embedding'], $embed['model'] );
		} catch ( ProviderException $e ) {
			if ( 'usage_cap_reached' === $e->error_code() ) {
				throw $e;
			}
			$this->logger->error( 'kb_embed', sprintf( 'Embedding failed for post %d: %s', $post_id, $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Return an actionable, resumable cap stop without advancing the cursor for the post
	 * that could not finish.
	 *
	 * @param array<int, array{post_id:int, title:string, status:string, message:string}> $items
	 * @return array{done:false, processed:int, total:int, offset:int, items:array<int, array{post_id:int, title:string, status:string, message:string}>, percent:int, paused:true, code:string, message:string}
	 */
	private function paused_result( int $offset, int $processed, int $total, array $items ): array {
		$new_offset = $offset + $processed;
		$percent    = $total > 0 ? min( 100, (int) round( ( $new_offset / $total ) * 100 ) ) : 0;

		return array(
			'done'      => false,
			'processed' => $processed,
			'total'     => $total,
			'offset'    => $new_offset,
			'items'     => $items,
			'percent'   => $percent,
			'paused'    => true,
			'code'      => 'usage_cap_reached',
			'message'   => __( 'The local AI usage cap has been reached. Raise or reset the token cap in Usage & Cost, then resume this indexing pass.', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * Fingerprint of what is indexed. The modified date is left out on purpose: saving a
	 * post without changing its text must not re-index it or empty the answer cache.
	 *
	 * @param array{title:string, sections:array<int, array{heading:string, text:string}>, facts:string, excerpt:string} $doc
	 */
	private function compute_hash( \WP_Post $post, array $doc ): string {
		$material = self::FORMAT . '|' . KnowledgeText::VERSION . '|' . $doc['title'] . '|' . $post->post_content . '|' . $doc['excerpt'] . '|' . $doc['facts'];

		return hash( 'sha256', $material );
	}

	/** The opening of the page, shown in the admin list instead of an AI summary. */
	private function lead( string $body ): string {
		$body = trim( (string) preg_replace( '/\s+/u', ' ', $body ) );
		if ( mb_strlen( $body ) <= 300 ) {
			return $body;
		}
		$cut = mb_strrpos( mb_substr( $body, 0, 300 ), ' ' );

		return mb_substr( $body, 0, false === $cut ? 300 : $cut ) . '…';
	}

	/** The page's most frequent meaningful words. */
	private function keywords( string $text ): string {
		$counts = array();
		foreach ( KnowledgeText::tokens( $text ) as $term ) {
			if ( ! ctype_digit( $term ) && mb_strlen( $term ) > 2 ) {
				$counts[ $term ] = ( $counts[ $term ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );

		return implode( ', ', array_slice( array_keys( $counts ), 0, 8 ) );
	}

	private function category( \WP_Post $post ): string {
		$taxonomy = 'product' === $post->post_type ? 'product_cat' : 'category';
		$terms    = get_the_terms( $post, $taxonomy );

		return is_array( $terms ) && array() !== $terms ? (string) $terms[0]->name : '';
	}

	/**
	 * A content type for the admin filter, from facts the post already has.
	 *
	 * @param array{title:string, sections:array<int, array{heading:string, text:string}>, facts:string, excerpt:string} $doc
	 */
	private function content_type( \WP_Post $post, array $doc ): string {
		if ( 'product' === $post->post_type ) {
			return 'product';
		}
		$questions = 0;
		foreach ( $doc['sections'] as $section ) {
			if ( preg_match( '/[?؟]\s*$/u', $section['heading'] ) ) {
				++$questions;
			}
		}
		if ( $questions >= 2 ) {
			return 'faq';
		}

		return 'page' === $post->post_type ? 'page' : 'article';
	}

	private function ensure_tables(): void {
		$this->kb->ensure_schema();
		if ( ! KnowledgeIndex::ready() ) {
			$this->index->create_tables();
		}
	}

	/**
	 * Get indexable post types (public types minus attachments).
	 *
	 * @return array<int, string>
	 */
	public function get_indexable_types(): array {
		$types = self::INDEXABLE_TYPES;

		// Also include any public CPTs the user has enabled in settings.
		$extra = \AISAAutoPilot\Core\Settings::get( 'kb_post_types', array() );
		if ( is_array( $extra ) ) {
			$types = array_merge( $types, array_filter( $extra, 'is_string' ) );
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Indexable post types with a friendly label and published count.
	 * Used to build the pre-index picker.
	 *
	 * @return array<int, array{slug:string, label:string, count:int}>
	 */
	public function get_indexable_types_with_counts(): array {
		$out = array();
		foreach ( $this->get_indexable_types() as $slug ) {
			$obj    = get_post_type_object( $slug );
			$counts = wp_count_posts( $slug );
			$out[]  = array(
				'slug'  => $slug,
				'label' => $obj && isset( $obj->labels->name ) ? $obj->labels->name : $slug,
				'count' => (int) ( $counts->publish ?? 0 ),
			);
		}

		return $out;
	}

	/**
	 * Resolve requested post types against what is actually indexable.
	 * Falls back to all indexable types when nothing valid is requested.
	 *
	 * @param array{post_types?:array<int,string>} $filters
	 * @return array<int, string>
	 */
	private function resolve_types( array $filters ): array {
		$indexable = $this->get_indexable_types();
		$requested = $filters['post_types'] ?? array();

		if ( ! is_array( $requested ) || empty( $requested ) ) {
			return $indexable;
		}

		$requested = array_map( 'sanitize_key', $requested );
		$valid     = array_values( array_intersect( $indexable, $requested ) );

		return empty( $valid ) ? $indexable : $valid;
	}

	/**
	 * Resolve requested category IDs (empty array = no category restriction).
	 *
	 * @param array{categories?:array<int,int>} $filters
	 * @return array<int, int>
	 */
	private function resolve_categories( array $filters ): array {
		$cats = $filters['categories'] ?? array();
		if ( ! is_array( $cats ) ) {
			return array();
		}

		$cats = array_values( array_filter( array_map( 'absint', $cats ) ) );

		return $cats;
	}
}
