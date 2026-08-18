<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Batch content indexer. Reads WordPress posts/pages/CPTs in small batches,
 * summarises each via AI, and stores the result in the Knowledge Base table.
 *
 * Designed for AJAX-driven progress: the admin UI calls /kb/index/batch
 * repeatedly, each time processing BATCH_SIZE posts, until all are done.
 */
final class ContentIndexer {

	/** Posts processed per AJAX batch (keeps each request < 30 s). */
	public const BATCH_SIZE = 3;

	/** Post types eligible for indexing. */
	private const INDEXABLE_TYPES = array( 'post', 'page' );

	private KnowledgeBase $kb;
	private Logger $logger;

	public function __construct( ?KnowledgeBase $kb = null, ?Logger $logger = null ) {
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
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
	 * Get the number of posts already indexed (by content_hash match).
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
	 *   percent: int
	 * }
	 */
	public function process_batch( int $offset = 0, array $filters = array() ): array {
		$this->kb->ensure_schema();

		$total     = $this->get_total_posts( $filters );
		$types     = $this->resolve_types( $filters );
		$cats      = $this->resolve_categories( $filters );
		$items     = array();
		$processed = 0;

		// Build a unified query across the selected types (and categories, if any).
		$query_args = array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => self::BATCH_SIZE,
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

		$provider_manager = new ProviderManager();
		if ( ! $provider_manager->is_configured() ) {
			return array(
				'done'      => true,
				'processed' => 0,
				'total'     => $total,
				'offset'    => $offset,
				'items'     => array(),
				'percent'   => 0,
				'error'     => __( 'AI provider is not configured. Please add your API key in Settings.', 'ai-site-assistant-autopilot' ),
			);
		}

		$provider      = $provider_manager->make();
		$summarizer    = new ContentSummarizer( $provider );
		$embeddings_on = $provider_manager->embeddings_enabled();
		$embed_model   = $provider_manager->embedding_model();

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$title   = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );

			try {
				// Check if content changed since last index.
				$content_hash = $this->compute_hash( $post );
				$existing     = $this->kb->get_by_post_id( $post_id );

				if ( $existing && $existing['content_hash'] === $content_hash ) {
					// Content is unchanged — but backfill a missing/stale embedding
					// so enabling semantic search doesn't require a full re-index.
					$needs_embed = $embeddings_on
						&& ( empty( $existing['embedding'] ) || ( $existing['embedding_model'] ?? '' ) !== $embed_model );

					if ( $needs_embed && $this->embed_entry( $provider, $embed_model, $post_id, (string) $existing['title'], (string) $existing['summary'], (string) $existing['keywords'] ) ) {
						$items[] = array(
							'post_id' => $post_id,
							'title'   => $title,
							'status'  => 'indexed',
							'message' => __( 'Added semantic embedding.', 'ai-site-assistant-autopilot' ),
						);
					} else {
						$items[] = array(
							'post_id' => $post_id,
							'title'   => $title,
							'status'  => 'skipped',
							'message' => __( 'Unchanged — skipped.', 'ai-site-assistant-autopilot' ),
						);
					}
					++$processed;
					continue;
				}

				// Summarise via AI.
				$summary = $summarizer->summarize( $post );

				// Store in KB.
				$this->kb->upsert(
					array(
						'post_id'      => $post_id,
						'post_type'    => $post->post_type,
						'title'        => $summary['title'],
						'summary'      => $summary['summary'],
						'keywords'     => $summary['keywords'],
						'category'     => $summary['category'],
						'content_type' => $summary['content_type'] ?? '',
						'content_hash' => $content_hash,
						'token_count'  => $summary['token_count'],
					)
				);

				// Generate the semantic embedding (best-effort; never blocks indexing).
				if ( $embeddings_on ) {
					$this->embed_entry( $provider, $embed_model, $post_id, $summary['title'], $summary['summary'], $summary['keywords'] );
				}

				$items[] = array(
					'post_id' => $post_id,
					'title'   => $title,
					'status'  => 'indexed',
					'message' => sprintf(
						/* translators: %s: post title. */
						__( 'Indexed "%s" successfully.', 'ai-site-assistant-autopilot' ),
						$title
					),
				);

				$this->logger->info( 'kb_index', sprintf( 'Indexed post %d: %s', $post_id, $title ) );

			} catch ( ProviderException $e ) {
				$items[] = array(
					'post_id' => $post_id,
					'title'   => $title,
					'status'  => 'error',
					'message' => sprintf(
						/* translators: 1: post title, 2: error message. */
						__( 'Failed to index "%1$s": %2$s', 'ai-site-assistant-autopilot' ),
						$title,
						$e->getMessage()
					),
				);

				$this->logger->error( 'kb_index', sprintf( 'Failed post %d: %s', $post_id, $e->getMessage() ) );

			} catch ( \Throwable $e ) {
				$items[] = array(
					'post_id' => $post_id,
					'title'   => $title,
					'status'  => 'error',
					'message' => __( 'Unexpected error during indexing.', 'ai-site-assistant-autopilot' ),
				);

				$this->logger->error( 'kb_index', sprintf( 'Unexpected error post %d: %s', $post_id, $e->getMessage() ) );
			}

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
	 * @return array{status:string, message:string}
	 */
	public function index_single( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
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

		$provider_manager = new ProviderManager();
		if ( ! $provider_manager->is_configured() ) {
			return array(
				'status'  => 'error',
				'message' => __( 'AI provider not configured.', 'ai-site-assistant-autopilot' ),
			);
		}

		try {
			$content_hash = $this->compute_hash( $post );
			$existing     = $this->kb->get_by_post_id( $post_id );

			if ( $existing && $existing['content_hash'] === $content_hash ) {
				return array(
					'status'  => 'skipped',
					'message' => __( 'Content unchanged.', 'ai-site-assistant-autopilot' ),
				);
			}

			$provider   = $provider_manager->make();
			$summarizer = new ContentSummarizer( $provider );
			$summary    = $summarizer->summarize( $post );

			$this->kb->upsert(
				array(
					'post_id'      => $post_id,
					'post_type'    => $post->post_type,
					'title'        => $summary['title'],
					'summary'      => $summary['summary'],
					'keywords'     => $summary['keywords'],
					'category'     => $summary['category'],
					'content_type' => $summary['content_type'] ?? '',
					'content_hash' => $content_hash,
					'token_count'  => $summary['token_count'],
				)
			);

			// Refresh the semantic embedding too (best-effort).
			if ( $provider_manager->embeddings_enabled() ) {
				$this->embed_entry( $provider, $provider_manager->embedding_model(), $post_id, $summary['title'], $summary['summary'], $summary['keywords'] );
			}

			$this->logger->info( 'kb_auto_refresh', sprintf( 'Auto-indexed post %d', $post_id ) );

			return array(
				'status'  => 'indexed',
				'message' => __( 'Content indexed successfully.', 'ai-site-assistant-autopilot' ),
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
	 * Remove a post from the KB (used when post is deleted/trashed).
	 */
	public function remove_post( int $post_id ): void {
		$this->kb->delete_by_post_id( $post_id );
		$this->logger->info( 'kb_remove', sprintf( 'Removed post %d from KB', $post_id ) );
	}

	/**
	 * Generate and store a semantic embedding for one entry.
	 * Best-effort: failures are logged and never abort indexing.
	 */
	private function embed_entry( \AISAAutoPilot\Providers\ProviderInterface $provider, string $model, int $post_id, string $title, string $summary, string $keywords ): bool {
		$text = trim( $title . "\n" . $summary . "\n" . $keywords );
		if ( '' === $text ) {
			return false;
		}

		try {
			$result = $provider->generate_embedding( $text );
			return $this->kb->update_embedding( $post_id, $result['embedding'], $model );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'kb_embed', sprintf( 'Embedding failed for post %d: %s', $post_id, $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Compute a SHA-256 hash of the post content for change detection.
	 */
	private function compute_hash( \WP_Post $post ): string {
		$material = $post->post_title . '|' . $post->post_content . '|' . $post->post_modified;

		return hash( 'sha256', $material );
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
