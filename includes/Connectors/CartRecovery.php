<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Support\Retention;

/**
 * Abandoned-cart reminders (Store Copilot > Orders), off until the store manager turns
 * "Abandoned cart reminders" on under What it can do.
 *
 * WooCommerce forgets a cart that is left before the order is placed. With the switch on,
 * the checkout shows a short notice under the email field: the cart and that email are
 * kept for the retention period so the store can send one reminder, with a button not to
 * keep them. Only when the shopper has typed (or, signed in, already has) a valid email is
 * a row kept: the email, the products with quantities and prices, and the total. Nothing
 * is sent by itself: the store manager sees carts left for an hour or more and decides
 * to send the one reminder or delete the cart. The reminder links back to a filled cart.
 * The row is deleted when an order is placed (for that session or that email), when the
 * shopper declines, when the cart is emptied, by the manager, by the WordPress personal
 * data eraser, or by the daily retention job after the "Abandoned carts" period.
 */
final class CartRecovery {

	public const TABLE = 'aisaautopilot_carts';

	public const SCHEMA_OPTION = 'aisaautopilot_carts_schema';

	/** How many orders were placed after a reminder: a number only. */
	public const RECOVERED_OPTION = 'aisaautopilot_carts_recovered';

	private const SCHEMA_VERSION = '1';

	/** A cart counts as left once it has not changed for this long. */
	public const IDLE_SECONDS = HOUR_IN_SECONDS;

	public const AJAX_CAPTURE = 'aisaautopilot_cart_capture';

	public const AJAX_FORGET = 'aisaautopilot_cart_forget';

	public const NONCE = 'aisaautopilot_cart';

	/** The query argument of the reminder link. */
	public const RESTORE_ARG = 'aisaautopilot-cart';

	/** WooCommerce session keys. */
	private const SESSION_ROW = 'aisaautopilot_cart_row';

	private const SESSION_HASH = 'aisaautopilot_cart_hash';

	private const SESSION_DECLINED = 'aisaautopilot_cart_declined';

	private \wpdb $db;

	public function __construct( ?\wpdb $db = null ) {
		$this->db = $db ?? $GLOBALS['wpdb'];
	}

	/** Hooks are always added; each callback checks the switch, so a change applies at once. */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_CAPTURE, array( $this, 'ajax_capture' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_CAPTURE, array( $this, 'ajax_capture' ) );
		add_action( 'wp_ajax_' . self::AJAX_FORGET, array( $this, 'ajax_forget' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_FORGET, array( $this, 'ajax_forget' ) );
		add_action( 'woocommerce_cart_updated', array( $this, 'on_cart_updated' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'on_cart_emptied' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_placed' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_order_placed' ) );
		add_action( 'template_redirect', array( $this, 'maybe_restore' ), 5 );
	}

	/** WooCommerce is active, the connector is on and the store manager turned reminders on. */
	public static function enabled(): bool {
		if ( ! WooCommerceConnector::is_active() ) {
			return false;
		}
		$settings = WooCommerceConnector::get_settings();

		return $settings['enabled'] && $settings['cart_recovery'];
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
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token CHAR(32) NOT NULL,
			email VARCHAR(190) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			contents LONGTEXT NOT NULL,
			total DECIMAL(19,4) NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT '',
			status VARCHAR(16) NOT NULL DEFAULT 'open',
			reminded_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY email (email),
			KEY updated_at (updated_at)
		) {$this->db->get_charset_collate()};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// ── checkout ───────────────────────────────────────────────

	/** The notice and the capture script, on the checkout page only. */
	public function enqueue(): void {
		if ( ! $this->on_checkout() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$days = (int) Retention::periods()['carts_days'];
		wp_enqueue_script( 'aisaautopilot-cart-recovery', AISAAUTOPILOT_URL . 'assets/frontend/cart-recovery.js', array(), AISAAUTOPILOT_VERSION, array( 'in_footer' => true ) );
		wp_enqueue_style( 'aisaautopilot-cart-recovery', AISAAUTOPILOT_URL . 'assets/frontend/cart-recovery.css', array(), AISAAUTOPILOT_VERSION );
		wp_localize_script(
			'aisaautopilot-cart-recovery',
			'AISAAutoPilotCart',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'declined'     => (bool) WC()->session->get( self::SESSION_DECLINED ),
				'notice'       => $days > 0
					/* translators: %s: number of days. */
					? sprintf( _n( 'If you do not finish your order, this store keeps your cart and email for %s day so it can send you one reminder.', 'If you do not finish your order, this store keeps your cart and email for %s days so it can send you one reminder.', $days, 'ai-site-assistant-autopilot' ), number_format_i18n( $days ) )
					: __( 'If you do not finish your order, this store keeps your cart and email so it can send you one reminder.', 'ai-site-assistant-autopilot' ),
				'decline'      => __( 'Do not keep my cart', 'ai-site-assistant-autopilot' ),
				'declinedText' => __( 'Your cart and email will not be kept.', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	public function ajax_capture(): void {
		$ok = check_ajax_referer( self::NONCE, 'nonce', false );
		wp_send_json(
			$ok ? $this->capture( sanitize_text_field( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) ) : array(
				'ok'   => false,
				'code' => 'bad_nonce',
			)
		); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just above.
	}

	public function ajax_forget(): void {
		$ok = check_ajax_referer( self::NONCE, 'nonce', false );
		wp_send_json(
			$ok ? $this->decline() : array(
				'ok'   => false,
				'code' => 'bad_nonce',
			)
		);
	}

	/**
	 * Keeps (or refreshes) this session's cart with the email the shopper typed at checkout.
	 *
	 * @return array{ok:bool, code:string}
	 */
	public function capture( string $email ): array {
		if ( ! self::enabled() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return array(
				'ok'   => false,
				'code' => 'off',
			);
		}
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'ok'   => false,
				'code' => 'staff',
			);
		}
		if ( WC()->session->get( self::SESSION_DECLINED ) ) {
			return array(
				'ok'   => false,
				'code' => 'declined',
			);
		}
		$email = sanitize_email( $email );
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 190 ) {
			return array(
				'ok'   => false,
				'code' => 'invalid_email',
			);
		}
		if ( WC()->cart->is_empty() ) {
			return array(
				'ok'   => false,
				'code' => 'empty',
			);
		}
		if ( ! RateLimiter::allow( 'public_cart', 30, HOUR_IN_SECONDS ) ) {
			return array(
				'ok'   => false,
				'code' => 'rate_limited',
			);
		}

		return array(
			'ok'   => $this->save_snapshot( $email ) > 0,
			'code' => 'kept',
		);
	}

	/**
	 * The shopper chose "Do not keep my cart": the row goes and nothing more is kept in this session.
	 *
	 * @return array{ok:bool, code:string}
	 */
	public function decline(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array(
				'ok'   => false,
				'code' => 'off',
			);
		}
		WC()->session->set( self::SESSION_DECLINED, 1 );
		$this->forget_session_row();

		return array(
			'ok'   => true,
			'code' => 'declined',
		);
	}

	/** A changed cart refreshes its kept copy; an emptied cart deletes it. */
	public function on_cart_updated(): void {
		if ( ! self::enabled() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}
		$id = (int) WC()->session->get( self::SESSION_ROW );
		if ( $id <= 0 ) {
			return;
		}
		if ( WC()->cart->is_empty() ) {
			$this->forget_session_row();
			return;
		}
		if ( WC()->cart->get_cart_hash() === WC()->session->get( self::SESSION_HASH ) ) {
			return;
		}
		$row = $this->get( $id );
		if ( null !== $row ) {
			$this->save_snapshot( $row['email'] );
		}
	}

	/** An emptied cart has nothing left to remind about. */
	public function on_cart_emptied(): void {
		if ( function_exists( 'WC' ) && WC()->session && (int) WC()->session->get( self::SESSION_ROW ) > 0 ) {
			$this->forget_session_row();
		}
	}

	/**
	 * An order was placed: this session's cart, and any cart kept for the same email, is
	 * deleted. A cart that had a reminder adds one to the recovered count.
	 *
	 * @param mixed $order_or_id A WC_Order (block checkout) or an order ID (classic checkout).
	 */
	public function on_order_placed( $order_or_id ): void {
		if ( ! $this->is_ready() ) {
			return;
		}
		$order = $order_or_id instanceof \WC_Order ? $order_or_id : ( function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_or_id ) : null );
		$ids   = array();
		if ( function_exists( 'WC' ) && WC()->session ) {
			$ids[] = (int) WC()->session->get( self::SESSION_ROW );
			WC()->session->set( self::SESSION_ROW, null );
			WC()->session->set( self::SESSION_HASH, null );
		}
		$email = $order instanceof \WC_Order ? sanitize_email( $order->get_billing_email() ) : '';
		if ( '' !== $email ) {
			$ids = array_merge( $ids, array_map( 'intval', (array) $this->db->get_col( $this->db->prepare( "SELECT id FROM {$this->table()} WHERE email = %s", $email ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
		}
		$recovered = 0;
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$row = $this->get( $id );
			if ( null === $row ) {
				continue;
			}
			$recovered += 'reminded' === $row['status'] ? 1 : 0;
			$this->delete( $id );
		}
		if ( $recovered > 0 ) {
			update_option( self::RECOVERED_OPTION, (int) get_option( self::RECOVERED_OPTION, 0 ) + $recovered, false );
		}
	}

	/** The reminder link: fills the cart again and opens checkout. */
	public function maybe_restore(): void {
		$token = isset( $_GET[ self::RESTORE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESTORE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the 128-bit token is the credential; it only refills a cart.
		if ( '' === $token ) {
			return;
		}
		if ( ! self::enabled() || ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session || 1 !== preg_match( '/^[a-f0-9]{32}$/', $token ) || ! RateLimiter::allow( 'public_cart_restore', 20, HOUR_IN_SECONDS ) ) {
			wp_safe_redirect( remove_query_arg( self::RESTORE_ARG ) );
			exit;
		}
		$row = $this->is_ready() ? $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE token = %s", $token ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
		if ( ! is_array( $row ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		// Emptying the cart first must not delete the cart being restored.
		WC()->session->set( self::SESSION_ROW, null );
		WC()->cart->empty_cart();
		foreach ( (array) json_decode( (string) $row['contents'], true ) as $item ) {
			$product = wc_get_product( (int) ( $item['variation_id'] ?? 0 ) ?: (int) ( $item['product_id'] ?? 0 ) );
			if ( $product && $product->is_purchasable() ) {
				WC()->cart->add_to_cart( (int) $item['product_id'], max( 1, (int) $item['quantity'] ), (int) ( $item['variation_id'] ?? 0 ) );
			}
		}
		if ( WC()->customer ) {
			WC()->customer->set_billing_email( (string) $row['email'] );
		}
		WC()->session->set( self::SESSION_ROW, (int) $row['id'] );
		WC()->session->set( self::SESSION_HASH, WC()->cart->get_cart_hash() );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	// ── store manager ──────────────────────────────────────────

	/**
	 * Carts left for at least IDLE_SECONDS, newest first.
	 *
	 * @return array<int, array{id:int, email:string, items:array<int, array{name:string, quantity:int}>, total:float, currency:string, status:string, reminded_at:string, updated_at:string}>
	 */
	public function abandoned( int $limit = 50 ): array {
		if ( ! $this->is_ready() ) {
			return array();
		}
		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table()} WHERE updated_at <= %s ORDER BY updated_at DESC LIMIT %d", gmdate( 'Y-m-d H:i:s', time() - self::IDLE_SECONDS ), max( 1, min( 200, $limit ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
			ARRAY_A
		);

		return array_map( array( $this, 'normalize' ), (array) $rows );
	}

	/**
	 * Sends the one reminder for a cart.
	 *
	 * @return array{ok:bool, code:string}
	 */
	public function remind( int $id ): array {
		$row = $this->get( $id );
		if ( null === $row ) {
			return array(
				'ok'   => false,
				'code' => 'not_found',
			);
		}
		if ( 'reminded' === $row['status'] ) {
			return array(
				'ok'   => false,
				'code' => 'already_reminded',
			);
		}
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$link    = add_query_arg( self::RESTORE_ARG, $row['token'], home_url( '/' ) );
		$message = SiteLanguage::for_site(
			static function () use ( $row, $site, $link ): array {
				$lines = array();
				foreach ( $row['items'] as $item ) {
					$lines[] = '- ' . $item['quantity'] . ' × ' . $item['name'];
				}
				return array(
					/* translators: %s: site name. */
					'subject' => sprintf( __( 'You left something in your cart at %s', 'ai-site-assistant-autopilot' ), $site ),
					'body'    => implode(
						"\n\n",
						array(
							__( 'Hello,', 'ai-site-assistant-autopilot' ),
							/* translators: %s: site name. */
							sprintf( __( 'You started an order at %s but did not finish it. Your cart is still waiting:', 'ai-site-assistant-autopilot' ), $site ),
							implode( "\n", $lines ),
							/* translators: %s: link that refills the cart. */
							sprintf( __( 'To finish your order, open: %s', 'ai-site-assistant-autopilot' ), $link ),
							__( 'You received this one reminder because you entered your email at checkout. We will not send another, and your cart will be deleted after a while.', 'ai-site-assistant-autopilot' ),
						)
					),
				);
			}
		);
		$sent    = wp_mail( $row['email'], $message['subject'], $message['body'] );
		if ( ! $sent ) {
			( new Logger() )->error( 'store.cart_reminder_failed', 'An abandoned-cart reminder could not be sent.' );
			return array(
				'ok'   => false,
				'code' => 'mail_failed',
			);
		}
		$this->db->update(
			$this->table(),
			array(
				'status'      => 'reminded',
				'reminded_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		( new Logger() )->info( 'store.cart_reminder_sent', 'An abandoned-cart reminder was sent.' );

		return array(
			'ok'   => true,
			'code' => 'reminded',
		);
	}

	public function delete( int $id ): bool {
		return $this->is_ready() && (int) $this->db->delete( $this->table(), array( 'id' => $id ), array( '%d' ) ) > 0;
	}

	public function recovered(): int {
		return (int) get_option( self::RECOVERED_OPTION, 0 );
	}

	// ── retention and privacy ──────────────────────────────────

	/** Deletes carts not changed since $before (a timestamp). */
	public function forget_before( int $before ): int {
		if ( ! $this->is_ready() ) {
			return 0;
		}

		return (int) $this->db->query( $this->db->prepare( "DELETE FROM {$this->table()} WHERE updated_at < %s", gmdate( 'Y-m-d H:i:s', $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.
	}

	/**
	 * Kept carts for an email address (WordPress personal data exporter and eraser).
	 *
	 * @return array<int, array{id:int, email:string, items:array<int, array{name:string, quantity:int}>, total:float, currency:string, status:string, reminded_at:string, updated_at:string}>
	 */
	public function rows_for_email( string $email ): array {
		if ( ! $this->is_ready() || '' === $email ) {
			return array();
		}
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.

		return array_map( array( $this, 'normalize' ), (array) $rows );
	}

	// ── internals ──────────────────────────────────────────────

	/** @return array{id:int, email:string, token:string, items:array<int, array{name:string, quantity:int}>, total:float, currency:string, status:string, reminded_at:string, updated_at:string}|null */
	public function get( int $id ): ?array {
		if ( $id <= 0 || ! $this->is_ready() ) {
			return null;
		}
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table.

		return is_array( $row ) ? $this->normalize( $row ) + array( 'token' => (string) $row['token'] ) : null;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{id:int, email:string, items:array<int, array{name:string, quantity:int}>, total:float, currency:string, status:string, reminded_at:string, updated_at:string}
	 */
	private function normalize( array $row ): array {
		$items = array();
		foreach ( (array) json_decode( (string) $row['contents'], true ) as $item ) {
			$items[] = array(
				'name'     => (string) ( $item['name'] ?? '' ),
				'quantity' => (int) ( $item['quantity'] ?? 0 ),
			);
		}

		return array(
			'id'          => (int) $row['id'],
			'email'       => (string) $row['email'],
			'items'       => $items,
			'total'       => (float) $row['total'],
			'currency'    => (string) $row['currency'],
			'status'      => (string) $row['status'],
			'reminded_at' => (string) ( $row['reminded_at'] ?? '' ),
			'updated_at'  => (string) $row['updated_at'],
		);
	}

	/** Writes this session's cart; returns the row id, or 0. */
	private function save_snapshot( string $email ): int {
		self::ensure_schema();
		$items = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$product = $line['data'] ?? null;
			$items[] = array(
				'product_id'   => (int) ( $line['product_id'] ?? 0 ),
				'variation_id' => (int) ( $line['variation_id'] ?? 0 ),
				'quantity'     => (int) ( $line['quantity'] ?? 0 ),
				'name'         => $product instanceof \WC_Product ? wp_strip_all_tags( $product->get_name() ) : '',
				'total'        => (float) ( $line['line_total'] ?? 0 ),
			);
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'email'      => $email,
			'user_id'    => get_current_user_id(),
			'contents'   => (string) wp_json_encode( $items ),
			'total'      => (float) WC()->cart->get_total( 'edit' ),
			'currency'   => get_woocommerce_currency(),
			'updated_at' => $now,
		);
		$id   = (int) WC()->session->get( self::SESSION_ROW );
		if ( $id > 0 && null !== $this->get( $id ) ) {
			// A changed cart is a new chance: a reminder already sent stays sent.
			$this->db->update( $this->table(), $data, array( 'id' => $id ), array( '%s', '%d', '%s', '%f', '%s', '%s' ), array( '%d' ) );
		} else {
			$data['token']      = bin2hex( random_bytes( 16 ) );
			$data['status']     = 'open';
			$data['created_at'] = $now;
			$ok                 = $this->db->insert( $this->table(), $data, array( '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ) );
			$id                 = false === $ok ? 0 : (int) $this->db->insert_id;
			WC()->session->set( self::SESSION_ROW, $id );
		}
		WC()->session->set( self::SESSION_HASH, WC()->cart->get_cart_hash() );

		return $id;
	}

	private function forget_session_row(): void {
		$id = (int) WC()->session->get( self::SESSION_ROW );
		if ( $id > 0 ) {
			$this->delete( $id );
		}
		WC()->session->set( self::SESSION_ROW, null );
		WC()->session->set( self::SESSION_HASH, null );
	}

	private function on_checkout(): bool {
		return self::enabled() && function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && function_exists( 'WC' ) && WC()->session && ! current_user_can( 'manage_woocommerce' );
	}
}
