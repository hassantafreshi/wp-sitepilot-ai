<?php
declare(strict_types=1);

namespace SitePilot\Execution;

/**
 * Stores a proposed action (a validated form plan awaiting approval) with a
 * short TTL, scoped to the user who created it. Approval consumes it, so a plan
 * cannot be executed twice or replayed later.
 */
final class ActionStore {

	private const TTL = 900; // 15 minutes.

	private function transient_key( string $action_id ): string {
		return 'sitepilot_action_' . $action_id;
	}

	/**
	 * @param array<string, mixed> $plan
	 * @return string The action id.
	 */
	public function create( array $plan, string $connector_id ): string {
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
	 * Fetch and delete atomically-ish so the same approval can't run twice.
	 *
	 * @return array<string, mixed>|null
	 */
	public function consume( string $action_id ): ?array {
		$record = $this->get( $action_id );
		if ( null !== $record ) {
			delete_transient( $this->transient_key( $action_id ) );
		}

		return $record;
	}

	private function uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
