<?php
declare(strict_types=1);
namespace SitePilot\Connectors;

use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;

/**
 * WooCommerce Order Intelligence Skill.
 *
 * Capabilities:
 * - Search orders by number, customer name, email
 * - List recent orders with pagination
 * - Update order status
 * - Create refunds
 * - Get order stats (daily/weekly/monthly)
 * - Add order notes
 */
class WooCommerceOrderSkill {

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
	 * Search orders by various criteria.
	 *
	 * @param array $args Search arguments.
	 * @return array{ok:bool, orders?:array, count?:int, error?:string}
	 */
	public function search_orders( array $args = array() ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$search_args = array(
				'limit'   => min( (int) ( $args['limit'] ?? 20 ), 100 ),
				'page'    => max( 1, (int) ( $args['page'] ?? 1 ) ),
				'orderby' => sanitize_text_field( $args['orderby'] ?? 'date' ),
				'order'   => in_array( strtoupper( $args['order'] ?? 'DESC' ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC',
			);

			// Search by order number.
			if ( ! empty( $args['order_number'] ) ) {
				$search_args['post__in'] = array( absint( $args['order_number'] ) );
			}

			// Search by customer.
			if ( ! empty( $args['customer_id'] ) ) {
				$search_args['customer_id'] = absint( $args['customer_id'] );
			}

			// Search by customer email.
			if ( ! empty( $args['customer_email'] ) ) {
				$search_args['billing_email'] = sanitize_email( $args['customer_email'] );
			}

			// Search by status.
			if ( ! empty( $args['status'] ) ) {
				$status = sanitize_text_field( $args['status'] );
				$valid_statuses = wc_get_order_statuses();
				if ( isset( $valid_statuses[ 'wc-' . $status ] ) || isset( $valid_statuses[ $status ] ) ) {
					$search_args['status'] = $status;
				}
			}

			// Date range.
			if ( ! empty( $args['date_from'] ) ) {
				$search_args['date_created'] = '>=' . sanitize_text_field( $args['date_from'] );
			}
			if ( ! empty( $args['date_to'] ) ) {
				$search_args['date_created'] = ( $search_args['date_created'] ?? '' ) . '...' . sanitize_text_field( $args['date_to'] );
			}

			$orders = wc_get_orders( $search_args );
			$result = array();

			foreach ( $orders as $order ) {
				$result[] = $this->format_order( $order );
			}

			return array(
				'ok'     => true,
				'orders' => $result,
				'count'  => count( $result ),
				'total'  => (int) ( $orders->total ?? count( $result ) ),
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_search', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Get recent orders.
	 */
	public function recent_orders( int $limit = 10 ): array {
		return $this->search_orders( array( 'limit' => $limit, 'orderby' => 'date', 'order' => 'DESC' ) );
	}

	/**
	 * Get a single order by ID.
	 */
	public function get_order( int $order_id ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'ok' => false, 'error' => __( 'Order not found.', 'sitepilot-ai' ) );
			}

			$data = $this->format_order( $order );

			// Add items detail.
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'name'     => $item->get_name(),
					'quantity' => $item->get_quantity(),
					'total'    => (float) $item->get_total(),
					'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
				);
			}
			$data['items'] = $items;

			// Add notes.
			$notes = wc_get_order_notes( array( 'order_id' => $order_id, 'limit' => 10 ) );
			$data['notes'] = array_map( function( $note ) {
				return array(
					'content' => $note->content,
					'date'    => $note->date_created->date( 'Y-m-d H:i' ),
					'author'  => $note->added_by,
				);
			}, $notes );

			return array( 'ok' => true, 'order' => $data );

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_get', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Update order status.
	 */
	public function update_status( int $order_id, string $new_status, string $note = '' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'ok' => false, 'error' => __( 'Order not found.', 'sitepilot-ai' ) );
			}

			$valid_statuses = wc_get_order_statuses();
			$status_key = 'wc-' . ltrim( $new_status, 'wc-' );
			if ( ! isset( $valid_statuses[ $status_key ] ) ) {
				return array(
					'ok'     => false,
					'error'  => __( 'Invalid status.', 'sitepilot-ai' ),
					'valid'  => array_keys( $valid_statuses ),
				);
			}

			$old_status = $order->get_status();
			$order->update_status( $new_status, $note ? $note : __( 'Status updated via SitePilot AI.', 'sitepilot-ai' ) );

			$this->logger->info( 'wc_order_status', "Order #{$order_id}: {$old_status} → {$new_status}" );

			return array(
				'ok'         => true,
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_status', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Create a refund for an order.
	 */
	public function create_refund( int $order_id, float $amount, string $reason = '' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'ok' => false, 'error' => __( 'Order not found.', 'sitepilot-ai' ) );
			}

			$max_refund = (float) $order->get_total() - (float) $order->get_total_refunded();
			if ( $amount > $max_refund ) {
				return array(
					'ok'         => false,
					'error'      => __( 'Refund amount exceeds maximum.', 'sitepilot-ai' ),
					'max_refund' => $max_refund,
				);
			}

			if ( $amount <= 0 ) {
				return array( 'ok' => false, 'error' => __( 'Refund amount must be positive.', 'sitepilot-ai' ) );
			}

			$refund = wc_create_refund( array(
				'order_id' => $order_id,
				'amount'   => $amount,
				'reason'   => $reason ? $reason : __( 'Refund via SitePilot AI.', 'sitepilot-ai' ),
			) );

			if ( is_wp_error( $refund ) ) {
				return array( 'ok' => false, 'error' => $refund->get_error_message() );
			}

			$this->logger->info( 'wc_order_refund', "Order #{$order_id}: refunded {$amount}" );

			return array(
				'ok'         => true,
				'order_id'   => $order_id,
				'refund_id'  => $refund->get_id(),
				'amount'     => $amount,
				'reason'     => $reason,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_refund', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Get order statistics.
	 */
	public function get_stats( string $period = 'month' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$now = current_time( 'timestamp' );
			$periods = array(
				'day'   => array( 'from' => date( 'Y-m-d', $now ), 'to' => date( 'Y-m-d', $now ) ),
				'week'  => array( 'from' => date( 'Y-m-d', strtotime( '-7 days', $now ) ), 'to' => date( 'Y-m-d', $now ) ),
				'month' => array( 'from' => date( 'Y-m-01', $now ), 'to' => date( 'Y-m-d', $now ) ),
				'year'  => array( 'from' => date( 'Y-01-01', $now ), 'to' => date( 'Y-m-d', $now ) ),
			);

			$p = $periods[ $period ] ?? $periods['month'];

			$args = array(
				'date_created' => $p['from'] . '...' . $p['to'],
				'limit'        => -1,
				'return'       => 'ids',
			);

			$all_orders = wc_get_orders( $args );
			$total_orders = count( $all_orders );
			$total_revenue = 0;
			$status_counts = array();

			foreach ( $all_orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$total_revenue += (float) $order->get_total();
					$status = $order->get_status();
					$status_counts[ $status ] = ( $status_counts[ $status ] ?? 0 ) + 1;
				}
			}

			return array(
				'ok'            => true,
				'period'        => $period,
				'from'          => $p['from'],
				'to'            => $p['to'],
				'total_orders'  => $total_orders,
				'total_revenue' => round( $total_revenue, 2 ),
				'avg_order'     => $total_orders > 0 ? round( $total_revenue / $total_orders, 2 ) : 0,
				'status_counts' => $status_counts,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_stats', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Add a note to an order.
	 */
	public function add_note( int $order_id, string $note, bool $customer_note = false ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'ok' => false, 'error' => __( 'Order not found.', 'sitepilot-ai' ) );
			}

			$note_id = $order->add_order_note( sanitize_textarea_field( $note ), $customer_note ? 1 : 0 );

			return array(
				'ok'       => true,
				'order_id' => $order_id,
				'note_id'  => $note_id,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_order_note', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Format an order for API response.
	 */
	private function format_order( \WC_Order $order ): array {
		return array(
			'id'             => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'total'          => (float) $order->get_total(),
			'currency'       => $order->get_currency(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
			'customer'       => array(
				'id'    => $order->get_customer_id(),
				'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'email' => $order->get_billing_email(),
			),
			'billing'        => array(
				'address' => $order->get_billing_address_1(),
				'city'    => $order->get_billing_city(),
				'country' => $order->get_billing_country(),
			),
			'payment_method' => $order->get_payment_method_title(),
			'items_count'    => $order->get_item_count(),
		);
	}
}
