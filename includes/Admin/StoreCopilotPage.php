<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\WooCommerceAnalyticsSkill;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Canonical Store Copilot surface.
 *
 * Phase 1 deliberately has four mockup-aligned tabs: proposal to reviewed
 * draft, capability/status settings, a read-only product list, and an honest
 * order-access contract. Publishing, general editing and order mutations are never
 * rendered as usable actions. The one exception is Descriptions: reviewed AI
 * descriptions for existing products, off until the store manager turns them on,
 * one approval per product, with Undo (Connectors\ProductDescriptions).
 */
final class StoreCopilotPage {

	private const TABS = array(
		'newproduct'   => 'New product',
		'capabilities' => 'What it can do',
		'products'     => 'Products',
		'descriptions' => 'Descriptions',
		'orders'       => 'Orders',
	);

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$tab            = $this->current_tab();
		$health         = WooCommerceConnector::get_health();
		$settings       = WooCommerceConnector::get_settings();
		$active         = ! empty( $health['active'] );
		$can_manage     = ! empty( $health['can_manage'] );
		$enabled        = ! empty( $settings['enabled'] );
		$provider_ready = ( new ProviderManager() )->is_configured();
		$proposal_ready = $active && $can_manage && $enabled && $provider_ready;

		Ui::grad_defs();
		?>
		<div
			class="wrap sp-app"
			data-sp-wc-dashboard
			data-sp-store-copilot
			data-sp-store-tab="<?php echo esc_attr( $tab ); ?>"
			data-sp-store-active="<?php echo $active ? '1' : '0'; ?>"
			data-sp-store-enabled="<?php echo $enabled ? '1' : '0'; ?>"
			data-sp-store-search="<?php echo ! empty( $settings['enable_search'] ) ? '1' : '0'; ?>"
			data-sp-store-descriptions="<?php echo ! empty( $settings['enable_descriptions'] ) ? '1' : '0'; ?>"
			data-sp-store-provider="<?php echo $provider_ready ? '1' : '0'; ?>"
			data-sp-store-can-manage="<?php echo $can_manage ? '1' : '0'; ?>"
		>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Store Copilot', 'ai-site-assistant-autopilot' ),
					__( 'Propose one product at a time. You review it, AutoPilot saves a draft.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'    => ConnectionChip::derive(),
						'subtabs' => $this->subtabs( $tab ),
					)
				);
				?>
				<div class="sp-pagebody sp-store-page">
					<?php $this->render_availability_notice( $active, $can_manage, $enabled ); ?>

					<?php
					switch ( $tab ) {
						case 'capabilities':
							$this->render_capabilities( $health, $settings, $provider_ready );
							break;
						case 'products':
							$this->render_products( $active, $enabled );
							break;
						case 'descriptions':
							$this->render_descriptions( $active && $enabled && $can_manage, ! empty( $settings['enable_descriptions'] ), $provider_ready );
							break;
						case 'orders':
							$this->render_orders( $active, $enabled, $can_manage );
							break;
						case 'newproduct':
						default:
							$this->render_new_product( $proposal_ready, $active, $enabled, $can_manage, $provider_ready );
							break;
					}
					?>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-store-i18n',
			array(
				'proposing'        => __( 'Writing the proposal...', 'ai-site-assistant-autopilot' ),
				'proposal_ready'   => __( 'Read the proposal before creating the draft.', 'ai-site-assistant-autopilot' ),
				'creating'         => __( 'Creating the WooCommerce draft...', 'ai-site-assistant-autopilot' ),
				'draft_created'    => __( 'Draft created. Publishing remains a manual WooCommerce step.', 'ai-site-assistant-autopilot' ),
				'network_error'    => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
				'prompt_required'  => __( 'Describe the product you want to draft.', 'ai-site-assistant-autopilot' ),
				'proposal_expired' => __( 'This proposal expired before it was approved. Nothing was created.', 'ai-site-assistant-autopilot' ),
				'draft_only'       => __( 'Draft only', 'ai-site-assistant-autopilot' ),
				'not_managed'      => __( 'Not managed', 'ai-site-assistant-autopilot' ),
				'settings_saved'   => __( 'Store Copilot settings saved.', 'ai-site-assistant-autopilot' ),
				'settings_failed'  => __( 'The Store Copilot settings could not be saved.', 'ai-site-assistant-autopilot' ),
				/* translators: %s: time left before the proposal expires, as m:ss. */
				'expires_in'       => __( 'Expires in %s', 'ai-site-assistant-autopilot' ),
				'review_draft'     => __( 'Review product draft', 'ai-site-assistant-autopilot' ),
				'lock_simple'      => __( 'Phase 1 creates simple products only', 'ai-site-assistant-autopilot' ),
				'lock_draft'       => __( 'Phase 1 always creates a draft', 'ai-site-assistant-autopilot' ),
				'desc_loading'     => __( 'Loading products…', 'ai-site-assistant-autopilot' ),
				'desc_none'        => __( 'Every product already has a description of at least 40 words. Clear the filter to rewrite one anyway.', 'ai-site-assistant-autopilot' ),
				/* translators: %s: number of words in the current description. */
				'desc_words'       => __( '%s words now', 'ai-site-assistant-autopilot' ),
				'desc_empty'       => __( 'No description yet', 'ai-site-assistant-autopilot' ),
				/* translators: 1: number of drafts written so far, 2: number of drafts asked for. */
				'desc_writing'     => __( 'Writing draft %1$s of %2$s…', 'ai-site-assistant-autopilot' ),
				'desc_written'     => __( 'Drafts are ready. Read each one, edit it if you like, then apply or skip it.', 'ai-site-assistant-autopilot' ),
				'desc_limit'       => __( 'Pick up to 10 products at a time.', 'ai-site-assistant-autopilot' ),
				'desc_current'     => __( 'Now', 'ai-site-assistant-autopilot' ),
				'desc_draft'       => __( 'AI draft (you can edit it)', 'ai-site-assistant-autopilot' ),
				'desc_apply'       => __( 'Apply', 'ai-site-assistant-autopilot' ),
				'desc_skip'        => __( 'Skip', 'ai-site-assistant-autopilot' ),
				'desc_undo'        => __( 'Undo', 'ai-site-assistant-autopilot' ),
				'desc_applied'     => __( 'Applied', 'ai-site-assistant-autopilot' ),
				'desc_skipped'     => __( 'Skipped. Nothing changed.', 'ai-site-assistant-autopilot' ),
				'desc_failed'      => __( 'No draft for this product.', 'ai-site-assistant-autopilot' ),
				'desc_edit_label'  => __( 'Description', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	private function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'newproduct'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		return array_key_exists( $tab, self::TABS ) ? $tab : 'newproduct';
	}

	/**
	 * Sub-tab definitions preserve the exact Store order from the mockup.
	 *
	 * @return array<int,array{id:string,label:string,url:string,active:bool}>
	 */
	private function subtabs( string $current ): array {
		$base  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD );
		$items = array(
			'newproduct'   => __( 'New product', 'ai-site-assistant-autopilot' ),
			'capabilities' => __( 'What it can do', 'ai-site-assistant-autopilot' ),
			'products'     => __( 'Products', 'ai-site-assistant-autopilot' ),
			'descriptions' => __( 'Descriptions', 'ai-site-assistant-autopilot' ),
			'orders'       => __( 'Orders', 'ai-site-assistant-autopilot' ),
		);
		$tabs  = array();
		foreach ( $items as $key => $label ) {
			$tabs[] = array(
				'id'     => $key,
				'label'  => $label,
				'url'    => add_query_arg( 'tab', $key, $base ),
				'active' => $current === $key,
			);
		}
		return $tabs;
	}

	private function render_availability_notice( bool $active, bool $can_manage, bool $enabled ): void {
		if ( ! $active ) {
			$install_url = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
			Ui::alert(
				'warn',
				'warning',
				sprintf(
					/* translators: %s: WooCommerce installation URL. */
					__( 'WooCommerce is not active. Store Copilot cannot read products, orders, or create drafts until it is active. <a href="%s">Install WooCommerce</a>.', 'ai-site-assistant-autopilot' ),
					esc_url( $install_url )
				)
			);
			return;
		}

		if ( ! $can_manage ) {
			Ui::alert(
				'warn',
				'warning',
				esc_html__( 'Your account does not have WooCommerce management access. Store Copilot remains read-only and cannot create a proposal.', 'ai-site-assistant-autopilot' )
			);
			return;
		}

		if ( ! $enabled ) {
			Ui::alert(
				'warn',
				'warning',
				esc_html__( 'The WooCommerce AI connector is paused. It cannot read store data or create a draft until you turn it on in What it can do.', 'ai-site-assistant-autopilot' )
			);
		}
	}

	private function render_new_product( bool $proposal_ready, bool $active, bool $enabled, bool $can_manage, bool $provider_ready ): void {
		?>
		<div class="sp-store-compose-wrap" data-sp-store-new-product>
			<section class="sp-card sp-card--24 sp-store-compose" id="sp-woo-compose">
				<h2 class="sp-h2 sp-h2--md"><?php esc_html_e( 'Describe the product', 'ai-site-assistant-autopilot' ); ?></h2>
				<p class="sp-sub"><?php esc_html_e( 'One product at a time. AutoPilot writes a proposal, you read it, and only then does it create a draft in WooCommerce.', 'ai-site-assistant-autopilot' ); ?></p>
				<label class="sp-sr" for="sp-woo-prompt"><?php esc_html_e( 'Product draft prompt', 'ai-site-assistant-autopilot' ); ?></label>
				<textarea id="sp-woo-prompt" class="sp-textarea" rows="5" maxlength="2000" placeholder="<?php esc_attr_e( 'A linen camp collar shirt for summer, 89 euros, in Shirts...', 'ai-site-assistant-autopilot' ); ?>" <?php disabled( ! $proposal_ready ); ?>></textarea>
				<div class="sp-store-compose__actions">
					<button type="button" id="sp-woo-plan" class="sp-btn sp-btn--r8" <?php disabled( ! $proposal_ready ); ?>><?php esc_html_e( 'Write proposal', 'ai-site-assistant-autopilot' ); ?></button>
					<span class="sp-status-text" id="sp-woo-plan-status" role="status" aria-live="polite"></span>
					<span class="sp-store-compose__note"><?php esc_html_e( 'Nothing is written to your store at this step.', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
				<?php if ( ! $proposal_ready ) : ?>
					<p class="sp-help sp-help--12 sp-store-compose__blocked">
						<?php echo esc_html( $this->proposal_block_reason( $active, $enabled, $can_manage, $provider_ready ) ); ?>
					</p>
				<?php endif; ?>
				<div class="sp-store-examples" aria-label="<?php esc_attr_e( 'Product examples', 'ai-site-assistant-autopilot' ); ?>">
					<span class="sp-store-examples__label"><?php esc_html_e( 'Try one of these', 'ai-site-assistant-autopilot' ); ?></span>
					<button type="button" class="sp-store-example" data-sp-store-example="<?php esc_attr_e( 'A simple 500g organic Arabic coffee, with a short description, SKU and introductory price.', 'ai-site-assistant-autopilot' ); ?>"><?php esc_html_e( 'A simple 500g organic Arabic coffee with a SKU and introductory price', 'ai-site-assistant-autopilot' ); ?></button>
					<button type="button" class="sp-store-example" data-sp-store-example="<?php esc_attr_e( 'A linen camp collar shirt for summer, 89 euros, in Shirts.', 'ai-site-assistant-autopilot' ); ?>"><?php esc_html_e( 'A linen camp collar shirt for summer, 89 euros, in Shirts', 'ai-site-assistant-autopilot' ); ?></button>
					<button type="button" class="sp-store-example" data-sp-store-example="<?php esc_attr_e( 'A simple ceramic travel mug, with a short description and stock status.', 'ai-site-assistant-autopilot' ); ?>"><?php esc_html_e( 'A simple ceramic travel mug with stock status', 'ai-site-assistant-autopilot' ); ?></button>
				</div>
			</section>

			<section class="sp-card sp-store-proposal" id="sp-woo-review" data-sp-store-proposal hidden>
				<div class="sp-store-proposal__head">
					<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Proposal - read it before approving', 'ai-site-assistant-autopilot' ); ?></h2>
					<?php echo Ui::pill( __( 'Not created yet', 'ai-site-assistant-autopilot' ), 'info', false, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Ui. ?>
					<span class="sp-store-proposal__ttl" id="sp-woo-ttl" aria-live="polite"></span>
				</div>
				<div class="sp-store-proposal__summary" id="sp-woo-review-summary"></div>
				<div class="sp-kv sp-store-proposal__fields" id="sp-woo-review-fields"></div>
				<div class="sp-store-proposal__actions">
					<button type="button" id="sp-woo-approve" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Approve - create the draft', 'ai-site-assistant-autopilot' ); ?></button>
					<button type="button" id="sp-woo-regenerate" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Propose again', 'ai-site-assistant-autopilot' ); ?></button>
					<button type="button" id="sp-woo-cancel" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Discard', 'ai-site-assistant-autopilot' ); ?></button>
					<span class="sp-store-proposal__note"><?php esc_html_e( 'Creates a draft only. You publish it yourself.', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
			</section>

			<section class="sp-store-result" id="sp-woo-result" data-sp-store-result hidden aria-live="polite"></section>
		</div>
		<?php
	}

	private function proposal_block_reason( bool $active, bool $enabled, bool $can_manage, bool $provider_ready ): string {
		if ( ! $active ) {
			return __( 'Activate WooCommerce before Store Copilot can make a proposal.', 'ai-site-assistant-autopilot' );
		}
		if ( ! $can_manage ) {
			return __( 'WooCommerce management access is required before a proposal can be approved.', 'ai-site-assistant-autopilot' );
		}
		if ( ! $enabled ) {
			return __( 'Turn on the WooCommerce AI connector in What it can do before writing a proposal.', 'ai-site-assistant-autopilot' );
		}
		if ( ! $provider_ready ) {
			return __( 'Connect an AI provider in Setup before Store Copilot can write a proposal.', 'ai-site-assistant-autopilot' );
		}
		return '';
	}

	/** @param array<string,mixed> $health @param array<string,bool> $settings */
	private function render_capabilities( array $health, array $settings, bool $provider_ready ): void {
		$capabilities = (array) ( $health['capabilities'] ?? array() );
		$active       = ! empty( $health['active'] );
		$can_manage   = ! empty( $health['can_manage'] );
		$enabled      = ! empty( $settings['enabled'] );
		$woo_status   = $active
			? sprintf(
				/* translators: %s: active WooCommerce version. */
				__( 'Active · %s', 'ai-site-assistant-autopilot' ),
				(string) ( $health['version'] ?: '-' )
			)
			: __( 'Not active', 'ai-site-assistant-autopilot' );
		?>
		<div class="sp-store-grid" data-sp-store-capabilities>
			<section class="sp-card sp-store-panel sp-store-capabilities">
				<div class="sp-store-panel__head">
					<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Phase 1 capabilities', 'ai-site-assistant-autopilot' ); ?></h2>
					<span class="sp-store-panel__sub"><?php esc_html_e( 'What the connector is allowed to do today', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
				<?php
				$this->capability_group(
					__( 'Products', 'ai-site-assistant-autopilot' ),
					array(
						array( __( 'Read your products and categories', 'ai-site-assistant-autopilot' ), ! empty( $capabilities['products']['read'] ), '' ),
						array( __( 'Propose one simple product as a draft', 'ai-site-assistant-autopilot' ), ! empty( $capabilities['products']['create_draft'] ) && $provider_ready, '' ),
						array( __( 'Publish a product', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ),
						array( __( 'Edit a product that already exists', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ),
						array( __( 'Change many products at once', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ),
					)
				);
				$this->capability_group(
					__( 'Orders', 'ai-site-assistant-autopilot' ),
					array(
						array( __( 'Read a recent order summary as a store manager', 'ai-site-assistant-autopilot' ), ! empty( $capabilities['orders']['read'] ), '' ),
						array( __( 'Change an order', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ),
						array( __( 'Issue a refund', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ),
					)
				);
				$this->capability_group(
					__( 'Analytics', 'ai-site-assistant-autopilot' ),
					array(
						array( __( 'Read store analytics', 'ai-site-assistant-autopilot' ), ! empty( $capabilities['analytics']['read'] ), '' ),
					)
				);
				?>
			</section>

			<aside class="sp-store-side">
				<section class="sp-card sp-store-panel" data-sp-store-status>
					<div class="sp-store-panel__head"><h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></h2></div>
					<div class="sp-kv sp-store-status-list">
						<?php $this->status_row( __( 'WooCommerce', 'ai-site-assistant-autopilot' ), $woo_status, $active ? 'ok' : 'err' ); ?>
						<?php $this->status_row( __( 'Your access', 'ai-site-assistant-autopilot' ), $active ? $this->access_label( $can_manage ) : '-', $can_manage ? 'ok' : 'err' ); ?>
						<?php $this->status_row( __( 'Products', 'ai-site-assistant-autopilot' ), $active ? number_format_i18n( (int) ( $health['product_count'] ?? 0 ) ) : '-' ); ?>
						<?php $this->status_row( __( 'Categories', 'ai-site-assistant-autopilot' ), $active ? number_format_i18n( (int) ( $health['category_count'] ?? 0 ) ) : '-' ); ?>
						<?php $this->status_row( __( 'Product tags', 'ai-site-assistant-autopilot' ), $active ? number_format_i18n( (int) ( $health['tag_count'] ?? 0 ) ) : '-' ); ?>
						<?php $this->status_row( __( 'Draft workflow', 'ai-site-assistant-autopilot' ), $enabled ? __( 'Always draft (fixed in Phase 1)', 'ai-site-assistant-autopilot' ) : __( 'Connector paused', 'ai-site-assistant-autopilot' ), $enabled ? 'ok' : 'err' ); ?>
					</div>
				</section>

				<section class="sp-card sp-store-panel" data-sp-store-settings>
					<div class="sp-store-panel__head"><h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Settings', 'ai-site-assistant-autopilot' ); ?></h2></div>
					<div class="sp-store-settings-list">
						<?php $this->setting_toggle( 'enabled', __( 'WooCommerce AI connector', 'ai-site-assistant-autopilot' ), __( 'Off means no proposals, drafts, or Store Copilot reads.', 'ai-site-assistant-autopilot' ), $enabled ); ?>
						<?php $this->setting_toggle( 'enable_search', __( 'Smart product search', 'ai-site-assistant-autopilot' ), __( 'Controls the protected Store search endpoint. It does not add a visitor-facing search interface.', 'ai-site-assistant-autopilot' ), ! empty( $settings['enable_search'] ) ); ?>
						<?php $this->setting_toggle( 'enable_descriptions', __( 'Product descriptions', 'ai-site-assistant-autopilot' ), __( 'Lets AutoPilot draft descriptions for products you already have. Each one waits for your approval, and Undo puts the old text back. Nothing else about a product is changed.', 'ai-site-assistant-autopilot' ), ! empty( $settings['enable_descriptions'] ) ); ?>
						<?php $this->locked_toggle( __( 'Product editing by AI', 'ai-site-assistant-autopilot' ), __( 'Prices, stock, titles and every other field are reserved for Phase 2. They cannot be switched on yet.', 'ai-site-assistant-autopilot' ), false ); ?>
						<?php $this->locked_toggle( __( 'Always create as draft', 'ai-site-assistant-autopilot' ), __( 'Fixed in Phase 1. Publishing stays a manual step.', 'ai-site-assistant-autopilot' ), true ); ?>
					</div>
					<p class="sp-status-text" data-sp-store-settings-status role="status" aria-live="polite"></p>
				</section>
			</aside>
		</div>
		<?php
	}

	/** @param array<int,array{0:string,1:bool,2:string}> $rows */
	private function capability_group( string $title, array $rows ): void {
		?>
		<div class="sp-store-capability-group">
			<h3 class="sp-store-capability-group__title"><?php echo esc_html( $title ); ?></h3>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				list( $label, $available, $phase ) = $row;
				$state                             = '' !== $phase ? 'locked' : ( $available ? 'ready' : 'unavailable' );
				?>
				<div class="sp-store-capability-row" data-sp-store-capability-state="<?php echo esc_attr( $state ); ?>">
					<span class="sp-store-capability-row__icon"><?php echo Ui::icon( $available ? 'tick' : 'dash', 13, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
					<span class="sp-store-capability-row__label"><?php echo esc_html( $label ); ?></span>
					<?php
					if ( '' !== $phase ) :
						?>
						<span class="sp-store-phase"><?php echo esc_html( $phase ); ?></span><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function status_row( string $label, string $value, string $tone = '' ): void {
		?>
		<div class="sp-kv__row sp-kv__row--ruled" data-sp-store-status-row>
			<span class="sp-kv__k"><?php echo esc_html( $label ); ?></span>
			<span class="sp-kv__v<?php echo '' !== $tone ? ' sp-store-status--' . esc_attr( $tone ) : ''; ?>"><?php echo esc_html( $value ); ?></span>
		</div>
		<?php
	}

	private function access_label( bool $can_manage ): string {
		if ( ! $can_manage ) {
			return __( 'No store-management access', 'ai-site-assistant-autopilot' );
		}
		return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	private function setting_toggle( string $setting, string $title, string $note, bool $on ): void {
		// The switch is icon-only: its name and description are the visible title and note beside it (T17).
		$id = 'sp-store-setting-' . sanitize_key( $setting );
		?>
		<div class="sp-store-setting">
			<button type="button" class="sp-store-switch<?php echo $on ? ' is-on' : ''; ?>" role="switch" aria-checked="<?php echo $on ? 'true' : 'false'; ?>" aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>" aria-describedby="<?php echo esc_attr( $id . '-note' ); ?>" data-sp-store-setting="<?php echo esc_attr( $setting ); ?>">
				<span class="sp-store-switch__knob"></span>
			</button>
			<div class="sp-store-setting__body"><strong id="<?php echo esc_attr( $id . '-title' ); ?>"><?php echo esc_html( $title ); ?></strong><p id="<?php echo esc_attr( $id . '-note' ); ?>"><?php echo esc_html( $note ); ?></p></div>
		</div>
		<?php
	}

	private function locked_toggle( string $title, string $note, bool $on ): void {
		?>
		<div class="sp-store-setting is-locked" aria-disabled="true">
			<span class="sp-store-switch<?php echo $on ? ' is-on' : ''; ?>" aria-hidden="true"><span class="sp-store-switch__knob"></span></span>
			<div class="sp-store-setting__body"><strong><?php echo esc_html( $title ); ?></strong><p><?php echo esc_html( $note ); ?></p></div>
		</div>
		<?php
	}

	private function render_products( bool $active, bool $enabled ): void {
		$products = $active && $enabled ? WooCommerceConnector::recent_products() : array();
		?>
		<section class="sp-card sp-store-panel sp-store-products" data-sp-store-products>
			<div class="sp-store-panel__head">
				<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Products', 'ai-site-assistant-autopilot' ); ?></h2>
				<span class="sp-store-panel__sub"><?php esc_html_e( 'Read-only in Phase 1 - AutoPilot cannot edit what already exists', 'ai-site-assistant-autopilot' ); ?></span>
			</div>
			<?php if ( ! $active || ! $enabled ) : ?>
				<p class="sp-empty sp-store-empty"><?php esc_html_e( 'Products become available here when WooCommerce and its AI connector are active.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php elseif ( array() === $products ) : ?>
				<p class="sp-empty sp-store-empty"><?php esc_html_e( 'No WooCommerce products were found yet. Drafts created by AutoPilot will be marked here.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php else : ?>
				<?php foreach ( $products as $product ) : ?>
					<?php $product_status = strtolower( (string) $product['status'] ); ?>
					<div class="sp-store-product-row" data-sp-store-product-row data-sp-store-product-generated="<?php echo ! empty( $product['generated'] ) ? '1' : '0'; ?>">
						<div class="sp-store-product-row__body">
							<strong><?php echo esc_html( $product['name'] ); ?></strong>
							<span><?php echo esc_html( $product['category'] . ' - ' . $product['origin'] ); ?></span>
						</div>
						<?php echo Ui::pill( (string) $product['status'], 'draft' === $product_status ? 'warn' : 'off', false, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Ui. ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<div class="sp-store-panel__foot"><?php esc_html_e( 'Drafts AutoPilot created are marked. Everything else it can read but not touch until Phase 2.', 'ai-site-assistant-autopilot' ); ?></div>
		</section>
		<?php
	}

	/**
	 * Descriptions: pick products with missing or thin descriptions, draft them with AI,
	 * read each draft beside the current text, edit it, and apply or skip it one by one.
	 */
	private function render_descriptions( bool $usable, bool $switched_on, bool $provider_ready ): void {
		$capabilities_url = add_query_arg( 'tab', 'capabilities', admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD ) );
		?>
		<section class="sp-card sp-store-panel sp-store-desc" data-sp-store-descriptions-panel data-sp-desc-ready="<?php echo $usable && $switched_on && $provider_ready ? '1' : '0'; ?>">
			<div class="sp-store-panel__head">
				<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Descriptions for products you already have', 'ai-site-assistant-autopilot' ); ?></h2>
				<span class="sp-store-panel__sub"><?php esc_html_e( 'You approve each one. Only the description changes, and Undo puts the old text back.', 'ai-site-assistant-autopilot' ); ?></span>
			</div>
			<?php if ( ! $usable ) : ?>
				<p class="sp-empty sp-store-empty"><?php esc_html_e( 'Descriptions become available here when WooCommerce and its AI connector are active.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php elseif ( ! $switched_on ) : ?>
				<div class="sp-store-desc__off" data-sp-desc-off>
					<p><?php esc_html_e( 'Product descriptions are off. Turn them on under What it can do, then pick the products to write for here.', 'ai-site-assistant-autopilot' ); ?></p>
					<a class="sp-btn sp-btn--ghost" href="<?php echo esc_url( $capabilities_url ); ?>"><?php esc_html_e( 'Open What it can do', 'ai-site-assistant-autopilot' ); ?></a>
				</div>
			<?php elseif ( ! $provider_ready ) : ?>
				<p class="sp-empty sp-store-empty"><?php esc_html_e( 'Connect an AI service in Setup & Connection first.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php else : ?>
				<div class="sp-store-desc__bar">
					<label class="sp-store-desc__filter"><input type="checkbox" data-sp-desc-only-short checked /> <?php esc_html_e( 'Only products with a missing or short description', 'ai-site-assistant-autopilot' ); ?></label>
					<label class="sp-store-desc__tone">
						<span><?php esc_html_e( 'Tone', 'ai-site-assistant-autopilot' ); ?></span>
						<select class="sp-select" data-sp-desc-tone>
							<option value="professional"><?php esc_html_e( 'Professional', 'ai-site-assistant-autopilot' ); ?></option>
							<option value="friendly"><?php esc_html_e( 'Friendly', 'ai-site-assistant-autopilot' ); ?></option>
							<option value="luxury"><?php esc_html_e( 'Premium', 'ai-site-assistant-autopilot' ); ?></option>
							<option value="technical"><?php esc_html_e( 'Technical', 'ai-site-assistant-autopilot' ); ?></option>
						</select>
					</label>
					<button type="button" class="sp-btn" data-sp-desc-write disabled><?php esc_html_e( 'Write drafts', 'ai-site-assistant-autopilot' ); ?></button>
				</div>
				<p class="sp-status-text" data-sp-desc-status role="status" aria-live="polite"></p>
				<ul class="sp-store-desc__list" data-sp-desc-list aria-label="<?php esc_attr_e( 'Products', 'ai-site-assistant-autopilot' ); ?>"></ul>
				<div class="sp-store-desc__reviews" data-sp-desc-reviews></div>
			<?php endif; ?>
			<div class="sp-store-panel__foot"><?php esc_html_e( 'Up to 10 drafts at a time, one AI request each. A draft is valid for 15 minutes and is refused if someone changes that description meanwhile.', 'ai-site-assistant-autopilot' ); ?></div>
		</section>
		<?php
	}

	private function render_orders( bool $active, bool $enabled, bool $can_manage ): void {
		$manager_read = $active && $enabled && $can_manage;
		if ( $manager_read ) {
			$this->render_checkout_dropoff();
		}
		?>
		<section class="sp-card sp-store-orders" data-sp-store-orders>
			<h2 class="sp-h2 sp-h2--md"><?php esc_html_e( 'What it may read from an order', 'ai-site-assistant-autopilot' ); ?></h2>
			<p class="sp-sub sp-store-orders__intro"><?php esc_html_e( 'The current order route is protected for a store manager. A visitor-facing order lookup is not registered in this release.', 'ai-site-assistant-autopilot' ); ?></p>
			<div class="sp-store-order-permissions">
				<?php $this->order_permission_row( __( 'Recent order summary for a store manager', 'ai-site-assistant-autopilot' ), $manager_read, $manager_read ? __( 'Read only', 'ai-site-assistant-autopilot' ) : __( 'Unavailable', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->order_permission_row( __( 'Customer-facing order lookup', 'ai-site-assistant-autopilot' ), false, __( 'Not registered', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->order_permission_row( __( 'Order items, notes, or personal details', 'ai-site-assistant-autopilot' ), false, __( 'Not registered', 'ai-site-assistant-autopilot' ) ); ?>
				<?php $this->order_permission_row( __( 'Change status, cancel, or refund', 'ai-site-assistant-autopilot' ), false, __( 'Phase 2', 'ai-site-assistant-autopilot' ) ); ?>
			</div>
			<div class="sp-store-orders__privacy">
				<?php echo Ui::icon( 'lock', 16, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
				<p><?php esc_html_e( 'Changing an order status, cancelling, and refunding are not registered as Store Copilot routes in Phase 1. No prompt in this UI can trigger them.', 'ai-site-assistant-autopilot' ); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * Checkout drop-off: paid against unpaid checkouts from WooCommerce's own orders,
	 * read on this page only (no route, no AI). See WooCommerceAnalyticsSkill::checkout_dropoff().
	 */
	private function render_checkout_dropoff(): void {
		$days   = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only period selection.
		$data   = ( new WooCommerceAnalyticsSkill() )->checkout_dropoff( $days );
		$days   = (int) ( $data['days'] ?? 30 );
		$base   = add_query_arg( 'tab', 'orders', admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD ) );
		$total  = (int) ( $data['total'] ?? 0 );
		$unpaid = $total - (int) ( $data['paid'] ?? 0 );
		$share  = static function ( int $n ) use ( $total ): string {
			/* translators: %s: share of all checkouts in the period, as a percentage. */
			return $total > 0 ? sprintf( __( '%s%%', 'ai-site-assistant-autopilot' ), number_format_i18n( 100 * $n / $total, 0 ) ) : '';
		};
		$rows   = array(
			array( 'paid', __( 'Paid', 'ai-site-assistant-autopilot' ), __( 'Processing, completed, on hold for an offline payment, or refunded later.', 'ai-site-assistant-autopilot' ), '' ),
			array( 'pending', __( 'Stopped at payment', 'ai-site-assistant-autopilot' ), __( 'Pending payment: the customer placed the order but never paid.', 'ai-site-assistant-autopilot' ), 'wc-pending' ),
			array( 'failed', __( 'Payment failed', 'ai-site-assistant-autopilot' ), __( 'The payment provider declined or the payment did not go through.', 'ai-site-assistant-autopilot' ), 'wc-failed' ),
			array( 'cancelled', __( 'Cancelled', 'ai-site-assistant-autopilot' ), __( 'Usually an unpaid order that WooCommerce cancelled when its held stock expired.', 'ai-site-assistant-autopilot' ), 'wc-cancelled' ),
		);
		?>
		<section class="sp-card sp-store-panel sp-store-funnel" data-sp-store-funnel data-sp-funnel-days="<?php echo esc_attr( (string) $days ); ?>">
			<div class="sp-store-panel__head">
				<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( 'Checkout drop-off', 'ai-site-assistant-autopilot' ); ?></h2>
				<nav class="sp-seg sp-store-funnel__period" aria-label="<?php esc_attr_e( 'Period', 'ai-site-assistant-autopilot' ); ?>">
					<?php foreach ( array( 7, 30, 90 ) as $option ) : ?>
						<a class="sp-seg__btn<?php echo $option === $days ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $option, $base ) ); ?>"<?php echo $option === $days ? ' aria-current="page"' : ''; ?>>
							<?php
							/* translators: %s: number of days. */
							echo esc_html( sprintf( __( '%s days', 'ai-site-assistant-autopilot' ), number_format_i18n( $option ) ) );
							?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
			<div class="sp-store-funnel__body">
				<?php if ( empty( $data['ok'] ) ) : ?>
					<p class="sp-status-text is-err" data-sp-funnel-error><?php echo esc_html( (string) ( $data['error'] ?? '' ) ); ?></p>
				<?php elseif ( 0 === $total ) : ?>
					<p class="sp-store-funnel__lead" data-sp-funnel-empty>
						<?php
						/* translators: %s: number of days. */
						echo esc_html( sprintf( __( 'No customer placed an order at checkout in the last %s days.', 'ai-site-assistant-autopilot' ), number_format_i18n( $days ) ) );
						?>
					</p>
				<?php else : ?>
					<p class="sp-store-funnel__lead" data-sp-funnel-lead>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of checkouts, 2: number of days, 3: percentage paid, 4: number not paid. */
								_n( '%1$s checkout in the last %2$s days: %3$s paid, %4$s not paid.', '%1$s checkouts in the last %2$s days: %3$s paid, %4$s not paid.', $total, 'ai-site-assistant-autopilot' ),
								number_format_i18n( $total ),
								number_format_i18n( $days ),
								$share( (int) $data['paid'] ),
								number_format_i18n( $unpaid )
							)
						);
						?>
					</p>
					<div class="sp-kv sp-store-funnel__rows">
						<?php foreach ( $rows as $row ) : ?>
							<?php
							list( $key, $label, $hint, $status ) = $row;
							$n                                   = (int) ( $data[ $key ] ?? 0 );
							?>
							<div class="sp-kv__row sp-kv__row--ruled" data-sp-funnel-row="<?php echo esc_attr( $key ); ?>">
								<span class="sp-store-funnel__k">
									<span class="sp-store-funnel__label"><?php echo esc_html( $label ); ?></span>
									<span class="sp-store-funnel__hint"><?php echo esc_html( $hint ); ?></span>
								</span>
								<span class="sp-kv__v">
									<span data-sp-funnel-count><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
									<span class="sp-store-funnel__share"><?php echo esc_html( $share( $n ) ); ?></span>
									<?php if ( '' !== $status && $n > 0 ) : ?>
										<a class="sp-store-funnel__link" href="<?php echo esc_url( $this->orders_url( $status ) ); ?>"><?php esc_html_e( 'View orders', 'ai-site-assistant-autopilot' ); ?></a>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $data['methods'] ) ) : ?>
						<h3 class="sp-store-funnel__subhead"><?php esc_html_e( 'Unpaid and failed checkouts by payment method', 'ai-site-assistant-autopilot' ); ?></h3>
						<ul class="sp-store-funnel__methods" data-sp-funnel-methods>
							<?php foreach ( (array) $data['methods'] as $method ) : ?>
								<li><span dir="auto"><?php echo esc_html( (string) $method['name'] ); ?></span> <strong><?php echo esc_html( number_format_i18n( (int) $method['count'] ) ); ?></strong></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( ! empty( $data['sampled'] ) ) : ?>
							<p class="sp-store-funnel__hint"><?php esc_html_e( 'Based on the latest 200 unpaid and failed checkouts.', 'ai-site-assistant-autopilot' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="sp-store-panel__foot"><?php esc_html_e( 'Counts orders that customers placed at checkout in this period, read from WooCommerce on this page. Orders you add yourself are left out, and carts left before checkout are not counted because WooCommerce does not record them.', 'ai-site-assistant-autopilot' ); ?></div>
		</section>
		<?php
	}

	/** WooCommerce's own order list filtered by one status, on either order storage. */
	private function orders_url( string $status ): string {
		$hpos = class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		return $hpos
			? add_query_arg( 'status', $status, admin_url( 'admin.php?page=wc-orders' ) )
			: add_query_arg(
				array(
					'post_status' => $status,
					'post_type'   => 'shop_order',
				),
				admin_url( 'edit.php' )
			);
	}

	private function order_permission_row( string $label, bool $on, string $note ): void {
		?>
		<div class="sp-store-order-permission" data-sp-store-order-permission="<?php echo $on ? 'on' : 'off'; ?>">
			<span class="sp-store-switch<?php echo $on ? ' is-on' : ''; ?>" aria-hidden="true"><span class="sp-store-switch__knob"></span></span>
			<span class="sp-store-order-permission__label"><?php echo esc_html( $label ); ?></span>
			<span class="sp-store-order-permission__note"><?php echo esc_html( $note ); ?></span>
		</div>
		<?php
	}
}
