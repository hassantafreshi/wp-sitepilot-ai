<?php
declare(strict_types=1);

namespace SitePilot\Connectors;

use SitePilot\Execution\ActionStore;
use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;
use SitePilot\Skills\ProductPlanValidator;

/**
 * Phase 1 WooCommerce AI workflow.
 *
 * It can only propose and, after an explicit approval, create one simple
 * product as a draft. The action is user-scoped, expires after 15 minutes and
 * is atomically claimed, matching the form-builder release contract.
 */
final class WooCommerceProductSkill {

	private Logger $logger;
	private ActionStore $store;

	public function __construct( ?ActionStore $store = null, ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
		$this->store  = $store ?? new ActionStore();
	}

	/** @return array<string,mixed> */
	public function plan( string $prompt ): array {
		if ( ! $this->ready() ) {
			return $this->failure( 'connector_not_available', __( 'WooCommerce AI draft creation is not available for this account.', 'sitepilot-ai' ) );
		}

		$manager = new ProviderManager();
		if ( ! $manager->is_configured() ) {
			return $this->failure( 'provider_not_configured', __( 'AI provider not configured.', 'sitepilot-ai' ) );
		}

		$categories       = WooCommerceConnector::get_categories();
		$category_context = array();
		foreach ( $categories as $id => $name ) {
			$category_context[] = sprintf( '%d: %s', (int) $id, $name );
		}

		$system_prompt = "You are SitePilot AI. Create a proposal for exactly one WooCommerce simple product.\n"
			. "Respond only with a JSON object matching this schema:\n"
			. '{"name":"string","description":"string","short_description":"string","price":"29.99","regular_price":"29.99","sale_price":"","categories":[1],"sku":"string","weight":"","stock_status":"instock|outofstock|onbackorder","stock_quantity":0,"manage_stock":false,"status":"draft","type":"simple"}' . "\n"
			. "Rules: status must be draft, type must be simple, omit fields you cannot infer, never propose an edit or a publish action.\n"
			. ( $category_context ? "Available categories (use IDs only when relevant):\n" . implode( "\n", $category_context ) : '' );

		try {
			$plan = $manager->make()->generate_structured_output( $system_prompt, $prompt );

			$validator  = new ProductPlanValidator();
			$validation = $validator->validate( $plan );
			if ( ! $validation['ok'] ) {
				return array(
					'ok'     => false,
					'code'   => 'validation_failed',
					'errors' => $validation['errors'],
				);
			}

			$clean_plan = $validator->sanitize( $plan );
			$action_id  = $this->store->create( $clean_plan, 'woocommerce_product_draft' );
			$this->logger->info( 'woo.draft.plan_created', 'WooCommerce product draft plan generated.', array( 'category_count' => count( $clean_plan['categories'] ) ) );

			return array(
				'ok'         => true,
				'code'       => 'ok',
				'plan'       => $clean_plan,
				'action_id'  => $action_id,
				'expires_at' => time() + ActionStore::ttl(),
			);
		} catch ( \Throwable $e ) {
			$this->logger->error( 'woo.draft.plan_failed', $e->getMessage() );
			return $this->failure( 'provider_error', __( 'The product proposal could not be created. Please try again.', 'sitepilot-ai' ) );
		}
	}

	/** @return array<string,mixed> */
	public function execute( string $action_id ): array {
		if ( ! $this->ready() ) {
			return $this->failure( 'connector_not_available', __( 'WooCommerce AI draft creation is not available for this account.', 'sitepilot-ai' ) );
		}

		$claim = $this->store->claim( $action_id, get_current_user_id() );
		if ( 'missing' === $claim['status'] ) {
			return $this->failure( 'action_expired', __( 'This product proposal has expired. Please create a new proposal.', 'sitepilot-ai' ) );
		}
		if ( 'forbidden' === $claim['status'] ) {
			return $this->failure( 'permission_denied', __( 'You are not allowed to approve this product proposal.', 'sitepilot-ai' ) );
		}
		if ( 'already_claimed' === $claim['status'] ) {
			return $this->failure( 'action_already_processed', __( 'This product proposal is already being processed or has already been used.', 'sitepilot-ai' ) );
		}

		$record = (array) ( $claim['record'] ?? array() );
		if ( 'woocommerce_product_draft' !== (string) ( $record['connector_id'] ?? '' ) ) {
			return $this->failure( 'invalid_action', __( 'This approval is not a WooCommerce product draft.', 'sitepilot-ai' ) );
		}
		$plan       = (array) ( $record['plan'] ?? array() );
		$validation = ( new ProductPlanValidator() )->validate( $plan );
		if ( ! $validation['ok'] ) {
			return array(
				'ok'     => false,
				'code'   => 'invalid_payload',
				'errors' => $validation['errors'],
			);
		}

		try {
			return $this->create_draft( $plan );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'woo.draft.create_failed', $e->getMessage() );
			return $this->failure( 'execution_failed', __( 'The product draft could not be created. Please try again.', 'sitepilot-ai' ) );
		}
	}

	/** @param array<string,mixed> $plan @return array<string,mixed> */
	private function create_draft( array $plan ): array {
		$sku = (string) ( $plan['sku'] ?? '' );
		if ( '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) && wc_get_product_id_by_sku( $sku ) ) {
			return $this->failure( 'duplicate_sku', __( 'A product with this SKU already exists. Edit the proposal and use a unique SKU.', 'sitepilot-ai' ) );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( (string) $plan['name'] );
		$product->set_status( 'draft' );
		$product->set_description( (string) ( $plan['description'] ?? '' ) );
		$product->set_short_description( (string) ( $plan['short_description'] ?? '' ) );
		$product->set_regular_price( (string) ( $plan['regular_price'] ?: $plan['price'] ) );
		if ( '' !== (string) ( $plan['sale_price'] ?? '' ) ) {
			$product->set_sale_price( (string) $plan['sale_price'] );
		}
		$product->set_stock_status( (string) ( $plan['stock_status'] ?? 'instock' ) );
		$product->set_manage_stock( ! empty( $plan['manage_stock'] ) );
		if ( ! empty( $plan['manage_stock'] ) ) {
			$product->set_stock_quantity( (int) ( $plan['stock_quantity'] ?? 0 ) );
		}
		$product->set_sku( $sku );
		$product->set_weight( (string) ( $plan['weight'] ?? '' ) );
		$product->set_category_ids( array_values( array_intersect( (array) $plan['categories'], array_keys( WooCommerceConnector::get_categories() ) ) ) );
		$product_id = $product->save();

		if ( $product_id <= 0 ) {
			throw new \RuntimeException( 'WooCommerce did not return a product ID.' );
		}
		$this->logger->info( 'woo.draft.created', 'WooCommerce product draft created.', array( 'product_id' => $product_id ) );
		return array(
			'ok'          => true,
			'code'        => 'ok',
			'product_id'  => $product_id,
			'edit_url'    => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
			'product_url' => get_permalink( $product_id ),
			'message'     => __( 'Product draft created. Review it in WooCommerce before publishing.', 'sitepilot-ai' ),
		);
	}

	private function ready(): bool {
		return WooCommerceConnector::is_active()
			&& WooCommerceConnector::is_ai_enabled()
			&& WooCommerceConnector::current_user_can_manage();
	}

	/** @return array{ok:false,code:string,errors:array<int,string>} */
	private function failure( string $code, string $message ): array {
		return array(
			'ok'     => false,
			'code'   => $code,
			'errors' => array( $message ),
		);
	}
}
