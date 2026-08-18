<?php
declare(strict_types=1);

namespace AISAAutoPilot\Execution;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * Executes an approved action: re-validates, runs the connector, and logs.
 * This is the only place that creates anything on the site.
 */
final class ActionExecutor {

	private ConnectorRegistry $registry;
	private ActionStore $store;
	private Logger $logger;

	public function __construct( ConnectorRegistry $registry, ?ActionStore $store = null, ?Logger $logger = null ) {
		$this->registry = $registry;
		$this->store    = $store ?? new ActionStore();
		$this->logger   = $logger ?? new Logger();
	}

	/**
	 * @return array<string, mixed> Result payload for the UI.
	 */
	public function approve_and_execute( string $action_id ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this action.', 'ai-site-assistant-autopilot' ) );
		}

		$claim = $this->store->claim( $action_id, get_current_user_id() );
		if ( 'missing' === $claim['status'] ) {
			$this->logger->error( 'action.expired', 'Approval failed: action not found or expired.', array( 'action_id' => $action_id ) );

			return $this->failure( 'action_expired', __( 'This proposal has expired. Please generate the form again.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 'forbidden' === $claim['status'] ) {
			$this->logger->error( 'action.forbidden', 'Approval rejected: different user.', array( 'action_id' => $action_id ) );

			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this action.', 'ai-site-assistant-autopilot' ) );
		}
		if ( 'already_claimed' === $claim['status'] ) {
			return $this->failure( 'action_already_processed', __( 'This proposal is already being processed or has already been used.', 'ai-site-assistant-autopilot' ) );
		}

		$record = $claim['record'] ?? array();

		$plan         = (array) $record['plan'];
		$connector_id = (string) ( $record['connector_id'] ?? '' );
		$connector    = $this->registry->get( $connector_id );

		if ( null === $connector || ! $connector->is_available() || ! in_array( 'form.create', $connector->get_capabilities(), true ) ) {
			$this->logger->error(
				'action.connector_unavailable',
				'The connector pinned to the approved proposal is unavailable.',
				array(
					'action_id'    => $action_id,
					'connector_id' => $connector_id,
				)
			);
			return $this->failure( 'connector_not_available', __( 'The connector selected for this proposal is no longer available. Generate a new proposal.', 'ai-site-assistant-autopilot' ) );
		}

		$this->logger->info(
			'action.approved',
			'Approval accepted for the pinned connector.',
			array(
				'action_id' => $action_id,
				'connector' => $connector_id,
			)
		);

		$errors = $connector->validate_action( $plan );
		if ( array() !== $errors ) {
			$this->logger->error( 'action.invalid', 'Connector rejected the plan.', array( 'error_count' => count( $errors ) ) );

			return $this->failure( 'invalid_payload', implode( ' ', $errors ) );
		}

		$context = array(
			'status' => 'publish' === Settings::get( 'default_form_status', 'draft' ) ? 'publish' : 'draft',
		);

		$result = $connector->execute_action( $plan, $context );

		if ( ( $result['status'] ?? '' ) !== 'success' ) {
			$this->logger->error(
				'action.failed',
				'Form creation failed.',
				array(
					'connector' => $connector->get_id(),
					'code'      => $result['code'] ?? 'execution_failed',
				)
			);

			return $this->failure(
				(string) ( $result['code'] ?? 'execution_failed' ),
				(string) ( $result['message'] ?? __( 'The form could not be created.', 'ai-site-assistant-autopilot' ) )
			);
		}

		$this->logger->info(
			'action.executed',
			'Form created.',
			array(
				'connector' => $connector->get_id(),
				'form_id'   => $result['form_id'] ?? null,
			)
		);

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function failure( string $code, string $message ): array {
		return array(
			'status'  => 'failed',
			'code'    => $code,
			'message' => $message,
		);
	}
}
