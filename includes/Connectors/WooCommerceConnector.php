<?php
declare(strict_types=1);
namespace AISAAutoPilot\Connectors;
use AISAAutoPilot\Core\Settings;
/**
 * WooCommerce Connector — detects WooCommerce, checks capabilities,
 * and provides the foundation for WooCommerce AI operations.
 *
 * All WooCommerce features are gated behind class_exists('WooCommerce')
 * to prevent fatal errors when WooCommerce is not installed/active.
 */
final class WooCommerceConnector {

	/**
	 * Check if WooCommerce is installed and active.
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get the WooCommerce version if available.
	 */
	public static function get_version(): string {
		if ( ! self::is_active() ) {
			return '';
		}
		return defined( 'WC_VERSION' ) ? WC_VERSION : ( defined( 'WOOCOMMERCE_VERSION' ) ? WOOCOMMERCE_VERSION : '' );
	}

	/**
	 * Check if the current user has WooCommerce management capabilities.
	 */
	public static function current_user_can_manage(): bool {

		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/** Whether AISAAutoPilot's deliberately small Phase 1 Woo workflow is enabled. */
	public static function is_ai_enabled(): bool {

		$settings = self::get_settings();
		return $settings['enabled'];
	}

	/**
	 * Read the non-secret Store Copilot settings with Phase 1 invariants.
	 *
	 * `auto_draft` and `enable_edit` are deliberately fixed: a proposal can only
	 * create one simple product draft, and it can never edit an existing product.
	 *
	 * @return array{enabled:bool,auto_draft:bool,enable_search:bool,enable_edit:bool}
	 */
	public static function get_settings(): array {

		$stored = Settings::get( 'woocommerce', array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'enabled'       => ! array_key_exists( 'enabled', $stored ) || ! empty( $stored['enabled'] ),
			'auto_draft'    => true,
			'enable_search' => ! empty( $stored['enable_search'] ),
			'enable_edit'   => false,
		);
	}

	/**
	 * The optional protected product-search endpoint is usable only while the
	 * connector and its explicit search preference are both enabled.
	 */
	public static function is_smart_search_enabled(): bool {

		$settings = self::get_settings();
		return self::is_active() && $settings['enabled'] && $settings['enable_search'];
	}
	/**
	 * Get a health/status array for the UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_health(): array {

		return array(
			'active'         => self::is_active(),
			'version'        => self::get_version(),
			'can_manage'     => self::current_user_can_manage(),
			'ai_enabled'     => self::is_ai_enabled(),
			'product_count'  => self::product_count(),
			'category_count' => count( self::get_categories() ),
			'tag_count'      => count( self::get_tags() ),
			'capabilities'   => self::get_capabilities(),
		);
	}

	/**
	 * Explicit capability contract for the Phase 1 WooCommerce integration.
	 *
	 * Callers can render a capability as unavailable rather than assuming a
	 * legacy connector supports an operation simply because WooCommerce is
	 * active. Only creating an approved simple product as a draft is writable.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function get_capabilities(): array {

		$available = self::is_active() && self::is_ai_enabled() && self::current_user_can_manage();

		return array(
			'products'  => array(
				'read'         => $available,
				'create_draft' => $available,
				'publish'      => false,
				'edit'         => false,
				'bulk_edit'    => false,
			),
			'orders'    => array(
				'read'   => $available,
				'change' => false,
				'refund' => false,
			),
			'analytics' => array(
				'read' => $available,
			),
			'content'   => array(
				'generate' => false,
				'publish'  => false,
			),
		);
	}

	public static function product_count(): int {

		if ( ! self::is_active() || ! function_exists( 'wp_count_posts' ) ) {
			return 0;
		}
		$counts = wp_count_posts( 'product' );
		return $counts ? (int) $counts->publish + (int) $counts->draft + (int) $counts->pending + (int) $counts->private : 0;
	}

	/**
	 * Read a compact, non-mutating product list for the Store Copilot mockup.
	 * The rows intentionally have no edit action: Phase 1 can inspect products
	 * but cannot mutate an existing one.
	 *
	 * @return array<int,array{id:int,name:string,category:string,origin:string,status:string,generated:bool}>
	 */
	public static function recent_products( int $limit = 8 ): array {

		if ( ! self::is_active() ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'      => min( max( 1, $limit ), 20 ),
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			$categories = wp_get_post_terms( (int) $post->ID, 'product_cat', array( 'fields' => 'names' ) );
			if ( is_wp_error( $categories ) || ! is_array( $categories ) ) {
				$categories = array();
			}

			$status_object = get_post_status_object( (string) $post->post_status );
			$generated     = '1' === (string) get_post_meta( (int) $post->ID, '_aisaautopilot_generated', true );
			$rows[]        = array(
				'id'        => (int) $post->ID,
				'name'      => (string) $post->post_title,
				'category'  => $categories ? implode( ', ', $categories ) : __( 'Uncategorized', 'ai-site-assistant-autopilot' ),
				'origin'    => $generated ? __( 'Created by AutoPilot', 'ai-site-assistant-autopilot' ) : __( 'Existing product', 'ai-site-assistant-autopilot' ),
				'status'    => $status_object ? (string) $status_object->label : ucfirst( (string) $post->post_status ),
				'generated' => $generated,
			);
		}

		return $rows;
	}
	/**
	 * Get product categories as id=>name pairs.
	 *
	 * @return array<int, string>
	 */
	public static function get_categories(): array {
		if ( ! self::is_active() ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$cats = array();
		foreach ( $terms as $t ) {
			$cats[ (int) $t->term_id ] = $t->name;
		}
		return $cats;
	}

	/**
	 * Get product tags as id=>name pairs.
	 *
	 * @return array<int, string>
	 */
	public static function get_tags(): array {
		if ( ! self::is_active() ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$tags = array();
		foreach ( $terms as $t ) {
			$tags[ (int) $t->term_id ] = $t->name;
		}
		return $tags;
	}
}
