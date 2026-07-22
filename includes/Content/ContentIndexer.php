<?php
declare(strict_types=1);

namespace SitePilot\Content;

use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderException;
use SitePilot\Providers\ProviderManager;

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
	 * Get the total number of indexable posts.
	 */
	public function get_total_posts(): int {
		$types = $this->get_indexable_types();
		$count = 0;
		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			$count += (int) ( $counts->publish ?? 0 );
		}

		return $count;
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
	 * @param int $offset Starting offset in the post list.
	 * @return array{
	 *   done: bool,
	 *   processed: int,
	 *   total: int,
	 *   offset: int,
	 *   items: array<int, array{post_id:int, title:string, status:string, message:string}>,
	 *   percent: int
	 * }
	 */
	public function process_batch( int $offset = 0 ): array {
		$total   = $this->get_total_posts();
		$types   = $this->get_indexable_types();
		$items   = array();
		$processed = 0;

		// Build a unified query across all indexable types.
		$posts = get_posts( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => self::BATCH_SIZE,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );

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
				'error'     => __( 'AI provider is not configured. Please add your API key in Settings.', 'sitepilot-ai' ),
			);
		}

		$provider   = $provider_manager->make();
		$summarizer = new ContentSummarizer( $provider );

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$title   = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );

			try {
				// Check if content changed since last index.
				$content_hash = $this->compute_hash( $post );
				$existing     = $this->kb->get_by_post_id( $post_id );

				if ( $existing && $existing['content_hash'] === $content_hash ) {
					$items[] = array(
						'post_id' => $post_id,
						'title'   => $title,
						'status'  => 'skipped',
						'message' => __( 'Unchanged — skipped.', 'sitepilot-ai' ),
					);
					$processed++;
					continue;
				}

				// Summarise via AI.
				$summary = $summarizer->summarize( $post );

				// Store in KB.
				$this->kb->upsert( array(
					'post_id'      => $post_id,
					'post_type'    => $post->post_type,
					'title'        => $summary['title'],
					'summary'      => $summary['summary'],
					'keywords'     => $summary['keywords'],
					'category'     => $summary['category'],
					'content_hash' => $content_hash,
					'token_count'  => $summary['token_count'],
				) );

				$items[] = array(
					'post_id' => $post_id,
					'title'   => $title,
					'status'  => 'indexed',
					'message' => sprintf(
						/* translators: %s: post title. */
						__( 'Indexed "%s" successfully.', 'sitepilot-ai' ),
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
						__( 'Failed to index "%1$s": %2$s', 'sitepilot-ai' ),
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
					'message' => __( 'Unexpected error during indexing.', 'sitepilot-ai' ),
				);

				$this->logger->error( 'kb_index', sprintf( 'Unexpected error post %d: %s', $post_id, $e->getMessage() ) );
			}

			$processed++;
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
				'message' => __( 'Post not found or not published.', 'sitepilot-ai' ),
			);
		}

		if ( ! in_array( $post->post_type, $this->get_indexable_types(), true ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Post type is not indexable.', 'sitepilot-ai' ),
			);
		}

		$provider_manager = new ProviderManager();
		if ( ! $provider_manager->is_configured() ) {
			return array(
				'status'  => 'error',
				'message' => __( 'AI provider not configured.', 'sitepilot-ai' ),
			);
		}

		try {
			$content_hash = $this->compute_hash( $post );
			$existing     = $this->kb->get_by_post_id( $post_id );

			if ( $existing && $existing['content_hash'] === $content_hash ) {
				return array(
					'status'  => 'skipped',
					'message' => __( 'Content unchanged.', 'sitepilot-ai' ),
				);
			}

			$provider   = $provider_manager->make();
			$summarizer = new ContentSummarizer( $provider );
			$summary    = $summarizer->summarize( $post );

			$this->kb->upsert( array(
				'post_id'      => $post_id,
				'post_type'    => $post->post_type,
				'title'        => $summary['title'],
				'summary'      => $summary['summary'],
				'keywords'     => $summary['keywords'],
				'category'     => $summary['category'],
				'content_hash' => $content_hash,
				'token_count'  => $summary['token_count'],
			) );

			$this->logger->info( 'kb_auto_refresh', sprintf( 'Auto-indexed post %d', $post_id ) );

			return array(
				'status'  => 'indexed',
				'message' => __( 'Content indexed successfully.', 'sitepilot-ai' ),
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
	private function get_indexable_types(): array {
		$types = self::INDEXABLE_TYPES;

		// Also include any public CPTs the user has enabled in settings.
		$extra = \SitePilot\Core\Settings::get( 'kb_post_types', array() );
		if ( is_array( $extra ) ) {
			$types = array_merge( $types, array_filter( $extra, 'is_string' ) );
		}

		return array_unique( $types );
	}
}
