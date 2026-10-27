<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

/**
 * Turns a post into clean, structured text for the knowledge index: sections under
 * their headings, plus the facts a WooCommerce product keeps outside its description
 * (price, stock, attributes). No AI request: the page's own words are indexed.
 */
final class KnowledgeExtractor {

	/**
	 * Whether a post may be indexed at all. Only published, public content without a
	 * password: a protected page must never reach an anonymous visitor through the chat.
	 */
	public static function is_indexable( \WP_Post $post ): bool {
		$allowed = 'publish' === $post->post_status && '' === (string) $post->post_password;

		/**
		 * Filters whether a post may be added to the AI knowledge index.
		 *
		 * @param bool     $allowed Published and not password-protected.
		 * @param \WP_Post $post    The post.
		 */
		return (bool) apply_filters( 'aisaautopilot_kb_should_index', $allowed, $post );
	}

	/**
	 * @return array{title:string, sections:array<int, array{heading:string, text:string}>, facts:string, excerpt:string}
	 */
	public static function extract( \WP_Post $post ): array {
		$title   = html_entity_decode( (string) get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		$content = (string) $post->post_content;
		if ( function_exists( 'strip_shortcodes' ) ) {
			$content = strip_shortcodes( $content );
		}

		/**
		 * Filters the HTML that is indexed for a post, e.g. for page builders that keep
		 * their text outside post_content.
		 *
		 * @param string   $content Post content without shortcodes.
		 * @param \WP_Post $post    The post.
		 */
		$content = (string) apply_filters( 'aisaautopilot_kb_post_html', $content, $post );

		return array(
			'title'    => $title,
			'sections' => self::html_to_sections( $content ),
			'facts'    => 'product' === $post->post_type ? self::product_facts( (int) $post->ID ) : '',
			'excerpt'  => trim( html_entity_decode( wp_strip_all_tags( (string) $post->post_excerpt ), ENT_QUOTES, 'UTF-8' ) ),
		);
	}

	/**
	 * Split HTML into sections at its headings. Paragraphs, list items and table rows
	 * become separate lines so chunking can break between them.
	 *
	 * @return array<int, array{heading:string, text:string}>
	 */
	public static function html_to_sections( string $html ): array {
		$html = (string) preg_replace( '#<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = (string) preg_replace( '/<!--.*?-->/s', ' ', $html );
		$html = (string) preg_replace_callback(
			'#<h([1-6])\b[^>]*>(.*?)</h\1>#is',
			static function ( array $m ): string {
				$heading = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $m[2] ) ) );
				return '' === $heading ? "\n" : "\n\x1E" . $heading . "\n";
			},
			$html
		);
		$html = (string) preg_replace( '#<li\b[^>]*>#i', "\n• ", $html );
		$html = (string) preg_replace( '#<(td|th)\b[^>]*>#i', ' | ', $html );
		$html = (string) preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = (string) preg_replace( '#</?(p|div|section|article|header|footer|aside|blockquote|ul|ol|li|tr|table|thead|tbody|figure|figcaption|dl|dt|dd|pre|details|summary)\b[^>]*>#i', "\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\u{00A0}", ' ', $text );

		$sections = array();
		$current  = array(
			'heading' => '',
			'lines'   => array(),
		);
		foreach ( preg_split( '/\R/u', $text ) ?: array() as $line ) {
			if ( str_starts_with( $line, "\x1E" ) ) {
				if ( array() !== $current['lines'] || '' !== $current['heading'] ) {
					$sections[] = $current;
				}
				$current = array(
					'heading' => trim( substr( $line, 1 ) ),
					'lines'   => array(),
				);
				continue;
			}
			$line = trim( (string) preg_replace( '/[ \t]+/u', ' ', $line ), " \t|" );
			if ( '' !== $line && '•' !== $line ) {
				$current['lines'][] = $line;
			}
		}
		$sections[] = $current;

		$out = array();
		foreach ( $sections as $section ) {
			$body = implode( "\n", $section['lines'] );
			if ( '' === $body && '' === $section['heading'] ) {
				continue;
			}
			$out[] = array(
				'heading' => mb_substr( $section['heading'], 0, 250 ),
				'text'    => $body,
			);
		}

		return $out;
	}

	/**
	 * One plain-language block with what a shopper asks about a product. WooCommerce
	 * keeps these outside the description, so a description-only index never sees a price.
	 */
	public static function product_facts( int $post_id ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return '';
		}

		$lines = array();
		$price = trim( html_entity_decode( wp_strip_all_tags( (string) $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $price ) {
			/* translators: %s: product price as shown in the shop. */
			$lines[] = sprintf( __( 'Price: %s', 'ai-site-assistant-autopilot' ), (string) preg_replace( '/\s+/u', ' ', $price ) );
		}
		$stock_labels = function_exists( 'wc_get_product_stock_status_options' ) ? wc_get_product_stock_status_options() : array();
		$status       = (string) $product->get_stock_status();
		if ( '' !== $status ) {
			$label = (string) ( $stock_labels[ $status ] ?? $status );
			if ( $product->managing_stock() && null !== $product->get_stock_quantity() && 'instock' === $status ) {
				/* translators: 1: stock status label, 2: number of items in stock. */
				$label = sprintf( __( '%1$s (%2$d available)', 'ai-site-assistant-autopilot' ), $label, (int) $product->get_stock_quantity() );
			}
			/* translators: %s: stock status, e.g. "In stock" or "Out of stock". */
			$lines[] = sprintf( __( 'Availability: %s', 'ai-site-assistant-autopilot' ), $label );
		}
		if ( '' !== (string) $product->get_sku() ) {
			/* translators: %s: product SKU. */
			$lines[] = sprintf( __( 'SKU: %s', 'ai-site-assistant-autopilot' ), $product->get_sku() );
		}
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
				continue;
			}
			$name  = (string) $attribute->get_name();
			$value = (string) $product->get_attribute( $name );
			if ( '' !== $value ) {
				$label   = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
				$lines[] = $label . ': ' . $value;
			}
		}
		if ( $product->has_weight() ) {
			/* translators: 1: weight, 2: weight unit. */
			$lines[] = sprintf( __( 'Shipping weight: %1$s %2$s', 'ai-site-assistant-autopilot' ), $product->get_weight(), (string) get_option( 'woocommerce_weight_unit', '' ) );
		}
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( is_array( $terms ) && array() !== $terms ) {
			/* translators: %s: comma-separated product categories. */
			$lines[] = sprintf( __( 'Categories: %s', 'ai-site-assistant-autopilot' ), implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
		}

		return implode( "\n", $lines );
	}
}
