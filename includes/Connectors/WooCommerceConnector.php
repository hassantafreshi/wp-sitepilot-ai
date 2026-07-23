<?php
declare(strict_types=1);
namespace SitePilot\Connectors;

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

	/**
	 * Get a health/status array for the UI.
	 *
	 * @return array{active:bool, version:string, can_manage:bool}
	 */
	public static function get_health(): array {
		return array(
			'active'     => self::is_active(),
			'version'    => self::get_version(),
			'can_manage' => self::current_user_can_manage(),
		);
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
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
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
		$terms = get_terms( array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
		) );
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
