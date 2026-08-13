<?php
declare(strict_types=1);

namespace SitePilot\Execution;

/**
 * Stores a proposed action (a validated form plan awaiting approval) with a
 * short TTL, scoped to the user who created it. Approval consumes it, so a plan
 * cannot be executed twice or replayed later.
 */
final class ActionStore {

	private const TTL             = 900; // 15 minutes.
	private const CLAIM_RETENTION = 86400; // One day; only replay locks use options.

	/**
	 * The public UI may display this deadline, but execution still enforces the
	 * transient expiry server-side.
	 */
	public static function ttl(): int {
		return self::TTL;
	}

	private function transient_key( string $action_id ): string {
		return 'sitepilot_action_' . $action_id;
	}

	private function claim_key( string $action_id ): string {
		return 'sitepilot_action_claim_' . str_replace( '-', '', $action_id );
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return string The action id.
	 */
	public function create( array $plan, string $connector_id ): string {
		$this->purge_stale_claims();
		$action_id = $this->uuid();
		$record    = array(
			'action_id'    => $action_id,
			'plan'         => $plan,
			'connector_id' => $connector_id,
			'user_id'      => get_current_user_id(),
			'created_at'   => time(),
			'status'       => 'previewed',
		);

		set_transient( $this->transient_key( $action_id ), $record, self::TTL );

		return $action_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $action_id ): ?array {
		$record = get_transient( $this->transient_key( $action_id ) );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * Atomically claim a proposal for its original creator.
	 *
	 * A persistent, non-autoloaded option is used as a compare-and-set lock:
	 * `add_option()` succeeds for exactly one request. We deliberately do not
	 * remove that lock after execution; doing so would allow a second concurrent
	 * request that already read the transient to replay the action.
	 *
	 * @return array{status:string,record?:array<string,mixed>}
	 */
	public function claim( string $action_id, int $user_id ): array {
		$record = $this->get( $action_id );
		if ( null === $record ) {
			return array( 'status' => 'missing' );
		}

		if ( (int) ( $record['user_id'] ?? 0 ) !== $user_id ) {
			return array( 'status' => 'forbidden' );
		}

		if ( ! add_option( $this->claim_key( $action_id ), (string) time(), '', false ) ) {
			return array( 'status' => 'already_claimed' );
		}

		delete_transient( $this->transient_key( $action_id ) );

		return array(
			'status' => 'claimed',
			'record' => $record,
		);
	}

	/**
	 * Removes only SitePilot's expired, non-autoloaded replay locks. This keeps
	 * the one-time-action guarantee without accumulating options indefinitely.
	 */
	private function purge_stale_claims(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! isset( $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return;
		}

		$like = $wpdb->esc_like( 'sitepilot_action_claim_' ) . '%';
		$sql  = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
			$like,
			time() - self::CLAIM_RETENTION
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared immediately above.
	}

	private function uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
