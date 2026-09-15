<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

/**
 * Read-only list of post/page drafts that Content Studio actually created.
 *
 * The marker is shared with the existing safe executor. Restricting the query
 * to posts and pages prevents other AutoPilot products, such as WooCommerce,
 * from appearing as content drafts.
 */
final class ContentStudioReadModel {

	private const GENERATED_META_KEY = '_aisaautopilot_generated';

	/**
	 * @return array<int,array{id:int,title:string,kind:string,when:string,state:string,tone:string,post_status:string,modified_at:int,edit_url:string}>
	 */
	public function recent_drafts( int $limit = 5 ): array {
		$limit = min( 20, max( 1, $limit ) );
		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'draft', 'pending', 'future', 'private', 'publish' ),
				'posts_per_page'         => $limit,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded list over the plugin-owned generated marker.
					array(
						'key'   => self::GENERATED_META_KEY,
						'value' => '1',
					),
				),
			)
		);

		$rows = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$stamp  = (int) get_post_modified_time( 'U', true, $post );
			$state  = $this->state( (string) $post->post_status );
			$title  = get_the_title( $post );
			$rows[] = array(
				'id'          => (int) $post->ID,
				'title'       => '' !== $title ? $title : __( 'Untitled draft', 'ai-site-assistant-autopilot' ),
				'kind'        => $this->kind( (string) $post->post_type ),
				'when'        => $this->when( $stamp ),
				'state'       => $state['label'],
				'tone'        => $state['tone'],
				'post_status' => (string) $post->post_status,
				'modified_at' => $stamp,
				'edit_url'    => (string) get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return $rows;
	}

	/**
	 * Durable, content-only history for the Content Studio surface.
	 *
	 * Unlike the recent-drafts list, this is ordered by when WordPress first
	 * created the saved output. A later editorial update must not make a draft
	 * look newly generated. Template attribution and per-draft usage were not
	 * recorded by the executor, so those fields deliberately remain explicit
	 * unknown values instead of inferred data.
	 *
	 * @return array<int,array{id:int,title:string,kind:string,when:string,created_at:int,created_at_iso:string,state:string,tone:string,post_status:string,edit_url:string,template:string,usage:string}>
	 */
	public function history( int $limit = 50 ): array {
		$limit = min( 100, max( 1, $limit ) );
		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'draft', 'pending', 'future', 'private', 'publish' ),
				'posts_per_page'         => $limit,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded list over the plugin-owned generated marker.
					array(
						'key'   => self::GENERATED_META_KEY,
						'value' => '1',
					),
				),
			)
		);

		$rows = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$created_at = $this->created_at( $post );
			$state      = $this->state( (string) $post->post_status );
			$title      = get_the_title( $post );
			$rows[]     = array(
				'id'             => (int) $post->ID,
				'title'          => '' !== $title ? $title : __( 'Untitled draft', 'ai-site-assistant-autopilot' ),
				'kind'           => $this->kind( (string) $post->post_type ),
				'when'           => $this->history_when( $created_at ),
				'created_at'     => $created_at,
				'created_at_iso' => $created_at > 0 ? gmdate( 'c', $created_at ) : '',
				'state'          => $state['label'],
				'tone'           => $state['tone'],
				'post_status'    => (string) $post->post_status,
				'edit_url'       => (string) get_edit_post_link( $post->ID, 'raw' ),
				'template'       => __( 'Template not recorded', 'ai-site-assistant-autopilot' ),
				'usage'          => __( 'Usage not recorded', 'ai-site-assistant-autopilot' ),
			);
		}

		return $rows;
	}

	private function kind( string $post_type ): string {
		$type = get_post_type_object( $post_type );
		if ( $type instanceof \WP_Post_Type && isset( $type->labels->singular_name ) ) {
			return (string) $type->labels->singular_name;
		}

		return ucfirst( $post_type );
	}

	/** @return array{label:string,tone:string} */
	private function state( string $post_status ): array {
		switch ( $post_status ) {
			case 'publish':
				return array(
					'label' => __( 'Published by you', 'ai-site-assistant-autopilot' ),
					'tone'  => 'ok',
				);
			case 'pending':
				return array(
					'label' => __( 'Waiting for you', 'ai-site-assistant-autopilot' ),
					'tone'  => 'warn',
				);
			case 'future':
				return array(
					'label' => __( 'Scheduled by you', 'ai-site-assistant-autopilot' ),
					'tone'  => 'info',
				);
			case 'private':
				return array(
					'label' => __( 'Private by you', 'ai-site-assistant-autopilot' ),
					'tone'  => 'off',
				);
			case 'draft':
			default:
				return array(
					'label' => __( 'Draft saved', 'ai-site-assistant-autopilot' ),
					'tone'  => 'warn',
				);
		}
	}

	private function when( int $stamp ): string {
		if ( $stamp <= 0 ) {
			return '—';
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. 2 minutes. */
			__( '%s ago', 'ai-site-assistant-autopilot' ),
			human_time_diff( $stamp, time() )
		);
	}

	private function history_when( int $stamp ): string {
		if ( $stamp <= 0 ) {
			return '—';
		}

		$time_format = (string) get_option( 'time_format' );
		if ( wp_date( 'Y-m-d', $stamp ) === wp_date( 'Y-m-d' ) ) {
			return sprintf(
				/* translators: %s: localized time. */
				__( 'Today %s', 'ai-site-assistant-autopilot' ),
				wp_date( $time_format, $stamp )
			);
		}

		return wp_date( (string) get_option( 'date_format' ) . ' ' . $time_format, $stamp );
	}

	private function created_at( \WP_Post $post ): int {
		$stamp = (int) get_post_time( 'U', true, $post );
		if ( $stamp > 0 ) {
			return $stamp;
		}

		// Fresh WordPress drafts may keep post_date_gmt at the zero date even
		// though their local creation date is durable and safe to display.
		return (int) get_post_time( 'U', false, $post );
	}
}
