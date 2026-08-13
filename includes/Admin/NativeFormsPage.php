<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\NativeFormConnector;

/**
 * A first-class list for forms made by the independent SitePilot connector.
 */
final class NativeFormsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$forms = get_posts(
			array(
				'post_type'      => NativeFormConnector::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap sitepilot-wrap sitepilot-overview">
			<header class="sitepilot-page-head">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Simple email forms', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><?php esc_html_e( 'SitePilot Forms', 'sitepilot-ai' ); ?></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Manage the simple, single-step forms created by SitePilot. Visitor submissions are sent by email and are not stored here.', 'sitepilot-ai' ); ?></p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER ) ); ?>"><?php esc_html_e( 'Create a form', 'sitepilot-ai' ); ?></a>
			</header>

			<div class="sitepilot-card sitepilot-overview-card">
				<?php if ( array() === $forms ) : ?>
					<p class="sitepilot-empty-state"><?php esc_html_e( 'No SitePilot forms have been created yet.', 'sitepilot-ai' ); ?></p>
				<?php else : ?>
					<div class="sitepilot-table-wrap">
						<table class="widefat striped sitepilot-overview-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Form', 'sitepilot-ai' ); ?></th>
									<th><?php esc_html_e( 'Status', 'sitepilot-ai' ); ?></th>
									<th><?php esc_html_e( 'Shortcode', 'sitepilot-ai' ); ?></th>
									<th><?php esc_html_e( 'Last updated', 'sitepilot-ai' ); ?></th>
									<th class="column-primary"><?php esc_html_e( 'Actions', 'sitepilot-ai' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $forms as $form ) : ?>
									<tr>
										<td><strong><?php echo esc_html( get_the_title( $form ) ); ?></strong></td>
										<td><?php echo esc_html( (string) $form->post_status ); ?></td>
										<td><code><?php echo esc_html( sprintf( '[sitepilot_form id="%d"]', (int) $form->ID ) ); ?></code></td>
										<td><?php echo esc_html( get_the_modified_date( '', $form ) ); ?></td>
										<td><a href="<?php echo esc_url( get_edit_post_link( $form->ID, 'raw' ) ); ?>"><?php esc_html_e( 'Open', 'sitepilot-ai' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
