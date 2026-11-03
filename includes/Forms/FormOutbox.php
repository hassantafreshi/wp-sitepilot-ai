<?php
declare(strict_types=1);

namespace AISAAutoPilot\Forms;

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * Form submissions whose email could not be sent, kept only when the administrator turns
 * on "Keep a submission when its email cannot be sent" (Advanced settings, off by default).
 *
 * Native forms store nothing by design, so a failed wp_mail() used to lose the visitor's
 * answers; failed email delivery is among the most common complaints about WordPress form
 * plugins. With the option on, the composed email is kept here, retried by WP-Cron, and
 * listed under Your forms to resend or delete. A row leaves the table when it is sent,
 * deleted by the administrator, or older than the retention period.
 */
final class FormOutbox {

	public const TABLE = 'aisaautopilot_form_outbox';

	public const RETRY_HOOK = 'aisaautopilot_form_outbox_retry';

	/** Automatic tries per submission; the administrator can always resend by hand. */
	public const MAX_AUTO_ATTEMPTS = 5;

	private const SCHEMA_OPTION = 'aisaautopilot_form_outbox_schema';

	private const SCHEMA_VERSION = '1';

	private const RETRY_BATCH = 20;

	private const COLUMNS = 'id, form_id, subject, body, headers, attempts, last_error, created_at, updated_at';

	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	public function register(): void {
		add_action( self::RETRY_HOOK, array( $this, 'cron' ) );
	}

	/** Whether the administrator chose to keep submissions whose email failed. */
	public static function enabled(): bool {
		return (bool) Settings::get( 'forms_keep_undelivered', false );
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

	/** Whether the table exists at the current schema version, so reads never query a missing table. */
	public function is_ready(): bool {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' );
	}

	public function create_table(): void {
		$table = $this->table();
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body LONGTEXT NOT NULL,
			headers TEXT NOT NULL,
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			last_error VARCHAR(190) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY attempts (attempts)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Keeps an email that could not be sent and queues a retry.
	 *
	 * @param string[] $headers
	 * @return int The row id, or 0 when it could not be stored.
	 */
	public function keep( int $form_id, string $subject, string $body, array $headers, string $error = '' ): int {
		self::ensure_schema();
		$now = current_time( 'mysql', true );
		$ok  = $this->db->insert(
			$this->table(),
			array(
				'form_id'    => max( 0, $form_id ),
				'subject'    => mb_substr( $subject, 0, 255 ),
				'body'       => $body,
				'headers'    => (string) wp_json_encode( array_values( array_map( 'strval', $headers ) ) ),
				'attempts'   => 1,
				'last_error' => mb_substr( sanitize_text_field( $error ), 0, 190 ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return 0;
		}
		self::schedule_retry( 15 * MINUTE_IN_SECONDS );

		return (int) $this->db->insert_id;
	}

	/**
	 * Kept submissions, oldest first, with the form's title for the list.
	 *
	 * @return array<int, array{id:int, form_id:int, form_title:string, subject:string, body:string, attempts:int, last_error:string, created_at:string}>
	 */
	public function list( int $limit = 50 ): array {
		if ( ! $this->is_ready() ) {
			return array();
		}
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . " FROM {$this->table()} ORDER BY id ASC LIMIT %d", max( 1, min( 200, $limit ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table and column list.
			ARRAY_A
		);

		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	public function count(): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}

		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
	}

	/**
	 * Sends a kept submission again. Sent: the row is deleted. Not sent: the attempt is counted.
	 *
	 * @return array{ok:bool, code:string}
	 */
	public function resend( int $id ): array {
		$row = $this->get( $id );
		if ( null === $row ) {
			return array(
				'ok'   => false,
				'code' => 'not_found',
			);
		}
		$recipient = self::recipient( $row['form_id'] );
		if ( '' === $recipient ) {
			$this->failed_attempt( $row['id'], 'no_recipient' );
			return array(
				'ok'   => false,
				'code' => 'no_recipient',
			);
		}
		$headers = json_decode( (string) $row['headers'], true );
		if ( ! wp_mail( $recipient, $row['subject'], $row['body'], is_array( $headers ) ? $headers : array() ) ) {
			$this->failed_attempt( $row['id'], 'wp_mail_failed' );
			return array(
				'ok'   => false,
				'code' => 'send_failed',
			);
		}
		$this->delete( $row['id'] );
		( new Logger() )->info( 'form.outbox_sent', 'A kept form submission was emailed.', array( 'connector_id' => 'native' ) );

		return array(
			'ok'   => true,
			'code' => 'sent',
		);
	}

	public function delete( int $id ): bool {
		if ( ! $this->is_ready() ) {
			return false;
		}

		return (bool) $this->db->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/** The WP-Cron callback. */
	public function cron(): void {
		$this->retry_due();
	}

	/**
	 * Tries every kept submission that still has automatic attempts left, then queues the
	 * next try an hour later while any remain.
	 *
	 * @return int How many were sent.
	 */
	public function retry_due(): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}
		$ids  = $this->db->get_col(
			$this->db->prepare( "SELECT id FROM {$this->table()} WHERE attempts < %d ORDER BY id ASC LIMIT %d", self::MAX_AUTO_ATTEMPTS, self::RETRY_BATCH ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
		);
		$sent = 0;
		foreach ( (array) $ids as $id ) {
			$sent += $this->resend( (int) $id )['ok'] ? 1 : 0;
		}
		$left = (int) $this->db->get_var(
			$this->db->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE attempts < %d", self::MAX_AUTO_ATTEMPTS ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
		);
		if ( $left > 0 ) {
			self::schedule_retry( HOUR_IN_SECONDS );
		}

		return $sent;
	}

	/** Deletes kept submissions received before the timestamp. Returns how many were deleted. */
	public function forget_before( int $timestamp ): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}

		return (int) $this->db->query(
			$this->db->prepare( "DELETE FROM {$this->table()} WHERE created_at < %s LIMIT 1000", gmdate( 'Y-m-d H:i:s', $timestamp ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table; retention delete.
		);
	}

	/**
	 * Kept submissions that mention an email address, for the WordPress privacy tools.
	 *
	 * @return array<int, array{id:int, form_id:int, form_title:string, subject:string, body:string, attempts:int, last_error:string, created_at:string}>
	 */
	public function rows_for_email( string $email, int $limit = 100 ): array {
		if ( ! $this->is_ready() || '' === $email ) {
			return array();
		}
		$like = '%' . $this->db->esc_like( $email ) . '%';
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE body LIKE %s OR headers LIKE %s ORDER BY id ASC LIMIT %d", $like, $like, max( 1, $limit ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table and column list.
			ARRAY_A
		);

		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	public static function schedule_retry( int $delay ): void {
		if ( false === wp_next_scheduled( self::RETRY_HOOK ) ) {
			wp_schedule_single_event( time() + max( 60, $delay ), self::RETRY_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_unschedule_hook( self::RETRY_HOOK );
	}

	/**
	 * Where a kept submission goes: the address the form was built with, or today's form
	 * address when that one is missing or invalid (it may be the reason the email failed).
	 */
	public static function recipient( int $form_id ): string {
		$plan      = $form_id > 0 ? get_post_meta( $form_id, NativeFormConnector::META_KEY, true ) : array();
		$recipient = is_array( $plan ) ? sanitize_email( (string) ( $plan['notification_email'] ?? '' ) ) : '';
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = Settings::form_notification_email();
		}

		return is_email( $recipient ) ? $recipient : '';
	}

	/**
	 * @return array{id:int, form_id:int, form_title:string, subject:string, body:string, headers:string, attempts:int, last_error:string, created_at:string}|null
	 */
	private function get( int $id ): ?array {
		if ( ! $this->is_ready() ) {
			return null;
		}
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table and column list.
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->normalize( $row ) + array( 'headers' => (string) $row['headers'] );
	}

	private function failed_attempt( int $id, string $error ): void {
		$this->db->query(
			$this->db->prepare( "UPDATE {$this->table()} SET attempts = attempts + 1, last_error = %s, updated_at = %s WHERE id = %d", $error, current_time( 'mysql', true ), $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{id:int, form_id:int, form_title:string, subject:string, body:string, attempts:int, last_error:string, created_at:string}
	 */
	private function normalize( array $row ): array {
		$form_id = (int) ( $row['form_id'] ?? 0 );
		$title   = $form_id > 0 ? get_the_title( $form_id ) : '';

		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'form_id'    => $form_id,
			'form_title' => '' !== $title ? $title : (string) ( $row['subject'] ?? '' ),
			'subject'    => (string) ( $row['subject'] ?? '' ),
			'body'       => (string) ( $row['body'] ?? '' ),
			'attempts'   => (int) ( $row['attempts'] ?? 0 ),
			'last_error' => (string) ( $row['last_error'] ?? '' ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
		);
	}
}
