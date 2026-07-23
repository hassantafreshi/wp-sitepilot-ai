<?php
declare(strict_types=1);
namespace SitePilot\Connectors;

use SitePilot\Logging\Logger;
use SitePilot\Providers\ProviderManager;

/**
 * WooCommerce Content + SEO Skill.
 *
 * Capabilities:
 * - Generate product descriptions with AI
 * - Generate SEO titles + meta descriptions
 * - Generate promotional text
 * - Bulk content generation for multiple products
 * - Content templates
 */
class WooCommerceContentSkill {

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
	 * Generate product description with AI.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $tone       Tone: professional, friendly, luxury, technical.
	 * @param int    $length     Target length in words.
	 * @return array{ok:bool, description?:string, error?:string}
	 */
	public function generate_description( int $product_id, string $tone = 'professional', int $length = 150 ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array( 'ok' => false, 'error' => __( 'Product not found.', 'sitepilot-ai' ) );
			}

			$manager = new ProviderManager();
			if ( ! $manager->is_configured() ) {
				return array( 'ok' => false, 'error' => __( 'AI provider is not configured.', 'sitepilot-ai' ) );
			}

			// Gather product info.
			$name = $product->get_name();
			$sku = $product->get_sku();
			$price = $product->get_price();
			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			$tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
			$attributes = $product->get_attributes();
			$existing_desc = $product->get_description();

			// Build attribute string.
			$attr_str = '';
			foreach ( $attributes as $attr ) {
				if ( $attr->is_taxonomy() ) {
					$terms = wc_get_product_terms( $product_id, $attr->get_name(), array( 'fields' => 'names' ) );
					$attr_str .= $attr->get_name() . ': ' . implode( ', ', $terms ) . '; ';
				}
			}

			$tone_map = array(
				'professional' => __( 'Professional and informative', 'sitepilot-ai' ),
				'friendly'     => __( 'Friendly and approachable', 'sitepilot-ai' ),
				'luxury'       => __( 'Luxurious and premium', 'sitepilot-ai' ),
				'technical'    => __( 'Technical and detailed', 'sitepilot-ai' ),
			);

			$tone_desc = $tone_map[ $tone ] ?? $tone_map['professional'];

			$system_prompt = "You are an expert e-commerce copywriter. Generate compelling product descriptions.\n"
				. "Tone: {$tone_desc}\n"
				. "Target length: approximately {$length} words.\n"
				. "Include key features and benefits.\n"
				. "Use HTML formatting (h2, p, ul, li, strong) for structure.\n"
				. "Do not include the product name as a heading.\n"
				. "Respond in the same language as the product name.";

			$user_prompt = "Product: {$name}\n"
				. ( $sku ? "SKU: {$sku}\n" : '' )
				. ( $price ? "Price: {$price}\n" : '' )
				. ( ! empty( $categories ) && ! is_wp_error( $categories ) ? "Categories: " . implode( ', ', $categories ) . "\n" : '' )
				. ( ! empty( $tags ) && ! is_wp_error( $tags ) ? "Tags: " . implode( ', ', $tags ) . "\n" : '' )
				. ( $attr_str ? "Attributes: {$attr_str}\n" : '' )
				. ( $existing_desc ? "Existing description (improve it): " . wp_strip_all_tags( $existing_desc ) . "\n" : '' )
				. "\nGenerate a compelling product description.";

			$provider = $manager->make();
			$result = $provider->generate_text( $system_prompt, $user_prompt );

			$this->logger->info( 'wc_content_desc', "Generated description for product #{$product_id}" );

			return array(
				'ok'          => true,
				'description' => $result['content'],
				'product_id'  => $product_id,
				'product'     => $name,
				'tone'        => $tone,
				'usage'       => $result['usage'],
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_content_desc', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Generate SEO title + meta description.
	 *
	 * @param int $product_id Product ID.
	 * @return array{ok:bool, seo?:array, error?:string}
	 */
	public function generate_seo( int $product_id ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array( 'ok' => false, 'error' => __( 'Product not found.', 'sitepilot-ai' ) );
			}

			$manager = new ProviderManager();
			if ( ! $manager->is_configured() ) {
				return array( 'ok' => false, 'error' => __( 'AI provider is not configured.', 'sitepilot-ai' ) );
			}

			$name = $product->get_name();
			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			$cat_str = ( ! empty( $categories ) && ! is_wp_error( $categories ) ) ? implode( ', ', $categories ) : '';

			$system_prompt = "You are an SEO expert for e-commerce.\n"
				. "Generate an SEO-optimized title (max 60 characters) and meta description (max 160 characters).\n"
				. "Include relevant keywords naturally.\n"
				. "Respond in JSON format: {\"title\": \"...\", \"meta_description\": \"...\", \"keywords\": [\"...\", \"...\"]}\n"
				. "Respond in the same language as the product name.";

			$user_prompt = "Product: {$name}\n"
				. ( $cat_str ? "Category: {$cat_str}\n" : '' )
				. "Price: " . $product->get_price() . "\n"
				. "Generate SEO title and meta description.";

			$provider = $manager->make();
			$result = $provider->generate_structured_output( $system_prompt, $user_prompt );

			$seo = array(
				'title'            => sanitize_text_field( $result['title'] ?? $name ),
				'meta_description' => sanitize_textarea_field( $result['meta_description'] ?? '' ),
				'keywords'         => array_map( 'sanitize_text_field', $result['keywords'] ?? array() ),
			);

			$this->logger->info( 'wc_content_seo', "Generated SEO for product #{$product_id}" );

			return array(
				'ok'         => true,
				'seo'        => $seo,
				'product_id' => $product_id,
				'product'    => $name,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_content_seo', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Generate promotional text.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       Type: short (social), medium (email), long (blog).
	 * @return array{ok:bool, promo?:string, error?:string}
	 */
	public function generate_promo( int $product_id, string $type = 'short' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array( 'ok' => false, 'error' => __( 'Product not found.', 'sitepilot-ai' ) );
			}

			$manager = new ProviderManager();
			if ( ! $manager->is_configured() ) {
				return array( 'ok' => false, 'error' => __( 'AI provider is not configured.', 'sitepilot-ai' ) );
			}

			$name = $product->get_name();
			$price = $product->get_price();
			$sale_price = $product->get_sale_price();

			$type_map = array(
				'short'  => __( 'Short social media post (max 280 characters). Include emojis and hashtags.', 'sitepilot-ai' ),
				'medium' => __( 'Medium email newsletter paragraph (100-150 words). Engaging and persuasive.', 'sitepilot-ai' ),
				'long'   => __( 'Long blog-style promotional article (300-400 words). Storytelling approach.', 'sitepilot-ai' ),
			);

			$type_desc = $type_map[ $type ] ?? $type_map['short'];

			$system_prompt = "You are a marketing copywriter.\n"
				. "Task: {$type_desc}\n"
				. "Make it compelling and conversion-focused.\n"
				. "Respond in the same language as the product name.";

			$user_prompt = "Product: {$name}\n"
				. "Price: {$price}\n"
				. ( $sale_price ? "Sale Price: {$sale_price} (ON SALE!)\n" : '' )
				. "Generate promotional text.";

			$provider = $manager->make();
			$result = $provider->generate_text( $system_prompt, $user_prompt );

			$this->logger->info( 'wc_content_promo', "Generated promo for product #{$product_id}" );

			return array(
				'ok'         => true,
				'promo'      => $result['content'],
				'product_id' => $product_id,
				'product'    => $name,
				'type'       => $type,
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_content_promo', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Bulk generate descriptions for multiple products.
	 *
	 * @param array  $product_ids Array of product IDs.
	 * @param string $tone        Tone for descriptions.
	 * @return array{ok:bool, results?:array, error?:string}
	 */
	public function bulk_generate( array $product_ids, string $tone = 'professional' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		$results = array();
		$success = 0;
		$failed = 0;

		foreach ( array_slice( $product_ids, 0, 20 ) as $pid ) {
			$pid = absint( $pid );
			if ( $pid <= 0 ) {
				continue;
			}

			$r = $this->generate_description( $pid, $tone );
			if ( $r['ok'] ) {
				$success++;
				$results[] = array(
					'product_id'  => $pid,
					'product'     => $r['product'],
					'description' => $r['description'],
					'status'      => 'generated',
				);
			} else {
				$failed++;
				$results[] = array(
					'product_id' => $pid,
					'status'     => 'failed',
					'error'      => $r['error'],
				);
			}
		}

		return array(
			'ok'      => true,
			'results' => $results,
			'success' => $success,
			'failed'  => $failed,
			'total'   => count( $product_ids ),
		);
	}

	/**
	 * Apply generated content to a product.
	 *
	 * @param int    $product_id  Product ID.
	 * @param string $description New description.
	 * @param string $seo_title   SEO title (optional).
	 * @param string $seo_desc    SEO meta description (optional).
	 * @return array{ok:bool, error?:string}
	 */
	public function apply_to_product( int $product_id, string $description, string $seo_title = '', string $seo_desc = '' ): array {
		if ( ! $this->is_available() ) {
			return array( 'ok' => false, 'error' => __( 'WooCommerce is not active.', 'sitepilot-ai' ) );
		}

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array( 'ok' => false, 'error' => __( 'Product not found.', 'sitepilot-ai' ) );
			}

			// Update description.
			$product->set_description( wp_kses_post( $description ) );

			// Update SEO fields (Yoast/RankMath compatible).
			if ( $seo_title ) {
				update_post_meta( $product_id, '_yoast_wpseo_title', sanitize_text_field( $seo_title ) );
				update_post_meta( $product_id, 'rank_math_focus_keyword', sanitize_text_field( $seo_title ) );
			}
			if ( $seo_desc ) {
				update_post_meta( $product_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $seo_desc ) );
				update_post_meta( $product_id, 'rank_math_description', sanitize_textarea_field( $seo_desc ) );
			}

			$product->save();

			$this->logger->info( 'wc_content_apply', "Applied content to product #{$product_id}" );

			return array(
				'ok'         => true,
				'product_id' => $product_id,
				'updated'    => array( 'description', $seo_title ? 'seo_title' : null, $seo_desc ? 'seo_desc' : null ),
			);

		} catch ( \Throwable $e ) {
			$this->logger->error( 'wc_content_apply', $e->getMessage() );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Get content templates.
	 *
	 * @return array{ok:bool, templates:array}
	 */
	public function get_templates(): array {
		return array(
			'ok'        => true,
			'templates' => array(
				array(
					'id'   => 'description',
					'name' => __( 'Product Description', 'sitepilot-ai' ),
					'tones' => array( 'professional', 'friendly', 'luxury', 'technical' ),
				),
				array(
					'id'   => 'seo',
					'name' => __( 'SEO Title + Meta', 'sitepilot-ai' ),
					'tones' => array(),
				),
				array(
					'id'   => 'promo_short',
					'name' => __( 'Social Media Post', 'sitepilot-ai' ),
					'tones' => array(),
				),
				array(
					'id'   => 'promo_medium',
					'name' => __( 'Email Newsletter', 'sitepilot-ai' ),
					'tones' => array(),
				),
				array(
					'id'   => 'promo_long',
					'name' => __( 'Blog Article', 'sitepilot-ai' ),
					'tones' => array(),
				),
			),
		);
	}
}
