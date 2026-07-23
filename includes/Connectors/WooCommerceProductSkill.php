<?php
declare(strict_types=1);
namespace SitePilot\Connectors;

use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;
use SitePilot\Skills\ProductPlanValidator;

/**
 * AI-powered WooCommerce product skill.
 *
 * Creates, edits, and searches products using natural language.
 * Follows the Plan → Validate → Execute pattern from Form Copilot.
 */
final class WooCommerceProductSkill {

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Generate a product plan from a natural language description.
	 *
	 * @param string $prompt User's request.
	 * @return array{ok:bool, plan?:array, errors?:string[], action_id?:string, action?:string}
	 */
	public function plan( string $prompt ): array {
		if ( ! WooCommerceConnector::is_active() ) {
			return array( 'ok' => false, 'errors' => array( __( 'WooCommerce is not active.', 'sitepilot-ai' ) ) );
		}

		$manager = new ProviderManager();
		if ( ! $manager->is_configured() ) {
			return array( 'ok' => false, 'errors' => array( __( 'AI provider not configured.', 'sitepilot-ai' ) ) );
		}

		$cats = WooCommerceConnector::get_categories();
		$cat_list = '';
		foreach ( $cats as $id => $name ) {
			$cat_list .= "  ID {$id}: {$name}\n";
		}

		$system_prompt = "You are SitePilot AI, a WooCommerce product assistant.\n"
			. "Generate a JSON product plan from the user's request.\n"
			. "Respond ONLY with valid JSON matching this schema:\n"
			. '{"name":"string","description":"string","short_description":"string","price":"string (number)","regular_price":"string","sale_price":"string","categories":[int],"tags":[int],"status":"draft|pending|publish","stock_status":"instock|outofstock|onbackorder","stock_quantity":int,"manage_stock":bool,"type":"simple|variable|grouped|external","sku":"string","weight":"string"}' . "\n\n"
			. ( $cat_list ? "Available categories:\n{$cat_list}\n" : '' )
			. "Rules:\n"
			. "- Set status to 'draft' unless the user explicitly requests publish.\n"
			. "- Price must be a string like '29.99'.\n"
			. "- If the user doesn't specify a category, leave categories empty.\n"
			. "- Use simple type by default.\n"
			. "- Keep descriptions concise but informative.\n";

		try {
			$provider = $manager->make();
			$result   = $provider->generate_structured_output( $system_prompt, $prompt );
			$plan     = $result;

			// Detect action type.
			$action = 'create';
			if ( preg_match( '/(ویرایش|edit|update|تغییر|قیمت|قیمتش)/ui', $prompt ) ) {
				$action = 'edit';
			}
			if ( preg_match( '/(جستجو|search|find|پیدا|نشون)/ui', $prompt ) ) {
				$action = 'search';
			}
			if ( preg_match( '/\b(bulk|همه|قیمت همه|تغییر همه|کلا)\b/ui', $prompt ) ) {
				$action = 'bulk';
			}

			// Validate.
			$validator = new ProductPlanValidator();
			$validation = $validator->validate( $plan );

			if ( ! $validation['ok'] ) {
				return array(
					'ok'     => false,
					'errors' => $validation['errors'],
					'plan'   => $plan,
				);
			}

			$clean_plan = $validator->sanitize( $plan );

			$action_id = 'woo_' . bin2hex( random_bytes( 8 ) );
			set_transient( "sitepilot_woo_{$action_id}", array(
				'plan'   => $clean_plan,
				'action' => $action,
				'user'   => get_current_user_id(),
				'time'   => time(),
			), 900 ); // 15 min TTL.

			return array(
				'ok'        => true,
				'plan'      => $clean_plan,
				'action'    => $action,
				'action_id' => $action_id,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'woo_product_plan', $e->getMessage() );
			return array( 'ok' => false, 'errors' => array( $e->getMessage() ) );
		}
	}

	/**
	 * Execute an approved product action.
	 *
	 * @param string $action_id The action ID from planning phase.
	 * @return array{ok:bool, product_id?:int, product_url?:string, edit_url?:string, message?:string, errors?:string[]}
	 */
	public function execute( string $action_id ): array {
		if ( ! WooCommerceConnector::is_active() ) {
			return array( 'ok' => false, 'errors' => array( __( 'WooCommerce is not active.', 'sitepilot-ai' ) ) );
		}

		$stored = get_transient( "sitepilot_woo_{$action_id}" );
		if ( ! $stored || ! is_array( $stored ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'Action expired or not found. Please try again.', 'sitepilot-ai' ) ) );
		}

		delete_transient( "sitepilot_woo_{$action_id}" );
		$plan   = $stored['plan'];
		$action = $stored['action'] ?? 'create';

		try {
			if ( 'create' === $action ) {
				return $this->create_product( $plan );
			} elseif ( 'edit' === $action ) {
				return $this->edit_product( $plan );
			} elseif ( 'search' === $action ) {
				return $this->search_products( $plan );
			} elseif ( 'bulk' === $action ) {
				return $this->bulk_update( $plan );
			}
			return array( 'ok' => false, 'errors' => array( __( 'Unknown action.', 'sitepilot-ai' ) ) );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'woo_product_execute', $e->getMessage() );
			return array( 'ok' => false, 'errors' => array( $e->getMessage() ) );
		}
	}

	/**
	 * Create a new WooCommerce product.
	 *
	 * @return array{ok:bool, product_id:int, product_url:string, edit_url:string, message:string}
	 */
	private function create_product( array $plan ): array {
		$product_data = array(
			'post_title'     => $plan['name'],
			'post_content'   => $plan['description'] ?? '',
			'post_excerpt'   => $plan['short_description'] ?? '',
			'post_status'    => $plan['status'] ?? 'draft',
			'post_type'      => 'product',
			'post_author'    => get_current_user_id(),
		);

		$product_id = wp_insert_post( $product_data );
		if ( is_wp_error( $product_id ) ) {
			throw new \RuntimeException( $product_id->get_error_message() );
		}

		// Set product type.
		wp_set_object_terms( $product_id, $plan['type'] ?? 'simple', 'product_type' );

		// Set price.
		if ( ! empty( $plan['regular_price'] ) ) {
			update_post_meta( $product_id, '_regular_price', $plan['regular_price'] );
			update_post_meta( $product_id, '_price', $plan['regular_price'] );
		} elseif ( ! empty( $plan['price'] ) ) {
			update_post_meta( $product_id, '_regular_price', $plan['price'] );
			update_post_meta( $product_id, '_price', $plan['price'] );
		}
		if ( ! empty( $plan['sale_price'] ) ) {
			update_post_meta( $product_id, '_sale_price', $plan['sale_price'] );
			update_post_meta( $product_id, '_price', $plan['sale_price'] );
		}

		// Stock.
		update_post_meta( $product_id, '_manage_stock', $plan['manage_stock'] ? 'yes' : 'no' );
		if ( $plan['manage_stock'] ) {
			update_post_meta( $product_id, '_stock', (string) $plan['stock_quantity'] );
		}
		update_post_meta( $product_id, '_stock_status', $plan['stock_status'] ?? 'instock' );

		// SKU.
		if ( ! empty( $plan['sku'] ) ) {
			update_post_meta( $product_id, '_sku', $plan['sku'] );
		}

		// Weight.
		if ( ! empty( $plan['weight'] ) ) {
			update_post_meta( $product_id, '_weight', $plan['weight'] );
		}

		// Dimensions.
		if ( ! empty( $plan['dimensions'] ) ) {
			foreach ( array( 'length', 'width', 'height' ) as $dim ) {
				if ( ! empty( $plan['dimensions'][ $dim ] ) ) {
					update_post_meta( $product_id, "_{$dim}", $plan['dimensions'][ $dim ] );
				}
			}
		}

		// Categories.
		if ( ! empty( $plan['categories'] ) ) {
			wp_set_object_terms( $product_id, array_map( 'absint', $plan['categories'] ), 'product_cat' );
		}

		// Tags.
		if ( ! empty( $plan['tags'] ) ) {
			wp_set_object_terms( $product_id, array_map( 'absint', $plan['tags'] ), 'product_tag' );
		}

		$this->logger->info( 'woo_create_product', "Created product #{$product_id}: {$plan['name']}" );

		return array(
			'ok'           => true,
			'product_id'   => $product_id,
			'product_url'  => get_permalink( $product_id ),
			'edit_url'     => admin_url( "post.php?post={$product_id}&action=edit" ),
			'message'      => sprintf( __( 'Product "%s" created.', 'sitepilot-ai' ), $plan['name'] ),
		);
	}

	/**
	 * Edit an existing product.
	 *
	 * @return array{ok:bool, message:string}
	 */
	private function edit_product( array $plan ): array {
		if ( empty( $plan['product_id'] ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'Product ID required for edit.', 'sitepilot-ai' ) ) );
		}

		$pid = (int) $plan['product_id'];
		$post = get_post( $pid );
		if ( ! $post || 'product' !== $post->post_type ) {
			return array( 'ok' => false, 'errors' => array( __( 'Product not found.', 'sitepilot-ai' ) ) );
		}

		$update = array( 'ID' => $pid );
		if ( ! empty( $plan['name'] ) ) { $update['post_title'] = $plan['name']; }
		if ( isset( $plan['description'] ) ) { $update['post_content'] = $plan['description']; }
		if ( isset( $plan['short_description'] ) ) { $update['post_excerpt'] = $plan['short_description']; }
		if ( ! empty( $plan['status'] ) ) { $update['post_status'] = $plan['status']; }

		wp_update_post( $update );

		$this->logger->info( 'woo_edit_product', "Edited product #{$pid}" );

		return array(
			'ok'       => true,
			'message'  => sprintf( __( 'Product #%d updated.', 'sitepilot-ai' ), $pid ),
			'edit_url' => admin_url( "post.php?post={$pid}&action=edit" ),
		);
	}

	/**
	 * Search products by keyword.
	 *
	 * @return array{ok:bool, results:array}
	 */
	private function search_products( array $plan ): array {
		$keyword = $plan['search_query'] ?? $plan['name'] ?? '';
		if ( empty( $keyword ) ) {
			return array( 'ok' => false, 'errors' => array( __( 'Search query required.', 'sitepilot-ai' ) ) );
		}

		$posts = get_posts( array(
			'post_type'   => 'product',
			's'           => $keyword,
			'numberposts' => 20,
		) );

		$results = array();
		foreach ( $posts as $p ) {
			$results[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'price' => get_post_meta( $p->ID, '_price', true ),
				'url'   => get_permalink( $p->ID ),
				'edit'  => admin_url( "post.php?post={$p->ID}&action=edit" ),
			);
		}

		return array( 'ok' => true, 'results' => $results, 'count' => count( $results ) );
	}

	/**
	 * Bulk update (stub — requires additional safeguards).
	 *
	 * @return array{ok:bool, message:string}
	 */
	private function bulk_update( array $plan ): array {
		return array(
			'ok'      => false,
			'errors'  => array( __( 'Bulk operations require additional security review.', 'sitepilot-ai' ) ),
		);
	}
}
