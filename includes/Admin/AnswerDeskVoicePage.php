<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Core\Settings;

/**
 * Mockup-aligned Answer Desk Voice surface.
 *
 * The mockup's realtime stream is intentionally represented as unavailable:
 * this release records a short clip for transcription, then uses the
 * visitor's browser SpeechSynthesis API for optional reply playback.
 */
final class AnswerDeskVoicePage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$widget        = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		$voice_enabled = ! empty( $widget['voice_enabled'] );
		$voice_auto    = ! empty( $widget['voice_auto'] );
		$integration   = ! empty( Settings::get( 'public_assistant_enabled', false ) );
		$model         = trim( sanitize_text_field( (string) Settings::get( 'voice_model', '' ) ) );
		$model         = '' !== $model ? $model : __( 'Not configured', 'ai-site-assistant-autopilot' );
		$model_label   = sprintf(
			/* translators: %s: configured speech-to-text model identifier. */
			__( 'STT model: %s', 'ai-site-assistant-autopilot' ),
			$model
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-answer-desk data-sp-answer-tab="voice" data-sp-voice-settings data-sp-voice-enabled="<?php echo $voice_enabled ? '1' : '0'; ?>" data-sp-voice-auto="<?php echo $voice_auto ? '1' : '0'; ?>">
			<section class="sp-screen">
			<?php
			Ui::page_header(
				__( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				__( 'The support chat on your site, and every conversation it handled.', 'ai-site-assistant-autopilot' ),
				array(
					'chip'    => ConnectionChip::derive(),
					'action'  => array(
						'label' => __( 'Widget settings', 'ai-site-assistant-autopilot' ),
						'url'   => AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ),
					),
					'subtabs' => AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_VOICE ),
				)
			);
			?>
				<div class="sp-pagebody">
				<?php if ( ! $integration || empty( $widget['enabled'] ) ) : ?>
					<?php Ui::alert( 'warn', 'warning', esc_html__( 'You can configure voice here, but visitors cannot use it until Answer Desk and its Widget are both enabled.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>
					<div class="sp-answer-voice">
						<section class="sp-card sp-answer-voice-card sp-answer-voice-card--realtime" data-sp-voice-realtime-locked aria-labelledby="sp-answer-voice-realtime-title">
							<div class="sp-answer-voice-feature">
								<button class="sp-answer-voice-switch is-locked" type="button" role="switch" aria-checked="false" disabled aria-describedby="sp-answer-voice-realtime-note" aria-label="<?php esc_attr_e( 'Real-time voice mode is not available', 'ai-site-assistant-autopilot' ); ?>"><span aria-hidden="true"></span></button>
								<div class="sp-answer-voice-feature__body">
									<h2 id="sp-answer-voice-realtime-title"><?php esc_html_e( 'Real-time voice mode', 'ai-site-assistant-autopilot' ); ?></h2>
									<p id="sp-answer-voice-realtime-note"><?php esc_html_e( 'A live, interruptible audio stream is not implemented in this release. The widget never opens a continuous audio session or stores audio. Use the available transcribe-and-speak mode below instead.', 'ai-site-assistant-autopilot' ); ?></p>
									<span class="sp-answer-voice-key is-locked"><span aria-hidden="true"></span><?php esc_html_e( 'Not available in this release', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
							</div>
						</section>

						<section class="sp-card sp-answer-voice-card" data-sp-voice-engine-card aria-labelledby="sp-answer-voice-engine-title">
							<h2 class="sp-answer-voice-card__title" id="sp-answer-voice-engine-title"><?php esc_html_e( 'Engine', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-answer-voice-card__intro"><?php esc_html_e( 'How speech is turned into a reply.', 'ai-site-assistant-autopilot' ); ?></p>
							<div class="sp-answer-voice-engines">
								<div class="sp-answer-voice-engine is-locked" data-sp-voice-engine-locked aria-disabled="true">
									<span class="sp-answer-voice-engine__radio" aria-hidden="true"></span>
									<span class="sp-answer-voice-engine__body"><strong><?php esc_html_e( 'Realtime stream', 'ai-site-assistant-autopilot' ); ?></strong><span><?php esc_html_e( 'One live audio connection with low latency and interruption support. It needs a realtime-capable provider and backend stream support.', 'ai-site-assistant-autopilot' ); ?></span></span>
									<span class="sp-answer-voice-engine__state is-locked"><?php esc_html_e( 'Not available', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
								<button class="sp-answer-voice-engine<?php echo $voice_enabled ? ' is-on' : ''; ?>" type="button" role="switch" aria-checked="<?php echo $voice_enabled ? 'true' : 'false'; ?>" data-sp-voice-engine="transcribe" aria-describedby="sp-answer-voice-transcribe-note">
									<span class="sp-answer-voice-engine__radio" aria-hidden="true"><span></span></span>
									<span class="sp-answer-voice-engine__body"><strong><?php esc_html_e( 'Transcribe + speak', 'ai-site-assistant-autopilot' ); ?></strong><span id="sp-answer-voice-transcribe-note"><?php esc_html_e( 'A visitor records one short clip. It is sent once for transcription, never stored by the plugin, and the transcript is placed in the message field. Reply playback is configured below.', 'ai-site-assistant-autopilot' ); ?></span></span>
									<span class="sp-answer-voice-engine__state" data-sp-voice-engine-state><?php echo esc_html( $voice_enabled ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Paused', 'ai-site-assistant-autopilot' ) ); ?></span>
								</button>
							</div>
							<span class="sp-answer-voice-model" data-sp-voice-model><?php echo esc_html( $model_label ); ?></span>
						</section>

						<section class="sp-card sp-answer-voice-card" data-sp-voice-options aria-labelledby="sp-answer-voice-options-title">
							<h2 class="screen-reader-text" id="sp-answer-voice-options-title"><?php esc_html_e( 'Voice reply options', 'ai-site-assistant-autopilot' ); ?></h2>
							<div class="sp-answer-voice-preferences">
								<div>
									<h3><?php esc_html_e( 'Reply voice', 'ai-site-assistant-autopilot' ); ?></h3>
									<div class="sp-answer-voice-choices"><span class="sp-answer-voice-choice is-on" data-sp-voice-browser-default><?php esc_html_e( 'Browser default', 'ai-site-assistant-autopilot' ); ?></span></div>
									<p><?php esc_html_e( 'The visitor’s browser chooses a local speech voice. The widget does not send reply text to a TTS provider.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
								<div>
									<h3><?php esc_html_e( 'Language', 'ai-site-assistant-autopilot' ); ?></h3>
									<div class="sp-answer-voice-choices"><span class="sp-answer-voice-choice is-on" data-sp-voice-language-auto><?php esc_html_e( 'Auto-detect', 'ai-site-assistant-autopilot' ); ?></span></div>
									<p><?php esc_html_e( 'Speech recognition keeps the recording language flexible instead of pinning it to the site locale.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>

							<div class="sp-answer-voice-toggles">
								<div class="sp-answer-voice-toggle is-locked" data-sp-voice-bargein-locked>
									<button class="sp-answer-voice-switch is-locked" type="button" role="switch" aria-checked="false" disabled aria-label="<?php esc_attr_e( 'Let visitors interrupt is not available', 'ai-site-assistant-autopilot' ); ?>"><span aria-hidden="true"></span></button>
									<div><strong><?php esc_html_e( 'Let visitors interrupt', 'ai-site-assistant-autopilot' ); ?></strong><p><?php esc_html_e( 'Interrupting an answer needs the unavailable realtime stream; one-shot transcription cannot barge in.', 'ai-site-assistant-autopilot' ); ?></p></div>
								</div>
								<div class="sp-answer-voice-toggle">
									<button class="sp-answer-voice-switch<?php echo $voice_auto ? ' is-on' : ''; ?>" type="button" role="switch" aria-checked="<?php echo $voice_auto ? 'true' : 'false'; ?>" data-sp-voice-auto-toggle aria-label="<?php esc_attr_e( 'Speak replies aloud', 'ai-site-assistant-autopilot' ); ?>"<?php disabled( ! $voice_enabled ); ?>><span aria-hidden="true"></span></button>
									<div><strong><?php esc_html_e( 'Speak replies aloud', 'ai-site-assistant-autopilot' ); ?></strong><p data-sp-voice-auto-note><?php echo esc_html( $voice_enabled ? __( 'Uses the visitor’s browser speech engine. Off keeps voice input but shows the answer as text only.', 'ai-site-assistant-autopilot' ) : __( 'Turn on transcribe + speak first; reply playback is unavailable while voice input is paused.', 'ai-site-assistant-autopilot' ) ); ?></p></div>
								</div>
							</div>

							<div class="sp-answer-voice-test-row">
								<button class="sp-btn sp-btn--ghost sp-btn--r8 sp-answer-voice-test" type="button" data-sp-voice-test<?php disabled( ! $voice_enabled ); ?>><?php echo Ui::icon( 'mic-full', 15, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span><?php esc_html_e( 'Play a test phrase', 'ai-site-assistant-autopilot' ); ?></span></button>
								<span><?php esc_html_e( 'Plays locally in this administrator’s browser; it does not contact a provider.', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<p class="sp-answer-voice-status" data-sp-voice-status role="status" aria-live="polite"></p>
						</section>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}
}
