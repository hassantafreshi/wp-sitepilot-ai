<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Admin\HealthDiagnostics;

/**
 * Runs the saved-config Health diagnostic report.
 *
 * No request parameter is accepted: an unsaved Settings draft must never
 * overwrite or certify the site-wide Health snapshot.
 */
final class HealthController {

	public function diagnostics(): \WP_REST_Response {
		try {
			$report = ( new HealthDiagnostics() )->run();
		} catch ( \Throwable $error ) {
			// Keep infrastructure details out of an authenticated-but-rendered UI.
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => 'health_diagnostics_failed',
					'message' => __( 'Health checks could not be completed. Please try again.', 'ai-site-assistant-autopilot' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'code'   => 'ok',
				'report' => $report,
			),
			200
		);
	}
}
