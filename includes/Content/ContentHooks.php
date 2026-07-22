<?php
declare(strict_types=1);

namespace SitePilot\Content;

use SitePilot\Logging\Logger;

/**
 * WordPress hooks that keep the Knowledge Base in sync with site content.
 *
 * Fires on:
 * - transition_post_status  (publish / unpublish)
 * - save_post               (edit to an already-published post)
 * - wp_trash_post           (move to trash)
 * - deleted_post            (permanent delete)
 *
 * All handlers are lightweight: they schedule a single-post re-index via
 * wp_schedule_single_event() so the save request is never blocked by an
 * AI API call.
 */
final class ContentHooks {

	private const CRON_ACTION = 'sitepilot_ai_index_post';

	private Logger $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * Register all WordPress hooks.
	 */
	public function register(): void {
		// Auto-index when a post is published or updated.
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 20, 3 );

		// Remove from KB when trashed or deleted.
		add_action( 'wp_trash_post', array( $this, 'on_trash' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'on_delete' ), 10, 1 );

		// Cron handler for deferred single-post indexing.
		add_action( self::CRON_ACTION, array( $this, 'run_deferred_index' ), 10, 1 );
	}

	/**
	 * Handle post status transitions.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only react to publish transitions.
		if ( 'publish' !== $new_status ) {
			// If unpublished, remove from KB.
			if ( 'publish' === $old_status ) {
				$this->remove_from_kb( (int) $post->ID );
			}
			return;
		}

		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) ) {
			return;
		}

		// Schedule deferred indexing (non-blocking).
		$this->schedule_index( (int) $post->ID );
	}

	/**
	 * Handle post trashing.
	 *
	 * @param int $post_id
	 */
	public function on_trash( int $post_id ): void {
		$this->remove_from_kb( $post_id );
	}

	/**
	 * Handle permanent post deletion.
	 *
	 * @param int $post_id
	 */
	public function on_delete( int $post_id ): void {
		$this->remove_from_kb( $post_id );
	}

	/**
	 * Cron callback: index a single post.
	 *
	 * @param int $post_id
	 */
	public function run_deferred_index( int $post_id ): void {
		$indexer = new ContentIndexer();
		$result  = $indexer->index_single( $post_id );

		$this->logger->info(
			'kb_cron',
			sprintf( 'Deferred index post %d: %s — %s', $post_id, $result['status'], $result['message'] )
		);
	}

	/**
	 * Schedule a single-post index via WP-Cron (fires in ~5 seconds).
	 */
	private function schedule_index( int $post_id ): void {
		// Avoid duplicate schedules.
		if ( false !== wp_next_scheduled( self::CRON_ACTION, array( $post_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 5, self::CRON_ACTION, array( $post_id ) );
	}

	/**
	 * Remove a post from the Knowledge Base.
	 */
	private function remove_from_kb( int $post_id ): void {
		$indexer = new ContentIndexer();
		$indexer->remove_post( $post_id );
	}
}
