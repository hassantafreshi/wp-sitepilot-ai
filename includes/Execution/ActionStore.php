<?php
declare(strict_types=1);

namespace AISAAutoPilot\Execution;

use AISAAutoPilot\Content\ContentTone;
use AISAAutoPilot\Logging\Logger;

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
		return 'aisaautopilot_action_' . $action_id;
	}

	private function claim_key( string $action_id ): string {
		return 'aisaautopilot_action_claim_' . str_replace( '-', '', $action_id );
	}

	/**
	 * @param array<string, mixed> $plan
	 * @param array<string, mixed> $execution_context Values the administrator reviewed with this proposal.
	 * @return string The action id.
	 */
	public function create( array $plan, string $connector_id, array $execution_context = array() ): string {
		$this->purge_stale_claims();
		$action_id = $this->uuid();
		// Only the explicit, bounded creation status belongs in the proposal. A
		// later Settings change must not alter what the administrator approved.
		$source_gap        = (string) ( $execution_context['source_gap'] ?? '' );
		$execution_context = array(
			'status'     => 'publish' === ( $execution_context['status'] ?? '' ) ? 'publish' : 'draft',
			// The unanswered question a Content Studio draft answers, validated by the caller; empty otherwise.
			'source_gap' => 1 === preg_match( '/^[a-f0-9]{32}$/', $source_gap ) ? $source_gap : '',
			// The Content Studio voice the draft was written in; the default for anything else.
			'tone'       => ContentTone::normalize( $execution_context['tone'] ?? '' ),
		);
		$record            = array(
			'action_id'         => $action_id,
			'plan'              => $plan,
			'connector_id'      => $connector_id,
			'execution_context' => $execution_context,
			'user_id'           => get_current_user_id(),
			'created_at'        => time(),
			'status'            => 'previewed',
		);

		set_transient( $this->transient_key( $action_id ), $record, self::TTL );
		// The transient deliberately disappears when an approval is consumed, but
		// the redacted audit event remains available to the Activity timeline.
		( new Logger() )->info(
			'action.previewed',
			'Approval-gated proposal created.',
			array(
				'action_id'    => $action_id,
				'connector_id' => $connector_id,
			)
		);

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
	 * @param string|null $expected_connector Optional connector that is allowed
	 *                                        to consume this proposal.
	 * @return array{status:string,record?:array<string,mixed>}
	 */
	public function claim( string $action_id, int $user_id, ?string $expected_connector = null ): array {
		$record = $this->get( $action_id );
		if ( null === $record ) {
			return array( 'status' => 'missing' );
		}

		if ( (int) ( $record['user_id'] ?? 0 ) !== $user_id ) {
			return array( 'status' => 'forbidden' );
		}

		// Check the route's intended connector before adding the one-time lock or
		// deleting the transient. A proposal opened in the wrong product surface
		// must remain reviewable and executable from its original surface.
		if ( null !== $expected_connector && (string) ( $record['connector_id'] ?? '' ) !== $expected_connector ) {
			return array( 'status' => 'wrong_connector' );
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
	 * Removes only AISAAutoPilot's expired, non-autoloaded replay locks. This keeps
	 * the one-time-action guarantee without accumulating options indefinitely.
	 */
	private function purge_stale_claims(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! isset( $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return;
		}

		$like = $wpdb->esc_like( 'aisaautopilot_action_claim_' ) . '%';
		$sql  = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
			$like,
			time() - self::CLAIM_RETENTION
		);
		// Garbage collection of expired single-use claims: a targeted DELETE on
		// wp_options that no WordPress API expresses, and nothing worth caching.
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared immediately above.
	}

	private function uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
