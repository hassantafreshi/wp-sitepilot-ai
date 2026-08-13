<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Content\KnowledgeBase;
use SitePilot\Core\Settings;
use SitePilot\Providers\ProviderManager;

/**
 * WP-00 setup checklist. It exposes the live prerequisites instead of making
 * the administrator discover them through disabled controls later.
 */
final class SetupPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$provider_ready  = ( new ProviderManager() )->is_configured();
		$email_ready     = '' !== Settings::form_notification_email();
		$efb_ready       = ( new EasyFormBuilderConnector() )->is_available();
		$knowledge       = new KnowledgeBase();
		$knowledge_stats = $knowledge->table_exists() ? $knowledge->get_stats() : array( 'total' => 0 );
		$knowledge_ready = (int) ( $knowledge_stats['total'] ?? 0 ) > 0;
		$settings_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$connections_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS );
		$builder_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER );
		$kb_url          = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$complete        = (int) $provider_ready + (int) $email_ready + 1 + (int) $knowledge_ready;
		?>
		<div class="wrap sitepilot-wrap sitepilot-overview sitepilot-setup">
			<nav class="sitepilot-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'sitepilot-ai' ); ?>"><span><?php esc_html_e( 'Setup', 'sitepilot-ai' ); ?></span></nav>
			<header class="sitepilot-page-head">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Getting started', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><?php esc_html_e( 'Set up SitePilot AI', 'sitepilot-ai' ); ?></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Complete the essentials once, then every AI action remains explicit, reviewable, and under your control.', 'sitepilot-ai' ); ?></p>
				</div>
				<span class="sitepilot-setup-progress"><?php echo esc_html( sprintf( __( '%1$d of %2$d ready', 'sitepilot-ai' ), $complete, 4 ) ); ?></span>
			</header>

			<section class="sitepilot-setup-grid" aria-label="<?php esc_attr_e( 'Setup checklist', 'sitepilot-ai' ); ?>">
				<?php $this->step( 1, __( 'Connect an AI provider', 'sitepilot-ai' ), __( 'Store an encrypted provider key and select the model used for planning.', 'sitepilot-ai' ), $provider_ready, $settings_url, $provider_ready ? __( 'Review provider', 'sitepilot-ai' ) : __( 'Connect provider', 'sitepilot-ai' ) ); ?>
				<?php $this->step( 2, __( 'Set form delivery', 'sitepilot-ai' ), __( 'Choose the email address that receives every submission from SitePilot Forms.', 'sitepilot-ai' ), $email_ready, $settings_url, $email_ready ? __( 'Review delivery', 'sitepilot-ai' ) : __( 'Set email address', 'sitepilot-ai' ) ); ?>
				<?php $this->step( 3, __( 'Choose your form connector', 'sitepilot-ai' ), $efb_ready ? __( 'SitePilot Forms is ready for basic single-step email forms. Easy Form Builder is also ready for advanced forms.', 'sitepilot-ai' ) : __( 'SitePilot Forms is ready for basic single-step email forms. Install Easy Form Builder when you need advanced forms.', 'sitepilot-ai' ), true, $connections_url, __( 'View connectors', 'sitepilot-ai' ) ); ?>
				<?php $this->step( 4, __( 'Prepare optional knowledge', 'sitepilot-ai' ), __( 'Add knowledge-base content when you want answers to use your own site context.', 'sitepilot-ai' ), $knowledge_ready, $kb_url, __( 'Open knowledge base', 'sitepilot-ai' ), __( 'Optional', 'sitepilot-ai' ) ); ?>
			</section>

			<div class="sitepilot-flow-note sitepilot-flow-note--security sitepilot-setup-note">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Every create action produces a plan first. You can inspect it, approve it, or go back to edit it; SitePilot never creates content automatically.', 'sitepilot-ai' ); ?></p>
			</div>

			<p class="sitepilot-setup-cta"><a class="button button-primary button-hero" href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Open Create with AI', 'sitepilot-ai' ); ?></a></p>
		</div>
		<?php
	}

	private function step( int $number, string $title, string $description, bool $ready, string $url, string $cta, string $suffix = '' ): void {
		?>
		<article class="sitepilot-setup-step <?php echo $ready ? 'is-ready' : 'is-pending'; ?>">
			<span class="sitepilot-setup-step__number"><?php echo esc_html( (string) $number ); ?></span>
			<div>
				<div class="sitepilot-setup-step__head"><h2><?php echo esc_html( $title ); ?></h2><span class="sitepilot-connector-card__badge <?php echo $ready ? 'sitepilot-connector-card__badge--ok' : 'sitepilot-connector-card__badge--muted'; ?>"><?php echo esc_html( $ready ? __( 'Ready', 'sitepilot-ai' ) : ( $suffix ?: __( 'Needs setup', 'sitepilot-ai' ) ) ); ?></span></div>
				<p><?php echo esc_html( $description ); ?></p>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
			</div>
		</article>
		<?php
	}
}
