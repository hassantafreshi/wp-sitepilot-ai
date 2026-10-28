<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

use AISAAutoPilot\Support\Retention;

/**
 * Runs on plugin deactivation. Keeps user data; only clears scheduled/transient state.
 */
final class Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
		Retention::unschedule();
	}
}
