<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Support\Retention;
use WP_REST_Response;

/**
 * "Delete expired data now" in Advanced settings: runs the daily retention cleanup at once,
 * with the saved periods. Administrators only.
 */
final class RetentionController {

	public function run(): WP_REST_Response {
		$last = ( new Retention() )->run();

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'code'     => 'retention_cleanup_done',
				'message'  => Retention::summary( $last ),
				'last_run' => $last,
			),
			200
		);
	}
}
