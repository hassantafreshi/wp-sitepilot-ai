<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;

/**
 * A truthful, server-rendered hub for the plugin's six visible capabilities.
 *
 * The page deliberately only exposes controls that have a persistent backend
 * contract. Capability details without a matching setting remain explanatory.
 */
final class IntegrationsPage {

	private const NONCE_ACTION = 'aisaautopilot_integrations';
	private const NONCE_NAME   = 'aisaautopilot_integrations_nonce';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved         = $this->maybe_save();
		$settings      = Settings::all();
		$woo           = (array) ( $settings['woocommerce'] ?? array() );
		$efb_available = ( new EasyFormBuilderConnector() )->is_available();
		$woo_available = WooCommerceConnector::is_active();
		$answer_on     = ! empty( $settings['public_assistant_enabled'] );
		$form_on       = ! empty( $settings['form_composer_enabled'] );
		$efb_on        = $efb_available && ! empty( $settings['efb_copilot_enabled'] );
		$content_on    = ! empty( $settings['content_studio_enabled'] );
		$store_on      = $woo_available && ! empty( $woo['enabled'] );
		$all_off       = ! $answer_on && ! $form_on && ! $content_on && ( ! $efb_available || ! $efb_on ) && ( ! $woo_available || ! $store_on );

		$install_efb_url = current_user_can( 'install_plugins' )
			? admin_url( 'plugin-install.php?s=easy+form+builder&tab=search&type=term' )
			: admin_url( 'admin.php?page=' . AdminMenu::SLUG_EFB );
		$install_woo_url = current_user_can( 'install_plugins' )
			? admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' )
			: admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD );

		$foundation = array(
			array(
				'id'           => 'site-knowledge',
				'icon'         => 'book-open',
				'title'        => __( 'Site Knowledge', 'ai-site-assistant-autopilot' ),
				'description'  => __( 'Keeps answers grounded in your published site content and its cited sources.', 'ai-site-assistant-autopilot' ),
				'status'       => __( 'Always on', 'ai-site-assistant-autopilot' ),
				'status_key'   => 'always-on',
				'tone'         => 'info',
				'available'    => true,
				'enabled'      => true,
				'toggle'       => false,
				'action'       => 'dialog',
				'action_label' => __( 'Configure', 'ai-site-assistant-autopilot' ),
			),
		);
		$visitors   = array(
			array(
				'id'           => 'answer-desk',
				'icon'         => 'bubble',
				'title'        => __( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				'description'  => __( 'Lets visitors ask grounded questions through the read-only public assistant.', 'ai-site-assistant-autopilot' ),
				'status'       => $answer_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'status_key'   => $answer_on ? 'on' : 'off',
				'tone'         => $answer_on ? 'ok' : 'off',
				'available'    => true,
				'enabled'      => $answer_on,
				'toggle'       => true,
				'action'       => 'dialog',
				'action_label' => __( 'Configure', 'ai-site-assistant-autopilot' ),
			),
		);
		$making     = array(
			array(
				'id'           => 'form-composer',
				'icon'         => 'form-lines',
				'title'        => __( 'Form Studio', 'ai-site-assistant-autopilot' ),
				'description'  => __( 'Creates reviewable native forms with email-only delivery and server-side validation.', 'ai-site-assistant-autopilot' ),
				'status'       => $form_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'status_key'   => $form_on ? 'on' : 'off',
				'tone'         => $form_on ? 'ok' : 'off',
				'available'    => true,
				'enabled'      => $form_on,
				'toggle'       => true,
				'action'       => 'dialog',
				'action_label' => __( 'Configure', 'ai-site-assistant-autopilot' ),
			),
			array(
				'id'           => 'efb-copilot',
				'icon'         => 'layers',
				'title'        => __( 'Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ),
				'description'  => $efb_available
					? __( 'Adds an approved AI helper to a compatible Easy Form Builder editor.', 'ai-site-assistant-autopilot' )
					: __( 'A compatible Easy Form Builder installation is required before this helper can run.', 'ai-site-assistant-autopilot' ),
				'status'       => $efb_available
					? ( $efb_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) )
					: __( 'Needs Easy Form Builder', 'ai-site-assistant-autopilot' ),
				'status_key'   => $efb_available ? ( $efb_on ? 'on' : 'off' ) : 'needs-efb',
				'tone'         => $efb_available ? ( $efb_on ? 'ok' : 'off' ) : 'warn',
				'available'    => $efb_available,
				'enabled'      => $efb_on,
				'toggle'       => $efb_available,
				'action'       => $efb_available ? 'dialog' : 'link',
				'action_label' => $efb_available ? __( 'Configure', 'ai-site-assistant-autopilot' ) : __( 'Install Easy Form Builder', 'ai-site-assistant-autopilot' ),
				'action_url'   => $install_efb_url,
			),
			array(
				'id'           => 'content-studio',
				'icon'         => 'pencil',
				'title'        => __( 'Content Studio', 'ai-site-assistant-autopilot' ),
				'description'  => __( 'Creates reviewable WordPress page and post drafts; publishing always stays with you.', 'ai-site-assistant-autopilot' ),
				'status'       => $content_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'status_key'   => $content_on ? 'on' : 'off',
				'tone'         => $content_on ? 'ok' : 'off',
				'available'    => true,
				'enabled'      => $content_on,
				'toggle'       => true,
				'action'       => 'dialog',
				'action_label' => __( 'Configure', 'ai-site-assistant-autopilot' ),
			),
		);
		$store      = array(
			array(
				'id'           => 'store-copilot',
				'icon'         => 'cart-mock',
				'title'        => __( 'Store Copilot', 'ai-site-assistant-autopilot' ),
				'description'  => $woo_available
					? __( 'Creates reviewed simple-product drafts and presents safe store insights.', 'ai-site-assistant-autopilot' )
					: __( 'WooCommerce must be installed and active before Store Copilot can be used.', 'ai-site-assistant-autopilot' ),
				'status'       => $woo_available
					? ( $store_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) )
					: __( 'Needs WooCommerce', 'ai-site-assistant-autopilot' ),
				'status_key'   => $woo_available ? ( $store_on ? 'on' : 'off' ) : 'needs-woocommerce',
				'tone'         => $woo_available ? ( $store_on ? 'ok' : 'off' ) : 'warn',
				'available'    => $woo_available,
				'enabled'      => $store_on,
				'toggle'       => $woo_available,
				'action'       => 'link',
				'action_label' => $woo_available ? __( 'Open settings', 'ai-site-assistant-autopilot' ) : __( 'Install WooCommerce', 'ai-site-assistant-autopilot' ),
				'action_url'   => $woo_available
					? admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD . '&tab=capabilities' )
					: $install_woo_url,
			),
		);
		?>
		<div class="wrap sp-app sp-app--narrow sp-integrations" data-sp-integrations>
			<?php Ui::grad_defs(); ?>
			<div class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Integrations', 'ai-site-assistant-autopilot' ),
					__( 'Each one is a job AutoPilot can do. Turn on only what you need.', 'ai-site-assistant-autopilot' ),
					array( 'chip' => ConnectionChip::derive() )
				);
				?>
				<div class="sp-pagebody sp-integrations__body">
					<?php if ( $saved ) : ?>
						<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Integration settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
					<?php endif; ?>

					<?php if ( $all_off ) : ?>
						<?php $this->render_all_off_banner(); ?>
					<?php endif; ?>

					<div class="sp-integrations__groups">
						<?php $this->render_group( 'foundation', __( 'Foundation', 'ai-site-assistant-autopilot' ), $foundation ); ?>
						<?php $this->render_group( 'visitors', __( 'Talking to visitors', 'ai-site-assistant-autopilot' ), $visitors ); ?>
						<?php $this->render_group( 'making', __( 'Making things', 'ai-site-assistant-autopilot' ), $making ); ?>
						<?php $this->render_group( 'store', __( 'Your store', 'ai-site-assistant-autopilot' ), $store ); ?>
					</div>

					<?php $this->render_dialogs( $settings, $efb_available ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Visible cards in one mockup group.
	 */
	private function render_group( string $id, string $title, array $cards ): void {
		?>
		<section class="sp-integrations__group" data-sp-integration-group="<?php echo esc_attr( $id ); ?>">
			<h2 class="sp-h2 sp-integrations__section"><?php echo esc_html( $title ); ?></h2>
			<div class="sp-integrations__grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php $this->render_card( $card ); ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $card A normalized, non-user-controlled capability descriptor.
	 */
	private function render_card( array $card ): void {
		$id           = (string) $card['id'];
		$title        = (string) $card['title'];
		$enabled      = ! empty( $card['enabled'] );
		$has_toggle   = ! empty( $card['toggle'] );
		$action       = (string) $card['action'];
		$action_label = (string) $card['action_label'];
		?>
		<article class="sp-card sp-card--flat sp-integration<?php echo $enabled ? ' sp-integration--on' : ''; ?>" data-sp-integration-card="<?php echo esc_attr( $id ); ?>">
			<div class="sp-integration__head">
				<span class="sp-integration__icon"><?php echo Ui::icon( (string) $card['icon'], 18, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-integration__headcopy">
					<h3 class="sp-h3 sp-integration__title"><?php echo esc_html( $title ); ?></h3>
					<span class="sp-integration__status sp-integration__status--<?php echo esc_attr( (string) $card['tone'] ); ?>" data-sp-integration-status="<?php echo esc_attr( (string) $card['status_key'] ); ?>"><?php echo esc_html( (string) $card['status'] ); ?></span>
				</div>
				<?php if ( $has_toggle ) : ?>
					<?php $this->render_toggle( $id, $title, $enabled ); ?>
				<?php endif; ?>
			</div>
			<p class="sp-help sp-help--12 sp-integration__description"><?php echo esc_html( (string) $card['description'] ); ?></p>
			<div class="sp-integration__foot">
				<?php if ( 'dialog' === $action ) : ?>
					<button class="sp-integration__action" type="button" data-sp-integration-open="<?php echo esc_attr( $id ); ?>" aria-controls="sp-integration-dialog-<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( $action_label ); ?>
						<?php echo Ui::icon( 'chevron', 13, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?>
					</button>
				<?php else : ?>
					<a class="sp-integration__action" href="<?php echo esc_url( (string) $card['action_url'] ); ?>">
						<?php echo esc_html( $action_label ); ?>
						<?php echo Ui::icon( 'chevron', 13, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private function render_toggle( string $id, string $title, bool $enabled ): void {
		$label = $enabled
			? sprintf(
				/* translators: %s: integration name. */
				__( 'Turn %s off', 'ai-site-assistant-autopilot' ),
				$title
			)
			: sprintf(
				/* translators: %s: integration name. */
				__( 'Turn %s on', 'ai-site-assistant-autopilot' ),
				$title
			);
		?>
		<form class="sp-integration__toggle-form" method="post">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="aisaautopilot_integration_action" value="toggle" />
			<input type="hidden" name="aisaautopilot_integration" value="<?php echo esc_attr( $id ); ?>" />
			<input type="hidden" name="aisaautopilot_enabled" value="<?php echo $enabled ? '0' : '1'; ?>" />
			<button class="sp-integration__toggle" type="submit" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-sp-integration-toggle="<?php echo esc_attr( $id ); ?>">
				<span aria-hidden="true"></span>
			</button>
		</form>
		<?php
	}

	private function render_all_off_banner(): void {
		?>
		<section class="sp-integration-banner" data-sp-integrations-all-off>
			<span class="sp-integration-banner__icon"><?php echo Ui::icon( 'info', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
			<div>
				<h2 class="sp-h3 sp-mb0"><?php esc_html_e( 'Everything is switched off', 'ai-site-assistant-autopilot' ); ?></h2>
				<p class="sp-help sp-help--12"><?php esc_html_e( 'Turn on the public assistant or one of the creation tools when you are ready to use it.', 'ai-site-assistant-autopilot' ); ?></p>
			</div>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="aisaautopilot_integration_action" value="toggle" />
				<input type="hidden" name="aisaautopilot_integration" value="answer-desk" />
				<input type="hidden" name="aisaautopilot_enabled" value="1" />
				<button class="sp-btn sp-btn--xs" type="submit"><?php esc_html_e( 'Turn on Answer Desk', 'ai-site-assistant-autopilot' ); ?></button>
			</form>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings Merged plugin settings.
	 */
	private function render_dialogs( array $settings, bool $efb_available ): void {
		$widget_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIDGET );
		?>
		<dialog class="sp-integration-dialog" id="sp-integration-dialog-site-knowledge" data-sp-integration-dialog="site-knowledge" aria-labelledby="sp-integration-title-site-knowledge">
			<?php $this->dialog_heading( 'site-knowledge', __( 'Site Knowledge', 'ai-site-assistant-autopilot' ), __( 'The core knowledge layer stays available. These controls only change how new content is indexed.', 'ai-site-assistant-autopilot' ) ); ?>
			<form method="post" class="sp-integration-dialog__form">
				<?php $this->dialog_fields( 'site-knowledge' ); ?>
				<?php $this->render_config_checkbox( 'kb_auto_index', __( 'Automatically index new published content', 'ai-site-assistant-autopilot' ), __( 'Schedules safe indexing only after a post is published.', 'ai-site-assistant-autopilot' ), ! empty( $settings['kb_auto_index'] ) ); ?>
				<?php $this->render_locked_row( __( 'Content scope', 'ai-site-assistant-autopilot' ), __( 'Published site content only', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->render_locked_row( __( 'Bulk crawling', 'ai-site-assistant-autopilot' ), __( 'Not available in this release', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->dialog_actions(); ?>
			</form>
		</dialog>

		<dialog class="sp-integration-dialog" id="sp-integration-dialog-answer-desk" data-sp-integration-dialog="answer-desk" aria-labelledby="sp-integration-title-answer-desk">
			<?php $this->dialog_heading( 'answer-desk', __( 'Answer Desk', 'ai-site-assistant-autopilot' ), __( 'Control the read-only public assistant separately from its visual widget settings.', 'ai-site-assistant-autopilot' ) ); ?>
			<form method="post" class="sp-integration-dialog__form">
				<?php $this->dialog_fields( 'answer-desk' ); ?>
				<?php $this->render_config_checkbox( 'public_assistant_enabled', __( 'Enable the public assistant', 'ai-site-assistant-autopilot' ), __( 'Allows visitors to ask grounded questions; it never edits site content.', 'ai-site-assistant-autopilot' ), ! empty( $settings['public_assistant_enabled'] ) ); ?>
				<?php $this->render_config_checkbox( 'voice_responses_enabled', __( 'Enable voice responses', 'ai-site-assistant-autopilot' ), __( 'Uses the configured voice response capability when it is available.', 'ai-site-assistant-autopilot' ), ! empty( $settings['voice_responses_enabled'] ) ); ?>
				<a class="sp-integration-dialog__link" href="<?php echo esc_url( $widget_url ); ?>"><?php esc_html_e( 'Customize the visitor widget', 'ai-site-assistant-autopilot' ); ?> <span aria-hidden="true">&rarr;</span></a>
				<?php $this->dialog_actions(); ?>
			</form>
		</dialog>

		<dialog class="sp-integration-dialog" id="sp-integration-dialog-form-composer" data-sp-integration-dialog="form-composer" aria-labelledby="sp-integration-title-form-composer">
			<?php $this->dialog_heading( 'form-composer', __( 'Form Studio', 'ai-site-assistant-autopilot' ), __( 'The form workflow creates proposals for review and keeps visitor submissions out of WordPress.', 'ai-site-assistant-autopilot' ) ); ?>
			<form method="post" class="sp-integration-dialog__form">
				<?php $this->dialog_fields( 'form-composer' ); ?>
				<?php $this->render_config_checkbox( 'form_composer_enabled', __( 'Enable Form Studio', 'ai-site-assistant-autopilot' ), __( 'Makes the native-form planning and execution endpoints available to administrators.', 'ai-site-assistant-autopilot' ), ! empty( $settings['form_composer_enabled'] ) ); ?>
				<?php $this->render_locked_row( __( 'Submission storage', 'ai-site-assistant-autopilot' ), __( 'Email delivery only; personal data is not stored here', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->render_locked_row( __( 'Validation', 'ai-site-assistant-autopilot' ), __( 'Server-side validation is always required', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->dialog_actions(); ?>
			</form>
		</dialog>

		<?php if ( $efb_available ) : ?>
			<dialog class="sp-integration-dialog" id="sp-integration-dialog-efb-copilot" data-sp-integration-dialog="efb-copilot" aria-labelledby="sp-integration-title-efb-copilot">
				<?php $this->dialog_heading( 'efb-copilot', __( 'Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ), __( 'The helper appears only in a compatible Easy Form Builder editor.', 'ai-site-assistant-autopilot' ) ); ?>
				<form method="post" class="sp-integration-dialog__form">
					<?php $this->dialog_fields( 'efb-copilot' ); ?>
					<?php $this->render_config_checkbox( 'efb_copilot_enabled', __( 'Enable Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ), __( 'Allows the approved helper panel and its protected administrator routes.', 'ai-site-assistant-autopilot' ), ! empty( $settings['efb_copilot_enabled'] ) ); ?>
					<?php $this->render_locked_row( __( 'Theme matching', 'ai-site-assistant-autopilot' ), __( 'Owned by Easy Form Builder', 'ai-site-assistant-autopilot' ) ); ?>
					<?php $this->render_locked_row( __( 'Calendar behavior', 'ai-site-assistant-autopilot' ), __( 'Owned by Easy Form Builder', 'ai-site-assistant-autopilot' ) ); ?>
					<?php $this->dialog_actions(); ?>
				</form>
			</dialog>
		<?php endif; ?>

		<dialog class="sp-integration-dialog" id="sp-integration-dialog-content-studio" data-sp-integration-dialog="content-studio" aria-labelledby="sp-integration-title-content-studio">
			<?php $this->dialog_heading( 'content-studio', __( 'Content Studio', 'ai-site-assistant-autopilot' ), __( 'Keep the reviewed draft workflow available only when you need it.', 'ai-site-assistant-autopilot' ) ); ?>
			<form method="post" class="sp-integration-dialog__form">
				<?php $this->dialog_fields( 'content-studio' ); ?>
				<?php $this->render_config_checkbox( 'content_studio_enabled', __( 'Enable Content Studio', 'ai-site-assistant-autopilot' ), __( 'Makes content planning and approved draft creation available to administrators.', 'ai-site-assistant-autopilot' ), ! empty( $settings['content_studio_enabled'] ) ); ?>
				<?php $this->render_locked_row( __( 'Publishing', 'ai-site-assistant-autopilot' ), __( 'Draft only; an administrator always approves publishing', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->render_locked_row( __( 'Source of truth', 'ai-site-assistant-autopilot' ), __( 'Your reviewed brief and WordPress draft', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->dialog_actions(); ?>
			</form>
		</dialog>
		<?php
	}

	private function dialog_heading( string $id, string $title, string $description ): void {
		?>
		<div class="sp-integration-dialog__heading">
			<div>
				<h2 class="sp-h2 sp-mb0" id="sp-integration-title-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h2>
				<p class="sp-help sp-help--12"><?php echo esc_html( $description ); ?></p>
			</div>
			<button class="sp-integration-dialog__close" type="button" data-sp-integration-close aria-label="<?php esc_attr_e( 'Close dialog', 'ai-site-assistant-autopilot' ); ?>">&times;</button>
		</div>
		<?php
	}

	private function dialog_fields( string $id ): void {
		?>
		<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
		<input type="hidden" name="aisaautopilot_integration_action" value="configure" />
		<input type="hidden" name="aisaautopilot_integration" value="<?php echo esc_attr( $id ); ?>" />
		<?php
	}

	private function render_config_checkbox( string $name, string $label, string $description, bool $checked ): void {
		?>
		<label class="sp-integration-config">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
			<span class="sp-integration-config__copy">
				<strong><?php echo esc_html( $label ); ?></strong>
				<small><?php echo esc_html( $description ); ?></small>
			</span>
		</label>
		<?php
	}

	private function render_locked_row( string $label, string $value ): void {
		?>
		<div class="sp-integration-config sp-integration-config--locked">
			<span class="sp-integration-config__copy">
				<strong><?php echo esc_html( $label ); ?></strong>
				<small><?php echo esc_html( $value ); ?></small>
			</span>
			<span class="sp-integration-config__locked"><?php esc_html_e( 'Locked', 'ai-site-assistant-autopilot' ); ?></span>
		</div>
		<?php
	}

	private function dialog_actions(): void {
		?>
		<div class="sp-integration-dialog__actions">
			<button class="sp-btn sp-btn--ghost" type="button" data-sp-integration-close><?php esc_html_e( 'Close', 'ai-site-assistant-autopilot' ); ?></button>
			<button class="sp-btn" type="submit"><?php esc_html_e( 'Done', 'ai-site-assistant-autopilot' ); ?></button>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['aisaautopilot_integration'] ) ) {
			return false;
		}

		$nonce = $this->post_value( self::NONCE_NAME );
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}

		$id     = sanitize_key( $this->post_value( 'aisaautopilot_integration' ) );
		$action = sanitize_key( $this->post_value( 'aisaautopilot_integration_action' ) );
		if ( 'toggle' === $action ) {
			return $this->save_toggle( $id, $this->posted_enabled( 'aisaautopilot_enabled' ) );
		}
		if ( 'configure' === $action ) {
			return $this->save_configuration( $id );
		}

		return false;
	}

	private function save_toggle( string $id, bool $enabled ): bool {
		switch ( $id ) {
			case 'answer-desk':
				Settings::update( array( 'public_assistant_enabled' => $enabled ) );
				return true;
			case 'form-composer':
				Settings::update( array( 'form_composer_enabled' => $enabled ) );
				return true;
			case 'efb-copilot':
				if ( ! ( new EasyFormBuilderConnector() )->is_available() ) {
					return false;
				}
				Settings::update( array( 'efb_copilot_enabled' => $enabled ) );
				return true;
			case 'content-studio':
				Settings::update( array( 'content_studio_enabled' => $enabled ) );
				return true;
			case 'store-copilot':
				if ( ! WooCommerceConnector::is_active() ) {
					return false;
				}
				$woo            = WooCommerceConnector::get_settings();
				$woo['enabled'] = $enabled;
				Settings::update( array( 'woocommerce' => $woo ) );
				return true;
		}

		return false;
	}

	private function save_configuration( string $id ): bool {
		switch ( $id ) {
			case 'site-knowledge':
				Settings::update( array( 'kb_auto_index' => $this->posted_enabled( 'kb_auto_index' ) ) );
				return true;
			case 'answer-desk':
				Settings::update(
					array(
						'public_assistant_enabled' => $this->posted_enabled( 'public_assistant_enabled' ),
						'voice_responses_enabled'  => $this->posted_enabled( 'voice_responses_enabled' ),
					)
				);
				return true;
			case 'form-composer':
				Settings::update( array( 'form_composer_enabled' => $this->posted_enabled( 'form_composer_enabled' ) ) );
				return true;
			case 'efb-copilot':
				if ( ! ( new EasyFormBuilderConnector() )->is_available() ) {
					return false;
				}
				Settings::update( array( 'efb_copilot_enabled' => $this->posted_enabled( 'efb_copilot_enabled' ) ) );
				return true;
			case 'content-studio':
				Settings::update( array( 'content_studio_enabled' => $this->posted_enabled( 'content_studio_enabled' ) ) );
				return true;
		}

		return false;
	}

	private function posted_enabled( string $key ): bool {
		return '1' === $this->post_value( $key );
	}

	private function post_value( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}
}
