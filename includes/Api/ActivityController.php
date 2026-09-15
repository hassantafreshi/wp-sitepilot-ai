<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Activity\ActivityFeed;

/** Authenticated, redacted activity feed for the redesigned Activity page. */
final class ActivityController {

	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		$feed = new ActivityFeed();
		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'code'  => 'ok',
				'items' => $feed->recent(
					absint( $request->get_param( 'limit' ) ?: 100 ),
					(string) $request->get_param( 'level' ),
					(string) $request->get_param( 'search' )
				),
			)
		);
	}
}
