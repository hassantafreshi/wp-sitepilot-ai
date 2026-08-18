<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;

/** Phase 1 WooCommerce screen: configuration plus reviewed draft creation. */
final class WooCommerceSettings {

	public const SLUG = 'aisaautopilot-woocommerce';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved  = $this->maybe_save();
		$health = WooCommerceConnector::get_health();
		$woo    = (array) Settings::get( 'woocommerce', array( 'enabled' => true ) );

		$active  = ! empty( $health['active'] );
		$enabled = ! empty( $woo['enabled'] );
		$ready   = $active && ! empty( $health['can_manage'] ) && $enabled;

		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-app--narrow" data-sp-wc>
			<section class="sp-screen">
				<?php Ui::title( __( 'WooCommerce AI', 'ai-site-assistant-autopilot' ), 'cart', true ); ?>
				<p class="sp-lead" style="margin-bottom:16px !important"><?php esc_html_e( 'AI-powered product management: create, edit, search, and bulk-update products using natural language.', 'ai-site-assistant-autopilot' ); ?></p>

				<?php if ( $saved ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<?php if ( $active ) : ?>
					<?php
					Ui::alert(
						'ok',
						'check-circle',
						esc_html(
							sprintf(
								/* translators: %s: WooCommerce version. */
								__( 'WooCommerce %s is active.', 'ai-site-assistant-autopilot' ),
								(string) ( $health['version'] ?: '—' )
							)
						)
					);
					?>
				<?php else : ?>
					<?php
					Ui::alert(
						'warn',
						'warning',
						esc_html__( 'WooCommerce is not installed or active.', 'ai-site-assistant-autopilot' )
							. ' <a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">'
							. esc_html__( 'Install WooCommerce', 'ai-site-assistant-autopilot' ) . '</a>'
					);
					?>
				<?php endif; ?>

				<div class="sp-cols sp-cols--16">
					<form method="post" class="sp-card sp-card--24" style="flex:1 1 min(100%,420px);min-width:0;display:flex;flex-direction:column;gap:16px">
						<?php wp_nonce_field( 'sp_woo_save', 'sp_woo_nonce' ); ?>

						<div>
							<label class="sp-check sp-check--14">
								<input type="checkbox" name="woocommerce[enabled]" value="1" <?php checked( $enabled ); ?> />
								<strong><?php esc_html_e( 'Enable WooCommerce AI Connector', 'ai-site-assistant-autopilot' ); ?></strong>
							</label>
							<p class="sp-help sp-help--12" style="margin:6px 0 0;padding-inline-start:26px"><?php esc_html_e( 'When enabled, users can create, edit, and search products using natural language through the AI Site Assistant AutoPilot chat.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<div class="sp-hr"></div>

						<div>
							<label class="sp-check sp-check--14">
								<input type="checkbox" name="woocommerce[auto_draft]" value="1" checked disabled />
								<span><?php esc_html_e( 'Create products as draft by default', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<p class="sp-help sp-help--12" style="margin:6px 0 0;padding-inline-start:26px"><?php esc_html_e( 'Products are saved as draft and require manual publishing. This is fixed in Phase 1.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<label class="sp-check sp-check--14">
							<input type="checkbox" name="woocommerce[enable_search]" value="1" <?php checked( ! empty( $woo['enable_search'] ) ); ?> />
							<span><?php esc_html_e( 'Enable smart product search', 'ai-site-assistant-autopilot' ); ?></span>
						</label>

						<label class="sp-check sp-check--14">
							<input type="checkbox" name="woocommerce[enable_edit]" value="1" disabled />
							<span style="color:var(--sp-faint)"><?php esc_html_e( 'Enable product editing via AI — Phase 2', 'ai-site-assistant-autopilot' ); ?></span>
						</label>

						<?php Ui::alert( 'soft', 'shield', esc_html__( 'Phase 1 creates only simple product drafts after explicit review. Publishing, edits, bulk operations and order changes are reserved for Phase 2.', 'ai-site-assistant-autopilot' ) ); ?>

						<button type="submit" class="sp-btn sp-btn--r8" style="align-self:flex-start"><?php esc_html_e( 'Save settings', 'ai-site-assistant-autopilot' ); ?></button>
					</form>

					<aside class="sp-card sp-card--24 sp-side--280" style="flex:1 1 min(100%,280px);min-width:0">
						<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'WooCommerce Status', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-kv">
							<div class="sp-kv__row sp-kv__row--ruled">
								<span class="sp-kv__k"><?php esc_html_e( 'Active', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-kv__v" style="color:<?php echo $active ? '#059669' : '#dc2626'; ?>"><?php echo $active ? '&#10003;' : '&#10005;'; ?></span>
							</div>
							<div class="sp-kv__row sp-kv__row--ruled">
								<span class="sp-kv__k"><?php esc_html_e( 'Version', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-kv__v sp-mono"><?php echo esc_html( (string) ( $health['version'] ?: '—' ) ); ?></span>
							</div>
							<div class="sp-kv__row sp-kv__row--ruled">
								<span class="sp-kv__k"><?php esc_html_e( 'Access', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-kv__v" style="color:<?php echo ! empty( $health['can_manage'] ) ? '#059669' : '#dc2626'; ?>"><?php echo ! empty( $health['can_manage'] ) ? '&#10003;' : '&#10005;'; ?></span>
							</div>
							<div class="sp-kv__row sp-kv__row--ruled">
								<span class="sp-kv__k"><?php esc_html_e( 'Categories', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-kv__v"><?php echo esc_html( number_format_i18n( count( WooCommerceConnector::get_categories() ) ) ); ?></span>
							</div>
							<div class="sp-kv__row">
								<span class="sp-kv__k"><?php esc_html_e( 'Total Products', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-kv__v"><?php echo esc_html( number_format_i18n( (int) ( $health['product_count'] ?? 0 ) ) ); ?></span>
							</div>
						</div>
					</aside>
				</div>

				<!-- Reviewed product-draft flow -->
				<div class="sp-card sp-card--24" style="margin-top:16px">
					<div class="sp-card__split">
						<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Create a product draft with AI', 'ai-site-assistant-autopilot' ); ?></h2>
						<?php echo Ui::pill( __( 'Draft only', 'ai-site-assistant-autopilot' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
					</div>
					<p class="sp-sub"><?php esc_html_e( 'Describe one product. Review every generated detail, then create a WooCommerce draft for manual publishing.', 'ai-site-assistant-autopilot' ); ?></p>

					<div id="sp-woo-compose">
						<label class="sp-label" for="sp-woo-prompt"><?php esc_html_e( 'What product should be drafted?', 'ai-site-assistant-autopilot' ); ?></label>
						<textarea id="sp-woo-prompt" class="sp-textarea" rows="5" maxlength="2000" style="min-height:110px" placeholder="<?php esc_attr_e( 'For example: Create a simple draft for a 500g organic Arabic coffee product, with an introductory price and a short product description.', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $ready ); ?>></textarea>
						<div class="sp-form-actions" style="margin-top:12px">
							<button type="button" id="sp-woo-plan" class="sp-btn sp-btn--r8" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Generate draft proposal', 'ai-site-assistant-autopilot' ); ?></button>
							<span id="sp-woo-plan-status" class="sp-status-text" role="status" aria-live="polite"></span>
						</div>
					</div>

					<div id="sp-woo-review" hidden style="margin-top:18px">
						<h3 class="sp-h3"><?php esc_html_e( 'Review the proposal', 'ai-site-assistant-autopilot' ); ?></h3>
						<div id="sp-woo-review-fields" class="sp-kv" style="margin:12px 0"></div>
						<p class="sp-help sp-help--12"><?php esc_html_e( 'Approving creates a single WooCommerce product in draft status. The proposal expires after 15 minutes and cannot be used twice.', 'ai-site-assistant-autopilot' ); ?></p>
						<div class="sp-form-actions" style="margin-top:12px">
							<button type="button" id="sp-woo-approve" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Create product draft', 'ai-site-assistant-autopilot' ); ?></button>
							<button type="button" id="sp-woo-cancel" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Start over', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
					</div>

					<div id="sp-woo-result" hidden aria-live="polite" style="margin-top:18px"></div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['sp_woo_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sp_woo_nonce'] ) ), 'sp_woo_save' ) ) {
			return false;
		}

		$input = isset( $_POST['woocommerce'] ) && is_array( $_POST['woocommerce'] )
			? (array) wp_unslash( $_POST['woocommerce'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only booleans are read below.
			: array();

		Settings::update(
			array(
				'woocommerce' => array(
					'enabled'       => ! empty( $input['enabled'] ),
					'auto_draft'    => true,
					'enable_search' => ! empty( $input['enable_search'] ),
					'enable_edit'   => false,
				),
			)
		);

		return true;
	}
}
