<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

use AISAAutoPilot\Support\Retention;

/**
 * Anonymous cart funnel for Store Copilot > Orders: how many shopping carts were
 * started each day and how many reached checkout. Paid checkouts come from WooCommerce's
 * own orders (WooCommerceAnalyticsSkill::checkout_dropoff()).
 *
 * WooCommerce does not record a cart that is left before checkout, so the plugin counts
 * it itself, but only when the store manager turns "Count carts" on (off by default).
 * Only two numbers per day are stored. No name, email, address, IP address, cart content
 * or identifier is kept: each WooCommerce session is counted once per step through a flag
 * in WooCommerce's own session, which expires with it. Store staff are not counted, and
 * days older than KEEP_DAYS are deleted by the daily retention job.
 */
final class CartFunnel {

	public const TABLE = 'aisaautopilot_cart_funnel';

	public const SCHEMA_OPTION = 'aisaautopilot_cart_funnel_schema';

	private const SCHEMA_VERSION = '1';

	/** Counted steps. */
	public const STAGES = array( 'cart', 'checkout' );

	/** Days kept; the card reads at most 90. */
	public const KEEP_DAYS = 120;

	/** WooCommerce session flags, so one shopper's session is counted once per step. */
	private const SESSION_FLAG = 'aisaautopilot_funnel_';

	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	/** Hooks are always added; each callback checks the switch, so a change applies at once. */
	public function register(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 20, 0 );
		add_action( 'template_redirect', array( $this, 'on_checkout_view' ), 20 );
		add_action( Retention::HOOK, array( $this, 'cron' ) );
	}

	/** WooCommerce is active, the connector is on and the store manager turned "Count carts" on. */
	public static function enabled(): bool {
		if ( ! WooCommerceConnector::is_active() ) {
			return false;
		}
		$settings = WooCommerceConnector::get_settings();

		return $settings['enabled'] && $settings['cart_funnel'];
	}

	public function table(): string {
		return $this->db->prefix . self::TABLE;
	}

	public static function ensure_schema(): void {
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' ) ) {
			return;
		}
		( new self() )->create_table();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public function is_ready(): bool {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION, '' );
	}

	public function create_table(): void {
		$table = $this->table();
		$sql   = "CREATE TABLE {$table} (
			day DATE NOT NULL,
			stage VARCHAR(20) NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,stage)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** A product went into a cart: the first time in this session, one more cart started. */
	public function on_add_to_cart(): void {
		if ( $this->countable() ) {
			$this->count_once( 'cart' );
		}
	}

	/** The checkout page with something in the cart: one more cart reached checkout. */
	public function on_checkout_view(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}
		if ( ! $this->countable() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		// A cart kept from an earlier visit (a returning customer) was started too.
		$this->count_once( 'cart' );
		$this->count_once( 'checkout' );
	}

	/**
	 * Carts started and carts that reached checkout over the last $days days (today included).
	 *
	 * @return array{cart:int, checkout:int, since:string}
	 */
	public function totals( int $days ): array {
		$totals = array(
			'cart'     => 0,
			'checkout' => 0,
			'since'    => '',
		);
		if ( ! $this->is_ready() ) {
			return $totals;
		}
		$from = $this->day( time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );
		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT stage, SUM(hits) AS hits FROM {$this->table()} WHERE day >= %s GROUP BY stage", $from ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table; live counters.
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			if ( in_array( $row['stage'], self::STAGES, true ) ) {
				$totals[ $row['stage'] ] = (int) $row['hits'];
			}
		}
		$totals['since'] = (string) $this->db->get_var( "SELECT MIN(day) FROM {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.

		return $totals;
	}

	/** The daily retention job. */
	public function cron(): void {
		$this->prune();
	}

	/** Deletes days older than KEEP_DAYS. */
	public function prune(): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}

		return (int) $this->db->query( $this->db->prepare( "DELETE FROM {$this->table()} WHERE day < %s", $this->day( time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
	}

	/** Adds one to today's count for a step (atomic, so concurrent shoppers are not lost). */
	public function bump( string $stage ): void {
		if ( ! in_array( $stage, self::STAGES, true ) ) {
			return;
		}
		self::ensure_schema();
		$this->db->query( $this->db->prepare( "INSERT INTO {$this->table()} (day, stage, hits) VALUES (%s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1", $this->day( time() ), $stage ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
	}

	/** Counting is on, WooCommerce has a session, and the visitor is not store staff. */
	private function countable(): bool {
		return self::enabled() && function_exists( 'WC' ) && WC()->session && ! current_user_can( 'manage_woocommerce' );
	}

	private function count_once( string $stage ): void {
		$flag = self::SESSION_FLAG . $stage;
		if ( WC()->session->get( $flag ) ) {
			return;
		}
		WC()->session->set( $flag, 1 );
		$this->bump( $stage );
	}

	/** The site's calendar day, so the days match the store's own reports. */
	private function day( int $timestamp ): string {
		return wp_date( 'Y-m-d', $timestamp );
	}
}
