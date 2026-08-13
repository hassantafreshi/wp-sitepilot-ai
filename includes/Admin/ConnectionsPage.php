<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Connectors\WooCommerceConnector;
use SitePilot\Providers\ProviderManager;

/**
 * Live WP-11 connector health page from the UI wireframe.
 */
final class ConnectionsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$provider_ready = ( new ProviderManager() )->is_configured();
		$efb            = new EasyFormBuilderConnector();
		$efb_ready      = $efb->is_available();
		$woo            = WooCommerceConnector::get_health();
		?>
		<div class="wrap sitepilot-wrap sitepilot-overview">
			<header class="sitepilot-page-head">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'SitePilot AI', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><?php esc_html_e( 'Connections & health', 'sitepilot-ai' ); ?></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Check every dependency SitePilot relies on before you start a task.', 'sitepilot-ai' ); ?></p>
				</div>
			</header>

			<div class="sitepilot-health-list">
				<?php $this->health_row( __( 'AI provider', 'sitepilot-ai' ), $provider_ready, $provider_ready ? __( 'An encrypted provider key is configured.', 'sitepilot-ai' ) : __( 'Connect a provider before using AI generation.', 'sitepilot-ai' ), admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ), $provider_ready ? __( 'Open settings', 'sitepilot-ai' ) : __( 'Connect provider', 'sitepilot-ai' ) ); ?>
				<?php $this->health_row( __( 'SitePilot Forms', 'sitepilot-ai' ), true, __( 'The built-in simple-form connector is always available.', 'sitepilot-ai' ), admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORMS ), __( 'Manage forms', 'sitepilot-ai' ) ); ?>
				<?php $this->health_row( __( 'Easy Form Builder', 'sitepilot-ai' ), $efb_ready, $efb_ready ? __( 'Installed. Use it for advanced form capabilities.', 'sitepilot-ai' ) : __( 'Not active. Install it for multi-step, files, payments, and conditional logic.', 'sitepilot-ai' ), $efb_ready ? admin_url( 'admin.php?page=' . AdminMenu::SLUG_EFB ) : admin_url( 'plugin-install.php?s=easy+form+builder&tab=search&type=term' ), $efb_ready ? __( 'Open Easy Form Builder', 'sitepilot-ai' ) : __( 'Install Easy Form Builder', 'sitepilot-ai' ) ); ?>
				<?php $this->health_row( __( 'WooCommerce', 'sitepilot-ai' ), (bool) $woo['active'], ! empty( $woo['active'] ) ? sprintf( __( 'WooCommerce %s is active.', 'sitepilot-ai' ), (string) ( $woo['version'] ?: '—' ) ) : __( 'Not active. Store tools stay disabled until it is installed.', 'sitepilot-ai' ), ! empty( $woo['active'] ) ? admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD ) : admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ), ! empty( $woo['active'] ) ? __( 'Open dashboard', 'sitepilot-ai' ) : __( 'Install WooCommerce', 'sitepilot-ai' ) ); ?>
			</div>
		</div>
		<?php
	}

	private function health_row( string $title, bool $ready, string $description, string $url, string $cta ): void {
		?>
		<article class="sitepilot-health-row">
			<span class="sitepilot-health-row__icon dashicons <?php echo esc_attr( $ready ? 'dashicons-yes-alt' : 'dashicons-warning' ); ?>" aria-hidden="true"></span>
			<div>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
			</div>
			<span class="sitepilot-connector-card__badge <?php echo $ready ? 'sitepilot-connector-card__badge--ok' : 'sitepilot-connector-card__badge--muted'; ?>"><?php echo esc_html( $ready ? __( 'Active', 'sitepilot-ai' ) : __( 'Inactive', 'sitepilot-ai' ) ); ?></span>
			<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
		</article>
		<?php
	}
}
