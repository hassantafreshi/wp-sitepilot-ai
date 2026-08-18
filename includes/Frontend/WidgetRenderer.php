<?php
declare(strict_types=1);

namespace AISAAutoPilot\Frontend;

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\SiteLanguage;

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
		wp_enqueue_style( 'aisaautopilot-public-chat', AISAAUTOPILOT_URL . 'assets/frontend/chat-widget.css', array(), AISAAUTOPILOT_VERSION );
		wp_enqueue_script( 'aisaautopilot-public-chat', AISAAUTOPILOT_URL . 'assets/frontend/chat-widget.js', array(), AISAAUTOPILOT_VERSION, true );
		wp_localize_script(
			'aisaautopilot-public-chat',
			'AISAAutoPilotPublicChat',
			array(
				'restUrl'        => esc_url_raw( rest_url( RestRoutes::NAMESPACE . '/' ) ),
				'language'       => SiteLanguage::primary( SiteLanguage::site() ),
				'voiceResponses' => (bool) Settings::get( 'voice_responses_enabled', false ),
				'isRTL'          => is_rtl(),
				'labels'         => array(
					'open'                => __( 'Ask AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
					'title'               => __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
					'newChat'             => __( 'New chat', 'ai-site-assistant-autopilot' ),
					'close'               => __( 'Close', 'ai-site-assistant-autopilot' ),
					'greeting'            => __( 'Hi! I can answer questions about this website.', 'ai-site-assistant-autopilot' ),
					'suggestions'         => array(
						__( 'What is this website about?', 'ai-site-assistant-autopilot' ),
						__( 'How can I contact you?', 'ai-site-assistant-autopilot' ),
						__( 'What services do you offer?', 'ai-site-assistant-autopilot' ),
					),
					'answerError'         => __( 'Sorry, I could not answer that.', 'ai-site-assistant-autopilot' ),
					'networkError'        => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
					'voiceUnsupported'    => __( 'Voice recording is not supported by this browser.', 'ai-site-assistant-autopilot' ),
					'recordingInvalid'    => __( 'The recording is empty or too large. Please try a shorter message.', 'ai-site-assistant-autopilot' ),
					'transcriptionFailed' => __( 'Voice transcription failed. Please try again.', 'ai-site-assistant-autopilot' ),
					'microphoneDenied'    => __( 'Microphone permission was denied. You can type your question instead.', 'ai-site-assistant-autopilot' ),
					'placeholder'         => __( 'Write a message…', 'ai-site-assistant-autopilot' ),
					'send'                => __( 'Send', 'ai-site-assistant-autopilot' ),
					'mic'                 => __( 'Speak your question', 'ai-site-assistant-autopilot' ),
					'stop'                => __( 'Stop recording', 'ai-site-assistant-autopilot' ),
					'listening'           => __( 'Listening…', 'ai-site-assistant-autopilot' ),
					'voiceReady'          => __( 'Your transcript is ready. Review it, then send.', 'ai-site-assistant-autopilot' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		?>
		<div id="aisaautopilot-public-chat" class="aisaautopilot-public-chat" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"></div>
		<?php
	}

	private function enabled(): bool {
		return ! is_admin() && (bool) Settings::get( 'public_assistant_enabled', false );
	}
}
