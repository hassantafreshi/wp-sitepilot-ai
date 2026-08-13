<?php
declare(strict_types=1);

namespace SitePilot\Core;

/**
 * I18n service that bridges PHP translations to JavaScript.
 *
 * Responsibilities:
 * 1. Register a REST endpoint so JS can fetch all phrases.
 * 2. Provide a static helper to localize any script handle with phrases.
 * 3. Ensure the plugin textdomain is loaded early.
 *
 * The JS side (i18n.js) calls sp_i18n(key) which prefers
 * wp.i18n.__() (WordPress 7.0+) and falls back to the
 * dictionary supplied via wp_localize_script.
 */
final class I18nService {

	public const REST_ROUTE = '/i18n/strings';

	/**
	 * Register hooks. Call once from Plugin::boot().
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_rest' ) );

		// Textdomain already loaded in Plugin.php, but double-check:
		if ( ! did_action( 'after_setup_theme' ) ) {
			add_action( 'after_setup_theme', array( self::class, 'load_textdomain' ) );
		}
	}

	/**
	 * Load the plugin textdomain early.
	 */
	public static function load_textdomain(): void {
		if ( ! is_textdomain_loaded( Phrases::DOMAIN ) ) {
			load_plugin_textdomain(
				Phrases::DOMAIN,
				false,
				dirname( SITEPILOT_AI_BASENAME ) . '/languages'
			);
		}
	}

	/**
	 * Register GET /sitepilot/v1/i18n/strings
	 */
	public static function register_rest(): void {
		register_rest_route(
			'sitepilot/v1',
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_strings' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST callback — returns all phrases as JSON.
	 */
	public static function get_strings(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'domain'  => Phrases::DOMAIN,
				'strings' => Phrases::all(),
			)
		);
	}

	/**
	 * Localise any already-enqueued script with ALL phrases.
	 *
	 * @param string $handle The script handle to attach data to.
	 */
	public static function localize_script( string $handle ): void {
		wp_localize_script(
			$handle,
			'spI18n',
			array(
				'domain'  => Phrases::DOMAIN,
				'strings' => Phrases::all(),
			)
		);
	}
}
