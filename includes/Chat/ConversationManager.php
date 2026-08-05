<?php
declare(strict_types=1);

namespace SitePilot\Chat;

/**
 * Manages chat conversations in a custom DB table (wp_sitepilot_chats).
 *
 * Each conversation has:
 * - id, title, messages (JSON), created_at, updated_at, user_id
 *
 * Messages are stored as a JSON array of {role, content, sources, usage, timestamp}.
 */
final class ConversationManager {

	private \wpdb $db;

	public const TABLE = 'sitepilot_chats';

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public function table(): string {
		return $this->db->prefix . self::TABLE;
	}

	/**
	 * Create the conversations table.
	 */
	public function create_table(): void {
		$table   = $this->table();
		$charset = $this->db->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			messages LONGTEXT NOT NULL,
			message_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the table (uninstall).
	 */
	public function drop_table(): void {
		$table = $this->table();
		$this->db->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
	}

	/**
	 * Create a new conversation.
	 */
	public function create( string $title = '' ): int {
		$now = current_time( 'mysql', true );

		$this->db->insert(
			$this->table(),
			array(
				'user_id'       => get_current_user_id(),
				'title'         => $title ?: __( 'New conversation', 'sitepilot-ai' ),
				'messages'      => '[]',
				'message_count' => 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $this->db->insert_id;
	}

	/**
	 * Add a message to a conversation.
	 *
	 * @param int   $conv_id Conversation ID.
	 * @param array $message {role, content, sources?, usage?, timestamp?}
	 */
	public function add_message( int $conv_id, array $message ): void {
		$row = $this->get( $conv_id );
		if ( ! $row ) {
			return;
		}

		$messages   = json_decode( $row['messages'], true ) ?? array();
		$messages[] = array_merge( array(
			'role'      => 'user',
			'content'   => '',
			'sources'   => array(),
			'usage'     => null,
			'timestamp' => gmdate( 'c' ),
		), $message );

		// Auto-generate title from first user message.
		$title = $row['title'];
		if ( 'New conversation' === $title && 'user' === $message['role'] ) {
			$title = mb_substr( wp_strip_all_tags( $message['content'] ), 0, 60 );
		}

		$now = current_time( 'mysql', true );

		$this->db->update(
			$this->table(),
			array(
				'title'         => $title,
				'messages'      => wp_json_encode( $messages, JSON_UNESCAPED_UNICODE ),
				'message_count' => count( $messages ),
				'updated_at'    => $now,
			),
			array( 'id' => $conv_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get a single conversation.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get( int $conv_id ): ?array {
		$table = $this->table();
		$uid   = get_current_user_id();
		$row   = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $conv_id, $uid ), // phpcs:ignore
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List conversations for the current user.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function list( int $limit = 50, int $offset = 0 ): array {
		$table = $this->table();
		$uid   = get_current_user_id();

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, user_id, title, message_count, created_at, updated_at FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d", // phpcs:ignore
				$uid,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a conversation.
	 */
	public function delete( int $conv_id ): void {
		$table = $this->table();
		$uid   = get_current_user_id();

		$this->db->query(
			$this->db->prepare( "DELETE FROM {$table} WHERE id = %d AND user_id = %d", $conv_id, $uid ) // phpcs:ignore
		);
	}

	/**
	 * Get the last N messages from a conversation for context.
	 *
	 * @return string Formatted conversation history.
	 */
	public function get_recent_context( int $conv_id, int $n = 6 ): string {
		$row = $this->get( $conv_id );
		if ( ! $row ) {
			return '';
		}

		$messages = json_decode( $row['messages'], true ) ?? array();
		$recent   = array_slice( $messages, -$n );
		$context  = '';

		foreach ( $recent as $msg ) {
			$role_label = 'user' === $msg['role'] ? 'User' : 'Assistant';
			$context   .= "{$role_label}: {$msg['content']}\n\n";
		}

		return trim( $context );
	}

	/**
	 * Check if table exists.
	 */
	public function truncate(): void {
		$table = $this->table();
		$this->db->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
	}

	public function table_exists(): bool {
		$table  = $this->table();
		$exists = $this->db->get_var(
			$this->db->prepare( 'SHOW TABLES LIKE %s', $table ) // phpcs:ignore
		);

		return $exists === $table;
	}
}
