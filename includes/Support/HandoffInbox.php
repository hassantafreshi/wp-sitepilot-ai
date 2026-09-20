<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

/**
 * Private, administrator-only inbox for visitor-initiated human handoffs.
 * We do not retain ordinary widget chats: clicking the handoff button is the
 * visitor's explicit request to pass the supplied messages to a human.
 *
 * Archiving is separate from the status: an archived thread keeps its status,
 * leaves the inbox list and no longer counts as waiting, and can be moved back.
 */
final class HandoffInbox {

	public const TABLE           = 'aisaautopilot_handoffs';
	public const STATUSES        = array( 'new', 'in_progress', 'resolved', 'failed' );
	private const SCHEMA_OPTION  = 'aisaautopilot_handoff_inbox_schema';
	private const SCHEMA_VERSION = '2';
	private const COLUMNS        = 'id, status, visitor_name, visitor_email, visitor_phone, visitor_website, messages, created_at, updated_at, archived_at';
	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public function table(): string {
		return $this->db->prefix . self::TABLE;
	}

	public static function ensure_schema(): void {
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' ) ) {
			return;
		}
		( new self() )->create_table();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/** Whether the table exists at the current schema version, so reads never query a missing column. */
	public function is_ready(): bool {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' );
	}

	public function create_table(): void {
		$table = $this->table();
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			visitor_name VARCHAR(120) NOT NULL DEFAULT '',
			visitor_email VARCHAR(190) NOT NULL DEFAULT '',
			visitor_phone VARCHAR(40) NOT NULL DEFAULT '',
			visitor_website VARCHAR(255) NOT NULL DEFAULT '',
			messages LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY updated_at (updated_at),
			KEY archived_at (archived_at)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array<int,array{role:string,content:string}> $messages
	 * @param string                                        $phone   Already validated by the caller.
	 * @param string                                        $website Already validated by the caller (http or https).
	 */
	public function record( array $messages, string $name, string $email, string $status, string $phone = '', string $website = '' ): int {
		$now = current_time( 'mysql', true );
		$this->db->insert(
			$this->table(),
			array(
				'status'          => $this->valid_status( $status ),
				'visitor_name'    => mb_substr( sanitize_text_field( $name ), 0, 120 ),
				'visitor_email'   => sanitize_email( $email ),
				'visitor_phone'   => mb_substr( sanitize_text_field( $phone ), 0, 40 ),
				'visitor_website' => mb_substr( esc_url_raw( $website, array( 'http', 'https' ) ), 0, 255 ),
				'messages'        => wp_json_encode( $messages, JSON_UNESCAPED_UNICODE ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->db->insert_id;
	}

	/**
	 * Threads in the inbox (not archived), or only archived threads, most recent activity first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list( int $limit = 50, int $offset = 0, bool $archived = false ): array {
		if ( ! $this->is_ready() ) {
			return array();
		}
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );
		$where  = $archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL';
		$rows   = $this->db->get_results(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table, column list and archive condition.
			ARRAY_A
		);
		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	/**
	 * Threads a person has not picked up yet (status "new"), not counting archived ones.
	 */
	public function count_new(): int {
		return $this->count_status( 'new' );
	}

	/**
	 * Threads no channel accepted (status "failed"), so nobody on the team was notified. Archived ones are not counted.
	 */
	public function count_failed(): int {
		return $this->count_status( 'failed' );
	}

	public function count_archived(): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table()} WHERE archived_at IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
	}

	/**
	 * Returns 0 until the inbox table has been created, so a fresh install never queries a missing table.
	 */
	private function count_status( string $status ): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}
		return (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE status = %s AND archived_at IS NULL", $this->valid_status( $status ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
		);
	}

	/** @return array<string,mixed>|null */
	public function get( int $id ): ?array {
		if ( ! $this->is_ready() ) {
			return null;
		}
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table and column list.
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	public function set_status( int $id, string $status ): bool {
		return false !== $this->db->update(
			$this->table(),
			array(
				'status'     => $this->valid_status( $status ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Archives a thread or moves it back to the inbox. The status and the activity date are kept,
	 * so a thread returns to the same place in the list.
	 */
	public function set_archived( int $id, bool $archived ): bool {
		return false !== $this->db->update(
			$this->table(),
			array( 'archived_at' => $archived ? current_time( 'mysql', true ) : null ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Archives every resolved thread still in the inbox.
	 *
	 * @return array{count:int,archived_at:string} How many threads moved, and the archive time written to them.
	 */
	public function archive_resolved(): array {
		$now = current_time( 'mysql', true );
		if ( ! $this->is_ready() ) {
			return array(
				'count'       => 0,
				'archived_at' => $now,
			);
		}
		$count = $this->db->query(
			$this->db->prepare( "UPDATE {$this->table()} SET archived_at = %s WHERE status = %s AND archived_at IS NULL", $now, 'resolved' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
		);
		return array(
			'count'       => is_int( $count ) ? $count : 0,
			'archived_at' => $now,
		);
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize( array $row ): array {
		$messages           = json_decode( (string) ( $row['messages'] ?? '' ), true );
		$row['id']          = (int) ( $row['id'] ?? 0 );
		$row['messages']    = is_array( $messages ) ? $messages : array();
		$row['archived_at'] = isset( $row['archived_at'] ) && '' !== (string) $row['archived_at'] ? (string) $row['archived_at'] : null;
		return $row;
	}

	private function valid_status( string $status ): string {
		return in_array( $status, self::STATUSES, true ) ? $status : 'new';
	}
}
