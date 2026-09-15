<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Hidden compatibility alias for bookmarked legacy WooCommerce AI URLs.
 *
 * The old screen promised editing and bulk operations that Phase 1 does not
 * ship. It now opens the canonical Store Copilot capability tab instead.
 */
final class WooCommerceSettings {

	public const SLUG = 'aisaautopilot-woocommerce';

	public function render(): void {
		$had_tab      = array_key_exists( 'tab', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only alias selection.
		$previous_tab = $had_tab ? $_GET['tab'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- restored only after canonical rendering.
		$_GET['tab']  = 'capabilities';

		try {
			( new StoreCopilotPage() )->render();
		} finally {
			if ( $had_tab ) {
				$_GET['tab'] = $previous_tab;
			} else {
				unset( $_GET['tab'] );
			}
		}
	}
}
