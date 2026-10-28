<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Content\ContentHooks;
use AISAAutoPilot\Efb\EfbBuilderButton;
use AISAAutoPilot\Frontend\ChatWidget;
use AISAAutoPilot\Frontend\FormShortcode;
use AISAAutoPilot\Admin\LogExport;
use AISAAutoPilot\Admin\ContentStudioHistoryExport;
use AISAAutoPilot\Support\PrivacyTools;
use AISAAutoPilot\Support\HandoffInbox;
use AISAAutoPilot\Chat\VisitorConversationStore;

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
		Settings::migrate_widget_defaults();

		$this->register_services();
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_menu', array( $this->container->get( AdminMenu::class ), 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this->container->get( AdminMenu::class ), 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this->container->get( RestRoutes::class ), 'register' ) );
		( new LogExport() )->register();
		( new ContentStudioHistoryExport() )->register();
		// Tools > Export / Erase Personal Data, and the suggested privacy-policy text.
		( new PrivacyTools() )->register();
		( new Network() )->register();

		/*
		 * Answer Desk has one visitor-facing implementation: the configurable
		 * widget. Both the Integration gate and the Widget visibility toggle are
		 * checked by the renderer, controller and shortcode. Registering these
		 * hooks unconditionally makes a later settings change take effect without
		 * a plugin reload and prevents a second, incompatible public widget.
		 */
		add_shortcode( 'aisaautopilot_chat', array( ChatWidget::class, 'render_standalone' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );

		if ( is_admin() ) {
			( new EfbBuilderButton() )->register();
		}
		/*
		 * ContentHooks owns both optional publish/update indexing and mandatory
		 * lifecycle cleanup. The handler itself checks kb_auto_index before it
		 * schedules work, but it must always be registered so unpublished,
		 * trashed, and deleted content is removed from the Knowledge Base.
		 */
		( new ContentHooks() )->register();
		WpConnectors::register();
	}

	private function register_services(): void {
		$registry = new ConnectorRegistry();
		$registry->register( new EasyFormBuilderConnector() );
		$registry->register( new NativeFormConnector() );
		$this->container->set( ConnectorRegistry::class, $registry );
	}

	/**
	 * Load the plugin's bundled translations.
	 *
	 * WordPress.org language packs load on their own since 4.6, so there is no
	 * load_plugin_textdomain() call here. The explicit load_textdomain() below is
	 * still needed for the Persian catalogue we ship ourselves, which WordPress
	 * does not know about, and which also has to resolve when the plugin sits in
	 * a versioned or test directory.
	 */
	private function load_translations(): void {
		$domain = 'ai-site-assistant-autopilot';
		$locale = determine_locale();
		$mofile = plugin_dir_path( AISAAUTOPILOT_FILE ) . 'languages/' . $domain . '-' . $locale . '.mo';

		if ( is_readable( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}
	}

	/**
	 * Register post types, shortcodes, and front-end widget hooks.
	 */
	public function on_init(): void {
		Activator::maybe_setup_site();
		NativeFormConnector::register_post_types();
		// Runs once per schema version, so existing installations receive the
		// inbox table without requiring a deactivate/reactivate cycle.
		HandoffInbox::ensure_schema();
		VisitorConversationStore::ensure_schema();
		( new FormShortcode() )->register();
	}

	public function enqueue_widget_assets(): void {

		if ( ! Settings::answer_desk_enabled() ) {
			return;
		}

		$settings = Settings::all();
		$copy     = Settings::widget_copy( (array) $settings['widget'] );
		wp_enqueue_style( 'aisaautopilot-widget', AISAAUTOPILOT_URL . 'assets/frontend/widget.css', array(), AISAAUTOPILOT_VERSION );
		wp_enqueue_script( 'aisaautopilot-widget', AISAAUTOPILOT_URL . 'assets/frontend/widget.js', array(), AISAAUTOPILOT_VERSION, true );
		wp_localize_script(
			'aisaautopilot-widget',
			'AISAAutoPilotWidget',
			array(
				'restUrl'      => rest_url( RestRoutes::NAMESPACE . '/' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'greeting'     => $copy['greeting'],
				// The widget reads its voice settings here instead of asking the REST API on
				// every page view (F-15): the panel is rendered from the same values.
				'voiceEnabled' => ! empty( $settings['widget']['voice_enabled'] ),
				'voiceName'    => (string) ( $settings['widget']['voice_name'] ?? '' ),
				'voiceAuto'    => ! empty( $settings['widget']['voice_auto'] ),
				// The handoff label and hook are rendered into the markup by ChatWidget and the
				// card's two status strings live in i18n, so nothing else about the handoff is
				// pushed to every page view.
				'i18n'         => self::widget_strings(),
			)
		);
	}

	/**
	 * Strings the visitor widget renders at runtime.
	 *
	 * @return array<string, string>
	 */
	private static function widget_strings(): array {
		return array(
			'sources'          => __( 'Sources', 'ai-site-assistant-autopilot' ),
			'retry'            => __( 'Try again', 'ai-site-assistant-autopilot' ),
			'send'             => __( 'Send', 'ai-site-assistant-autopilot' ),
			// Honest about what the button does: the question has already reached the site.
			'stopWaiting'      => __( 'Stop waiting for the reply', 'ai-site-assistant-autopilot' ),
			'stoppedWaiting'   => __( 'You stopped waiting. The site may still finish this answer, but it will not appear here.', 'ai-site-assistant-autopilot' ),
			'clarifyLabel'     => __( 'Choose what you mean', 'ai-site-assistant-autopilot' ),
			'sending'          => __( 'Sending…', 'ai-site-assistant-autopilot' ),
			'sent'             => __( 'Sent — we will be in touch.', 'ai-site-assistant-autopilot' ),
			'sendFailed'       => __( 'Could not send. Please try again.', 'ai-site-assistant-autopilot' ),
			'errorGeneric'     => __( 'Sorry, I could not answer that.', 'ai-site-assistant-autopilot' ),
			'errorNetwork'     => __( 'Connection problem. Please try again.', 'ai-site-assistant-autopilot' ),
			'fullScreen'       => __( 'Full screen', 'ai-site-assistant-autopilot' ),
			'exitFullScreen'   => __( 'Exit full screen', 'ai-site-assistant-autopilot' ),
			'voiceInput'       => __( 'Voice input', 'ai-site-assistant-autopilot' ),
			'stopRecording'    => __( 'Stop recording', 'ai-site-assistant-autopilot' ),
			'voiceUnsupported' => __( 'Voice recording is not supported in this browser.', 'ai-site-assistant-autopilot' ),
			'micDenied'        => __( 'Microphone access was denied.', 'ai-site-assistant-autopilot' ),
			'noAudio'          => __( 'No audio was captured. Please try again.', 'ai-site-assistant-autopilot' ),
			'transcribeFailed' => __( 'Could not transcribe that. Please try again.', 'ai-site-assistant-autopilot' ),
			// Voice phases announced in the widget's status region: recording is the full
			// sentence below, and these two cover the rest of the transcript-first flow.
			'transcribing'     => __( 'Transcribing your recording…', 'ai-site-assistant-autopilot' ),
			'transcriptReady'  => __( 'Your words are in the message box. Check them, then press Send. Nothing was sent yet.', 'ai-site-assistant-autopilot' ),
			'from'             => __( 'From', 'ai-site-assistant-autopilot' ),
			'typing'           => __( 'The assistant is writing a reply', 'ai-site-assistant-autopilot' ),
			'recording'        => __( 'Listening… tap the mic again to stop', 'ai-site-assistant-autopilot' ),
			// The placeholder while recording stays one line on a phone; the mic shows a stop square.
			'listening'        => __( 'Listening…', 'ai-site-assistant-autopilot' ),
			'fieldRequired'    => __( 'Please fill this in.', 'ai-site-assistant-autopilot' ),
			'emailInvalid'     => __( 'Please provide a valid email address.', 'ai-site-assistant-autopilot' ),
			'phoneInvalid'     => __( 'Please enter a valid mobile number.', 'ai-site-assistant-autopilot' ),
			'websiteInvalid'   => __( 'Please enter a valid website address.', 'ai-site-assistant-autopilot' ),
		);
	}

	public function render_widget(): void {

		$widget = new ChatWidget();
		if ( ! Settings::answer_desk_enabled() || ! $widget->should_render_widget() ) {
			return;
		}
		echo $widget->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes dynamic values.
	}
}
