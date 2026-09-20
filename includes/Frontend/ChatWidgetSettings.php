<?php
declare(strict_types=1);

namespace AISAAutoPilot\Frontend;

use AISAAutoPilot\Admin\AnswerDeskNavigation;
use AISAAutoPilot\Admin\ConnectionChip;
use AISAAutoPilot\Admin\HandoffInboxPage;
use AISAAutoPilot\Admin\Ui;
use AISAAutoPilot\Core\Settings;

/**
 * Advanced Widget settings: how the visitor chat looks, the pages it appears on, what it says
 * first and whether conversations are recorded, with a live preview that follows every control.
 *
 * Only the fields this form renders are saved. Each switch posts an explicit "0" before its
 * checkbox, and settings the form does not show (the on/off switch, voice, handoff channels and
 * credentials) are left exactly as they are; they live on their own Answer Desk tabs.
 */
final class ChatWidgetSettings {

	/** Position keys mapped to the short codes the preview stage uses. */
	private const POSITIONS = array(
		'bottom-right' => 'br',
		'bottom-left'  => 'bl',
		'top-right'    => 'tr',
		'top-left'     => 'tl',
	);

	/** Brand colour shortcuts; any other colour can be picked with the colour input. */
	private const BRANDS = array( '#202a8d', '#1b4d89', '#198754', '#14152b', '#ff455f' );

	/** Floating-button corner radius behind each shape choice. */
	private const BUTTON_SHAPES = array(
		'circle'  => 30,
		'rounded' => 16,
		'square'  => 6,
	);

	/** Text fields and their saved length limits. */
	private const TEXT_LIMITS = array(
		'title'               => 80,
		'subtitle'            => 80,
		'greeting'            => 160,
		'handoff_text'        => 40,
		'handoff_hook'        => 120,
		'conversation_notice' => 240,
	);

	/** Text fields that keep their previous value instead of being saved empty. */
	private const REQUIRED_TEXT = array( 'title', 'greeting', 'handoff_text' );

	/** Delays offered for opening the chat by itself, in seconds; 0 never opens it. */
	private const AUTO_OPEN_CHOICES = array( 0, 5, 10, 20, 30, 60 );

	/** The longest delay a saved value may carry, in seconds. */
	private const AUTO_OPEN_MAX = 120;

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved = $this->save();
		$w     = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		// Untouched defaults show in the admin's language; save() stores them back as defaults.
		$w = array_merge( $w, Settings::widget_copy( $w ) );

		$brand         = sanitize_hex_color( (string) ( $w['color'] ?? '' ) ) ?: '#202a8d';
		$theme         = 'dark' === ( $w['theme'] ?? 'light' ) ? 'dark' : 'light';
		$derived       = Settings::widget_derived_colors( $brand, $theme );
		$custom        = ! empty( $w['custom_colors'] );
		$colors        = $custom ? array_merge( $derived, (array) ( $w['colors'] ?? array() ) ) : $derived;
		$icons         = (array) ( $w['icons'] ?? array() );
		$position      = array_key_exists( (string) ( $w['position'] ?? '' ), self::POSITIONS ) ? (string) $w['position'] : 'bottom-right';
		$recording     = ! empty( $w['conversation_logging_enabled'] );
		$bubble_radius = max( 6, min( 30, (int) $w['bubble_radius'] ) );
		$preset_shape  = in_array( $bubble_radius, self::BUTTON_SHAPES, true );
		$auto_open     = max( 0, min( self::AUTO_OPEN_MAX, (int) $w['auto_open'] ) );
		$auto_choices  = self::AUTO_OPEN_CHOICES;
		if ( ! in_array( $auto_open, $auto_choices, true ) ) {
			$auto_choices[] = $auto_open;
			sort( $auto_choices );
		}

		Ui::grad_defs();
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'widget'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		$current_tab   = in_array( $requested_tab, array( 'voice', 'handoff' ), true ) ? $requested_tab : AnswerDeskNavigation::TAB_WIDGET;
		?>
		<div class="wrap sp-app" data-sp-widget>
			<section class="sp-screen">
		<?php
		Ui::page_header(
			__( 'Answer Desk', 'ai-site-assistant-autopilot' ),
			__( 'The support chat on your site, and every conversation it handled.', 'ai-site-assistant-autopilot' ),
			array(
				'chip'    => ConnectionChip::derive(),
				'action'  => array(
					'label' => __( 'Back to Widget', 'ai-site-assistant-autopilot' ),
					'url'   => AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ),
				),
				'subtabs' => AnswerDeskNavigation::subtabs( $current_tab ),
			)
		);
		?>
		<div class="sp-pagebody">

		<?php if ( $saved ) : ?>
			<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
		<?php endif; ?>

			<div class="sp-wz-intro">
				<div class="sp-wz-intro__body">
					<h2 class="sp-wz-intro__title"><?php esc_html_e( 'Customise the widget', 'ai-site-assistant-autopilot' ); ?></h2>
					<p class="sp-wz-intro__text"><?php esc_html_e( 'How the chat looks, which pages it appears on, what it says first and whether conversations are recorded. The on/off switch, voice and human handoff have their own tabs.', 'ai-site-assistant-autopilot' ); ?></p>
				</div>
				<nav class="sp-wz-intro__links" aria-label="<?php esc_attr_e( 'Related Answer Desk settings', 'ai-site-assistant-autopilot' ); ?>">
					<a href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_WIDGET ) ); ?>"><?php esc_html_e( 'Show or hide the widget', 'ai-site-assistant-autopilot' ); ?></a>
					<a href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_VOICE ) ); ?>"><?php esc_html_e( 'Voice', 'ai-site-assistant-autopilot' ); ?></a>
					<a href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF ) ); ?>"><?php esc_html_e( 'Human handoff channels', 'ai-site-assistant-autopilot' ); ?></a>
				</nav>
			</div>

			<form method="post" class="sp-wz" data-sp-widget-form>
		<?php wp_nonce_field( 'sp_widget_save' ); ?>
				<div class="sp-wz__main">

					<section class="sp-wz-card" id="answer-desk-widget" aria-labelledby="sp-wz-look-title">
						<h2 class="sp-wz-card__title" id="sp-wz-look-title"><?php esc_html_e( 'Where it sits and how it looks', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-wz-card__grid">
							<fieldset class="sp-wz-field">
								<legend class="sp-wz-field__label"><?php esc_html_e( 'Position', 'ai-site-assistant-autopilot' ); ?></legend>
								<div class="sp-wz-choices sp-wz-choices--2" data-sp-radio-group="position">
								<?php foreach ( self::POSITIONS as $key => $code ) : ?>
									<label class="sp-wz-choice<?php echo $position === $key ? ' is-on' : ''; ?>">
										<input type="radio" class="sp-sr" name="widget[position]" value="<?php echo esc_attr( $key ); ?>" data-sp-pos="<?php echo esc_attr( $code ); ?>" <?php checked( $position, $key ); ?> />
										<span><?php echo esc_html( $this->position_label( $key ) ); ?></span>
									</label>
								<?php endforeach; ?>
								</div>
							</fieldset>
							<fieldset class="sp-wz-field">
								<legend class="sp-wz-field__label"><?php esc_html_e( 'Theme', 'ai-site-assistant-autopilot' ); ?></legend>
								<div class="sp-wz-choices sp-wz-choices--2" data-sp-radio-group="theme">
									<label class="sp-wz-choice<?php echo 'light' === $theme ? ' is-on' : ''; ?>">
										<input type="radio" class="sp-sr" name="widget[theme]" value="light" data-sp-theme="light" <?php checked( 'light', $theme ); ?> />
										<span><?php esc_html_e( 'Light', 'ai-site-assistant-autopilot' ); ?></span>
									</label>
									<label class="sp-wz-choice<?php echo 'dark' === $theme ? ' is-on' : ''; ?>">
										<input type="radio" class="sp-sr" name="widget[theme]" value="dark" data-sp-theme="dark" <?php checked( 'dark', $theme ); ?> />
										<span><?php esc_html_e( 'Dark', 'ai-site-assistant-autopilot' ); ?></span>
									</label>
								</div>
							</fieldset>
						</div>
					</section>

					<?php $this->render_visibility( $w ); ?>

					<section class="sp-wz-card" aria-labelledby="sp-wz-colours-title">
						<div class="sp-wz-card__head">
							<h2 class="sp-wz-card__title" id="sp-wz-colours-title"><?php esc_html_e( 'Colours', 'ai-site-assistant-autopilot' ); ?></h2>
							<button type="button" class="sp-wz-link" id="sp-w-reset-colors"><?php esc_html_e( 'Reset to brand colour', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
						<p class="sp-wz-card__text"><?php esc_html_e( 'Pick a brand colour, then adjust any single part.', 'ai-site-assistant-autopilot' ); ?></p>
						<div class="sp-wz-brands" role="group" aria-label="<?php esc_attr_e( 'Brand colour', 'ai-site-assistant-autopilot' ); ?>">
						<?php foreach ( self::BRANDS as $swatch ) : ?>
							<?php $is_brand = strtolower( $brand ) === $swatch; ?>
							<button type="button" class="sp-wz-brand<?php echo $is_brand ? ' is-on' : ''; ?>" data-sp-brand="<?php echo esc_attr( $swatch ); ?>" aria-pressed="<?php echo $is_brand ? 'true' : 'false'; ?>" aria-label="<?php /* translators: %s: hex colour such as #202a8d. */ echo esc_attr( sprintf( __( 'Use %s as the brand colour', 'ai-site-assistant-autopilot' ), $swatch ) ); ?>">
								<span style="background:<?php echo esc_attr( $swatch ); ?>"></span>
							</button>
						<?php endforeach; ?>
							<label class="sp-wz-brand-custom">
								<input type="color" value="<?php echo esc_attr( $brand ); ?>" data-sp-brand-custom />
								<span><?php esc_html_e( 'Other colour', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<input type="hidden" name="widget[color]" id="sp-w-brand" value="<?php echo esc_attr( $brand ); ?>" />
							<input type="hidden" name="widget[custom_colors]" id="sp-w-custom-flag" value="<?php echo $custom ? '1' : '0'; ?>" />
						</div>
						<p class="sp-wz-card__note" id="sp-w-custom-hint" data-custom-text="<?php esc_attr_e( 'Some parts use their own colour. Reset to follow the brand colour again.', 'ai-site-assistant-autopilot' ); ?>" data-derived-text="<?php esc_attr_e( 'Every part follows the brand colour.', 'ai-site-assistant-autopilot' ); ?>">
						<?php
						echo esc_html(
							$custom
								? __( 'Some parts use their own colour. Reset to follow the brand colour again.', 'ai-site-assistant-autopilot' )
								: __( 'Every part follows the brand colour.', 'ai-site-assistant-autopilot' )
						);
						?>
						</p>
						<div class="sp-wz-parts">
						<?php foreach ( $this->color_labels() as $key => $label ) : ?>
							<?php $hex = (string) ( $colors[ $key ] ?? '#202a8d' ); ?>
							<label class="sp-wz-part">
								<input type="color" class="sp-wz-part__input" name="widget[colors][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $hex ); ?>" data-sp-color="<?php echo esc_attr( $key ); ?>" />
								<span class="sp-wz-part__label"><?php echo esc_html( $label ); ?></span>
								<code class="sp-wz-part__hex" dir="ltr" data-sp-color-hex="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $hex ); ?></code>
							</label>
						<?php endforeach; ?>
						</div>
					</section>

					<section class="sp-wz-card" aria-labelledby="sp-wz-header-title">
						<h2 class="sp-wz-card__title" id="sp-wz-header-title"><?php esc_html_e( 'Header text and icons', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-wz-card__grid">
							<label class="sp-wz-field">
								<span class="sp-wz-field__label"><?php esc_html_e( 'Title', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="text" class="sp-input sp-input--sm" name="widget[title]" maxlength="80" required value="<?php echo esc_attr( (string) $w['title'] ); ?>" data-sp-bind="title" />
							</label>
							<label class="sp-wz-field">
								<span class="sp-wz-field__label"><?php esc_html_e( 'Subtitle', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="text" class="sp-input sp-input--sm" name="widget[subtitle]" maxlength="80" value="<?php echo esc_attr( (string) $w['subtitle'] ); ?>" data-sp-bind="subtitle" />
							</label>
						</div>
						<div class="sp-wz-slots">
						<?php
						Ui::slot( 'widget[icons][avatar]', __( 'Header icon', 'ai-site-assistant-autopilot' ), __( 'Beside the header title', 'ai-site-assistant-autopilot' ), (string) ( $icons['avatar'] ?? '' ) );
						Ui::slot( 'widget[icons][voice]', __( 'Voice icon', 'ai-site-assistant-autopilot' ), __( 'The mic button', 'ai-site-assistant-autopilot' ), (string) ( $icons['voice'] ?? '' ) );
						Ui::slot( 'widget[icons][handoff]', __( 'Handoff icon', 'ai-site-assistant-autopilot' ), __( 'The talk-to-human button', 'ai-site-assistant-autopilot' ), (string) ( $icons['handoff'] ?? '' ) );
						?>
						</div>
						<p class="sp-wz-card__note"><?php esc_html_e( 'PNG, JPEG, WebP or SVG up to 200 KB. Remove an image to use the built-in icon again.', 'ai-site-assistant-autopilot' ); ?></p>
					</section>

					<section class="sp-wz-card" aria-labelledby="sp-wz-first-title">
						<h2 class="sp-wz-card__title" id="sp-wz-first-title"><?php esc_html_e( 'What visitors see first', 'ai-site-assistant-autopilot' ); ?></h2>
						<label class="sp-wz-field">
							<span class="sp-wz-field__label"><?php esc_html_e( 'Greeting', 'ai-site-assistant-autopilot' ); ?></span>
							<input type="text" class="sp-input sp-input--sm" name="widget[greeting]" maxlength="160" required value="<?php echo esc_attr( (string) $w['greeting'] ); ?>" data-sp-bind="greeting" />
						</label>
						<label class="sp-wz-field">
							<span class="sp-wz-field__label"><?php esc_html_e( 'Suggested questions', 'ai-site-assistant-autopilot' ); ?></span>
							<textarea class="sp-textarea" name="widget[suggestions]" rows="4" aria-describedby="sp-wz-suggestions-help"
								placeholder="<?php esc_attr_e( "Do you ship to my city?\nWhat are your opening hours?\nHow do I return an item?", 'ai-site-assistant-autopilot' ); ?>"
								data-sp-bind="suggestions"><?php echo esc_textarea( implode( "\n", (array) ( $w['suggestions'] ?? array() ) ) ); ?></textarea>
							<span class="sp-wz-field__help" id="sp-wz-suggestions-help"><?php esc_html_e( 'One per line, up to four. Shown as one-tap buttons before the visitor types — the fastest way to a useful first answer.', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
						<label class="sp-wz-field" data-sp-auto-open-field>
							<span class="sp-wz-field__label"><?php esc_html_e( 'Open the chat by itself', 'ai-site-assistant-autopilot' ); ?></span>
							<select class="sp-select sp-input--sm" name="widget[auto_open]" aria-describedby="sp-wz-auto-open-help" data-sp-auto-open>
								<?php foreach ( $auto_choices as $seconds ) : ?>
									<option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( $auto_open, $seconds ); ?>>
									<?php
									echo esc_html(
										0 === $seconds
											? __( 'Never — show a short hint beside the button', 'ai-site-assistant-autopilot' )
											/* translators: %d: number of seconds after the page loads. */
											: sprintf( _n( 'After %d second', 'After %d seconds', $seconds, 'ai-site-assistant-autopilot' ), $seconds )
									);
									?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="sp-wz-field__help" id="sp-wz-auto-open-help"><?php esc_html_e( 'Counted from each page load. On phones the open chat covers the whole screen, so most sites leave this off.', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
					</section>

					<section class="sp-wz-card" aria-labelledby="sp-wz-corners-title">
						<h2 class="sp-wz-card__title" id="sp-wz-corners-title"><?php esc_html_e( 'Corners and the floating button', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-wz-card__grid">
							<label class="sp-wz-field">
								<span class="sp-wz-field__label sp-wz-field__label--split">
									<span><?php esc_html_e( 'Panel corners', 'ai-site-assistant-autopilot' ); ?></span>
									<span class="sp-wz-field__value" dir="ltr" data-sp-out="panel_radius"><?php echo esc_html( (string) (int) $w['panel_radius'] ); ?>px</span>
								</span>
								<input type="range" class="sp-wz-range" min="0" max="28" name="widget[panel_radius]" value="<?php echo esc_attr( (string) (int) $w['panel_radius'] ); ?>" data-sp-range="panel_radius" />
							</label>
							<label class="sp-wz-field">
								<span class="sp-wz-field__label sp-wz-field__label--split">
									<span><?php esc_html_e( 'Message corners', 'ai-site-assistant-autopilot' ); ?></span>
									<span class="sp-wz-field__value" dir="ltr" data-sp-out="msg_radius"><?php echo esc_html( (string) (int) $w['msg_radius'] ); ?>px</span>
								</span>
								<input type="range" class="sp-wz-range" min="0" max="24" name="widget[msg_radius]" value="<?php echo esc_attr( (string) (int) $w['msg_radius'] ); ?>" data-sp-range="msg_radius" />
							</label>
						</div>
						<fieldset class="sp-wz-field">
							<legend class="sp-wz-field__label"><?php esc_html_e( 'Button shape', 'ai-site-assistant-autopilot' ); ?></legend>
							<div class="sp-wz-choices sp-wz-choices--auto" data-sp-radio-group="shape">
							<?php foreach ( self::BUTTON_SHAPES as $shape => $radius ) : ?>
								<label class="sp-wz-choice<?php echo $bubble_radius === $radius ? ' is-on' : ''; ?>">
									<input type="radio" class="sp-sr" name="widget[bubble_radius]" value="<?php echo esc_attr( (string) $radius ); ?>" data-sp-shape="<?php echo esc_attr( (string) $radius ); ?>" <?php checked( $bubble_radius, $radius ); ?> />
									<span><?php echo esc_html( $this->shape_label( $shape ) ); ?></span>
								</label>
							<?php endforeach; ?>
							<?php if ( ! $preset_shape ) : ?>
								<label class="sp-wz-choice is-on">
									<input type="radio" class="sp-sr" name="widget[bubble_radius]" value="<?php echo esc_attr( (string) $bubble_radius ); ?>" data-sp-shape="<?php echo esc_attr( (string) $bubble_radius ); ?>" checked />
									<span>
									<?php
									/* translators: %d: corner radius in pixels. */
									echo esc_html( sprintf( __( 'Custom (%dpx)', 'ai-site-assistant-autopilot' ), $bubble_radius ) );
									?>
									</span>
								</label>
							<?php endif; ?>
							</div>
						</fieldset>
						<div class="sp-wz-slots sp-wz-slots--single">
						<?php Ui::slot( 'widget[icons][float]', __( 'Floating button icon', 'ai-site-assistant-autopilot' ), __( 'Replaces the chat bubble on the button', 'ai-site-assistant-autopilot' ), (string) ( $icons['float'] ?? '' ) ); ?>
						</div>
					</section>

					<section class="sp-wz-card" aria-labelledby="sp-wz-handoff-title">
						<h2 class="sp-wz-card__title" id="sp-wz-handoff-title"><?php esc_html_e( 'Talk to a person button', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wz-card__text"><?php esc_html_e( 'Visitors see this button only while Human handoff has a ready channel.', 'ai-site-assistant-autopilot' ); ?></p>
						<div class="sp-wz-card__grid">
							<label class="sp-wz-field">
								<span class="sp-wz-field__label"><?php esc_html_e( 'Button text', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="text" class="sp-input sp-input--sm" name="widget[handoff_text]" maxlength="40" required value="<?php echo esc_attr( (string) ( $w['handoff_text'] ?? __( 'Talk to a human', 'ai-site-assistant-autopilot' ) ) ); ?>" data-sp-bind="handoffText" />
							</label>
							<label class="sp-wz-field">
								<span class="sp-wz-field__label"><?php esc_html_e( 'Text above the button', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="text" class="sp-input sp-input--sm" name="widget[handoff_hook]" maxlength="120" value="<?php echo esc_attr( (string) ( $w['handoff_hook'] ?? '' ) ); ?>" data-sp-bind="hook" />
							</label>
						</div>
						<a class="sp-wz-link" href="<?php echo esc_url( AnswerDeskNavigation::url( AnswerDeskNavigation::TAB_HANDOFF ) ); ?>"><?php esc_html_e( 'Set up handoff channels', 'ai-site-assistant-autopilot' ); ?></a>
					</section>

					<section class="sp-wz-card" id="answer-desk-privacy" aria-labelledby="sp-wz-privacy-title">
						<h2 class="sp-wz-card__title" id="sp-wz-privacy-title"><?php esc_html_e( 'Recording and privacy', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wz-card__text"><?php esc_html_e( 'Decide whether visitor conversations are kept, and what visitors are told.', 'ai-site-assistant-autopilot' ); ?></p>
						<div class="sp-wz-switch-row">
							<input type="hidden" name="widget[conversation_logging_enabled]" value="0" />
							<input type="checkbox" class="sp-wz-switch" id="sp-wz-recording" role="switch" name="widget[conversation_logging_enabled]" value="1" aria-describedby="sp-wz-recording-note" data-sp-recording <?php checked( $recording ); ?> />
							<div class="sp-wz-switch-row__body">
								<label class="sp-wz-switch-row__title" for="sp-wz-recording"><?php esc_html_e( 'Record visitor conversations', 'ai-site-assistant-autopilot' ); ?></label>
								<p class="sp-wz-switch-row__note" id="sp-wz-recording-note" data-on-text="<?php esc_attr_e( 'Recorded conversations appear under Conversations with the pages each answer used.', 'ai-site-assistant-autopilot' ); ?>" data-off-text="<?php esc_attr_e( 'Conversations are not kept. Questions your content could not answer are still listed as content gaps, without who asked.', 'ai-site-assistant-autopilot' ); ?>">
								<?php
								echo esc_html(
									$recording
										? __( 'Recorded conversations appear under Conversations with the pages each answer used.', 'ai-site-assistant-autopilot' )
										: __( 'Conversations are not kept. Questions your content could not answer are still listed as content gaps, without who asked.', 'ai-site-assistant-autopilot' )
								);
								?>
								</p>
							</div>
						</div>
						<label class="sp-wz-field<?php echo $recording ? '' : ' is-muted'; ?>" data-sp-notice-field>
							<span class="sp-wz-field__label"><?php esc_html_e( 'What visitors are told', 'ai-site-assistant-autopilot' ); ?></span>
							<textarea class="sp-textarea" name="widget[conversation_notice]" rows="3" maxlength="240" aria-describedby="sp-wz-notice-help" data-sp-bind="notice"><?php echo esc_textarea( (string) $w['conversation_notice'] ); ?></textarea>
							<span class="sp-wz-field__help" id="sp-wz-notice-help"><?php esc_html_e( 'Shown above the message box while recording is on. Make sure it matches your privacy policy.', 'ai-site-assistant-autopilot' ); ?></span>
						</label>
						<p class="sp-wz-info">
							<?php echo Ui::icon( 'info', 15, '#202a8d', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'When a visitor asks for a person, that conversation is kept in the handoff inbox so your team can reply — even while recording is off.', 'ai-site-assistant-autopilot' ); ?></span>
						</p>
					</section>

					<div class="sp-wz-actions">
						<button type="submit" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Save changes', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
				</div>

				<aside class="sp-wz__preview" aria-labelledby="sp-wz-preview-title">
					<div class="sp-wz-preview__head">
						<h2 class="sp-wz-preview__title" id="sp-wz-preview-title"><?php esc_html_e( 'Live preview', 'ai-site-assistant-autopilot' ); ?></h2>
						<div class="sp-wz-devices" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'ai-site-assistant-autopilot' ); ?>">
							<button type="button" class="sp-wz-device is-on" data-sp-device="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'ai-site-assistant-autopilot' ); ?></button>
							<button type="button" class="sp-wz-device" data-sp-device="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
					</div>
					<?php $this->preview( $w, $colors, $icons, self::POSITIONS[ $position ], $theme, $recording, $bubble_radius ); ?>
					<p class="sp-wz-preview__note"><?php esc_html_e( 'Preview only. Visitors see changes after you save.', 'ai-site-assistant-autopilot' ); ?></p>
				</aside>

				<div class="sp-wz-savebar" data-sp-savebar hidden>
					<div class="sp-wz-savebar__inner" role="region" aria-label="<?php esc_attr_e( 'Unsaved changes', 'ai-site-assistant-autopilot' ); ?>">
						<span class="sp-wz-savebar__dot" aria-hidden="true"></span>
						<span class="sp-wz-savebar__text"><?php esc_html_e( 'Unsaved widget changes — visitors still see the saved version', 'ai-site-assistant-autopilot' ); ?></span>
						<button type="button" class="sp-wz-savebar__discard" data-sp-discard><?php esc_html_e( 'Discard', 'ai-site-assistant-autopilot' ); ?></button>
						<button type="submit" class="sp-wz-savebar__save"><?php esc_html_e( 'Save changes', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
				</div>
			</form>

			<?php if ( AnswerDeskNavigation::TAB_HANDOFF === $current_tab ) : ?>
				<div class="sp-wz-inbox" id="answer-desk-handoff-inbox">
					<?php ( new HandoffInboxPage() )->render_inbox(); ?>
				</div>
			<?php endif; ?>
			<?php
			Ui::json_script(
				'sp-wz-i18n',
				array(
					'discardTitle' => __( 'Discard your changes?', 'ai-site-assistant-autopilot' ),
					'discardBody'  => __( 'The widget goes back to its last saved settings. Nothing is sent to visitors.', 'ai-site-assistant-autopilot' ),
					'discardCta'   => __( 'Discard changes', 'ai-site-assistant-autopilot' ),
					'iconTooLarge' => __( 'That image is too large. Please use a PNG under 200 KB.', 'ai-site-assistant-autopilot' ),
					'whereLoading' => __( 'Searching…', 'ai-site-assistant-autopilot' ),
					'whereFound'   => __( 'Choose an entry to hide the chat on it.', 'ai-site-assistant-autopilot' ),
					'whereNone'    => __( 'No published page, post or product has that title.', 'ai-site-assistant-autopilot' ),
					'whereError'   => __( 'Search is not available right now. Try again.', 'ai-site-assistant-autopilot' ),
					'whereAdd'     => __( 'Hide here', 'ai-site-assistant-autopilot' ),
					'whereAdded'   => __( 'Hidden', 'ai-site-assistant-autopilot' ),
					/* translators: %s: title of a page, post or product. */
					'whereAddedOn' => __( '%s added. Save changes to apply it.', 'ai-site-assistant-autopilot' ),
					/* translators: %s: title of a page, post or product. */
					'whereRemove'  => __( 'Show the chat again on %s', 'ai-site-assistant-autopilot' ),
					/* translators: %d: the most single entries the chat can be hidden on. */
					'whereMax'     => sprintf( __( 'You can hide the chat on up to %d single entries.', 'ai-site-assistant-autopilot' ), Settings::WIDGET_EXCLUDE_IDS_MAX ),
					'whereNoTitle' => __( '(no title)', 'ai-site-assistant-autopilot' ),
				)
			);
			?>
		</div>
		</section>
		<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	// ── Pages where the chat appears ────────────────────────────

	/**
	 * @param array<string, mixed> $w
	 */
	private function render_visibility( array $w ): void {
		$except    = 'exclude' === ( $w['widget_visibility'] ?? 'all' );
		$rules     = Settings::widget_exclusions( $w );
		$types     = $this->visibility_types();
		$cats      = get_categories(
			array(
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$cat_ids   = array_map( static fn( \WP_Term $term ): int => (int) $term->term_id, $cats );
		$picked    = $this->picked_entries( $rules['ids'] );
		$has_rules = array() !== array_intersect( $rules['types'], array_keys( $types ) )
			|| array() !== array_intersect( $rules['cats'], $cat_ids )
			|| array() !== $picked;
		?>
		<section class="sp-wz-card" id="answer-desk-visibility" aria-labelledby="sp-wz-where-title" data-sp-visibility data-sp-where-url="<?php echo esc_url( rest_url( 'wp/v2/search' ) ); ?>" data-sp-where-max="<?php echo esc_attr( (string) Settings::WIDGET_EXCLUDE_IDS_MAX ); ?>">
			<h2 class="sp-wz-card__title" id="sp-wz-where-title"><?php esc_html_e( 'Pages where the chat appears', 'ai-site-assistant-autopilot' ); ?></h2>
			<p class="sp-wz-card__text"><?php esc_html_e( 'Hide the chat where it gets in the way, such as checkout or a page that already has its own contact form.', 'ai-site-assistant-autopilot' ); ?></p>
			<fieldset class="sp-wz-field">
				<legend class="sp-wz-field__label"><?php esc_html_e( 'Show the chat on', 'ai-site-assistant-autopilot' ); ?></legend>
				<div class="sp-wz-choices sp-wz-choices--2" data-sp-radio-group="visibility">
					<label class="sp-wz-choice<?php echo $except ? '' : ' is-on'; ?>">
						<input type="radio" class="sp-sr" name="widget[widget_visibility]" value="all" <?php checked( ! $except ); ?> />
						<span><?php esc_html_e( 'Every page', 'ai-site-assistant-autopilot' ); ?></span>
					</label>
					<label class="sp-wz-choice<?php echo $except ? ' is-on' : ''; ?>">
						<input type="radio" class="sp-sr" name="widget[widget_visibility]" value="exclude" <?php checked( $except ); ?> />
						<span><?php esc_html_e( 'Every page except the ones below', 'ai-site-assistant-autopilot' ); ?></span>
					</label>
				</div>
			</fieldset>
			<p class="sp-wz-where__warn" data-sp-visibility-none<?php echo $except && ! $has_rules ? '' : ' hidden'; ?>><?php esc_html_e( 'Nothing is chosen below yet, so the chat still shows on every page.', 'ai-site-assistant-autopilot' ); ?></p>
			<div class="sp-wz-where" data-sp-visibility-rules<?php echo $except ? '' : ' hidden'; ?>>
				<fieldset class="sp-wz-field">
					<legend class="sp-wz-field__label"><?php esc_html_e( 'Content types', 'ai-site-assistant-autopilot' ); ?></legend>
					<input type="hidden" name="widget[exclude_types][]" value="" />
					<div class="sp-wz-checks">
					<?php foreach ( $types as $slug => $label ) : ?>
						<label class="sp-wz-check">
							<input type="checkbox" name="widget[exclude_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $rules['types'], true ) ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
					</div>
					<span class="sp-wz-field__help"><?php esc_html_e( 'Hides the chat on every item of a ticked type and on that type’s archive page.', 'ai-site-assistant-autopilot' ); ?></span>
				</fieldset>
				<?php if ( array() !== $cats ) : ?>
					<fieldset class="sp-wz-field">
						<legend class="sp-wz-field__label"><?php esc_html_e( 'Post categories', 'ai-site-assistant-autopilot' ); ?></legend>
						<input type="hidden" name="widget[exclude_cats][]" value="" />
						<div class="sp-wz-checks sp-wz-checks--scroll">
						<?php foreach ( $cats as $cat ) : ?>
							<label class="sp-wz-check">
								<input type="checkbox" name="widget[exclude_cats][]" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php checked( in_array( (int) $cat->term_id, $rules['cats'], true ) ); ?> />
								<span><?php echo esc_html( $cat->name ); ?></span>
							</label>
						<?php endforeach; ?>
						</div>
						<span class="sp-wz-field__help"><?php esc_html_e( 'Hides the chat on posts filed in a ticked category and on that category’s own page.', 'ai-site-assistant-autopilot' ); ?></span>
					</fieldset>
				<?php endif; ?>
				<div class="sp-wz-field">
					<label class="sp-wz-field__label" for="sp-wz-where-search"><?php esc_html_e( 'Single pages, posts or products', 'ai-site-assistant-autopilot' ); ?></label>
					<input type="search" id="sp-wz-where-search" class="sp-input sp-input--sm" autocomplete="off" placeholder="<?php esc_attr_e( 'Search by title', 'ai-site-assistant-autopilot' ); ?>" aria-describedby="sp-wz-where-status" aria-controls="sp-wz-where-results" data-sp-where-search />
					<p class="sp-wz-where__status" id="sp-wz-where-status" role="status" aria-live="polite" data-sp-where-status></p>
					<ul class="sp-wz-where__results" id="sp-wz-where-results" data-sp-where-results hidden></ul>
					<input type="hidden" name="widget[exclude_ids][]" value="" />
					<ul class="sp-wz-where__picked" aria-label="<?php esc_attr_e( 'The chat is hidden on', 'ai-site-assistant-autopilot' ); ?>" data-sp-where-picked>
					<?php foreach ( $picked as $entry ) : ?>
						<li class="sp-wz-where__item" data-sp-where-item="<?php echo esc_attr( (string) $entry['id'] ); ?>">
							<span class="sp-wz-where__name"><?php echo esc_html( $entry['title'] ); ?></span>
							<span class="sp-wz-where__type"><?php echo esc_html( $entry['type'] ); ?></span>
							<input type="hidden" name="widget[exclude_ids][]" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" />
							<button type="button" class="sp-wz-where__remove" data-sp-where-remove aria-label="<?php /* translators: %s: title of a page, post or product. */ echo esc_attr( sprintf( __( 'Show the chat again on %s', 'ai-site-assistant-autopilot' ), $entry['title'] ) ); ?>"><span aria-hidden="true">&times;</span></button>
						</li>
					<?php endforeach; ?>
					</ul>
					<p class="sp-wz-field__help" data-sp-where-empty<?php echo array() === $picked ? '' : ' hidden'; ?>><?php esc_html_e( 'No single entry is chosen.', 'ai-site-assistant-autopilot' ); ?></p>
				</div>
			</div>
			<p class="sp-wz-card__note"><?php esc_html_e( 'The same rules apply to the [aisaautopilot_chat] shortcode.', 'ai-site-assistant-autopilot' ); ?></p>
		</section>
		<?php
		Ui::json_script( 'sp-wz-where-types', $this->entry_type_labels() );
	}

	/**
	 * Post types a visitor can open, as slug => plural name. Media attachments are left out.
	 *
	 * @return array<string, string>
	 */
	private function visibility_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $type ) {
			if ( 'attachment' !== $slug ) {
				$out[ (string) $slug ] = (string) $type->labels->name;
			}
		}
		return $out;
	}

	/**
	 * Singular names for the entry type shown beside each search result and chosen entry.
	 *
	 * @return array<string, string>
	 */
	private function entry_type_labels(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $type ) {
			$out[ (string) $slug ] = (string) $type->labels->singular_name;
		}
		return $out;
	}

	/**
	 * Saved entry IDs that still exist, with the title and type the picker shows.
	 *
	 * @param array<int, int> $ids
	 * @return array<int, array{id:int, title:string, type:string}>
	 */
	private function picked_entries( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$entry = get_post( $id );
			if ( ! $entry instanceof \WP_Post || 'trash' === $entry->post_status ) {
				continue;
			}
			$type  = get_post_type_object( $entry->post_type );
			$title = trim( wp_strip_all_tags( get_the_title( $entry ) ) );
			$out[] = array(
				'id'    => $id,
				/* translators: %d: numeric ID of a page or post that has no title. */
				'title' => '' !== $title ? $title : sprintf( __( '(no title) #%d', 'ai-site-assistant-autopilot' ), $id ),
				'type'  => $type ? (string) $type->labels->singular_name : $entry->post_type,
			);
		}
		return $out;
	}

	/**
	 * @param array<int|string, mixed> $raw
	 * @return array<int, string>
	 */
	private function sanitize_types( array $raw ): array {
		$allowed = $this->visibility_types();
		$out     = array();
		foreach ( $raw as $value ) {
			$slug = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
			if ( isset( $allowed[ $slug ] ) ) {
				$out[ $slug ] = $slug;
			}
		}
		return array_values( $out );
	}

	/**
	 * @param array<int|string, mixed> $raw
	 * @return array<int, int>
	 */
	private function sanitize_categories( array $raw ): array {
		$out = array();
		foreach ( $raw as $value ) {
			$id = Settings::positive_id( $value );
			if ( $id > 0 && get_term( $id, 'category' ) instanceof \WP_Term ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	/**
	 * Entries that exist, are not in the trash and have a public page, up to the saved limit.
	 *
	 * @param array<int|string, mixed> $raw
	 * @return array<int, int>
	 */
	private function sanitize_entries( array $raw ): array {
		$out = array();
		foreach ( $raw as $value ) {
			$id    = Settings::positive_id( $value );
			$entry = $id > 0 ? get_post( $id ) : null;
			if ( ! $entry instanceof \WP_Post || 'trash' === $entry->post_status || ! is_post_type_viewable( $entry->post_type ) ) {
				continue;
			}
			$out[ $id ] = $id;
			if ( count( $out ) >= Settings::WIDGET_EXCLUDE_IDS_MAX ) {
				break;
			}
		}
		return array_values( $out );
	}

	// ── Live preview ────────────────────────────────────────────

	/**
	 * @param array<string, mixed>  $w
	 * @param array<string, string> $colors
	 * @param array<string, mixed>  $icons
	 */
	private function preview( array $w, array $colors, array $icons, string $position, string $theme, bool $recording, int $bubble_radius ): void {
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
			$bubble_radius
		);
		?>
		<div class="sp-wstage" id="sp-w-stage" data-pos="<?php echo esc_attr( $position ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>" data-device="desktop" aria-hidden="true" style="<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value escaped above. ?>">
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

				<div class="sp-wpanel__handoff" data-sp-region="handoff">
					<span class="sp-wpanel__hook" data-sp-out="hook"><?php echo esc_html( (string) ( $w['handoff_hook'] ?? '' ) ); ?></span>
					<span class="sp-wpanel__handoff-btn">
						<span data-sp-icon="handoff">
		<?php if ( ! empty( $icons['handoff'] ) ) : ?>
								<img src="<?php echo esc_url( (string) $icons['handoff'] ); ?>" alt="" style="width:14px;height:14px;object-fit:contain" />
							<?php else : ?>
								<?php echo Ui::icon( 'headset', 14, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<?php endif; ?>
						</span>
						<span data-sp-out="handoffText"><?php echo esc_html( (string) ( $w['handoff_text'] ?? __( 'Talk to a human', 'ai-site-assistant-autopilot' ) ) ); ?></span>
					</span>
				</div>

				<div class="sp-wpanel__notice" data-sp-region="notice"<?php echo $recording ? '' : ' hidden'; ?>>
					<span data-sp-out="notice"><?php echo esc_html( (string) $w['conversation_notice'] ); ?></span>
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

	private function shape_label( string $shape ): string {
		switch ( $shape ) {
			case 'rounded':
				return __( 'Rounded', 'ai-site-assistant-autopilot' );
			case 'square':
				return __( 'Square', 'ai-site-assistant-autopilot' );
			default:
				return __( 'Circle', 'ai-site-assistant-autopilot' );
		}
	}

	/**
	 * @return array<string, string>
	 */
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
			'bubble'    => __( 'Floating button', 'ai-site-assistant-autopilot' ),
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
		$parsed   = array();

		// Only keys this form submitted are written, so the on/off switch, voice and handoff settings stay untouched.
		if ( array_key_exists( 'position', $raw ) ) {
			$parsed['position'] = array_key_exists( (string) $raw['position'], self::POSITIONS ) ? (string) $raw['position'] : 'bottom-right';
		}
		if ( array_key_exists( 'theme', $raw ) ) {
			$parsed['theme'] = 'dark' === $raw['theme'] ? 'dark' : 'light';
		}
		if ( array_key_exists( 'color', $raw ) ) {
			$parsed['color'] = sanitize_hex_color( (string) $raw['color'] ) ?: '#202a8d';
		}
		if ( array_key_exists( 'custom_colors', $raw ) ) {
			// empty() already treats the string '0' as off.
			$parsed['custom_colors'] = ! empty( $raw['custom_colors'] );
		}
		if ( array_key_exists( 'colors', $raw ) ) {
			$parsed['colors'] = $this->sanitize_colors( (array) $raw['colors'] );
		}
		if ( array_key_exists( 'icons', $raw ) ) {
			$parsed['icons'] = $this->sanitize_icons( (array) $raw['icons'], (array) ( $existing['icons'] ?? array() ) );
		}
		if ( array_key_exists( 'suggestions', $raw ) ) {
			$parsed['suggestions'] = $this->sanitize_suggestions( (string) $raw['suggestions'] );
		}
		$default_copy = Settings::widget_default_copy();
		$defaults     = Settings::widget_defaults();
		foreach ( self::TEXT_LIMITS as $key => $limit ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$value = mb_substr( trim( sanitize_text_field( (string) $raw[ $key ] ) ), 0, $limit );
			if ( '' === $value && in_array( $key, self::REQUIRED_TEXT, true ) ) {
				continue;
			}
			// The form shows untouched defaults translated; keep them stored as defaults so they follow the site language.
			if ( isset( $default_copy[ $key ] ) && $value === $default_copy[ $key ] ) {
				$value = (string) $defaults[ $key ];
			}
			$parsed[ $key ] = $value;
		}
		if ( array_key_exists( 'auto_open', $raw ) ) {
			$parsed['auto_open'] = min( self::AUTO_OPEN_MAX, absint( $raw['auto_open'] ) );
		}
		foreach ( array(
			'panel_radius'  => array( 0, 28 ),
			'msg_radius'    => array( 0, 24 ),
			'bubble_radius' => array( 6, 30 ),
		) as $key => $bounds ) {
			if ( array_key_exists( $key, $raw ) ) {
				$parsed[ $key ] = max( $bounds[0], min( $bounds[1], absint( $raw[ $key ] ) ) );
			}
		}
		if ( array_key_exists( 'conversation_logging_enabled', $raw ) ) {
			$parsed['conversation_logging_enabled'] = ! empty( $raw['conversation_logging_enabled'] );
		}
		if ( array_key_exists( 'widget_visibility', $raw ) ) {
			$parsed['widget_visibility'] = 'exclude' === $raw['widget_visibility'] ? 'exclude' : 'all';
		}
		if ( array_key_exists( 'exclude_types', $raw ) ) {
			$parsed['exclude_types'] = $this->sanitize_types( (array) $raw['exclude_types'] );
		}
		if ( array_key_exists( 'exclude_cats', $raw ) ) {
			$parsed['exclude_cats'] = $this->sanitize_categories( (array) $raw['exclude_cats'] );
		}
		if ( array_key_exists( 'exclude_ids', $raw ) ) {
			$parsed['exclude_ids'] = $this->sanitize_entries( (array) $raw['exclude_ids'] );
			// The picker showed entries from every key the renderer reads, so a removal must empty them all.
			$parsed['exclude_pages'] = array();
			if ( array_key_exists( 'widget_exclude_ids', $existing ) ) {
				$parsed['widget_exclude_ids'] = array();
			}
		}

		if ( array() === $parsed ) {
			return false;
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
	 * @param  array<string, mixed> $raw
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
	 * @param  array<string, mixed> $raw
	 * @param  array<string, mixed> $existing
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
