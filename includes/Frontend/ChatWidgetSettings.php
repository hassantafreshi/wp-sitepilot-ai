<?php
declare(strict_types=1);

namespace AISAAutoPilot\Frontend;

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\Ui;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffEmail;
use AISAAutoPilot\Support\HandoffTelegram;
use AISAAutoPilot\Support\HandoffTwilio;

/**
 * Widget settings — the visitor-facing assistant, with a live preview that
 * reflects every control as it changes.
 */
final class ChatWidgetSettings {

	/** Position keys mapped to the short codes the preview stage uses. */
	private const POSITIONS = array(
		'bottom-right' => 'br',
		'bottom-left'  => 'bl',
		'top-right'    => 'tr',
		'top-left'     => 'tl',
	);

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved = $this->save();
		$w     = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );

		$brand    = (string) ( $w['color'] ?: '#4f46e5' );
		$theme    = 'dark' === ( $w['theme'] ?? 'light' ) ? 'dark' : 'light';
		$derived  = Settings::widget_derived_colors( $brand, $theme );
		$custom   = ! empty( $w['custom_colors'] );
		$colors   = $custom ? array_merge( $derived, (array) ( $w['colors'] ?? array() ) ) : $derived;
		$icons    = (array) ( $w['icons'] ?? array() );
		$position = self::POSITIONS[ $w['position'] ?? 'bottom-right' ] ?? 'br';

		$mail_on = ! empty( $w['handoff_email_enabled'] );
		$tg_on   = ! empty( $w['handoff_telegram_enabled'] );
		$tw_on   = ! empty( $w['handoff_twilio_enabled'] );
		$count   = (int) $mail_on + (int) $tg_on + (int) $tw_on;

		$has_tg_token  = Settings::has_handoff_secret( 'handoff_telegram_token' );
		$has_tw_sid    = Settings::has_handoff_secret( 'handoff_twilio_sid' );
		$has_tw_token  = Settings::has_handoff_secret( 'handoff_twilio_token' );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-widget>
			<section class="sp-screen">
				<?php Ui::title( __( 'Widget', 'ai-site-assistant-autopilot' ), 'chat', true ); ?>
				<p class="sp-lead" style="margin-bottom:16px !important"><?php esc_html_e( 'The visitor assistant is read-only and rate-limited. It never edits site content.', 'ai-site-assistant-autopilot' ); ?></p>

				<?php if ( $saved ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<form method="post">
					<?php wp_nonce_field( 'sp_widget_save' ); ?>
					<div class="sp-cols">
						<div class="sp-col sp-col--480">

							<!-- ── Widget basics ─────────────────────────── -->
							<div class="sp-card sp-card--24">
								<div class="sp-card__rule">
									<?php echo Ui::icon( 'chat', 18, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span><?php esc_html_e( 'Widget basics', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
								<label class="sp-check sp-check--14" style="margin-bottom:16px">
									<input type="checkbox" name="widget[enabled]" value="1" <?php checked( ! empty( $w['enabled'] ) ); ?> />
									<span><?php esc_html_e( 'Enable Widget', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-cols sp-cols--16">
									<label class="sp-field" style="flex:1 1 200px">
										<span class="sp-label sp-mb0"><?php esc_html_e( 'Title', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="text" class="sp-input sp-input--sm" name="widget[title]" value="<?php echo esc_attr( (string) $w['title'] ); ?>" data-sp-bind="title" />
									</label>
									<label class="sp-field" style="flex:1 1 200px">
										<span class="sp-label sp-mb0"><?php esc_html_e( 'Greeting', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="text" class="sp-input sp-input--sm" name="widget[greeting]" value="<?php echo esc_attr( (string) $w['greeting'] ); ?>" data-sp-bind="greeting" />
									</label>
								</div>

								<label class="sp-field" style="margin-top:16px">
									<span class="sp-label sp-mb0"><?php esc_html_e( 'Suggested questions', 'ai-site-assistant-autopilot' ); ?></span>
									<textarea class="sp-textarea" name="widget[suggestions]" rows="3" style="min-height:84px"
										placeholder="<?php esc_attr_e( "Do you ship to my city?\nWhat are your opening hours?\nHow do I return an item?", 'ai-site-assistant-autopilot' ); ?>"
										data-sp-bind="suggestions"><?php echo esc_textarea( implode( "\n", (array) ( $w['suggestions'] ?? array() ) ) ); ?></textarea>
									<span class="sp-help"><?php esc_html_e( 'One per line, up to four. Shown as one-tap buttons before the visitor types — the fastest way to a useful first answer.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
							</div>

							<!-- ── Appearance ────────────────────────────── -->
							<div class="sp-card sp-card--24">
								<div class="sp-card__rule">
									<?php echo Ui::icon( 'palette', 18, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span><?php esc_html_e( 'Appearance', 'ai-site-assistant-autopilot' ); ?></span>
								</div>

								<div style="margin-bottom:16px">
									<span class="sp-label"><?php esc_html_e( 'Position', 'ai-site-assistant-autopilot' ); ?></span>
									<div class="sp-row-wrap" data-sp-radio-group="position">
										<?php foreach ( self::POSITIONS as $key => $code ) : ?>
											<label class="sp-choice sp-choice--round<?php echo ( $w['position'] ?? 'bottom-right' ) === $key ? ' is-on' : ''; ?>">
												<input type="radio" class="sp-sr" name="widget[position]" value="<?php echo esc_attr( $key ); ?>" data-sp-pos="<?php echo esc_attr( $code ); ?>" <?php checked( $w['position'] ?? 'bottom-right', $key ); ?> />
												<span><?php echo esc_html( $this->position_label( $key ) ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>

								<div style="margin-bottom:16px">
									<span class="sp-label"><?php esc_html_e( 'Theme', 'ai-site-assistant-autopilot' ); ?></span>
									<div class="sp-row-wrap" data-sp-radio-group="theme">
										<label class="sp-choice sp-choice--round<?php echo 'light' === $theme ? ' is-on' : ''; ?>">
											<input type="radio" class="sp-sr" name="widget[theme]" value="light" data-sp-theme="light" <?php checked( 'light', $theme ); ?> />
											<span><?php esc_html_e( 'Light', 'ai-site-assistant-autopilot' ); ?></span>
										</label>
										<label class="sp-choice sp-choice--round<?php echo 'dark' === $theme ? ' is-on' : ''; ?>">
											<input type="radio" class="sp-sr" name="widget[theme]" value="dark" data-sp-theme="dark" <?php checked( 'dark', $theme ); ?> />
											<span><?php esc_html_e( 'Dark', 'ai-site-assistant-autopilot' ); ?></span>
										</label>
									</div>
								</div>

								<div style="margin-bottom:16px">
									<span class="sp-label"><?php esc_html_e( 'Brand color', 'ai-site-assistant-autopilot' ); ?></span>
									<div class="sp-swatches">
										<?php foreach ( array( '#4f46e5', '#7c3aed', '#059669', '#1d2327' ) as $swatch ) : ?>
											<button type="button" class="sp-swatch<?php echo strtolower( $brand ) === $swatch ? ' is-on' : ''; ?>" data-sp-brand="<?php echo esc_attr( $swatch ); ?>" aria-label="<?php echo esc_attr( $swatch ); ?>">
												<span style="background:<?php echo esc_attr( $swatch ); ?>"></span>
											</button>
										<?php endforeach; ?>
										<input type="hidden" name="widget[color]" id="sp-w-brand" value="<?php echo esc_attr( $brand ); ?>" />
										<button type="button" class="sp-choice sp-choice--round<?php echo $custom ? ' is-on' : ''; ?>" id="sp-w-customize" aria-expanded="<?php echo $custom ? 'true' : 'false'; ?>">
											<?php echo Ui::icon( 'gear', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
											<span><?php esc_html_e( 'Customize', 'ai-site-assistant-autopilot' ); ?></span>
										</button>
									</div>
									<input type="hidden" name="widget[custom_colors]" id="sp-w-custom-flag" value="<?php echo $custom ? '1' : '0'; ?>" />
									<span class="sp-help" style="display:block;margin-top:8px" id="sp-w-custom-hint">
										<?php
										echo esc_html(
											$custom
												? __( 'Set each part separately; the brand colour is the starting point.', 'ai-site-assistant-autopilot' )
												: __( 'Open Customize for header text, icons, corner rounding and per-part colours.', 'ai-site-assistant-autopilot' )
										);
										?>
									</span>
								</div>

								<!-- Customize panel -->
								<div class="sp-card sp-card--soft" id="sp-w-custom-panel" style="display:<?php echo $custom ? 'flex' : 'none'; ?>;flex-direction:column;gap:18px;padding:18px">

									<div>
										<div class="sp-h3" style="font-size:13px;margin-bottom:10px"><?php esc_html_e( 'Header text', 'ai-site-assistant-autopilot' ); ?></div>
										<div class="sp-cols sp-cols--16">
											<label class="sp-field" style="flex:1 1 200px">
												<span class="sp-label sp-mb0"><?php esc_html_e( 'Head', 'ai-site-assistant-autopilot' ); ?></span>
												<input type="text" class="sp-input sp-input--sm" value="<?php echo esc_attr( (string) $w['title'] ); ?>" data-sp-mirror="title" />
											</label>
											<label class="sp-field" style="flex:1 1 200px">
												<span class="sp-label sp-mb0"><?php esc_html_e( 'Sub head', 'ai-site-assistant-autopilot' ); ?></span>
												<input type="text" class="sp-input sp-input--sm" name="widget[subtitle]" value="<?php echo esc_attr( (string) $w['subtitle'] ); ?>" data-sp-bind="subtitle" />
											</label>
										</div>
									</div>

									<div>
										<div class="sp-h3" style="font-size:13px;margin-bottom:10px"><?php esc_html_e( 'Icons (drop a PNG)', 'ai-site-assistant-autopilot' ); ?></div>
										<div class="sp-tiles" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr))">
											<?php
											Ui::slot( 'widget[icons][avatar]', __( 'Header icon', 'ai-site-assistant-autopilot' ), __( 'Beside the header title', 'ai-site-assistant-autopilot' ), (string) ( $icons['avatar'] ?? '' ) );
											Ui::slot( 'widget[icons][voice]', __( 'Voice icon', 'ai-site-assistant-autopilot' ), __( 'The mic button', 'ai-site-assistant-autopilot' ), (string) ( $icons['voice'] ?? '' ) );
											Ui::slot( 'widget[icons][handoff]', __( 'Handoff icon', 'ai-site-assistant-autopilot' ), __( 'The talk-to-human button', 'ai-site-assistant-autopilot' ), (string) ( $icons['handoff'] ?? '' ) );
											?>
										</div>
									</div>

									<div>
										<div class="sp-h3" style="font-size:13px;margin-bottom:10px"><?php esc_html_e( 'Corner rounding', 'ai-site-assistant-autopilot' ); ?></div>
										<div class="sp-cols sp-cols--16">
											<label style="flex:1 1 200px">
												<span class="sp-bar-head" style="font-weight:600;font-size:13px">
													<span><?php esc_html_e( 'Panel', 'ai-site-assistant-autopilot' ); ?></span>
													<span class="sp-mono sp-help" data-sp-out="panel_radius"><?php echo esc_html( (string) (int) $w['panel_radius'] ); ?>px</span>
												</span>
												<input type="range" class="sp-range" min="0" max="28" name="widget[panel_radius]" value="<?php echo esc_attr( (string) (int) $w['panel_radius'] ); ?>" data-sp-range="panel_radius" />
											</label>
											<label style="flex:1 1 200px">
												<span class="sp-bar-head" style="font-weight:600;font-size:13px">
													<span><?php esc_html_e( 'Bubbles', 'ai-site-assistant-autopilot' ); ?></span>
													<span class="sp-mono sp-help" data-sp-out="msg_radius"><?php echo esc_html( (string) (int) $w['msg_radius'] ); ?>px</span>
												</span>
												<input type="range" class="sp-range" min="0" max="24" name="widget[msg_radius]" value="<?php echo esc_attr( (string) (int) $w['msg_radius'] ); ?>" data-sp-range="msg_radius" />
											</label>
										</div>
									</div>

									<div>
										<div class="sp-h3" style="font-size:13px;margin-bottom:10px"><?php esc_html_e( 'Floating button', 'ai-site-assistant-autopilot' ); ?></div>
										<div class="sp-tiles" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:start">
											<label>
												<span class="sp-bar-head" style="font-weight:600;font-size:13px">
													<span><?php esc_html_e( 'Corners', 'ai-site-assistant-autopilot' ); ?></span>
													<span class="sp-mono sp-help" data-sp-out="bubble_radius"><?php echo esc_html( (string) (int) $w['bubble_radius'] ); ?>px</span>
												</span>
												<input type="range" class="sp-range" min="6" max="30" name="widget[bubble_radius]" value="<?php echo esc_attr( (string) (int) $w['bubble_radius'] ); ?>" data-sp-range="bubble_radius" />
											</label>
											<label class="sp-kv__row" style="font-size:13px;font-weight:600">
												<span><?php esc_html_e( 'Colour', 'ai-site-assistant-autopilot' ); ?></span>
												<input type="color" class="sp-color" name="widget[colors][bubble]" value="<?php echo esc_attr( (string) $colors['bubble'] ); ?>" data-sp-color="bubble" />
											</label>
											<?php Ui::slot( 'widget[icons][float]', __( 'Icon', 'ai-site-assistant-autopilot' ), __( 'Drop a PNG', 'ai-site-assistant-autopilot' ), (string) ( $icons['float'] ?? '' ) ); ?>
										</div>
									</div>

									<div>
										<div class="sp-h3" style="font-size:13px;margin-bottom:10px"><?php esc_html_e( 'Colors of each part', 'ai-site-assistant-autopilot' ); ?></div>
										<div class="sp-tiles" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px 24px">
											<?php foreach ( $this->color_labels() as $key => $label ) : ?>
												<label class="sp-kv__row" style="font-size:13px;font-weight:600">
													<span><?php echo esc_html( $label ); ?></span>
													<input type="color" class="sp-color" name="widget[colors][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $colors[ $key ] ?? '#4f46e5' ) ); ?>" data-sp-color="<?php echo esc_attr( $key ); ?>" />
												</label>
											<?php endforeach; ?>
										</div>
										<button type="button" class="sp-btn sp-btn--ghost sp-btn--r8" id="sp-w-reset-colors" style="margin-top:14px;padding:8px 14px;font-size:13px"><?php esc_html_e( 'Reset to brand colour', 'ai-site-assistant-autopilot' ); ?></button>
									</div>
								</div>
							</div>

							<!-- ── Voice ─────────────────────────────────── -->
							<div class="sp-card sp-card--24">
								<div class="sp-card__rule">
									<?php echo Ui::icon( 'mic-full', 18, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span><?php esc_html_e( 'Voice', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
								<label class="sp-check sp-check--14" style="margin-bottom:12px">
									<input type="checkbox" name="widget[voice_enabled]" value="1" <?php checked( ! empty( $w['voice_enabled'] ) ); ?> />
									<span><?php esc_html_e( 'Enable voice input', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<label class="sp-check sp-check--14">
									<input type="checkbox" name="widget[voice_auto]" value="1" <?php checked( ! empty( $w['voice_auto'] ) ); ?> />
									<span><?php esc_html_e( 'Auto-play response', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
							</div>

							<!-- ── Handoff ───────────────────────────────── -->
							<div class="sp-card sp-card--24">
								<div class="sp-card__rule">
									<?php echo Ui::icon( 'headset', 18, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span><?php esc_html_e( 'Handoff', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
								<label class="sp-check sp-check--14" style="margin-bottom:12px">
									<input type="checkbox" name="widget[handoff_enabled]" value="1" <?php checked( ! empty( $w['handoff_enabled'] ) ); ?> data-sp-bind="handoff" />
									<span><?php esc_html_e( 'Enable human handoff', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-cols sp-cols--16">
									<label class="sp-field" style="flex:1 1 200px">
										<span class="sp-label sp-mb0"><?php esc_html_e( 'Button text', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="text" class="sp-input sp-input--sm" name="widget[handoff_text]" value="<?php echo esc_attr( (string) ( $w['handoff_text'] ?? __( 'Talk to human', 'ai-site-assistant-autopilot' ) ) ); ?>" data-sp-bind="handoffText" />
									</label>
									<label class="sp-field" style="flex:1 1 200px">
										<span class="sp-label sp-mb0"><?php esc_html_e( 'Hook text', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="text" class="sp-input sp-input--sm" name="widget[handoff_hook]" value="<?php echo esc_attr( (string) ( $w['handoff_hook'] ?? '' ) ); ?>" data-sp-bind="hook" />
									</label>
								</div>

								<div style="margin-top:20px;padding-top:16px;border-block-start:1px solid var(--sp-line-soft)">
									<div class="sp-h3" style="font-size:13px;margin-bottom:2px"><?php esc_html_e( 'Channels', 'ai-site-assistant-autopilot' ); ?></div>
									<p class="sp-help sp-help--12" id="sp-w-channel-summary">
										<?php
										echo 0 === $count
											? esc_html__( 'No channel is enabled, so the handoff button stays hidden from visitors.', 'ai-site-assistant-autopilot' )
											: esc_html(
												sprintf(
													/* translators: %s: number of enabled handoff channels. */
													_n( '%s channel enabled. The handoff message goes to every enabled channel.', '%s channels enabled. The handoff message goes to every enabled channel.', $count, 'ai-site-assistant-autopilot' ),
													number_format_i18n( $count )
												)
											);
										?>
									</p>
								</div>

								<?php
								$this->channel_email( $w, $mail_on );
								$this->channel_telegram( $w, $tg_on, $has_tg_token );
								$this->channel_twilio( $w, $tw_on, $has_tw_sid, $has_tw_token );
								?>
							</div>

							<div style="position:sticky;bottom:0;display:flex;gap:10px;align-items:center;padding:14px 0;margin-top:8px">
								<button type="submit" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Save settings', 'ai-site-assistant-autopilot' ); ?></button>
							</div>
						</div>

						<!-- ── Live preview ──────────────────────────────── -->
						<aside class="sp-side sp-side--340" style="position:sticky;top:40px">
							<div class="sp-eyebrow"><?php esc_html_e( 'Live preview', 'ai-site-assistant-autopilot' ); ?></div>
							<?php $this->preview( $w, $colors, $icons, $position, $theme ); ?>
						</aside>
					</div>
				</form>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	// ── Live preview ────────────────────────────────────────────

	/**
	 * @param array<string, mixed>  $w
	 * @param array<string, string> $colors
	 * @param array<string, mixed>  $icons
	 */
	private function preview( array $w, array $colors, array $icons, string $position, string $theme ): void {
		$style = sprintf(
			'--wc-panel:%s;--wc-msgs:%s;--wc-border:%s;--wc-ai-bg:%s;--wc-ai-text:%s;--wc-user:%s;--wc-send:%s;--wc-handoff:%s;--wc-bubble:%s;--wc-input:%s;--wc-head-from:%s;--wc-head-to:%s;--wc-mic:%s;--wc-muted:%s;--wp-radius:%dpx;--wp-msg-radius:%dpx;--wp-bubble-radius:%dpx',
			esc_attr( $colors['panel'] ),
			esc_attr( $colors['msgs'] ),
			esc_attr( $colors['border'] ),
			esc_attr( $colors['ai_bg'] ),
			esc_attr( $colors['ai_text'] ),
			esc_attr( $colors['user'] ),
			esc_attr( $colors['send'] ),
			esc_attr( $colors['handoff'] ),
			esc_attr( $colors['bubble'] ),
			esc_attr( $colors['input'] ),
			esc_attr( $colors['head_from'] ),
			esc_attr( $colors['head_to'] ),
			'dark' === $theme ? '#374151' : '#ffffff',
			'dark' === $theme ? '#9ca3af' : '#6b7280',
			(int) $w['panel_radius'],
			(int) $w['msg_radius'],
			(int) $w['bubble_radius']
		);
		?>
		<div class="sp-wstage" id="sp-w-stage" data-pos="<?php echo esc_attr( $position ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>" style="<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value escaped above. ?>">
			<div class="sp-wpanel">
				<div class="sp-wpanel__head">
					<span class="sp-wpanel__avatar" data-sp-icon="avatar">
						<?php if ( ! empty( $icons['avatar'] ) ) : ?>
							<img src="<?php echo esc_url( (string) $icons['avatar'] ); ?>" alt="" />
						<?php else : ?>
							<?php echo Ui::solid( 'sparkle2', 22, 'currentColor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<?php endif; ?>
					</span>
					<span class="sp-wpanel__id">
						<span class="sp-wpanel__title" data-sp-out="title"><?php echo esc_html( (string) $w['title'] ); ?></span>
						<span class="sp-wpanel__sub" data-sp-out="subtitle"><?php echo esc_html( (string) $w['subtitle'] ); ?></span>
					</span>
					<span class="sp-wpanel__x"><?php echo Ui::icon( 'close', 16, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				</div>

				<?php
				/*
				 * Mirrors the real panel's empty state: greeting as a heading, then
				 * the one-tap suggested questions, then a sample exchange.
				 */
				$preview_suggestions = array_slice( (array) ( $w['suggestions'] ?? array() ), 0, 4 );
				?>
				<div class="sp-wpanel__msgs">
					<div class="sp-wwelcome">
						<span class="sp-wwelcome__icon" data-sp-icon="avatar">
							<?php if ( ! empty( $icons['avatar'] ) ) : ?>
								<img src="<?php echo esc_url( (string) $icons['avatar'] ); ?>" alt="" />
							<?php else : ?>
								<?php echo Ui::solid( 'sparkle2', 22, 'currentColor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<?php endif; ?>
						</span>
						<span class="sp-wwelcome__title" data-sp-out="greeting"><?php echo esc_html( (string) $w['greeting'] ); ?></span>
						<span class="sp-wwelcome__list" data-sp-out="suggestions">
							<?php foreach ( $preview_suggestions as $suggestion ) : ?>
								<span class="sp-wwelcome__chip"><?php echo esc_html( (string) $suggestion ); ?></span>
							<?php endforeach; ?>
						</span>
					</div>
					<span class="sp-wbubble sp-wbubble--me"><?php esc_html_e( 'Do you ship to Shiraz?', 'ai-site-assistant-autopilot' ); ?></span>
				</div>

				<div class="sp-wpanel__handoff" data-sp-region="handoff" <?php echo empty( $w['handoff_enabled'] ) ? 'hidden' : ''; ?>>
					<span class="sp-wpanel__hook" data-sp-out="hook"><?php echo esc_html( (string) ( $w['handoff_hook'] ?? '' ) ); ?></span>
					<button type="button" class="sp-wpanel__handoff-btn" tabindex="-1">
						<span data-sp-icon="handoff">
							<?php if ( ! empty( $icons['handoff'] ) ) : ?>
								<img src="<?php echo esc_url( (string) $icons['handoff'] ); ?>" alt="" style="width:14px;height:14px;object-fit:contain" />
							<?php else : ?>
								<?php echo Ui::icon( 'headset', 14, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<?php endif; ?>
						</span>
						<span data-sp-out="handoffText"><?php echo esc_html( (string) ( $w['handoff_text'] ?? __( 'Talk to human', 'ai-site-assistant-autopilot' ) ) ); ?></span>
					</button>
				</div>

				<div class="sp-wpanel__foot">
					<span class="sp-wpanel__mic" data-sp-icon="voice">
						<?php if ( ! empty( $icons['voice'] ) ) : ?>
							<img src="<?php echo esc_url( (string) $icons['voice'] ); ?>" alt="" />
						<?php else : ?>
							<?php echo Ui::icon( 'mic-full', 15, 'var(--wc-muted,#6b7280)', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<?php endif; ?>
					</span>
					<span class="sp-wpanel__input"><?php esc_html_e( 'Type your message…', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-wpanel__send"><?php echo Ui::icon( 'send', 16, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				</div>
			</div>

			<span class="sp-wfloat" data-sp-icon="float">
				<?php if ( ! empty( $icons['float'] ) ) : ?>
					<img src="<?php echo esc_url( (string) $icons['float'] ); ?>" alt="" />
				<?php else : ?>
					<?php echo Ui::icon( 'chat', 26, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
				<?php endif; ?>
			</span>
		</div>
		<?php
	}

	// ── Channel cards ───────────────────────────────────────────

	/** @param array<string, mixed> $w */
	private function channel_email( array $w, bool $on ): void {
		$configured = ( new HandoffEmail() )->is_configured();
		?>
		<div class="sp-channel<?php echo $on ? ' is-on' : ''; ?>" data-sp-channel>
			<div class="sp-channel__head">
				<span class="sp-channel__icon"><?php echo Ui::icon( 'mail', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				<span class="sp-channel__id">
					<span class="sp-channel__name"><?php esc_html_e( 'Email', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-channel__note"><?php esc_html_e( 'Where the handoff message is sent', 'ai-site-assistant-autopilot' ); ?></span>
				</span>
				<?php echo Ui::pill( $on && $configured ? __( 'Enabled', 'ai-site-assistant-autopilot' ) : ( $on ? __( 'Needs setup', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) ), $on && $configured ? 'ok' : ( $on ? 'warn' : 'off' ), true, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
				<label class="sp-check" style="font-size:12.5px;font-weight:600;white-space:nowrap">
					<input type="checkbox" name="widget[handoff_email_enabled]" value="1" <?php checked( $on ); ?> data-sp-channel-toggle />
					<span><?php esc_html_e( 'Enable', 'ai-site-assistant-autopilot' ); ?></span>
				</label>
			</div>
			<div class="sp-channel__fields">
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'Recipient address', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="email" class="sp-input sp-input--sm" dir="ltr" name="widget[handoff_email_to]" value="<?php echo esc_attr( (string) ( $w['handoff_email_to'] ?? '' ) ); ?>" placeholder="support@example.com" />
				</label>
			</div>
		</div>
		<?php
	}

	/** @param array<string, mixed> $w */
	private function channel_telegram( array $w, bool $on, bool $has_token ): void {
		$configured = ( new HandoffTelegram() )->is_configured();
		?>
		<div class="sp-channel<?php echo $on ? ' is-on' : ''; ?>" data-sp-channel>
			<div class="sp-channel__head">
				<span class="sp-channel__icon"><?php echo Ui::icon( 'send', 17, 'currentColor', 1.7 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				<span class="sp-channel__id">
					<span class="sp-channel__name"><?php esc_html_e( 'Telegram', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-channel__note"><?php esc_html_e( 'Sends into one chat or group', 'ai-site-assistant-autopilot' ); ?></span>
				</span>
				<?php echo Ui::pill( $on && $configured ? __( 'Enabled', 'ai-site-assistant-autopilot' ) : ( $on ? __( 'Needs setup', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) ), $on && $configured ? 'ok' : ( $on ? 'warn' : 'off' ), true, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
				<label class="sp-check" style="font-size:12.5px;font-weight:600;white-space:nowrap">
					<input type="checkbox" name="widget[handoff_telegram_enabled]" value="1" <?php checked( $on ); ?> data-sp-channel-toggle />
					<span><?php esc_html_e( 'Enable', 'ai-site-assistant-autopilot' ); ?></span>
				</label>
			</div>
			<div class="sp-channel__fields">
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'Bot token', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="password" class="sp-input sp-input--sm sp-mono" autocomplete="new-password" name="widget[handoff_telegram_token]" value=""
						placeholder="<?php echo esc_attr( $has_token ? __( 'Saved securely — enter a new token to replace it', 'ai-site-assistant-autopilot' ) : '123456789:AA…' ); ?>" />
					<?php if ( $has_token ) : ?>
						<label class="sp-check" style="font-size:12px;margin-top:6px">
							<input type="checkbox" name="widget[clear_handoff_telegram_token]" value="1" />
							<span><?php esc_html_e( 'Remove saved token', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
					<?php endif; ?>
				</label>
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'Chat ID', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="text" class="sp-input sp-input--sm sp-mono" dir="ltr" name="widget[handoff_telegram_chat]" value="<?php echo esc_attr( (string) ( $w['handoff_telegram_chat'] ?? '' ) ); ?>" placeholder="-1001234567890" />
				</label>
			</div>
		</div>
		<?php
	}

	/** @param array<string, mixed> $w */
	private function channel_twilio( array $w, bool $on, bool $has_sid, bool $has_token ): void {
		$configured = ( new HandoffTwilio() )->is_configured();
		?>
		<div class="sp-channel<?php echo $on ? ' is-on' : ''; ?>" data-sp-channel>
			<div class="sp-channel__head">
				<span class="sp-channel__icon"><?php echo Ui::icon( 'phone', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				<span class="sp-channel__id">
					<span class="sp-channel__name"><?php esc_html_e( 'Twilio (SMS)', 'ai-site-assistant-autopilot' ); ?></span>
					<span class="sp-channel__note"><?php esc_html_e( 'Text message to your support number', 'ai-site-assistant-autopilot' ); ?></span>
				</span>
				<?php echo Ui::pill( $on && $configured ? __( 'Enabled', 'ai-site-assistant-autopilot' ) : ( $on ? __( 'Needs setup', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ) ), $on && $configured ? 'ok' : ( $on ? 'warn' : 'off' ), true, 'sp-pill--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
				<label class="sp-check" style="font-size:12.5px;font-weight:600;white-space:nowrap">
					<input type="checkbox" name="widget[handoff_twilio_enabled]" value="1" <?php checked( $on ); ?> data-sp-channel-toggle />
					<span><?php esc_html_e( 'Enable', 'ai-site-assistant-autopilot' ); ?></span>
				</label>
			</div>
			<div class="sp-channel__fields">
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'Account SID', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="text" class="sp-input sp-input--sm sp-mono" autocomplete="off" name="widget[handoff_twilio_sid]" value=""
						placeholder="<?php echo esc_attr( $has_sid ? __( 'Saved securely — enter a new SID to replace it', 'ai-site-assistant-autopilot' ) : 'AC…' ); ?>" />
					<?php if ( $has_sid ) : ?>
						<label class="sp-check" style="font-size:12px;margin-top:6px">
							<input type="checkbox" name="widget[clear_handoff_twilio_sid]" value="1" />
							<span><?php esc_html_e( 'Remove saved SID', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
					<?php endif; ?>
				</label>
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'Auth token', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="password" class="sp-input sp-input--sm sp-mono" autocomplete="new-password" name="widget[handoff_twilio_token]" value=""
						placeholder="<?php echo esc_attr( $has_token ? __( 'Saved securely — enter a new token to replace it', 'ai-site-assistant-autopilot' ) : '••••••••' ); ?>" />
					<?php if ( $has_token ) : ?>
						<label class="sp-check" style="font-size:12px;margin-top:6px">
							<input type="checkbox" name="widget[clear_handoff_twilio_token]" value="1" />
							<span><?php esc_html_e( 'Remove saved token', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
					<?php endif; ?>
				</label>
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'From number', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="text" class="sp-input sp-input--sm sp-mono" dir="ltr" name="widget[handoff_twilio_from]" value="<?php echo esc_attr( (string) ( $w['handoff_twilio_from'] ?? '' ) ); ?>" placeholder="+15551234567" />
				</label>
				<label class="sp-field sp-field--row">
					<span class="sp-label sp-label--sm sp-mb0"><?php esc_html_e( 'To number', 'ai-site-assistant-autopilot' ); ?></span>
					<input type="text" class="sp-input sp-input--sm sp-mono" dir="ltr" name="widget[handoff_twilio_to]" value="<?php echo esc_attr( (string) ( $w['handoff_twilio_to'] ?? '' ) ); ?>" placeholder="+989120000000" />
				</label>
			</div>
		</div>
		<?php
	}

	// ── Helpers ─────────────────────────────────────────────────

	private function position_label( string $key ): string {
		switch ( $key ) {
			case 'bottom-left':
				return __( 'Bottom Left', 'ai-site-assistant-autopilot' );
			case 'top-right':
				return __( 'Top Right', 'ai-site-assistant-autopilot' );
			case 'top-left':
				return __( 'Top Left', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Bottom Right', 'ai-site-assistant-autopilot' );
		}
	}

	/** @return array<string, string> */
	private function color_labels(): array {
		return array(
			'head_from' => __( 'Header from', 'ai-site-assistant-autopilot' ),
			'head_to'   => __( 'Header to', 'ai-site-assistant-autopilot' ),
			'user'      => __( 'User bubble', 'ai-site-assistant-autopilot' ),
			'ai_bg'     => __( 'Assistant bubble', 'ai-site-assistant-autopilot' ),
			'ai_text'   => __( 'Assistant text', 'ai-site-assistant-autopilot' ),
			'panel'     => __( 'Panel background', 'ai-site-assistant-autopilot' ),
			'msgs'      => __( 'Messages background', 'ai-site-assistant-autopilot' ),
			'border'    => __( 'Border', 'ai-site-assistant-autopilot' ),
			'input'     => __( 'Input background', 'ai-site-assistant-autopilot' ),
			'send'      => __( 'Send button', 'ai-site-assistant-autopilot' ),
			'handoff'   => __( 'Handoff button', 'ai-site-assistant-autopilot' ),
		);
	}

	// ── Save ────────────────────────────────────────────────────

	private function save(): bool {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'sp_widget_save' ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$raw      = (array) wp_unslash( $_POST['widget'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized below.
		$existing = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );

		$parsed = array(
			'enabled'                  => ! empty( $raw['enabled'] ),
			'position'                 => array_key_exists( (string) ( $raw['position'] ?? '' ), self::POSITIONS ) ? sanitize_text_field( (string) $raw['position'] ) : 'bottom-right',
			'theme'                    => 'dark' === ( $raw['theme'] ?? 'light' ) ? 'dark' : 'light',
			'color'                    => sanitize_hex_color( (string) ( $raw['color'] ?? '#4f46e5' ) ) ?: '#4f46e5',
			'bg'                       => sanitize_hex_color( (string) ( $raw['bg'] ?? '#ffffff' ) ) ?: '#ffffff',
			'title'                    => sanitize_text_field( (string) ( $raw['title'] ?? 'AI Site Assistant AutoPilot' ) ),
			'subtitle'                 => sanitize_text_field( (string) ( $raw['subtitle'] ?? 'AI Assistant' ) ),
			'greeting'                 => sanitize_text_field( (string) ( $raw['greeting'] ?? 'Hi!' ) ),
			'suggestions'              => $this->sanitize_suggestions( (string) ( $raw['suggestions'] ?? '' ) ),
			'panel_radius'             => max( 0, min( 28, absint( $raw['panel_radius'] ?? 16 ) ) ),
			'msg_radius'               => max( 0, min( 24, absint( $raw['msg_radius'] ?? 14 ) ) ),
			'bubble_radius'            => max( 6, min( 30, absint( $raw['bubble_radius'] ?? 30 ) ) ),
			// empty() already treats the string '0' as off, so no second guard.
			'custom_colors'            => ! empty( $raw['custom_colors'] ),
			'colors'                   => $this->sanitize_colors( (array) ( $raw['colors'] ?? array() ) ),
			'icons'                    => $this->sanitize_icons( (array) ( $raw['icons'] ?? array() ), (array) ( $existing['icons'] ?? array() ) ),
			'auto_open'                => absint( $raw['auto_open'] ?? $existing['auto_open'] ),
			'voice_enabled'            => ! empty( $raw['voice_enabled'] ),
			'voice_auto'               => ! empty( $raw['voice_auto'] ),
			'voice_name'               => sanitize_text_field( (string) ( $raw['voice_name'] ?? $existing['voice_name'] ) ),
			'handoff_enabled'          => ! empty( $raw['handoff_enabled'] ),
			'handoff_text'             => sanitize_text_field( (string) ( $raw['handoff_text'] ?? 'Talk to human' ) ),
			'handoff_hook'             => sanitize_text_field( (string) ( $raw['handoff_hook'] ?? '' ) ),
			'handoff_email_enabled'    => ! empty( $raw['handoff_email_enabled'] ),
			'handoff_email_to'         => sanitize_email( (string) ( $raw['handoff_email_to'] ?? '' ) ),
			'handoff_telegram_enabled' => ! empty( $raw['handoff_telegram_enabled'] ),
			'handoff_telegram_chat'    => sanitize_text_field( (string) ( $raw['handoff_telegram_chat'] ?? '' ) ),
			'handoff_twilio_enabled'   => ! empty( $raw['handoff_twilio_enabled'] ),
			'handoff_twilio_from'      => sanitize_text_field( (string) ( $raw['handoff_twilio_from'] ?? '' ) ),
			'handoff_twilio_to'        => sanitize_text_field( (string) ( $raw['handoff_twilio_to'] ?? '' ) ),
		);

		foreach ( array( 'handoff_telegram_token', 'handoff_twilio_sid', 'handoff_twilio_token' ) as $secret_key ) {
			$secret = trim( sanitize_text_field( (string) ( $raw[ $secret_key ] ?? '' ) ) );
			if ( ! empty( $raw[ 'clear_' . $secret_key ] ) ) {
				Settings::set_handoff_secret( $secret_key, '' );
			} elseif ( '' !== $secret ) {
				Settings::set_handoff_secret( $secret_key, $secret );
			}
		}

		Settings::update( array( 'widget' => array_merge( $existing, $parsed ) ) );

		return true;
	}

	/**
	 * One suggestion per line, capped so the empty state stays scannable.
	 *
	 * @return array<int, string>
	 */
	private function sanitize_suggestions( string $raw ): array {
		$out = array();
		foreach ( preg_split( '/\R/', $raw ) ?: array() as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$out[] = mb_substr( $line, 0, 90 );
			}
		}

		return array_slice( $out, 0, 4 );
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, string>
	 */
	private function sanitize_colors( array $raw ): array {
		$out = array();
		foreach ( Settings::WIDGET_COLOR_KEYS as $key ) {
			$value = sanitize_hex_color( (string) ( $raw[ $key ] ?? '' ) );
			if ( $value ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Accept either an uploaded data URI (produced by the drop slot) or a plain
	 * URL. Anything else — or an oversized payload — is dropped, and an empty
	 * submission keeps whatever was saved before.
	 *
	 * @param array<string, mixed> $raw
	 * @param array<string, mixed> $existing
	 * @return array<string, string>
	 */
	private function sanitize_icons( array $raw, array $existing ): array {
		$out = array();
		foreach ( Settings::WIDGET_ICON_KEYS as $key ) {
			$value = trim( (string) ( $raw[ $key ] ?? '' ) );

			if ( '' === $value ) {
				continue; // Explicitly cleared by the slot's remove button.
			}

			if ( 0 === strpos( $value, 'data:image/' ) ) {
				// ~200 KB of base64 keeps the option small enough to autoload safely.
				if ( strlen( $value ) > 280000 ) {
					$out[ $key ] = (string) ( $existing[ $key ] ?? '' );
					continue;
				}
				if ( preg_match( '#^data:image/(png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $value ) ) {
					$out[ $key ] = $value;
				}
				continue;
			}

			$url = esc_url_raw( $value );
			if ( '' !== $url ) {
				$out[ $key ] = $url;
			}
		}

		return array_filter( $out );
	}
}
