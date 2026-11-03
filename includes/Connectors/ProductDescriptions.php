<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Logging\Logger;

/**
 * AI descriptions for products that already exist, reviewed one by one.
 *
 * Bulk catalog work is the most valued AI use in store-owner reviews, and the
 * description generator already existed without a route. This keeps the Phase 1
 * promise as narrow as possible: it is off until the administrator turns on
 * "Product descriptions" in Store Copilot, it writes the long description and nothing
 * else (never price, stock, title, status or images), every product needs its own
 * approval of a reviewed draft, an approval is refused when the description changed
 * after the draft was written, and the replaced text is kept so Undo can restore it.
 */
final class ProductDescriptions {

	public const CONNECTOR = 'woocommerce_description';

	/** The description an approval replaced, for Undo: array{html:string, time:int, user:int}. */
	public const PREVIOUS_META = '_aisaautopilot_previous_description';

	/** A description shorter than this many words counts as missing or thin. */
	public const SHORT_WORDS = 40;

	public const TONES = array( 'professional', 'friendly', 'luxury', 'technical' );

	/** The administrator turned it on, and the Store Copilot connector can run. */
	public static function enabled(): bool {
		$settings = WooCommerceConnector::get_settings();

		return WooCommerceConnector::is_active() && $settings['enabled'] && $settings['enable_descriptions'];
	}

	/**
	 * Products to write for, thinnest description first.
	 *
	 * @return array<int, array{id:int, name:string, status:string, words:int, short:bool, can_undo:bool}>
	 */
	public function candidates( bool $only_short = true, int $limit = 50 ): array {
		if ( ! WooCommerceConnector::is_active() || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$products = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'   => 200,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		$rows     = array();
		foreach ( (array) $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$words = self::word_count( (string) $product->get_description() );
			if ( $only_short && $words >= self::SHORT_WORDS ) {
				continue;
			}
			$rows[] = array(
				'id'       => (int) $product->get_id(),
				'name'     => (string) $product->get_name(),
				'status'   => (string) $product->get_status(),
				'words'    => $words,
				'short'    => $words < self::SHORT_WORDS,
				'can_undo' => is_array( get_post_meta( (int) $product->get_id(), self::PREVIOUS_META, true ) ),
			);
		}
		usort( $rows, static fn ( array $a, array $b ): int => $a['words'] <=> $b['words'] );

		return array_slice( $rows, 0, max( 1, min( 100, $limit ) ) );
	}

	/**
	 * Asks the AI for a description and keeps it as a proposal for this administrator.
	 *
	 * @return array{ok:bool, code:string, message?:string, action_id?:string, product_id?:int, name?:string, current?:string, draft?:string, expires_in?:int}
	 */
	public function draft( int $product_id, string $tone ): array {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return array(
				'ok'      => false,
				'code'    => 'product_not_found',
				'message' => __( 'Product not found.', 'ai-site-assistant-autopilot' ),
			);
		}
		$tone   = in_array( $tone, self::TONES, true ) ? $tone : 'professional';
		$result = ( new WooCommerceContentSkill() )->generate_description( $product_id, $tone, 150 );
		if ( empty( $result['ok'] ) || '' === trim( (string) ( $result['description'] ?? '' ) ) ) {
			return array(
				'ok'      => false,
				'code'    => 'draft_failed',
				'message' => __( 'The AI service did not write a description. Try again, or check Setup & Connection.', 'ai-site-assistant-autopilot' ),
			);
		}
		$draft     = self::clean_html( (string) $result['description'] );
		$current   = (string) $product->get_description();
		$action_id = ( new ActionStore() )->create(
			array(
				'product_id'   => $product_id,
				'description'  => $draft,
				'current_hash' => md5( $current ),
			),
			self::CONNECTOR
		);

		return array(
			'ok'         => true,
			'code'       => 'drafted',
			'action_id'  => $action_id,
			'product_id' => $product_id,
			'name'       => (string) $product->get_name(),
			'current'    => wp_strip_all_tags( $current ),
			'draft'      => $draft,
			'expires_in' => ActionStore::ttl(),
		);
	}

	/**
	 * Writes a reviewed draft (or the administrator's edit of it) as the product's description.
	 *
	 * @return array{ok:bool, code:string, message:string}
	 */
	public function apply( string $action_id, string $edited = '' ): array {
		$claim = ( new ActionStore() )->claim( $action_id, get_current_user_id(), self::CONNECTOR );
		if ( 'claimed' !== $claim['status'] ) {
			$messages = array(
				'missing'         => __( 'This draft expired or was already used. Write a new one.', 'ai-site-assistant-autopilot' ),
				'forbidden'       => __( 'Only the administrator who asked for this draft can apply it.', 'ai-site-assistant-autopilot' ),
				'already_claimed' => __( 'This draft was already applied.', 'ai-site-assistant-autopilot' ),
				'wrong_connector' => __( 'This draft is not a product description.', 'ai-site-assistant-autopilot' ),
			);

			return array(
				'ok'      => false,
				'code'    => 'draft_' . $claim['status'],
				'message' => $messages[ $claim['status'] ] ?? $messages['missing'],
			);
		}
		$plan    = (array) ( $claim['record']['plan'] ?? array() );
		$id      = (int) ( $plan['product_id'] ?? 0 );
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return array(
				'ok'      => false,
				'code'    => 'product_not_found',
				'message' => __( 'Product not found.', 'ai-site-assistant-autopilot' ),
			);
		}
		$current = (string) $product->get_description();
		if ( md5( $current ) !== (string) ( $plan['current_hash'] ?? '' ) ) {
			return array(
				'ok'      => false,
				'code'    => 'changed_since_draft',
				'message' => __( 'Someone changed this description after the draft was written, so it was not replaced. Write a new draft.', 'ai-site-assistant-autopilot' ),
			);
		}
		$text = '' !== trim( $edited ) ? self::clean_html( $edited ) : (string) ( $plan['description'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			return array(
				'ok'      => false,
				'code'    => 'empty_description',
				'message' => __( 'The description is empty.', 'ai-site-assistant-autopilot' ),
			);
		}

		update_post_meta(
			$id,
			self::PREVIOUS_META,
			array(
				'html' => $current,
				'time' => time(),
				'user' => get_current_user_id(),
			)
		);
		$product->set_description( $text );
		$product->save();
		( new Logger() )->info(
			'store.description_applied',
			'An approved AI description replaced a product description.',
			array(
				'action_id'    => $action_id,
				'connector_id' => self::CONNECTOR,
			)
		);

		return array(
			'ok'      => true,
			'code'    => 'applied',
			'message' => __( 'Applied. The previous description is kept; Undo puts it back.', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * Puts back the description the last approval replaced.
	 *
	 * @return array{ok:bool, code:string, message:string}
	 */
	public function undo( int $product_id ): array {
		$previous = get_post_meta( $product_id, self::PREVIOUS_META, true );
		$product  = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! is_array( $previous ) || ! $product instanceof \WC_Product ) {
			return array(
				'ok'      => false,
				'code'    => 'nothing_to_undo',
				'message' => __( 'There is no earlier description to put back.', 'ai-site-assistant-autopilot' ),
			);
		}
		$product->set_description( (string) ( $previous['html'] ?? '' ) );
		$product->save();
		delete_post_meta( $product_id, self::PREVIOUS_META );
		( new Logger() )->info( 'store.description_restored', 'A product description was restored after an AI change.', array( 'connector_id' => self::CONNECTOR ) );

		return array(
			'ok'      => true,
			'code'    => 'restored',
			'message' => __( 'The previous description is back.', 'ai-site-assistant-autopilot' ),
		);
	}

	/** Words in a description, whatever its markup or script. */
	public static function word_count( string $html ): int {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );

		return '' === $text ? 0 : count( explode( ' ', $text ) );
	}

	/** Model output reduced to the markup a product description may hold. */
	private static function clean_html( string $html ): string {
		$html = (string) preg_replace( '/^```(?:html)?\s*|\s*```$/i', '', trim( $html ) );

		return trim( wp_kses_post( $html ) );
	}
}
