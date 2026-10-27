<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

/**
 * Storage for the passage index: passages of each post, and an inverted index of their
 * terms. The post-level row (title, short summary) stays in KnowledgeBase for the admin
 * screens; this is what search reads.
 */
final class KnowledgeIndex {

	public const CHUNKS_TABLE = 'aisaautopilot_kb_chunks';
	public const TERMS_TABLE  = 'aisaautopilot_kb_terms';

	/** Which table layout and text format the stored index was built with (autoloaded, read per question). */
	public const FORMAT_OPTION = 'aisaautopilot_kb_index_format';

	/** Cursor of a background rebuild in progress. */
	public const REBUILD_OPTION = 'aisaautopilot_kb_rebuild';

	/** Table layout version; bump with a schema change. */
	private const SCHEMA_VERSION = 1;

	/** Title words weigh more than body words: a page titled "Shipping" is about shipping. */
	private const TITLE_WEIGHT   = 3;
	private const HEADING_WEIGHT = 2;

	private \wpdb $db;

	/** @var array{chunks:int, avg_len:float}|null */
	private static ?array $stats = null;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public function chunks_table(): string {
		return $this->db->prefix . self::CHUNKS_TABLE;
	}

	public function terms_table(): string {
		return $this->db->prefix . self::TERMS_TABLE;
	}

	public function create_tables(): void {
		$charset = $this->db->get_charset_collate();
		$chunks  = $this->chunks_table();
		$terms   = $this->terms_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$chunks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			ordinal SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			heading VARCHAR(255) NOT NULL DEFAULT '',
			content TEXT NOT NULL,
			token_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY post_ordinal (post_id,ordinal)
		) {$charset};"
		);
		// Terms are compared byte for byte: a case- or accent-insensitive collation would
		// merge words the normaliser keeps apart and break the primary key.
		dbDelta(
			"CREATE TABLE {$terms} (
			term VARBINARY(160) NOT NULL,
			chunk_id BIGINT UNSIGNED NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			tf SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY  (term,chunk_id),
			KEY chunk_id (chunk_id),
			KEY post_id (post_id)
		) {$charset};"
		);
		update_option( self::FORMAT_OPTION, self::format(), true );
	}

	/**
	 * The format string a current index carries: table layout, text rules and chunking.
	 */
	public static function format(): string {
		return self::SCHEMA_VERSION . '|' . KnowledgeText::VERSION . '|' . ContentIndexer::FORMAT;
	}

	/** Whether the index tables exist in the current layout. One autoloaded option read, no query. */
	public static function ready(): bool {
		return self::format() === (string) get_option( self::FORMAT_OPTION, '' );
	}

	public function tables_exist(): bool {
		$table = $this->chunks_table();
		return $table === $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	public function drop_tables(): void {
		$this->db->query( "DROP TABLE IF EXISTS {$this->terms_table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query( "DROP TABLE IF EXISTS {$this->chunks_table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function truncate(): void {
		$this->db->query( "TRUNCATE TABLE {$this->terms_table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query( "TRUNCATE TABLE {$this->chunks_table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::$stats = null;
	}

	/**
	 * Replace every passage of a post. Returns how many passages were stored.
	 *
	 * @param array<int, array{heading:string, text:string}> $chunks
	 */
	public function replace_post( int $post_id, string $title, array $chunks ): int {
		$this->delete_post( $post_id );
		if ( array() === $chunks ) {
			// A page with no body text can still be found by its title.
			$chunks = array(
				array(
					'heading' => '',
					'text'    => $title,
				),
			);
		}

		$title_terms = KnowledgeText::frequencies( $title, self::TITLE_WEIGHT );
		$stored      = 0;
		foreach ( array_values( $chunks ) as $ordinal => $chunk ) {
			$text  = (string) $chunk['text'];
			$terms = KnowledgeText::frequencies( $text );
			$len   = array_sum( $terms );
			foreach ( KnowledgeText::frequencies( (string) $chunk['heading'], self::HEADING_WEIGHT ) as $term => $count ) {
				$terms[ $term ] = ( $terms[ $term ] ?? 0 ) + $count;
			}
			foreach ( $title_terms as $term => $count ) {
				$terms[ $term ] = ( $terms[ $term ] ?? 0 ) + $count;
			}

			$ok = $this->db->insert(
				$this->chunks_table(),
				array(
					'post_id'     => $post_id,
					'ordinal'     => min( 65535, $ordinal ),
					'heading'     => mb_substr( (string) $chunk['heading'], 0, 250 ),
					'content'     => $text,
					'token_count' => min( 65535, max( 1, $len ) ),
				),
				array( '%d', '%d', '%s', '%s', '%d' )
			);
			if ( false === $ok ) {
				continue;
			}
			$chunk_id = (int) $this->db->insert_id;
			++$stored;
			$this->insert_terms( $chunk_id, $post_id, $terms );
		}
		self::$stats = null;

		return $stored;
	}

	/**
	 * @param array<string, int> $terms
	 */
	private function insert_terms( int $chunk_id, int $post_id, array $terms ): void {
		$rows = array();
		$args = array();
		foreach ( $terms as $term => $tf ) {
			$rows[] = '(%s,%d,%d,%d)';
			array_push( $args, (string) $term, $chunk_id, $post_id, min( 65535, (int) $tf ) );
			if ( count( $rows ) >= 200 ) {
				$this->flush_terms( $rows, $args );
				$rows = array();
				$args = array();
			}
		}
		if ( array() !== $rows ) {
			$this->flush_terms( $rows, $args );
		}
	}

	/**
	 * @param array<int, string> $rows
	 * @param array<int, mixed>  $args
	 */
	private function flush_terms( array $rows, array $args ): void {
		$sql = "INSERT INTO {$this->terms_table()} (term, chunk_id, post_id, tf) VALUES " . implode( ',', $rows ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query( $this->db->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function delete_post( int $post_id ): int {
		$this->db->delete( $this->terms_table(), array( 'post_id' => $post_id ), array( '%d' ) );
		$removed     = (int) $this->db->delete( $this->chunks_table(), array( 'post_id' => $post_id ), array( '%d' ) );
		self::$stats = null;

		return $removed;
	}

	public function count_for_post( int $post_id ): int {
		return (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->chunks_table()} WHERE post_id = %d", $post_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Passage count and average passage length, for BM25.
	 *
	 * @return array{chunks:int, avg_len:float}
	 */
	public function stats(): array {
		if ( null === self::$stats ) {
			$row         = $this->db->get_row( "SELECT COUNT(*) AS n, AVG(token_count) AS avg_len FROM {$this->chunks_table()}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::$stats = array(
				'chunks'  => (int) ( $row['n'] ?? 0 ),
				'avg_len' => max( 1.0, (float) ( $row['avg_len'] ?? 1 ) ),
			);
		}

		return self::$stats;
	}

	/**
	 * How many passages contain each term.
	 *
	 * @param array<int, string> $terms
	 * @return array<string, int>
	 */
	public function document_frequencies( array $terms ): array {
		if ( array() === $terms ) {
			return array();
		}
		$place = implode( ',', array_fill( 0, count( $terms ), '%s' ) );
		$rows  = $this->db->get_results(
			$this->db->prepare( "SELECT term, COUNT(*) AS df FROM {$this->terms_table()} WHERE term IN ({$place}) GROUP BY term", ...$terms ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['term'] ] = (int) $row['df'];
		}

		return $out;
	}

	/**
	 * Every (passage, term) match for the given terms, with the passage length.
	 *
	 * @param array<int, string> $terms
	 * @return array<int, array{term:string, chunk_id:int, post_id:int, tf:int, len:int}>
	 */
	public function postings( array $terms ): array {
		if ( array() === $terms ) {
			return array();
		}
		$place = implode( ',', array_fill( 0, count( $terms ), '%s' ) );
		$rows  = $this->db->get_results(
			$this->db->prepare(
				"SELECT t.term, t.chunk_id, t.post_id, t.tf, c.token_count AS len FROM {$this->terms_table()} t INNER JOIN {$this->chunks_table()} c ON c.id = t.chunk_id WHERE t.term IN ({$place})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$terms
			), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'term'     => (string) $row['term'],
				'chunk_id' => (int) $row['chunk_id'],
				'post_id'  => (int) $row['post_id'],
				'tf'       => (int) $row['tf'],
				'len'      => (int) $row['len'],
			);
		}

		return $out;
	}

	/**
	 * @param array<int, int> $ids
	 * @return array<int, array{id:int, post_id:int, ordinal:int, heading:string, content:string}>
	 */
	public function chunks_by_id( array $ids ): array {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return array();
		}
		$place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows  = $this->db->get_results(
			$this->db->prepare( "SELECT id, post_id, ordinal, heading, content FROM {$this->chunks_table()} WHERE id IN ({$place})", ...$ids ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = array(
				'id'      => (int) $row['id'],
				'post_id' => (int) $row['post_id'],
				'ordinal' => (int) $row['ordinal'],
				'heading' => (string) $row['heading'],
				'content' => (string) $row['content'],
			);
		}

		return $out;
	}

	/**
	 * The first passage of each post, for posts found by meaning (embeddings) rather than words.
	 *
	 * @param array<int, int> $post_ids
	 * @return array<int, int> post_id => chunk_id
	 */
	public function first_chunk_ids( array $post_ids ): array {
		$post_ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
		if ( array() === $post_ids ) {
			return array();
		}
		$place = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$rows  = $this->db->get_results(
			$this->db->prepare( "SELECT post_id, MIN(id) AS id FROM {$this->chunks_table()} WHERE post_id IN ({$place}) GROUP BY post_id", ...$post_ids ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['post_id'] ] = (int) $row['id'];
		}

		return $out;
	}
}
