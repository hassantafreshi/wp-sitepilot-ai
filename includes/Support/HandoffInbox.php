<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

/**
 * Private, administrator-only inbox for visitor-initiated human handoffs.
 * We do not retain ordinary widget chats: clicking the handoff button is the
 * visitor's explicit request to pass the supplied messages to a human.
 */
final class HandoffInbox {

	public const TABLE           = 'aisaautopilot_handoffs';
	private const SCHEMA_OPTION  = 'aisaautopilot_handoff_inbox_schema';
	private const SCHEMA_VERSION = '1';
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

	public function create_table(): void {
		$table = $this->table();
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			visitor_name VARCHAR(120) NOT NULL DEFAULT '',
			visitor_email VARCHAR(190) NOT NULL DEFAULT '',
			messages LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** @param array<int,array{role:string,content:string}> $messages */
	public function record( array $messages, string $name, string $email, string $status ): int {
		$now = current_time( 'mysql', true );
		$this->db->insert(
			$this->table(),
			array(
				'status'        => $this->valid_status( $status ),
				'visitor_name'  => mb_substr( sanitize_text_field( $name ), 0, 120 ),
				'visitor_email' => sanitize_email( $email ),
				'messages'      => wp_json_encode( $messages, JSON_UNESCAPED_UNICODE ),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->db->insert_id;
	}

	/** @return array<int,array<string,mixed>> */
	public function list( int $limit = 50, int $offset = 0 ): array {
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );
		$rows   = $this->db->get_results(
			$this->db->prepare( "SELECT id, status, visitor_name, visitor_email, messages, created_at, updated_at FROM {$this->table()} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $limit, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
			ARRAY_A
		);
		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	/** @return array<string,mixed>|null */
	public function get( int $id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT id, status, visitor_name, visitor_email, messages, created_at, updated_at FROM {$this->table()} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
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

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize( array $row ): array {
		$messages        = json_decode( (string) ( $row['messages'] ?? '' ), true );
		$row['id']       = (int) ( $row['id'] ?? 0 );
		$row['messages'] = is_array( $messages ) ? $messages : array();
		return $row;
	}

	private function valid_status( string $status ): string {
		return in_array( $status, array( 'new', 'in_progress', 'resolved', 'failed' ), true ) ? $status : 'new';
	}
}
