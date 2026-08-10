<?php
declare(strict_types=1);

namespace SitePilot\Frontend;

use SitePilot\Api\RestRoutes;
use SitePilot\Core\Settings;
use SitePilot\Core\SiteLanguage;

/** Renders the opt-in, read-only public assistant. */
final class WidgetRenderer {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	public function enqueue(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'sitepilot-public-chat', SITEPILOT_AI_URL . 'assets/frontend/chat-widget.css', array(), SITEPILOT_AI_VERSION );
		wp_enqueue_script( 'sitepilot-public-chat', SITEPILOT_AI_URL . 'assets/frontend/chat-widget.js', array(), SITEPILOT_AI_VERSION, true );
		wp_localize_script(
			'sitepilot-public-chat',
			'SitePilotPublicChat',
			array(
				'restUrl' => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'language' => SiteLanguage::primary( SiteLanguage::site() ),
				'voiceResponses' => (bool) Settings::get( 'voice_responses_enabled', false ),
				'isRTL' => is_rtl(),
				'labels' => array(
					'open' => __( 'Ask SitePilot AI', 'sitepilot-ai' ),
					'title' => __( 'SitePilot AI', 'sitepilot-ai' ),
					'newChat' => __( 'New chat', 'sitepilot-ai' ),
					'close' => __( 'Close', 'sitepilot-ai' ),
					'greeting' => __( 'Hi! I can answer questions about this website.', 'sitepilot-ai' ),
					'suggestions' => array(
						__( 'What is this website about?', 'sitepilot-ai' ),
						__( 'How can I contact you?', 'sitepilot-ai' ),
						__( 'What services do you offer?', 'sitepilot-ai' ),
					),
					'answerError' => __( 'Sorry, I could not answer that.', 'sitepilot-ai' ),
					'networkError' => __( 'Network error. Please try again.', 'sitepilot-ai' ),
					'voiceUnsupported' => __( 'Voice recording is not supported by this browser.', 'sitepilot-ai' ),
					'recordingInvalid' => __( 'The recording is empty or too large. Please try a shorter message.', 'sitepilot-ai' ),
					'transcriptionFailed' => __( 'Voice transcription failed. Please try again.', 'sitepilot-ai' ),
					'microphoneDenied' => __( 'Microphone permission was denied. You can type your question instead.', 'sitepilot-ai' ),
					'placeholder' => __( 'Write a message…', 'sitepilot-ai' ),
					'send' => __( 'Send', 'sitepilot-ai' ),
					'mic' => __( 'Speak your question', 'sitepilot-ai' ),
					'stop' => __( 'Stop recording', 'sitepilot-ai' ),
					'listening' => __( 'Listening…', 'sitepilot-ai' ),
					'voiceReady' => __( 'Your transcript is ready. Review it, then send.', 'sitepilot-ai' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		?>
		<div id="sitepilot-public-chat" class="sitepilot-public-chat" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"></div>
		<?php
	}

	private function enabled(): bool {
		return ! is_admin() && (bool) Settings::get( 'public_assistant_enabled', false );
	}
}
