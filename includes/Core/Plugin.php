<?php
declare(strict_types=1);

namespace SitePilot\Core;

use SitePilot\Admin\AdminMenu;
use SitePilot\Api\RestRoutes;
use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Content\ContentHooks;
use SitePilot\Efb\EfbBuilderButton;
use SitePilot\Frontend\ChatWidget;
use SitePilot\Frontend\FormShortcode;
use SitePilot\Frontend\WidgetRenderer;

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

	public function boot(): void {
		$this->load_translations();
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		Settings::migrate_legacy_handoff_secrets();

		$this->register_services();
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_menu', array( $this->container->get( AdminMenu::class ), 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this->container->get( AdminMenu::class ), 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this->container->get( RestRoutes::class ), 'register' ) );

		/*
		 * The lightweight public assistant and the configurable ChatWidget are
		 * alternate front-end experiences. This avoids rendering two launchers.
		 */
		if ( ! Settings::get( 'public_assistant_enabled', false ) ) {
			add_shortcode( 'sitepilot_chat', array( ChatWidget::class, 'render_standalone' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
			add_action( 'wp_footer', array( $this, 'render_widget' ) );
		}

		if ( is_admin() ) {
			( new EfbBuilderButton() )->register();
		}
		if ( Settings::get( 'kb_auto_index', true ) ) {
			( new ContentHooks() )->register();
		}
		WpConnectors::register();
	}

	private function register_services(): void {
		$registry = new ConnectorRegistry();
		$registry->register( new EasyFormBuilderConnector() );
		$registry->register( new NativeFormConnector() );
		$this->container->set( ConnectorRegistry::class, $registry );
	}

	/**
	 * Load the plugin's bundled translations, including local development copies.
	 *
	 * WordPress normally finds a language pack automatically. The explicit local
	 * file makes the packaged Persian translation work as well when the plugin is
	 * installed under a versioned or test directory.
	 */
	private function load_translations(): void {
		$domain   = 'sitepilot-ai';
		$relative = dirname( SITEPILOT_AI_BASENAME ) . '/languages';
		$locale   = determine_locale();
		$mofile   = plugin_dir_path( SITEPILOT_AI_FILE ) . 'languages/' . $domain . '-' . $locale . '.mo';

		load_plugin_textdomain( $domain, false, $relative );
		if ( is_readable( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}
	}

	/**
	 * Register post types, shortcodes, and front-end widget hooks.
	 */
	public function on_init(): void {
		NativeFormConnector::register_post_types();
		( new FormShortcode() )->register();
		( new WidgetRenderer() )->register();
	}

	public function enqueue_widget_assets(): void {

		$settings = Settings::all();
		// The opt-in public assistant has its own renderer. Never enqueue the
		// legacy configurable widget alongside it, otherwise visitors receive
		// two overlapping chat launchers.
		if ( ! empty( $settings['public_assistant_enabled'] ) || empty( $settings['widget']['enabled'] ) ) {
				return;
		}
		wp_enqueue_style( 'sitepilot-widget', SITEPILOT_AI_URL . 'assets/frontend/widget.css', array(), SITEPILOT_AI_VERSION );
		wp_enqueue_script( 'sitepilot-widget', SITEPILOT_AI_URL . 'assets/frontend/widget.js', array(), SITEPILOT_AI_VERSION, true );
		wp_localize_script(
			'sitepilot-widget',
			'SitePilotWidget',
			array(
				'restUrl'        => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'greeting'       => $settings['widget']['greeting'] ?? 'Hi!',
				'voiceName'      => $settings['widget']['voice_name'] ?? 'Google UK English Female',
				'voiceAuto'      => ! empty( $settings['widget']['voice_auto'] ),
				'handoffText'    => $settings['widget']['handoff_text'] ?? 'Talk to human',
				'handoffSending' => 'Connecting...',
				'handoffSent'    => 'Support notified!',
				'handoffHook'    => $settings['widget']['handoff_hook'] ?? '',
			)
		);
	}

	public function render_widget(): void {

		$settings = Settings::all();
		if ( ! empty( $settings['public_assistant_enabled'] ) || empty( $settings['widget']['enabled'] ) ) {
			return;
		}
		echo ( new ChatWidget() )->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes dynamic values.
	}
}
