<?php
declare(strict_types=1);

namespace SitePilot\Content;

/**
 * Knowledge Base storage layer.
 *
 * Manages the custom DB table that holds AI-summarised site content.
 * All queries use $wpdb->prepare() — no raw interpolation.
 */
final class KnowledgeBase {

	public const TABLE_SUFFIX = 'sitepilot_kb';

	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public function table(): string {
		return $this->db->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the KB table. Idempotent — uses dbDelta.
	 */
	public function create_table(): void {
		$table   = $this->table();
		$charset = $this->db->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			post_type VARCHAR(50) NOT NULL DEFAULT 'post',
			title VARCHAR(500) NOT NULL DEFAULT '',
			summary TEXT NOT NULL,
			keywords VARCHAR(1000) NOT NULL DEFAULT '',
			category VARCHAR(200) NOT NULL DEFAULT '',
			content_hash VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'indexed',
			token_count INT UNSIGNED NOT NULL DEFAULT 0,
			indexed_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY post_type (post_type),
			KEY status (status),
			KEY content_hash (content_hash)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the KB table (uninstall only).
	 */
	public function drop_table(): void {
		$table = $this->table();
		$this->db->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert or update a KB entry (upsert by post_id).
	 *
	 * @param array{post_id:int, post_type:string, title:string, summary:string, keywords:string, category:string, content_hash:string, token_count:int} $data
	 */
	public function upsert( array $data ): bool {
		$table = $this->table();
		$now   = current_time( 'mysql', true );

		$existing = $this->get_by_post_id( (int) $data['post_id'] );

		if ( $existing ) {
			$result = $this->db->update(
				$table,
				array(
					'post_type'    => sanitize_text_field( $data['post_type'] ),
					'title'        => sanitize_text_field( $data['title'] ),
					'summary'      => wp_kses_post( $data['summary'] ),
					'keywords'     => sanitize_text_field( $data['keywords'] ),
					'category'     => sanitize_text_field( $data['category'] ),
					'content_hash' => sanitize_text_field( $data['content_hash'] ),
					'token_count'  => absint( $data['token_count'] ),
					'status'       => 'indexed',
					'updated_at'   => $now,
				),
				array( 'post_id' => (int) $data['post_id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $this->db->insert(
				$table,
				array(
					'post_id'      => (int) $data['post_id'],
					'post_type'    => sanitize_text_field( $data['post_type'] ),
					'title'        => sanitize_text_field( $data['title'] ),
					'summary'      => wp_kses_post( $data['summary'] ),
					'keywords'     => sanitize_text_field( $data['keywords'] ),
					'category'     => sanitize_text_field( $data['category'] ),
					'content_hash' => sanitize_text_field( $data['content_hash'] ),
					'token_count'  => absint( $data['token_count'] ),
					'status'       => 'indexed',
					'indexed_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_by_post_id( int $post_id ): ?array {
		$table = $this->table();
		$row   = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete a KB entry by post_id.
	 */
	public function delete_by_post_id( int $post_id ): bool {
		$table  = $this->table();
		$result = $this->db->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get all KB entries with pagination.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function get_all( int $page = 1, int $per_page = 20, string $search = '', string $post_type = '' ): array {
		$table  = $this->table();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		$where  = '1=1';
		$params = array();

		if ( '' !== $search ) {
			$like     = '%' . $this->db->esc_like( $search ) . '%';
			$where   .= ' AND (title LIKE %s OR summary LIKE %s OR keywords LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $post_type ) {
			$where   .= ' AND post_type = %s';
			$params[] = $post_type;
		}

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $params ) ) {
			$count_sql = $this->db->prepare( $count_sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $this->db->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Fetch page.
		$query_sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params[]  = $per_page;
		$params[]  = $offset;
		$items     = $this->db->get_results(
			$this->db->prepare( $query_sql, ...$params ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get distinct post types in the KB.
	 *
	 * @return array<int, string>
	 */
	public function get_post_types(): array {
		$table = $this->table();
		$types = $this->db->get_col( "SELECT DISTINCT post_type FROM {$table} ORDER BY post_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $types ) ? $types : array();
	}

	/**
	 * Get KB statistics.
	 *
	 * @return array{total:int, indexed:int, pending:int, post_types:array<string,int>, last_indexed:string|null}
	 */
	public function get_stats(): array {
		$table = $this->table();

		$total   = (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$indexed = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'indexed' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pending = (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$last    = $this->db->get_var( "SELECT MAX(updated_at) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$type_rows = $this->db->get_results(
			"SELECT post_type, COUNT(*) as cnt FROM {$table} GROUP BY post_type ORDER BY cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$post_types = array();
		if ( is_array( $type_rows ) ) {
			foreach ( $type_rows as $row ) {
				$post_types[ $row['post_type'] ] = (int) $row['cnt'];
			}
		}

		return array(
			'total'        => $total,
			'indexed'      => $indexed,
			'pending'      => $pending,
			'post_types'   => $post_types,
			'last_indexed' => is_string( $last ) ? $last : null,
		);
	}

	/**
	 * Search the KB for content relevant to a query.
	 * Returns the top N most relevant entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $query, int $limit = 5 ): array {
		$table = $this->table();
		$like  = '%' . $this->db->esc_like( $query ) . '%';

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT *, 
					(CASE WHEN title LIKE %s THEN 3 ELSE 0 END) +
					(CASE WHEN keywords LIKE %s THEN 2 ELSE 0 END) +
					(CASE WHEN summary LIKE %s THEN 1 ELSE 0 END) AS relevance
				FROM {$table}
				WHERE (title LIKE %s OR keywords LIKE %s OR summary LIKE %s) AND status = 'indexed'
				ORDER BY relevance DESC, updated_at DESC
				LIMIT %d",
				$like, $like, $like, $like, $like, $like, $limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get all indexed summaries as a compact context string for AI prompts.
	 */
	public function get_context_for_ai( int $max_entries = 50, int $max_chars = 12000 ): string {
		$table   = $this->table();
		$entries = $this->db->get_results(
			$this->db->prepare(
				"SELECT title, post_type, summary, keywords, category FROM {$table} WHERE status = 'indexed' ORDER BY updated_at DESC LIMIT %d",
				$max_entries
			),
			ARRAY_A
		);

		if ( ! is_array( $entries ) || empty( $entries ) ) {
			return '';
		}

		$context = '';
		foreach ( $entries as $entry ) {
			$line = sprintf(
				"[%s] %s: %s (keywords: %s)",
				$entry['post_type'],
				$entry['title'],
				$entry['summary'],
				$entry['keywords']
			);
			if ( strlen( $context ) + strlen( $line ) > $max_chars ) {
				break;
			}
			$context .= $line . "\n";
		}

		return trim( $context );
	}

	/**
	 * Truncate all KB entries (full re-index).
	 */
	public function truncate(): void {
		$table = $this->table();
		$this->db->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check if the KB table exists.
	 */
	public function table_exists(): bool {
		$table = $this->table();
		$exists = $this->db->get_var(
			$this->db->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return $exists === $table;
	}
}
