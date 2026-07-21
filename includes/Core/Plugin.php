<?php
declare(strict_types=1);

namespace SitePilot\Core;

use SitePilot\Admin\AdminMenu;
use SitePilot\Api\RestRoutes;
use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Frontend\FormShortcode;

/**
 * Main plugin bootstrap. Wires services and WordPress hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Container $container;

	private bool $booted = false;

	private function __construct() {
		$this->container = new Container();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register services and hooks. Safe to call once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register_services();

		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_menu', array( $this->container->get( AdminMenu::class ), 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this->container->get( AdminMenu::class ), 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this->container->get( RestRoutes::class ), 'register' ) );

		load_plugin_textdomain( 'sitepilot-ai', false, dirname( SITEPILOT_AI_BASENAME ) . '/languages' );
	}

	/**
	 * Build the connector registry and register services in the container.
	 */
	private function register_services(): void {
		$registry = new ConnectorRegistry();
		// Easy Form Builder is preferred when available; the built-in connector is the safe fallback.
		$registry->register( new EasyFormBuilderConnector() );
		$registry->register( new NativeFormConnector() );

		$this->container->set( ConnectorRegistry::class, $registry );
	}

	/**
	 * Register post types, shortcodes, and other init-time hooks.
	 */
	public function on_init(): void {
		NativeFormConnector::register_post_types();
		( new FormShortcode() )->register();
	}
}
