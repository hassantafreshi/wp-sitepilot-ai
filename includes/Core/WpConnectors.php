<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

/**
 * WordPress 7.0+ Connectors API integration.
 *
 * When WordPress 7's Connectors API is available, the user can choose to
 * manage their AI provider API key through the native Settings → Connectors
 * screen instead of (or alongside) AISAAutoPilot's own encrypted KeyVault.
 *
 * The integration is fully backward-compatible: on WordPress < 7.0 the
 * Connectors API functions don't exist, so we fall back to KeyVault silently.
 *
 * @see https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
 */
final class WpConnectors {

	/** The connector slug registered with WordPress. */
	public const CONNECTOR_ID = 'ai-site-assistant-autopilot';

	/** The wp_options key where WP Connectors stores the API key. */
	public const WP_OPTION_KEY = 'aisaautopilot_wp_connector_key';

	/**
	 * Check whether the WordPress Connectors API is available (WP 7.0+).
	 */
	public static function is_available(): bool {
		return function_exists( 'wp_register_connector' )
			|| function_exists( 'wp_connectors_register' );
	}

	/**
	 * Register AI Site Assistant AutoPilot as a connector in WordPress 7's Connectors API.
	 * Called on `init` — safe to call on WP < 7 (no-ops gracefully).
	 */
	public static function register(): void {
		if ( ! self::is_available() ) {
			return;
		}

		$args = array(
			'label'       => __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
			'description' => __( 'AI copilot for WordPress — form creation, content intelligence, and site assistance.', 'ai-site-assistant-autopilot' ),
			'icon'        => 'dashicons-superhero',
			'auth'        => array(
				'type'          => 'api_key',
				'setting_name'  => self::WP_OPTION_KEY,
				'env_var_name'  => 'AISAAUTOPILOT_API_KEY',
				'constant_name' => 'AISAAUTOPILOT_API_KEY',
				'label'         => __( 'API Key', 'ai-site-assistant-autopilot' ),
				'description'   => __( 'Enter your AI provider API key (DeepSeek, OpenAI, Qwen, Clusy.io, or any OpenAI-compatible provider).', 'ai-site-assistant-autopilot' ),
				'key_url'       => 'https://platform.deepseek.com/api_keys',
			),
		);

		// WP 7.0 may use either function name depending on the final API.
		if ( function_exists( 'wp_register_connector' ) ) {
			wp_register_connector( self::CONNECTOR_ID, $args );
		} elseif ( function_exists( 'wp_connectors_register' ) ) {
			wp_connectors_register( self::CONNECTOR_ID, $args );
		}
	}

	/**
	 * Get the API key from the WordPress Connectors system.
	 *
	 * Resolution order (matches WP Connectors convention):
	 * 1. Environment variable (AISAAUTOPILOT_API_KEY)
	 * 2. PHP constant (AISAAUTOPILOT_API_KEY)
	 * 3. wp_options (set via Settings → Connectors UI)
	 *
	 * @return string The API key, or empty string if not set.
	 */
	public static function get_api_key(): string {
		// 1. Environment variable.
		$env = getenv( 'AISAAUTOPILOT_API_KEY' );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}

		// 2. PHP constant.
		if ( defined( 'AISAAUTOPILOT_API_KEY' ) && '' !== trim( (string) constant( 'AISAAUTOPILOT_API_KEY' ) ) ) {
			return trim( (string) constant( 'AISAAUTOPILOT_API_KEY' ) );
		}

		// 3. wp_options (set via WP Connectors UI or our fallback).
		$stored = get_option( self::WP_OPTION_KEY, '' );

		return is_string( $stored ) ? trim( $stored ) : '';
	}

	/**
	 * Check whether a key is available through the WP Connectors system.
	 */
	public static function has_api_key(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Determine which key management mode is active.
	 *
	 * @return string 'wp_connectors' | 'keyvault' | 'none'
	 */
	public static function get_active_mode(): string {
		$preference = (string) Settings::get( 'key_management', 'keyvault' );

		if ( 'wp_connectors' === $preference && self::is_available() && self::has_api_key() ) {
			return 'wp_connectors';
		}

		if ( Settings::has_api_key() ) {
			return 'keyvault';
		}

		if ( self::has_api_key() ) {
			return 'wp_connectors';
		}

		return 'none';
	}

	/**
	 * Get the effective API key, respecting the user's key management preference.
	 *
	 * Priority:
	 * 1. If user chose "wp_connectors" and it's available → use WP Connectors key
	 * 2. Otherwise → use AISAAutoPilot KeyVault (encrypted)
	 * 3. Fallback → check WP Connectors anyway (in case key was set there)
	 */
	public static function get_effective_api_key(): string {
		$mode = self::get_active_mode();

		if ( 'wp_connectors' === $mode ) {
			return self::get_api_key();
		}

		// Default: use KeyVault.
		$key = Settings::get_api_key();
		if ( '' !== $key ) {
			return $key;
		}

		// Fallback: check WP Connectors.
		return self::get_api_key();
	}

	/**
	 * Get a human-readable label for the active key source.
	 */
	public static function get_key_source_label(): string {
		$mode = self::get_active_mode();

		return match ( $mode ) {
			'wp_connectors' => __( 'WordPress Connectors (Settings → Connectors)', 'ai-site-assistant-autopilot' ),
			'keyvault'      => __( 'AISAAutoPilot Encrypted Vault', 'ai-site-assistant-autopilot' ),
			default         => __( 'No API key configured', 'ai-site-assistant-autopilot' ),
		};
	}
}
