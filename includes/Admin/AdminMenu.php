<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Frontend\ChatWidgetSettings;
use AISAAutoPilot\Usage\UsageTracker;

final class AdminMenu {


	public const SLUG_SETUP         = 'aisaautopilot-setup';
	public const SLUG_HOME          = 'aisaautopilot-home';
	public const SLUG_FORM_BUILDER  = 'aisaautopilot-form-builder';
	public const SLUG_FORMS         = 'aisaautopilot-forms';
	public const SLUG_HISTORY       = 'aisaautopilot-history';
	public const SLUG_EFB           = 'aisaautopilot-efb';
	public const SLUG_CONNECTIONS   = 'aisaautopilot-connections';
	public const SLUG_CHAT          = 'aisaautopilot-chat';
	public const SLUG_KB            = 'aisaautopilot-kb';
	public const SLUG_USAGE         = 'aisaautopilot-usage';
	public const SLUG_SETTINGS      = 'aisaautopilot-settings';
	public const SLUG_LOGS          = 'aisaautopilot-logs';
	public const SLUG_WIDGET        = 'aisaautopilot-widget';
	public const SLUG_WC            = 'aisaautopilot-woocommerce';
	public const SLUG_WC_DASHBOARD  = 'aisaautopilot-wc-dashboard';
	public const SLUG_INTEGRATIONS  = 'aisaautopilot-integrations';
	public const SLUG_HANDOFFS      = 'aisaautopilot-handoffs';
	public const SLUG_CONVERSATIONS = 'aisaautopilot-conversations';

	/**
	 * The menu order mirrors the redesign sidebar. Old routes are registered as
	 * hidden aliases so bookmarks keep resolving while the visible IA stays calm.
	 *
	 * Setup and Settings are one screen. The old settings slug stays registered
	 * so existing bookmarks and links keep resolving, but it is removed from the
	 * menu so the same page is not offered twice.
	 */
	public function register(): void {
		$cap          = 'manage_options';
		$settings     = new SettingsPage();
		$home         = new HomePage();
		$integrations = new IntegrationsPage();
		$parent       = self::SLUG_HOME;
		add_menu_page( __( 'AutoPilot AI', 'ai-site-assistant-autopilot' ), __( 'AutoPilot AI', 'ai-site-assistant-autopilot' ), $cap, $parent, array( $home, 'render' ), 'dashicons-superhero', 58 );

		$pages = array(
			array( __( 'Overview', 'ai-site-assistant-autopilot' ), self::SLUG_HOME, $home ),
			array( __( 'Setup & Connection', 'ai-site-assistant-autopilot' ), self::SLUG_SETUP, $settings ),
			array( __( 'Integrations', 'ai-site-assistant-autopilot' ), self::SLUG_INTEGRATIONS, $integrations ),
			array( __( 'Site Knowledge', 'ai-site-assistant-autopilot' ), self::SLUG_KB, new KnowledgeBasePage() ),
			array( __( 'Answer Desk', 'ai-site-assistant-autopilot' ), self::SLUG_CONVERSATIONS, new AnswerConversationsPage() ),
		);

		// Store Copilot remains visible when WooCommerce is unavailable so the
		// sidebar always mirrors the 10-item mockup. Its page renders an honest
		// unavailable state and never exposes a store-write control until Woo is
		// active; administrators may still save the two non-destructive preferences.
		$pages[] = array( __( 'Store Copilot', 'ai-site-assistant-autopilot' ), self::SLUG_WC_DASHBOARD, new StoreCopilotPage() );
		$pages[] = array( __( 'Content Studio', 'ai-site-assistant-autopilot' ), self::SLUG_FORM_BUILDER, new CopilotPage() );
		$pages[] = array( __( 'Usage & Cost', 'ai-site-assistant-autopilot' ), self::SLUG_USAGE, new UsagePage() );
		$pages[] = array( __( 'Health', 'ai-site-assistant-autopilot' ), self::SLUG_CONNECTIONS, new ConnectionsPage() );
		$pages[] = array( __( 'Activity Log', 'ai-site-assistant-autopilot' ), self::SLUG_LOGS, new LogsPage() );

		foreach ( $pages as $page ) {
			add_submenu_page( $parent, $page[0], $page[0], $cap, $page[1], array( $page[2], 'render' ) );
		}

		// Legacy aliases deliberately use WordPress's empty-parent pattern. This
		// keeps them out of the visible sidebar while registering the `admin.php`
		// hook WordPress resolves for bookmarked direct URLs.
		$aliases = array(
			array( self::SLUG_SETTINGS, $settings ),
			array( self::SLUG_FORMS, new NativeFormsPage() ),
			array( self::SLUG_HISTORY, new HistoryPage() ),
			array( self::SLUG_CHAT, new ChatPage() ),
			array( self::SLUG_EFB, new EasyFormBuilderPage() ),
			array( self::SLUG_WC, new WooCommerceSettings() ),
			// These are Answer Desk sub-surfaces, not top-level navigation items.
			array( self::SLUG_WIDGET, new ChatWidgetSettings() ),
			array( self::SLUG_HANDOFFS, new HandoffInboxPage() ),
		);
		foreach ( $aliases as $alias ) {
			add_submenu_page( '', __( 'AutoPilot AI', 'ai-site-assistant-autopilot' ), __( 'AutoPilot AI', 'ai-site-assistant-autopilot' ), $cap, $alias[0], array( $alias[1], 'render' ) );
		}
	}

	/**
	 * Per-screen scripts. key = page slug, value = list of [filename, localized-object-name].
	 */
	private const SCREEN_SCRIPTS = array(
		self::SLUG_SETUP         => array( array( 'setup-wizard.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_INTEGRATIONS  => array( array( 'integrations.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_FORM_BUILDER  => array(),
		self::SLUG_HISTORY       => array(),
		self::SLUG_LOGS          => array(),
		self::SLUG_CHAT          => array( array( 'chat.js', 'AISAAutoPilotChat' ), array( 'voice.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_KB            => array( array( 'knowledge-base.js', 'AISAAutoPilotKB' ) ),
		self::SLUG_USAGE         => array( array( 'usage.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_SETTINGS      => array( array( 'settings.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_CONNECTIONS   => array( array( 'connections.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_WC            => array( array( 'woocommerce.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_WC_DASHBOARD  => array( array( 'woocommerce.js', 'AISAAutoPilotAI' ) ),
		self::SLUG_CONVERSATIONS => array(
			array( 'answer-widget.js', 'AISAAutoPilotAI' ),
			array( 'answer-handoff.js', 'AISAAutoPilotAI' ),
			array( 'answer-voice.js', 'AISAAutoPilotAI' ),
		),
		self::SLUG_WIDGET        => array( array( 'widget-settings.js', 'AISAAutoPilotAI' ) ),
	);

	public function enqueue_assets( string $hook ): void {
		unset( $hook ); // Slug is resolved from the request, not the hook suffix (which is ambiguous across our slugs).
		$slug = $this->current_screen_slug();
		if ( null === $slug ) {
			return;
		}

		// One stylesheet for every screen: the wireframe's design system.
		wp_enqueue_style( 'aisaautopilot-plus-jakarta-sans', 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'aisaautopilot-ui', AISAAUTOPILOT_URL . 'assets/admin/aisaautopilot-ui.css', array(), AISAAUTOPILOT_VERSION );

		// Shared shell helpers (toast, banner dismissal, job bar) on every screen.
		wp_enqueue_script( 'aisaautopilot-shell', AISAAUTOPILOT_URL . 'assets/admin/shell.js', array(), AISAAUTOPILOT_VERSION, true );
		wp_localize_script(
			'aisaautopilot-shell',
			'AISAAutoPilotShellConfig',
			array(
				'dismiss'  => __( 'Dismiss', 'ai-site-assistant-autopilot' ),
				'pause'    => __( 'Pause', 'ai-site-assistant-autopilot' ),
				'resume'   => __( 'Resume', 'ai-site-assistant-autopilot' ),
				'paused'   => __( 'Paused — nothing is running', 'ai-site-assistant-autopilot' ),
				'progress' => __( '{label} — {done} of {total} {unit}', 'ai-site-assistant-autopilot' ),
				'eta'      => __( 'About {seconds} seconds left · you can leave this page, it keeps running', 'ai-site-assistant-autopilot' ),
			)
		);

		$conf = array(
			'restUrl' => rest_url( RestRoutes::NAMESPACE . '/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'usage'   => ( new UsageTracker() )->get_threshold_warning(),
			'i18n'    => $this->script_strings(),
		);

		$scripts = self::SCREEN_SCRIPTS[ $slug ] ?? array();
		if ( self::SLUG_FORM_BUILDER === $slug ) {
			$view = ContentStudioNavigation::current_view();
			if ( ContentStudioNavigation::VIEW_FORMS === $view ) {
				$scripts = array( array( 'form-builder.js', 'AISAAutoPilotAI' ) );
			} elseif ( ContentStudioNavigation::TAB_HISTORY === $view ) {
				$scripts = array();
			} else {
				$scripts = array( array( 'content-studio.js', 'AISAAutoPilotAI' ) );
			}
		}

		foreach ( $scripts as $script ) {
			list($file, $object) = $script;
			$handle              = 'aisaautopilot-' . pathinfo( $file, PATHINFO_FILENAME );
			wp_enqueue_script( $handle, AISAAUTOPILOT_URL . 'assets/admin/' . $file, array(), AISAAUTOPILOT_VERSION, true );
			wp_localize_script( $handle, $object, $conf );
		}
	}

	/**
	 * Strings the shared scripts need. Kept here so every screen's JS reads the
	 * translated copy rather than hard-coded English.
	 *
	 * @return array<string, string>
	 */
	private function script_strings(): array {
		return array(
			'dismiss'                    => __( 'Dismiss', 'ai-site-assistant-autopilot' ),
			'testing'                    => __( 'Testing…', 'ai-site-assistant-autopilot' ),
			'testingBody'                => __( 'A short request was sent to the provider.', 'ai-site-assistant-autopilot' ),
			'testOk'                     => __( 'Connection works', 'ai-site-assistant-autopilot' ),
			'testOkBody'                 => __( 'Valid response received. You can continue.', 'ai-site-assistant-autopilot' ),
			'testFail'                   => __( 'Key or endpoint was rejected', 'ai-site-assistant-autopilot' ),
			'testFailBody'               => __( 'Replace the key or check the custom endpoint.', 'ai-site-assistant-autopilot' ),
			'networkError'               => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'sending'                    => __( 'Sending test…', 'ai-site-assistant-autopilot' ),
			'deliveryOk'                 => __( 'Test accepted.', 'ai-site-assistant-autopilot' ),
			'deliveryFail'               => __( 'Test failed.', 'ai-site-assistant-autopilot' ),
			'noChannels'                 => __( 'No channel is enabled, so the handoff button stays hidden from visitors.', 'ai-site-assistant-autopilot' ),
			'iconTooLarge'               => __( 'That image is too large. Please use a PNG under 200 KB.', 'ai-site-assistant-autopilot' ),
			'widgetSaving'               => __( 'Saving...', 'ai-site-assistant-autopilot' ),
			'widgetSaved'                => __( 'Saved.', 'ai-site-assistant-autopilot' ),
			'widgetSaveFailed'           => __( 'Could not save the Widget setting. Please try again.', 'ai-site-assistant-autopilot' ),
			'handoffSaving'              => __( 'Saving...', 'ai-site-assistant-autopilot' ),
			'handoffSaved'               => __( 'Saved.', 'ai-site-assistant-autopilot' ),
			'handoffSaveFailed'          => __( 'Could not save the handoff channel. Please try again.', 'ai-site-assistant-autopilot' ),
			'handoffConnectionPrompt'    => __( 'This contacts the provider to validate the saved credential. Continue?', 'ai-site-assistant-autopilot' ),
			'handoffConnectionFailed'    => __( 'Could not check the connection. Please try again.', 'ai-site-assistant-autopilot' ),
			'voiceSaving'                => __( 'Saving...', 'ai-site-assistant-autopilot' ),
			'voiceSaved'                 => __( 'Saved.', 'ai-site-assistant-autopilot' ),
			'voiceSaveFailed'            => __( 'Could not save the voice settings. Please try again.', 'ai-site-assistant-autopilot' ),
			'voiceEnableFirst'           => __( 'Turn on transcribe + speak before playing a test phrase.', 'ai-site-assistant-autopilot' ),
			'voiceTestPlaying'           => __( 'Playing a test phrase locally...', 'ai-site-assistant-autopilot' ),
			'voiceTestDone'              => __( 'Test phrase finished in this browser.', 'ai-site-assistant-autopilot' ),
			'voiceTestUnavailable'       => __( 'Speech output is not available in this browser.', 'ai-site-assistant-autopilot' ),
			'voiceTestFailed'            => __( 'This browser could not play the test phrase.', 'ai-site-assistant-autopilot' ),
			'contentBriefRequired'       => __( 'Describe the draft first.', 'ai-site-assistant-autopilot' ),
			'contentPlanning'            => __( 'Building a reviewable draft…', 'ai-site-assistant-autopilot' ),
			'contentWriteDraft'          => __( 'Write a draft', 'ai-site-assistant-autopilot' ),
			'contentWriting'             => __( 'Writing…', 'ai-site-assistant-autopilot' ),
			'contentCreating'            => __( 'Creating your WordPress draft…', 'ai-site-assistant-autopilot' ),
			'contentCreated'             => __( 'Draft created. Review and publish it from WordPress when you are ready.', 'ai-site-assistant-autopilot' ),
			'contentOpenDraft'           => __( 'Open draft in WordPress', 'ai-site-assistant-autopilot' ),
			'contentDraftSaved'          => __( 'Draft saved', 'ai-site-assistant-autopilot' ),
			'contentJustNow'             => __( 'Just now', 'ai-site-assistant-autopilot' ),
			'contentPost'                => __( 'Post', 'ai-site-assistant-autopilot' ),
			'contentPage'                => __( 'Page', 'ai-site-assistant-autopilot' ),
			'contentUntitledDraft'       => __( 'Untitled draft', 'ai-site-assistant-autopilot' ),
			'setupSelectStorage'         => __( 'Choose a key-storage option to continue.', 'ai-site-assistant-autopilot' ),
			'setupSelectProvider'        => __( 'Choose an AI service to continue.', 'ai-site-assistant-autopilot' ),
			'setupTestRequired'          => __( 'Test a working provider key and model before continuing.', 'ai-site-assistant-autopilot' ),
			'setupModelRequired'         => __( 'Enter or choose a model before continuing.', 'ai-site-assistant-autopilot' ),
			'setupUntested'              => __( 'Not tested yet', 'ai-site-assistant-autopilot' ),
			'setupUntestedBody'          => __( 'Run a short connection test before continuing.', 'ai-site-assistant-autopilot' ),
			'setupNoKey'                 => __( 'Add an API key', 'ai-site-assistant-autopilot' ),
			'setupUnauthorized'          => __( 'That key was rejected', 'ai-site-assistant-autopilot' ),
			'setupPayment'               => __( 'Billing action required', 'ai-site-assistant-autopilot' ),
			'setupRateLimit'             => __( 'Provider is rate limiting', 'ai-site-assistant-autopilot' ),
			'setupUnreachable'           => __( 'Your server could not reach the provider', 'ai-site-assistant-autopilot' ),
			'setupNotFound'              => __( 'Wrong endpoint or model', 'ai-site-assistant-autopilot' ),
			'setupIncomplete'            => __( 'Connection details are incomplete', 'ai-site-assistant-autopilot' ),
			'setupModelsEmpty'           => __( 'The key works, but no models came back. Type a model name or enable model access with the provider.', 'ai-site-assistant-autopilot' ),
			'setupModelsUnsupported'     => __( 'This provider does not publish a model list. Type its model name above.', 'ai-site-assistant-autopilot' ),
			'setupConnectorKey'          => __( 'This choice uses the key saved in WordPress Connectors.', 'ai-site-assistant-autopilot' ),
			'setupGetKeyFrom'            => __( 'Get a key from {provider}.', 'ai-site-assistant-autopilot' ),
			'setupEmbeddingsSupported'   => __( 'This provider supports embeddings. ', 'ai-site-assistant-autopilot' ),
			'setupEmbeddingsUnsupported' => __( 'This provider does not publish an embeddings endpoint. ', 'ai-site-assistant-autopilot' ),
		);
	}

	/**
	 * Resolve the exact AISAAutoPilot admin page slug from the request, or null if not one of ours.
	 */
	private function current_screen_slug(): ?string {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only asset routing, no state change.
		$known = array(
			self::SLUG_SETUP,
			self::SLUG_HOME,
			self::SLUG_FORM_BUILDER,
			self::SLUG_FORMS,
			self::SLUG_HISTORY,
			self::SLUG_EFB,
			self::SLUG_CONNECTIONS,
			self::SLUG_CHAT,
			self::SLUG_KB,
			self::SLUG_USAGE,
			self::SLUG_WIDGET,
			self::SLUG_WC,
			self::SLUG_WC_DASHBOARD,
			self::SLUG_SETTINGS,
			self::SLUG_LOGS,
			self::SLUG_INTEGRATIONS,
			self::SLUG_HANDOFFS,
			self::SLUG_CONVERSATIONS,
		);

		return in_array( $page, $known, true ) ? $page : null;
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
