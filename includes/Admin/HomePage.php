<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Connectors\WooCommerceConnector;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;

/**
 * The SitePilot landing page. It turns the WP-01 wireframe into a live,
 * dependency-aware admin page instead of a static mock-up.
 */
final class HomePage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$provider_ready = ( new ProviderManager() )->is_configured();
		$efb_ready      = ( new EasyFormBuilderConnector() )->is_available();
		$woo_ready      = WooCommerceConnector::is_active();
		$form_counts    = wp_count_posts( NativeFormConnector::POST_TYPE );
		$form_total     = $form_counts ? (int) $form_counts->publish + (int) $form_counts->draft : 0;
		$logs           = ( new Logger() )->recent( 4 );
		$current_user   = wp_get_current_user();
		$display_name   = $current_user instanceof \WP_User ? $current_user->display_name : '';
		$site_healthy   = $provider_ready && '' !== \SitePilot\Core\Settings::form_notification_email();

		$builder_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER );
		$forms_url       = admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORMS );
		$connections_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS );
		$settings_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$woo_url         = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD );
		?>
		<div class="wrap sitepilot-wrap sitepilot-overview">
			<header class="sitepilot-page-head">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Home', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><?php echo esc_html( '' !== $display_name ? sprintf( __( 'Welcome back, %s', 'sitepilot-ai' ), $display_name ) : __( 'Welcome back', 'sitepilot-ai' ) ); ?></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Start a safe AI task, review the plan, and keep every site change under your control.', 'sitepilot-ai' ); ?></p>
				</div>
				<div class="sitepilot-page-head__actions">
					<span class="sitepilot-health-chip <?php echo $site_healthy ? 'is-ready' : 'is-attention'; ?>"><span aria-hidden="true"></span><?php echo esc_html( $site_healthy ? __( 'System ready', 'sitepilot-ai' ) : __( 'Setup needs attention', 'sitepilot-ai' ) ); ?></span>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'New AI task', 'sitepilot-ai' ); ?></a>
				</div>
			</header>

			<?php if ( ! $provider_ready ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Connect an AI provider before generating a form or product plan.', 'sitepilot-ai' ); ?></span>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open settings', 'sitepilot-ai' ); ?></a>
				</div>
			<?php endif; ?>

			<section class="sitepilot-overview-grid" aria-label="<?php esc_attr_e( 'Connector status', 'sitepilot-ai' ); ?>">
				<?php $this->connector_card( 'dashicons-feedback', __( 'SitePilot Forms', 'sitepilot-ai' ), sprintf( _n( '%d form is available.', '%d forms are available.', $form_total, 'sitepilot-ai' ), $form_total ), true, $forms_url, __( 'Manage forms', 'sitepilot-ai' ) ); ?>
				<?php $this->connector_card( 'dashicons-list-view', __( 'Easy Form Builder', 'sitepilot-ai' ), $efb_ready ? __( 'Installed and ready for advanced forms.', 'sitepilot-ai' ) : __( 'Install it for advanced fields, logic, files, and multi-step forms.', 'sitepilot-ai' ), $efb_ready, $connections_url, $efb_ready ? __( 'Open connector', 'sitepilot-ai' ) : __( 'View setup', 'sitepilot-ai' ) ); ?>
				<?php $this->connector_card( 'dashicons-cart', __( 'WooCommerce', 'sitepilot-ai' ), $woo_ready ? __( 'Store tools are available.', 'sitepilot-ai' ) : __( 'Install and activate WooCommerce to use store tools.', 'sitepilot-ai' ), $woo_ready, $woo_ready ? $woo_url : $connections_url, $woo_ready ? __( 'Open dashboard', 'sitepilot-ai' ) : __( 'View setup', 'sitepilot-ai' ) ); ?>
			</section>

			<section class="sitepilot-overview-split">
				<div class="sitepilot-card sitepilot-overview-card">
					<div class="sitepilot-card__head">
						<div>
							<h2><?php esc_html_e( 'Start with a safe, reviewable action', 'sitepilot-ai' ); ?></h2>
							<p><?php esc_html_e( 'SitePilot prepares a plan first. Nothing is created until an administrator reviews and approves it.', 'sitepilot-ai' ); ?></p>
						</div>
						<span class="sitepilot-status-dot sitepilot-status-dot--ok" aria-label="<?php esc_attr_e( 'Approval required', 'sitepilot-ai' ); ?>"></span>
					</div>
					<div class="sitepilot-action-grid">
						<a class="sitepilot-action-tile" href="<?php echo esc_url( $builder_url ); ?>">
							<span class="dashicons dashicons-edit-large" aria-hidden="true"></span>
							<span><strong><?php esc_html_e( 'Build a simple form', 'sitepilot-ai' ); ?></strong><small><?php esc_html_e( 'Single step, email delivery', 'sitepilot-ai' ); ?></small></span>
						</a>
						<a class="sitepilot-action-tile" href="<?php echo esc_url( $connections_url ); ?>">
							<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
							<span><strong><?php esc_html_e( 'Use an advanced connector', 'sitepilot-ai' ); ?></strong><small><?php esc_html_e( 'Easy Form Builder and WooCommerce', 'sitepilot-ai' ); ?></small></span>
						</a>
					</div>
				</div>

				<div class="sitepilot-card sitepilot-overview-card">
					<div class="sitepilot-card__head">
						<div>
							<h2><?php esc_html_e( 'Recent activity', 'sitepilot-ai' ); ?></h2>
							<p><?php esc_html_e( 'Only operational events are shown here; sensitive form submissions are never displayed.', 'sitepilot-ai' ); ?></p>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ) ); ?>"><?php esc_html_e( 'View all', 'sitepilot-ai' ); ?></a>
					</div>
					<?php if ( array() === $logs ) : ?>
						<p class="sitepilot-empty-state"><?php esc_html_e( 'No activity has been recorded yet.', 'sitepilot-ai' ); ?></p>
					<?php else : ?>
						<ul class="sitepilot-activity-list">
							<?php foreach ( $logs as $log ) : ?>
								<li>
									<span class="sitepilot-activity-list__dot sitepilot-activity-list__dot--<?php echo esc_attr( (string) ( $log['level'] ?? 'info' ) ); ?>"></span>
									<span><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></span>
									<time datetime="<?php echo esc_attr( (string) ( $log['time'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $log['time'] ?? '' ) ); ?></time>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
	}

	private function connector_card( string $icon, string $title, string $description, bool $is_ready, string $url, string $cta ): void {
		?>
		<article class="sitepilot-connector-card">
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<div>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
			</div>
			<span class="sitepilot-connector-card__badge <?php echo $is_ready ? 'sitepilot-connector-card__badge--ok' : 'sitepilot-connector-card__badge--muted'; ?>">
				<?php echo esc_html( $is_ready ? __( 'Ready', 'sitepilot-ai' ) : __( 'Needs setup', 'sitepilot-ai' ) ); ?>
			</span>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
		</article>
		<?php
	}
}
