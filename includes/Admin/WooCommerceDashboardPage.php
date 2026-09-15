<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Backwards-compatible class name for integrations that rendered the former
 * WooCommerce dashboard directly. The canonical page is StoreCopilotPage.
 */
final class WooCommerceDashboardPage {

	public function render(): void {
		( new StoreCopilotPage() )->render();
	}
}
