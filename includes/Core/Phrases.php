<?php
declare(strict_types=1);

namespace SitePilot\Core;

/**
 * Centralized translation phrases for the entire plugin.
 *
 * ─── Architecture ──────────────────────────────────────────────
 *  1. Every gettext call lives HERE — one file, one truth.
 *  2. Scanning THIS FILE with xgettext / wp i18n make-pot
 *     produces the complete .pot in a single pass.
 *  3. JS side loads the same strings via sp_i18n(key) which
 *     prefers wp.i18n.__() and falls back to a local dictionary.
 *  4. PHP callers use Phrases::get('section.key') which is
 *     identical to __($literal, 'sitepilot-ai').
 *  5. Organised by module — WooCommerce, Widget, Voice, etc.
 * ──────────────────────────────────────────────────────────────
 *
 * Usage examples:
 *   // PHP
 *   use SitePilot\Core\Phrases;
 *   $msg = Phrases::get('general.no_permission');
 *   echo Phrases::section('widget')['greeting'];
 *
 *   // JS
 *   sp_i18n('widget.greeting')       // "Hi! How can I help you?"
 *   sp_i18n('woocommerce.not_active') // "WooCommerce is not active."
 */
final class Phrases {

	public const DOMAIN = 'sitepilot-ai';

	// ── All sections ─────────────────────────────────────────

	/**
	 * Every translatable string in one array.
	 * Keys follow the pattern "section.slug".
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$d = self::DOMAIN;

		return array_merge(
			self::general( $d ),
			self::errors( $d ),
			self::woocommerce( $d ),
			self::product_ai( $d ),
			self::orders( $d ),
			self::analytics( $d ),
			self::content_seo( $d ),
			self::widget( $d ),
			self::voice( $d ),
			self::handoff( $d ),
			self::settings( $d ),
		);
	}

	// ── Get a single translated string ─────────────────────

	public static function get( string $key ): string {
		static $all = null;
		if ( null === $all ) {
			$all = self::all();
		}
		return $all[ $key ] ?? $key;
	}

	// ── Get one section as an array ────────────────────────

	public static function section( string $section ): array {
		static $sections = null;
		if ( null === $sections ) {
			$sections = array();
			foreach ( self::all() as $k => $v ) {
				$parts = explode( '.', $k, 2 );
				if ( count( $parts ) === 2 ) {
					$sections[ $parts[0] ][ $parts[1] ] = $v;
				}
			}
		}
		return $sections[ $section ] ?? array();
	}

	// ── JSON for JS side ─────────────────────────────────────

	public static function to_json(): string {
		return wp_json_encode( self::all(), JSON_UNESCAPED_UNICODE );
	}

	// =========================================================
	//  SECTION DEFINITIONS — each method returns section-prefixed keys
	// =========================================================

	private static function general( string $d ): array {
		return array(
			'general.no_permission'       => __( 'You do not have permission to view this page.', $d ),
			'general.enter_question'      => __( 'Please enter a question.', $d ),
			'general.error_occurred'      => __( 'An error occurred while processing your question. Please try again.', $d ),
			'general.api_not_configured'  => __( 'AI provider is not configured. Please add your API key in Settings.', $d ),
			'general.connection_ok'       => __( 'Connection successful.', $d ),
			'general.wp_requires'         => __( 'WordPress 6.2+ is required.', $d ),
			'general.php_requires'        => __( 'PHP 8.1+ is required.', $d ),
		);
	}

	private static function errors( string $d ): array {
		return array(
			'errors.provider_unreachable'   => __( 'Could not reach the AI provider. Check the base URL and your connection.', $d ),
			'errors.provider_unauthorized'  => __( 'The AI provider rejected your API key.', $d ),
			'errors.provider_rate_limited'  => __( 'The AI provider is rate-limiting requests. Please try again shortly.', $d ),
			'errors.provider_error'         => __( 'The AI provider returned an error (HTTP %d).', $d ),
			'errors.provider_empty'         => __( 'The AI provider returned an empty response.', $d ),
			'errors.invalid_ai_output'      => __( 'The AI did not return a valid structured result. Please simplify your request and try again.', $d ),
			'errors.network_error'          => __( 'Network error. Please try again.', $d ),
			'errors.no_api_key'             => __( 'No API key configured.', $d ),
			'errors.invalid_id'             => __( 'Invalid ID.', $d ),
		);
	}

	private static function woocommerce( string $d ): array {
		return array(
			'woocommerce.not_active'       => __( 'WooCommerce is not active.', $d ),
			'woocommerce.product_not_found' => __( 'Product not found.', $d ),
			'woocommerce.order_not_found'   => __( 'Order not found.', $d ),
			'woocommerce.customer_not_found' => __( 'Customer not found.', $d ),
			'woocommerce.invalid_order_id'  => __( 'Invalid order ID.', $d ),
			'woocommerce.invalid_customer_id' => __( 'Invalid customer ID.', $d ),
			'woocommerce.invalid_status'    => __( 'Invalid status.', $d ),
			'woocommerce.refund_exceeds'    => __( 'Refund amount exceeds maximum.', $d ),
			'woocommerce.refund_positive'   => __( 'Refund amount must be positive.', $d ),
			'woocommerce.ai_not_configured' => __( 'AI provider is not configured.', $d ),
			'woocommerce.wc_not_installed'  => __( 'WooCommerce is not installed or activated.', $d ),
		);
	}

	// ── Product AI ────────────────────────────────────────────

	private static function product_ai( string $d ): array {
		return array(
			'product_ai.plan_ok'      => __( 'Product plan created successfully.', $d ),
			'product_ai.created'      => __( 'Product created successfully.', $d ),
			'product_ai.updated'      => __( 'Product updated successfully.', $d ),
			'product_ai.search'       => __( 'Search products', $d ),
			'product_ai.edit'         => __( 'Edit product', $d ),
			'product_ai.categories'   => __( 'Product categories', $d ),
			'product_ai.plan_prompt'  => __( 'Create a simple product called %s with price %s in %s status', $d ),
			'product_ai.plan_failed'  => __( 'Failed to create product plan.', $d ),
			'product_ai.execute_ok'   => __( 'Product created via WooCommerce API.', $d ),
			'product_ai.execute_failed' => __( 'Failed to execute product plan.', $d ),
		);
	}

	private static function orders( string $d ): array {
		return array(
			'orders.search'           => __( 'Search orders', $d ),
			'orders.recent'           => __( 'Recent orders', $d ),
			'orders.update_status'    => __( 'Update order status', $d ),
			'orders.create_refund'    => __( 'Create refund', $d ),
			'orders.statistics'       => __( 'Order statistics', $d ),
			'orders.add_note'         => __( 'Add order note', $d ),
			'orders.status_updated'   => __( 'Status updated via SitePilot AI.', $d ),
			'orders.refund_via_ai'    => __( 'Refund via SitePilot AI.', $d ),
			'orders.required_params'  => __( 'order_id and status are required.', $d ),
		);
	}

	private static function analytics( string $d ): array {
		return array(
			'analytics.sales_report'      => __( 'Sales report', $d ),
			'analytics.top_products'      => __( 'Top selling products', $d ),
			'analytics.low_stock'         => __( 'Low stock alerts', $d ),
			'analytics.customer_history'  => __( 'Customer purchase history', $d ),
			'analytics.category_revenue'  => __( 'Revenue by category', $d ),
			'analytics.dashboard'         => __( 'Dashboard summary', $d ),
			'analytics.today'             => __( 'Today', $d ),
			'analytics.last_7_days'       => __( 'Last 7 days', $d ),
			'analytics.this_month'        => __( 'This month', $d ),
			'analytics.this_year'         => __( 'This year', $d ),
		);
	}

	private static function content_seo( string $d ): array {
		return array(
			'content.desc_generate'    => __( 'Generate product description with AI', $d ),
			'content.seo_generate'     => __( 'Generate SEO title and meta description', $d ),
			'content.promo_generate'   => __( 'Generate promotional text', $d ),
			'content.bulk_generate'    => __( 'Bulk content generation', $d ),
			'content.apply_to_product' => __( 'Apply content to product', $d ),
			'content.templates'        => __( 'Content templates', $d ),
			'content.tone_professional' => __( 'Professional and informative', $d ),
			'content.tone_friendly'    => __( 'Friendly and approachable', $d ),
			'content.tone_luxury'      => __( 'Luxurious and premium', $d ),
			'content.tone_technical'   => __( 'Technical and detailed', $d ),
		);
	}

	// ── Chat Widget ──────────────────────────────────────────

	private static function widget( string $d ): array {
		return array(
			'widget.title'            => __( 'SitePilot AI', $d ),
			'widget.greeting'         => __( 'Hi! How can I help you?', $d ),
			'widget.connecting'       => __( 'Connecting to AI...', $d ),
			'widget.error'            => __( 'Something went wrong. Please try again.', $d ),
			'widget.retry'            => __( 'Retry', $d ),
			'widget.empty'            => __( 'No messages yet. Start a conversation!', $d ),
			'widget.listening'        => __( 'Listening...', $d ),
			'widget.thinking'         => __( 'AI is thinking...', $d ),
			'widget.talk_to_human'    => __( 'Talk to human', $d ),
			'widget.handoff_hook'     => __( 'Want to talk to a human?', $d ),
			'widget.input_placeholder' => __( 'Type your message...', $d ),
			'widget.connecting_you'   => __( 'Connecting you to support...', $d ),
			'widget.send'             => __( 'Send', $d ),
			'widget.close'            => __( 'Close', $d ),
			'widget.minimize'         => __( 'Minimize', $d ),
		);
	}

	// ── Voice ────────────────────────────────────────────────

	private static function voice( string $d ): array {
		return array(
			'voice.start_record'    => __( 'Start recording', $d ),
			'voice.stop_record'     => __( 'Stop recording', $d ),
			'voice.processing'      => __( 'Processing your speech...', $d ),
			'voice.not_supported'   => __( 'Voice input is not supported in your browser.', $d ),
			'voice.permission_error' => __( 'Microphone permission denied.', $d ),
			'voice.auto_play'       => __( 'Auto-play response', $d ),
			'voice.enable'          => __( 'Enable voice input', $d ),
		);
	}

	// ── Handoff ──────────────────────────────────────────────

	private static function handoff( string $d ): array {
		return array(
			'handoff.support_request' => __( 'Support Request', $d ),
			'handoff.sent'            => __( 'Support request sent.', $d ),
			'handoff.failed'          => __( 'Failed to send.', $d ),
			'handoff.email_label'     => __( 'Email', $d ),
			'handoff.telegram_label'  => __( 'Telegram', $d ),
			'handoff.twilio_label'    => __( 'Twilio WhatsApp', $d ),
			'handoff.webhook_label'   => __( 'Webhook', $d ),
			'handoff.no_channels'     => __( 'No handoff channels configured.', $d ),
		);
	}

	// ── Settings ──────────────────────────────────────────────

	private static function settings( string $d ): array {
		return array(
			'settings.saved'              => __( 'Settings saved.', $d ),
			'settings.enable_widget'      => __( 'Enable Widget', $d ),
			'settings.title'              => __( 'Title', $d ),
			'settings.greeting'           => __( 'Greeting', $d ),
			'settings.position'           => __( 'Position', $d ),
			'settings.position_br'        => __( 'Bottom Right', $d ),
			'settings.position_bl'        => __( 'Bottom Left', $d ),
			'settings.position_tr'        => __( 'Top Right', $d ),
			'settings.position_tl'        => __( 'Top Left', $d ),
			'settings.theme'              => __( 'Theme', $d ),
			'settings.theme_light'        => __( 'Light', $d ),
			'settings.theme_dark'         => __( 'Dark', $d ),
			'settings.voice'              => __( 'Voice', $d ),
			'settings.voice_enable'       => __( 'Enable voice input', $d ),
			'settings.voice_auto'         => __( 'Auto-play response', $d ),
			'settings.handoff'            => __( 'Handoff', $d ),
			'settings.handoff_enable'     => __( 'Enable human handoff', $d ),
			'settings.handoff_text'       => __( 'Button text', $d ),
			'settings.handoff_hook'       => __( 'Hook text', $d ),
			'settings.save'               => __( 'Save settings', $d ),
			'settings.test_connection'    => __( 'Test connection', $d ),
		);
	}
}
