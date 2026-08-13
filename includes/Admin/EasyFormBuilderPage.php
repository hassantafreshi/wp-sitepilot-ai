<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\EasyFormBuilderConnector;

/**
 * Gives Easy Form Builder a clear, separate SitePilot entry point.
 * Advanced form features remain owned by EFB itself.
 */
final class EasyFormBuilderPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$connector  = new EasyFormBuilderConnector();
		$health     = $connector->get_health();
		$ready      = ! empty( $health['available'] );
		$list_url   = admin_url( 'admin.php?page=Emsfb' );
		$create_url = admin_url( 'admin.php?page=Emsfb_create' );
		?>
		<div class="wrap sitepilot-wrap sitepilot-overview">
			<header class="sitepilot-page-head">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Advanced forms', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><?php esc_html_e( 'Easy Form Builder', 'sitepilot-ai' ); ?></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Use Easy Form Builder for multi-step forms, uploads, calculations, conditional logic, payment, and its own advanced settings.', 'sitepilot-ai' ); ?></p>
				</div>
			</header>

			<?php if ( ! $ready ) : ?>
				<div class="sitepilot-card sitepilot-overview-card">
					<div class="sitepilot-card__head">
						<div>
							<h2><?php esc_html_e( 'Easy Form Builder is not active', 'sitepilot-ai' ); ?></h2>
							<p><?php esc_html_e( 'The built-in SitePilot Forms connector remains available for simple, single-step forms sent by email.', 'sitepilot-ai' ); ?></p>
						</div>
					</div>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=easy+form+builder&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install Easy Form Builder', 'sitepilot-ai' ); ?></a></p>
				</div>
			<?php else : ?>
				<div class="sitepilot-overview-split">
					<div class="sitepilot-card sitepilot-overview-card">
						<div class="sitepilot-card__head">
							<div>
								<h2><?php esc_html_e( 'Connector status', 'sitepilot-ai' ); ?></h2>
								<p><?php echo esc_html( (string) ( $health['notes'] ?? '' ) ); ?></p>
							</div>
							<span class="sitepilot-connector-card__badge sitepilot-connector-card__badge--ok"><?php esc_html_e( 'Active', 'sitepilot-ai' ); ?></span>
						</div>
						<dl class="sitepilot-key-value-list">
							<div><dt><?php esc_html_e( 'Detected version', 'sitepilot-ai' ); ?></dt><dd><?php echo esc_html( (string) ( $health['version'] ?: '—' ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'SitePilot action', 'sitepilot-ai' ); ?></dt><dd><?php esc_html_e( 'Create an EFB form from an approved plan', 'sitepilot-ai' ); ?></dd></div>
						</dl>
					</div>
					<div class="sitepilot-card sitepilot-overview-card">
						<h2><?php esc_html_e( 'Continue in Easy Form Builder', 'sitepilot-ai' ); ?></h2>
						<p><?php esc_html_e( 'SitePilot does not replace EFB’s editor. Open EFB to create, edit, save, and configure advanced form features.', 'sitepilot-ai' ); ?></p>
						<p class="sitepilot-button-row">
							<a class="button button-primary" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Create in EFB', 'sitepilot-ai' ); ?></a>
							<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Open EFB forms', 'sitepilot-ai' ); ?></a>
						</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
