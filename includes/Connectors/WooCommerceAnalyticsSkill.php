<?php
declare(strict_types=1);
namespace SitePilot\Connectors;

use SitePilot\Logging\Logger;

/**
 * WooCommerce Analytics Skill.
 *
 * Capabilities:
 * - Sales reports (daily/weekly/monthly/yearly)
 * - Top selling products
 * - Low stock alerts
 * - Customer purchase history
 * - Revenue by category
 * - Order status breakdown
 */
class WooCommerceAnalyticsSkill {

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Check if WooCommerce is active.
	 */
	public function is_available(): bool {
		return WooCommerceConnector::is_active();
	}

	/**
	 * Get sales report for a period.
	 *
	 * @param string $period day|week|month|year
	 * @return array{ok:bool, report?:array, error?:string}
	 */
	public function sales_report( string $period = 'month' ): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$now     = current_datetime()->getTimestamp();
			$periods = array(
				'day'   => array(
					'from'  => wp_date( 'Y-m-d', $now ),
					'to'    => wp_date( 'Y-m-d', $now ),
					'label' => __( 'Today', 'sitepilot-ai' ),
				),
				'week'  => array(
					'from'  => wp_date( 'Y-m-d', strtotime( '-7 days', $now ) ),
					'to'    => wp_date( 'Y-m-d', $now ),
					'label' => __( 'Last 7 days', 'sitepilot-ai' ),
				),
				'month' => array(
					'from'  => wp_date( 'Y-m-01', $now ),
					'to'    => wp_date( 'Y-m-d', $now ),
					'label' => __( 'This month', 'sitepilot-ai' ),
				),
				'year'  => array(
					'from'  => wp_date( 'Y-01-01', $now ),
					'to'    => wp_date( 'Y-m-d', $now ),
					'label' => __( 'This year', 'sitepilot-ai' ),
				),
			);

			$p = $periods[ $period ] ?? $periods['month'];

			// Get all orders in period.
			$orders = wc_get_orders(
				array(
					'date_created' => $p['from'] . '...' . $p['to'],
					'limit'        => -1,
					'status'       => array( 'processing', 'completed' ),
				)
			);

			$total_revenue = 0;
			$total_orders  = 0;
			$total_items   = 0;
			$avg_order     = 0;
			$daily_sales   = array();

			foreach ( $orders as $order ) {
				$total          = (float) $order->get_total();
				$total_revenue += $total;
				++$total_orders;
				$total_items += $order->get_item_count();

				$day = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : 'unknown';
				if ( ! isset( $daily_sales[ $day ] ) ) {
					$daily_sales[ $day ] = array(
						'revenue' => 0,
						'orders'  => 0,
					);
				}
				$daily_sales[ $day ]['revenue'] += $total;
				++$daily_sales[ $day ]['orders'];
			}

			$avg_order = $total_orders > 0 ? round( $total_revenue / $total_orders, 2 ) : 0;

			return array(
				'ok'     => true,
				'report' => array(
					'period'        => $period,
					'label'         => $p['label'],
					'from'          => $p['from'],
					'to'            => $p['to'],
					'total_revenue' => round( $total_revenue, 2 ),
					'total_orders'  => $total_orders,
					'total_items'   => $total_items,
					'avg_order'     => $avg_order,
					'daily_sales'   => $daily_sales,
					'currency'      => get_woocommerce_currency(),
				),
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_sales', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get top selling products.
	 *
	 * @param int    $limit  Number of products.
	 * @param string $period day|week|month|year
	 * @return array{ok:bool, products?:array, error?:string}
	 */
	public function top_products( int $limit = 10, string $period = 'month' ): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$now     = current_datetime()->getTimestamp();
			$periods = array(
				'day'   => wp_date( 'Y-m-d', $now ),
				'week'  => wp_date( 'Y-m-d', strtotime( '-7 days', $now ) ),
				'month' => wp_date( 'Y-m-01', $now ),
				'year'  => wp_date( 'Y-01-01', $now ),
			);

			$from = $periods[ $period ] ?? $periods['month'];

			// Get completed/processing orders.
			$orders = wc_get_orders(
				array(
					'date_created' => '>=' . $from,
					'limit'        => -1,
					'status'       => array( 'processing', 'completed' ),
				)
			);

			$product_stats = array();

			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					if ( ! $item instanceof \WC_Order_Item_Product ) {
						continue;
					}
					$product_id = $item->get_product_id();
					if ( $product_id <= 0 ) {
						continue;
					}
					if ( ! isset( $product_stats[ $product_id ] ) ) {
						$product                      = $item->get_product();
						$product_stats[ $product_id ] = array(
							'id'       => $product_id,
							'name'     => $item->get_name(),
							'sku'      => $product ? $product->get_sku() : '',
							'quantity' => 0,
							'revenue'  => 0,
						);
					}
					$product_stats[ $product_id ]['quantity'] += $item->get_quantity();
					$product_stats[ $product_id ]['revenue']  += (float) $item->get_total();
				}
			}

			// Sort by revenue descending.
			usort(
				$product_stats,
				function ( $a, $b ) {
					return $b['revenue'] <=> $a['revenue'];
				}
			);

			$top = array_slice( $product_stats, 0, min( $limit, 50 ) );

			return array(
				'ok'       => true,
				'products' => $top,
				'count'    => count( $top ),
				'period'   => $period,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_top', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get low stock products.
	 *
	 * @param int $threshold Stock threshold.
	 * @return array{ok:bool, products?:array, error?:string}
	 */
	public function low_stock( int $threshold = 5 ): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$products = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => -1,
					'manage_stock' => true,
				)
			);

			$low = array();
			foreach ( $products as $product ) {
				$stock = $product->get_stock_quantity();
				if ( null !== $stock && $stock <= $threshold ) {
					$low[] = array(
						'id'           => $product->get_id(),
						'name'         => $product->get_name(),
						'sku'          => $product->get_sku(),
						'stock'        => (int) $stock,
						'threshold'    => $threshold,
						'stock_status' => $product->get_stock_status(),
					);
				}
			}

			// Sort by stock ascending.
			usort(
				$low,
				function ( $a, $b ) {
					return $a['stock'] <=> $b['stock'];
				}
			);

			return array(
				'ok'        => true,
				'products'  => $low,
				'count'     => count( $low ),
				'threshold' => $threshold,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_stock', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get customer purchase history.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit       Number of orders.
	 * @return array{ok:bool, customer?:array, error?:string}
	 */
	public function customer_history( int $customer_id, int $limit = 20 ): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$customer = new \WC_Customer( $customer_id );
			if ( ! $customer->get_id() ) {
				return array(
					'ok'    => false,
					'error' => __( 'Customer not found.', 'sitepilot-ai' ),
				);
			}

			$orders = wc_get_orders(
				array(
					'customer_id' => $customer_id,
					'limit'       => min( $limit, 100 ),
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			$order_list  = array();
			$total_spent = 0;

			foreach ( $orders as $order ) {
				$total        = (float) $order->get_total();
				$total_spent += $total;
				$order_list[] = array(
					'id'     => $order->get_id(),
					'number' => $order->get_order_number(),
					'status' => $order->get_status(),
					'total'  => $total,
					'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
					'items'  => $order->get_item_count(),
				);
			}

			return array(
				'ok'       => true,
				'customer' => array(
					'id'          => $customer_id,
					'name'        => $customer->get_first_name() . ' ' . $customer->get_last_name(),
					'email'       => $customer->get_email(),
					'total_spent' => round( $total_spent, 2 ),
					'order_count' => count( $order_list ),
					'orders'      => $order_list,
				),
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_customer', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get revenue by category.
	 *
	 * @param string $period day|week|month|year
	 * @return array{ok:bool, categories?:array, error?:string}
	 */
	public function category_breakdown( string $period = 'month' ): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$now     = current_datetime()->getTimestamp();
			$periods = array(
				'day'   => wp_date( 'Y-m-d', $now ),
				'week'  => wp_date( 'Y-m-d', strtotime( '-7 days', $now ) ),
				'month' => wp_date( 'Y-m-01', $now ),
				'year'  => wp_date( 'Y-01-01', $now ),
			);

			$from = $periods[ $period ] ?? $periods['month'];

			$orders = wc_get_orders(
				array(
					'date_created' => '>=' . $from,
					'limit'        => -1,
					'status'       => array( 'processing', 'completed' ),
				)
			);

			$cat_stats = array();

			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					if ( ! $item instanceof \WC_Order_Item_Product ) {
						continue;
					}
					$product = $item->get_product();
					if ( ! $product ) {
						continue;
					}

					$terms    = get_the_terms( $product->get_id(), 'product_cat' );
					$cat_name = __( 'Uncategorized', 'sitepilot-ai' );
					if ( $terms && ! is_wp_error( $terms ) ) {
						$cat_name = $terms[0]->name;
					}

					if ( ! isset( $cat_stats[ $cat_name ] ) ) {
						$cat_stats[ $cat_name ] = array(
							'category' => $cat_name,
							'revenue'  => 0,
							'items'    => 0,
							'orders'   => 0,
						);
					}
					$cat_stats[ $cat_name ]['revenue'] += (float) $item->get_total();
					$cat_stats[ $cat_name ]['items']   += $item->get_quantity();
				}
			}

			// Sort by revenue.
			$cats = array_values( $cat_stats );
			usort(
				$cats,
				function ( $a, $b ) {
					return $b['revenue'] <=> $a['revenue'];
				}
			);

			return array(
				'ok'         => true,
				'categories' => $cats,
				'count'      => count( $cats ),
				'period'     => $period,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_category', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get dashboard summary.
	 *
	 * @return array{ok:bool, dashboard?:array, error?:string}
	 */
	public function dashboard(): array {
		if ( ! $this->is_available() ) {
			return array(
				'ok'    => false,
				'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ),
			);
		}

		try {
			$sales = $this->sales_report( 'month' );
			$top   = $this->top_products( 5, 'month' );
			$low   = $this->low_stock( 5 );

			return array(
				'ok'        => true,
				'dashboard' => array(
					'sales'        => $sales['ok'] ? $sales['report'] : null,
					'top_products' => $top['ok'] ? $top['products'] : array(),
					'low_stock'    => $low['ok'] ? $low['products'] : array(),
					'currency'     => get_woocommerce_currency(),
				),
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_analytics_dashboard', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}
}
