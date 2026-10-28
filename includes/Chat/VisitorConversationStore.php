<?php
declare(strict_types=1);

namespace AISAAutoPilot\Chat;

/** Private audit store for widget conversations when the site owner opts in. */
final class VisitorConversationStore {

	public const TABLE           = 'aisaautopilot_widget_chats';
	private const SCHEMA_OPTION  = 'aisaautopilot_widget_chat_schema';
	private const SCHEMA_VERSION = '1';
	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public static function ensure_schema(): void {
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' ) ) {
			return;
		}
		( new self() )->create_table();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public function table(): string {
		return $this->db->prefix . self::TABLE;
	}

	public function create_table(): void {
		$table = $this->table();
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_token CHAR(36) NOT NULL,
			messages LONGTEXT NOT NULL,
			message_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY session_token (session_token),
			KEY updated_at (updated_at)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** @return array{token:string,messages:array<int,array{role:string,content:string,sources:array<int,mixed>,time:string}>} */
	public function append_turn( string $token, string $question, string $answer, array $sources = array() ): array {
		$token      = $this->valid_token( $token ) ? $token : wp_generate_uuid4();
		$row        = $this->row_by_token( $token );
		$messages   = $row ? json_decode( (string) $row['messages'], true ) : array();
		$messages   = is_array( $messages ) ? $messages : array();
		$now        = gmdate( 'c' );
		$messages[] = array(
			'role'    => 'user',
			'content' => mb_substr( sanitize_textarea_field( $question ), 0, 1500 ),
			'sources' => array(),
			'time'    => $now,
		);
		$messages[] = array(
			'role'    => 'assistant',
			'content' => mb_substr( sanitize_textarea_field( $answer ), 0, 5000 ),
			'sources' => $this->clean_sources( $sources ),
			'time'    => $now,
		);
		$messages   = array_slice( $messages, -40 );
		$payload    = wp_json_encode( $messages, JSON_UNESCAPED_UNICODE );
		$mysql_now  = current_time( 'mysql', true );
		if ( $row ) {
			$this->db->update(
				$this->table(),
				array(
					'messages'      => $payload,
					'message_count' => count( $messages ),
					'updated_at'    => $mysql_now,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$this->db->insert(
				$this->table(),
				array(
					'session_token' => $token,
					'messages'      => $payload,
					'message_count' => count( $messages ),
					'created_at'    => $mysql_now,
					'updated_at'    => $mysql_now,
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
		}
		return array(
			'token'    => $token,
			'messages' => $messages,
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function list( int $limit = 50, int $offset = 0 ): array {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT id, session_token, messages, message_count, created_at, updated_at FROM {$this->table()} ORDER BY updated_at DESC LIMIT %d OFFSET %d", max( 1, min( 100, $limit ) ), max( 0, $offset ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	/** @return array<string,mixed>|null */
	public function get( int $id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT id, session_token, messages, message_count, created_at, updated_at FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	/**
	 * The recorded messages of one conversation, or none when the id is unknown or malformed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function messages_for_token( string $token ): array {
		if ( ! $this->valid_token( $token ) ) {
			return array();
		}
		$row      = $this->row_by_token( $token );
		$messages = null !== $row ? json_decode( (string) $row['messages'], true ) : null;

		return is_array( $messages ) ? array_values( array_filter( $messages, 'is_array' ) ) : array();
	}

	/** @return array<string,mixed>|null */
	private function row_by_token( string $token ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT id, messages FROM {$this->table()} WHERE session_token = %s", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal identifier.
		return is_array( $row ) ? $row : null;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize( array $row ): array {
		$messages        = json_decode( (string) ( $row['messages'] ?? '' ), true );
		$row['id']       = (int) $row['id'];
		$row['messages'] = is_array( $messages ) ? $messages : array();
		return $row;
	}

	private function valid_token( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $token );
	}

	/** @param array<int,mixed> $sources @return array<int,array{title:string,url:string}> */
	private function clean_sources( array $sources ): array {
		$out = array();
		foreach ( array_slice( $sources, 0, 6 ) as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$url = esc_url_raw( (string) ( $source['url'] ?? '' ) );
			if ( '' === $url || ! wp_http_validate_url( $url ) ) {
				continue;
			}
			$out[] = array(
				'title' => mb_substr( sanitize_text_field( (string) ( $source['title'] ?? '' ) ), 0, 180 ),
				'url'   => $url,
			);
		}
		return $out;
	}
}
