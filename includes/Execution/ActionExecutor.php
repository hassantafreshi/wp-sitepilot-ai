<?php
declare(strict_types=1);

namespace SitePilot\Execution;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Settings;
use SitePilot\Logging\Logger;

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
		$record = $this->store->consume( $action_id );

		if ( null === $record ) {
			$this->logger->error( 'action.expired', 'Approval failed: action not found or expired.', array( 'action_id' => $action_id ) );

			return $this->failure( 'action_expired', __( 'This proposal has expired. Please generate the form again.', 'sitepilot-ai' ) );
		}

		// The approver must be the same user who created the proposal (and be capable).
		if ( (int) $record['user_id'] !== get_current_user_id() ) {
			$this->logger->error( 'action.forbidden', 'Approval rejected: different user.', array( 'action_id' => $action_id ) );

			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this action.', 'sitepilot-ai' ) );
		}

		$plan      = (array) $record['plan'];
		$connector = $this->registry->resolve( 'form.create' );

		if ( null === $connector ) {
			return $this->failure( 'connector_not_available', __( 'No form connector is available on this site.', 'sitepilot-ai' ) );
		}

		$errors = $connector->validate_action( $plan );
		if ( array() !== $errors ) {
			$this->logger->error( 'action.invalid', 'Connector rejected the plan.', array( 'errors' => $errors ) );

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
				(string) ( $result['message'] ?? __( 'The form could not be created.', 'sitepilot-ai' ) )
			);
		}

		$this->logger->info(
			'action.executed',
			'Form created.',
			array(
				'connector' => $connector->get_id(),
				'form_id'   => $result['form_id'] ?? null,
				'title'     => $plan['title'] ?? '',
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
