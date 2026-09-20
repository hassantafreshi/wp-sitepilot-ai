<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffEmail;
use AISAAutoPilot\Support\HandoffInbox;
use AISAAutoPilot\Support\HandoffTelegram;
use AISAAutoPilot\Support\HandoffTwilio;

/**
 * Mockup-aligned Human handoff configuration within the Answer Desk route.
 *
 * It presents only delivery channels that have a real transport in this
 * build. The legacy full Widget settings page remains a backwards-compatible
 * advanced route; it is deliberately not used as this tab's form handler.
 */
final class AnswerDeskHandoffPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$widget      = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		$email       = new HandoffEmail();
		$telegram    = new HandoffTelegram();
		$twilio      = new HandoffTwilio();
		$integration = ! empty( Settings::get( 'public_assistant_enabled', false ) );
		$widget_on   = ! empty( $widget['enabled'] );
		$show_twilio = ! empty( $widget['handoff_twilio_enabled'] ) || $twilio->is_configured();

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-answer-desk data-sp-answer-tab="handoff" data-sp-handoff-settings data-sp-handoff-available="<?php echo Settings::answer_desk_handoff_available() ? '1' : '0'; ?>">
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
					'subtabs' => AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_HANDOFF ),
				)
			);
			?>
				<div class="sp-pagebody">
				<?php if ( ! $integration || ! $widget_on ) : ?>
					<?php Ui::alert( 'warn', 'warning', esc_html__( 'Configure handoff channels here, but visitors cannot request a person until Answer Desk and its Widget are both enabled.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>
					<div class="sp-answer-handoff">
						<p class="sp-answer-handoff__intro" data-sp-handoff-delivery-mode="fan-out"><?php esc_html_e( 'When a visitor asks for a person, the plugin sends the handoff to every enabled and configured channel below. It does not use an ordered fallback queue.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php $this->render_inbox_link(); ?>
						<div class="sp-answer-handoff__cards">
						<?php $this->render_email( $widget, $email ); ?>
						<?php $this->render_telegram( $widget, $telegram ); ?>
						<?php $this->render_whatsapp_locked(); ?>
						<?php $this->render_twilio( $widget, $twilio, $show_twilio ); ?>
						</div>
						<?php $this->render_add_channels( $show_twilio ); ?>
						<?php $this->render_visitor_fields(); ?>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * Delivery state of each channel that has a real transport, shared with the handoff inbox.
	 *
	 * @return array<int, array{id:string,label:string,state:string,state_label:string}>
	 */
	public static function channel_states(): array {
		$widget   = array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
		$channels = array(
			array( 'email', __( 'Email', 'ai-site-assistant-autopilot' ), ! empty( $widget['handoff_email_enabled'] ), ( new HandoffEmail() )->is_configured() ),
			array( 'telegram', __( 'Telegram', 'ai-site-assistant-autopilot' ), ! empty( $widget['handoff_telegram_enabled'] ), ( new HandoffTelegram() )->is_configured() ),
			array( 'twilio', __( 'SMS (Twilio)', 'ai-site-assistant-autopilot' ), ! empty( $widget['handoff_twilio_enabled'] ), ( new HandoffTwilio() )->is_configured() ),
		);
		$states   = array();
		foreach ( $channels as $channel ) {
			list( $id, $label, $enabled, $configured ) = $channel;
			$states[]                                  = array(
				'id'          => $id,
				'label'       => $label,
				'state'       => substr( trim( self::state_class( $enabled, $configured ) ), 3 ),
				'state_label' => self::state_label( $enabled, $configured ),
			);
		}
		return $states;
	}

	/**
	 * The strip into the handoff inbox: real new and delivery-failed counts from the inbox table, and
	 * whether visitors are offered a person at all (a request is only taken when a channel is ready).
	 */
	private function render_inbox_link(): void {
		$inbox       = new HandoffInbox();
		$new         = $inbox->count_new();
		$failed      = $inbox->count_failed();
		$available   = Settings::answer_desk_handoff_available();
		$count_state = $new + $failed > 0 ? 'waiting' : 'quiet';
		$state       = $available ? $count_state : 'unavailable';

		// Switching a channel on or off updates availability in place (answer-handoff.js), so both texts travel with the strip.
		$ready_body       = 'waiting' === $count_state
			? __( 'Each request stays in the inbox with its conversation. Set its status so your team knows who has it.', 'ai-site-assistant-autopilot' )
			: __( 'The inbox keeps every earlier request with the conversation that led to it.', 'ai-site-assistant-autopilot' );
		$unavailable_body = __( 'Visitors are not offered a person until handoff is on and at least one channel below is ready. Earlier requests stay in the inbox.', 'ai-site-assistant-autopilot' );
		$body             = $available ? $ready_body : $unavailable_body;
		?>
		<div class="sp-answer-handoff-inbox is-<?php echo esc_attr( $state ); ?>" data-sp-handoff-inbox-strip="<?php echo esc_attr( $state ); ?>" data-count-state="<?php echo esc_attr( $count_state ); ?>" data-body-ready="<?php echo esc_attr( $ready_body ); ?>" data-body-unavailable="<?php echo esc_attr( $unavailable_body ); ?>">
			<span class="sp-answer-handoff-inbox__icon" aria-hidden="true"><?php echo Ui::icon( 'headset', 19, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
			<div class="sp-answer-handoff-inbox__body">
				<div class="sp-answer-handoff-inbox__title-row">
					<strong class="sp-answer-handoff-inbox__title"><?php echo esc_html( $new + $failed > 0 ? __( 'Waiting for a person', 'ai-site-assistant-autopilot' ) : __( 'Nobody is waiting', 'ai-site-assistant-autopilot' ) ); ?></strong>
					<?php if ( $new > 0 ) : ?>
						<span class="sp-answer-handoff-inbox__pill" data-sp-handoff-strip-new="<?php echo esc_attr( (string) $new ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of handoff requests nobody has picked up yet. */
								__( '%s new', 'ai-site-assistant-autopilot' ),
								number_format_i18n( $new )
							)
						);
						?>
						</span>
					<?php endif; ?>
					<?php if ( $failed > 0 ) : ?>
						<span class="sp-answer-handoff-inbox__pill is-failed" data-sp-handoff-strip-failed="<?php echo esc_attr( (string) $failed ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of handoff requests that no channel accepted. */
								__( '%s delivery failed', 'ai-site-assistant-autopilot' ),
								number_format_i18n( $failed )
							)
						);
						?>
						</span>
					<?php endif; ?>
				</div>
				<p class="sp-answer-handoff-inbox__text"><?php echo esc_html( $body ); ?></p>
			</div>
			<a class="sp-answer-handoff-inbox__cta" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HANDOFFS ) ); ?>" data-sp-handoff-inbox-link>
				<?php esc_html_e( 'Open inbox', 'ai-site-assistant-autopilot' ); ?>
				<?php echo Ui::icon( 'chevron', 14, 'currentColor', 2.3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?>
			</a>
		</div>
		<?php
	}

	/** @param array<string, mixed> $widget */
	private function render_email( array $widget, HandoffEmail $email ): void {
		$enabled   = ! empty( $widget['handoff_email_enabled'] );
		$recipient = $email->get_recipient();
		$custom    = sanitize_email( (string) ( $widget['handoff_email_to'] ?? '' ) );
		$summary   = '' !== $custom
			? $recipient
			: sprintf(
				/* translators: %s: WordPress administration email address. */
				__( 'Uses the WordPress administration email: %s', 'ai-site-assistant-autopilot' ),
				$recipient
			);

		$this->card_start(
			'email',
			__( 'Email', 'ai-site-assistant-autopilot' ),
			__( 'A copy of the conversation arrives in a support inbox.', 'ai-site-assistant-autopilot' ),
			$summary,
			'mail',
			$enabled,
			$email->is_configured()
		);
		?>
			<div class="sp-answer-handoff-fields">
				<label class="sp-answer-handoff-field">
					<span><?php esc_html_e( 'Send to', 'ai-site-assistant-autopilot' ); ?></span>
					<input class="sp-input sp-input--sm" type="email" dir="ltr" name="email_to" value="<?php echo esc_attr( $custom ); ?>" placeholder="support@example.com" />
					<small><?php esc_html_e( 'Leave empty to use the WordPress administration email.', 'ai-site-assistant-autopilot' ); ?></small>
				</label>
			</div>
		<?php
		$this->card_end( 'email', false );
	}

	/** @param array<string, mixed> $widget */
	private function render_telegram( array $widget, HandoffTelegram $telegram ): void {
		$enabled   = ! empty( $widget['handoff_telegram_enabled'] );
		$has_token = Settings::has_handoff_secret( 'handoff_telegram_token' );
		$chat      = (string) ( $widget['handoff_telegram_chat'] ?? '' );
		$summary   = $telegram->is_configured()
			? sprintf(
				/* translators: %s: configured Telegram chat identifier. */
				__( 'Credential stored securely; chat %s', 'ai-site-assistant-autopilot' ),
				$chat
			)
			: __( 'Not set up yet', 'ai-site-assistant-autopilot' );

		$this->card_start(
			'telegram',
			__( 'Telegram', 'ai-site-assistant-autopilot' ),
			__( 'Checks the configured bot and routes the handoff to one chat or group.', 'ai-site-assistant-autopilot' ),
			$summary,
			'send',
			$enabled,
			$telegram->is_configured()
		);
		?>
			<div class="sp-answer-handoff-fields">
				<label class="sp-answer-handoff-field">
					<span><?php esc_html_e( 'Bot token', 'ai-site-assistant-autopilot' ); ?> <em><?php esc_html_e( 'Stored encrypted', 'ai-site-assistant-autopilot' ); ?></em></span>
					<input class="sp-input sp-input--sm sp-mono" type="password" autocomplete="new-password" name="telegram_token" value="" placeholder="<?php echo esc_attr( $has_token ? __( 'Saved securely - enter a new token to replace it', 'ai-site-assistant-autopilot' ) : '123456789:ABC...' ); ?>" />
				<?php if ( $has_token ) : ?>
					<label class="sp-answer-handoff-clear"><input type="checkbox" name="clear_telegram_token" value="1" /> <?php esc_html_e( 'Remove saved token', 'ai-site-assistant-autopilot' ); ?></label>
				<?php endif; ?>
				</label>
				<label class="sp-answer-handoff-field">
					<span><?php esc_html_e( 'Chat ID', 'ai-site-assistant-autopilot' ); ?></span>
					<input class="sp-input sp-input--sm sp-mono" type="text" dir="ltr" name="telegram_chat" value="<?php echo esc_attr( $chat ); ?>" placeholder="-1001234567890" />
					<small><?php esc_html_e( 'Use the ID for the person, chat or group that should receive handoffs.', 'ai-site-assistant-autopilot' ); ?></small>
				</label>
			</div>
		<?php
		$this->card_end( 'telegram', $telegram->is_configured() );
	}

	/** @param array<string, mixed> $widget */
	private function render_twilio( array $widget, HandoffTwilio $twilio, bool $show ): void {
		$enabled   = ! empty( $widget['handoff_twilio_enabled'] );
		$has_sid   = Settings::has_handoff_secret( 'handoff_twilio_sid' );
		$has_token = Settings::has_handoff_secret( 'handoff_twilio_token' );
		$to        = (string) ( $widget['handoff_twilio_to'] ?? '' );
		$summary   = $twilio->is_configured()
			? sprintf(
				/* translators: %s: configured recipient phone number. */
				__( 'Credentials stored securely; sends to %s', 'ai-site-assistant-autopilot' ),
				$to
			)
			: __( 'Not set up yet', 'ai-site-assistant-autopilot' );
		?>
		<article class="sp-answer-handoff-card<?php echo esc_attr( $this->card_class( $enabled, $twilio->is_configured() ) ); ?>" data-sp-handoff-card data-sp-handoff-channel="twilio" data-sp-handoff-enabled="<?php echo $enabled ? '1' : '0'; ?>" data-sp-handoff-configured="<?php echo $twilio->is_configured() ? '1' : '0'; ?>"<?php echo $show ? '' : ' hidden'; ?>>
			<div class="sp-answer-handoff-card__head">
				<span class="sp-answer-handoff-card__icon" aria-hidden="true"><?php echo Ui::icon( 'phone', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-answer-handoff-card__body">
					<div class="sp-answer-handoff-card__title"><strong><?php esc_html_e( 'SMS (Twilio)', 'ai-site-assistant-autopilot' ); ?></strong><span class="sp-answer-handoff-state<?php echo esc_attr( self::state_class( $enabled, $twilio->is_configured() ) ); ?>" data-sp-handoff-state><?php echo esc_html( self::state_label( $enabled, $twilio->is_configured() ) ); ?></span></div>
					<p><?php esc_html_e( 'Sends a text message to the configured support number.', 'ai-site-assistant-autopilot' ); ?></p>
					<span class="sp-answer-handoff-card__summary" data-sp-handoff-summary><?php echo esc_html( $summary ); ?></span>
				</div>
				<div class="sp-answer-handoff-card__actions">
					<button class="sp-answer-widget-switch<?php echo $enabled ? ' is-on' : ''; ?>" type="button" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>" data-sp-handoff-toggle aria-label="<?php esc_attr_e( 'Toggle SMS handoff', 'ai-site-assistant-autopilot' ); ?>"><span aria-hidden="true"></span></button>
					<button class="sp-btn sp-btn--ghost sp-btn--r8 sp-answer-handoff-edit" type="button" data-sp-handoff-edit><?php esc_html_e( 'Edit', 'ai-site-assistant-autopilot' ); ?></button>
				</div>
			</div>
			<form class="sp-answer-handoff-card__form" data-sp-handoff-form hidden>
				<div class="sp-answer-handoff-fields">
					<label class="sp-answer-handoff-field">
						<span><?php esc_html_e( 'Account SID', 'ai-site-assistant-autopilot' ); ?> <em><?php esc_html_e( 'Stored encrypted', 'ai-site-assistant-autopilot' ); ?></em></span>
						<input class="sp-input sp-input--sm sp-mono" type="text" autocomplete="off" name="twilio_sid" value="" placeholder="<?php echo esc_attr( $has_sid ? __( 'Saved securely - enter a new SID to replace it', 'ai-site-assistant-autopilot' ) : 'AC...' ); ?>" />
					<?php if ( $has_sid ) : ?>
						<label class="sp-answer-handoff-clear"><input type="checkbox" name="clear_twilio_sid" value="1" /> <?php esc_html_e( 'Remove saved SID', 'ai-site-assistant-autopilot' ); ?></label>
					<?php endif; ?>
					</label>
					<label class="sp-answer-handoff-field">
						<span><?php esc_html_e( 'Auth token', 'ai-site-assistant-autopilot' ); ?> <em><?php esc_html_e( 'Stored encrypted', 'ai-site-assistant-autopilot' ); ?></em></span>
						<input class="sp-input sp-input--sm sp-mono" type="password" autocomplete="new-password" name="twilio_token" value="" placeholder="<?php echo esc_attr( $has_token ? __( 'Saved securely - enter a new token to replace it', 'ai-site-assistant-autopilot' ) : 'Token...' ); ?>" />
					<?php if ( $has_token ) : ?>
						<label class="sp-answer-handoff-clear"><input type="checkbox" name="clear_twilio_token" value="1" /> <?php esc_html_e( 'Remove saved token', 'ai-site-assistant-autopilot' ); ?></label>
					<?php endif; ?>
					</label>
					<label class="sp-answer-handoff-field">
						<span><?php esc_html_e( 'From number', 'ai-site-assistant-autopilot' ); ?></span>
						<input class="sp-input sp-input--sm sp-mono" type="text" dir="ltr" name="twilio_from" value="<?php echo esc_attr( (string) ( $widget['handoff_twilio_from'] ?? '' ) ); ?>" placeholder="+1 555 000 0000" />
					</label>
					<label class="sp-answer-handoff-field">
						<span><?php esc_html_e( 'Send to', 'ai-site-assistant-autopilot' ); ?></span>
						<input class="sp-input sp-input--sm sp-mono" type="text" dir="ltr" name="twilio_to" value="<?php echo esc_attr( $to ); ?>" placeholder="+1 555 019 4471" />
					</label>
				</div>
				<?php $this->card_actions( 'twilio', $twilio->is_configured() ); ?>
			</form>
		</article>
		<?php
	}

	private function render_whatsapp_locked(): void {
		?>
		<article class="sp-answer-handoff-card sp-answer-handoff-card--locked" data-sp-handoff-unsupported="whatsapp">
			<div class="sp-answer-handoff-card__head">
				<span class="sp-answer-handoff-card__icon" aria-hidden="true"><?php echo Ui::icon( 'phone', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-answer-handoff-card__body">
					<div class="sp-answer-handoff-card__title"><strong><?php esc_html_e( 'WhatsApp', 'ai-site-assistant-autopilot' ); ?></strong><span class="sp-answer-handoff-state is-off"><?php esc_html_e( 'Not available', 'ai-site-assistant-autopilot' ); ?></span></div>
					<p><?php esc_html_e( 'Reply from a WhatsApp Business number.', 'ai-site-assistant-autopilot' ); ?></p>
					<span class="sp-answer-handoff-card__summary"><?php esc_html_e( 'This build has no WhatsApp delivery transport yet.', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
				<span class="sp-answer-handoff-lock"><?php echo Ui::icon( 'lock', 15, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><?php esc_html_e( 'Planned', 'ai-site-assistant-autopilot' ); ?></span>
			</div>
		</article>
		<?php
	}

	private function render_add_channels( bool $show_twilio ): void {
		?>
		<section class="sp-answer-handoff-add" aria-labelledby="sp-answer-handoff-add-title">
			<h2 id="sp-answer-handoff-add-title"><?php esc_html_e( 'Add another channel', 'ai-site-assistant-autopilot' ); ?></h2>
			<div class="sp-answer-handoff-add__choices">
				<button type="button" data-sp-handoff-add="twilio"<?php echo $show_twilio ? ' hidden' : ''; ?>><?php echo Ui::icon( 'phone', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span><?php esc_html_e( 'SMS (Twilio)', 'ai-site-assistant-autopilot' ); ?></span><?php echo Ui::icon( 'plus', 12, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></button>
				<button type="button" disabled data-sp-handoff-unsupported="slack"><?php echo Ui::icon( 'chat', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span><?php esc_html_e( 'Slack', 'ai-site-assistant-autopilot' ); ?></span><?php echo Ui::icon( 'lock', 12, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></button>
				<button type="button" disabled data-sp-handoff-unsupported="discord"><?php echo Ui::icon( 'chat', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span><?php esc_html_e( 'Discord', 'ai-site-assistant-autopilot' ); ?></span><?php echo Ui::icon( 'lock', 12, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></button>
				<button type="button" disabled data-sp-handoff-unsupported="webhook"><?php echo Ui::icon( 'plug', 15, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><span><?php esc_html_e( 'Custom webhook', 'ai-site-assistant-autopilot' ); ?></span><?php echo Ui::icon( 'lock', 12, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></button>
			</div>
			<p><?php esc_html_e( 'This build supports Email, Telegram and SMS through Twilio. Unsupported channels stay locked instead of collecting credentials that cannot be delivered.', 'ai-site-assistant-autopilot' ); ?></p>
		</section>
		<?php
	}

	/**
	 * The contact details the widget's handoff card asks a visitor for. Each detail is off, optional or
	 * required, saved through `handoff/visitor-fields`; nothing is asked while every detail is off.
	 */
	private function render_visitor_fields(): void {
		$fields  = Settings::handoff_fields();
		$details = array(
			'name'    => array( __( 'Name', 'ai-site-assistant-autopilot' ), __( 'So your team knows who they are talking to.', 'ai-site-assistant-autopilot' ) ),
			'email'   => array( __( 'Email address', 'ai-site-assistant-autopilot' ), __( 'Lets your team reply by email from the inbox.', 'ai-site-assistant-autopilot' ) ),
			'phone'   => array( __( 'Mobile number', 'ai-site-assistant-autopilot' ), __( 'Lets your team call or text the visitor back.', 'ai-site-assistant-autopilot' ) ),
			'website' => array( __( 'Website address', 'ai-site-assistant-autopilot' ), __( 'Useful when visitors ask about their own site or business.', 'ai-site-assistant-autopilot' ) ),
		);
		$modes   = array(
			'off'      => __( 'Off', 'ai-site-assistant-autopilot' ),
			'optional' => __( 'Optional', 'ai-site-assistant-autopilot' ),
			'required' => __( 'Required', 'ai-site-assistant-autopilot' ),
		);
		?>
		<section class="sp-answer-handoff-details" aria-labelledby="sp-answer-handoff-details-title" data-sp-handoff-details>
			<div class="sp-answer-handoff-details__head">
				<h2 id="sp-answer-handoff-details-title"><?php esc_html_e( 'Details to ask for', 'ai-site-assistant-autopilot' ); ?></h2>
				<p><?php esc_html_e( 'Before a visitor sends the conversation, the widget asks for the details you pick here. A required detail has to be filled in. When every detail is off, the visitor only sends the conversation.', 'ai-site-assistant-autopilot' ); ?></p>
			</div>
			<form class="sp-answer-handoff-details__form" data-sp-handoff-details-form novalidate>
				<?php foreach ( $details as $key => $detail ) : ?>
					<div class="sp-answer-handoff-detail" role="radiogroup" aria-labelledby="sp-handoff-detail-<?php echo esc_attr( $key ); ?>" aria-describedby="sp-handoff-detail-<?php echo esc_attr( $key ); ?>-hint" data-sp-handoff-detail="<?php echo esc_attr( $key ); ?>">
						<div class="sp-answer-handoff-detail__text">
							<strong id="sp-handoff-detail-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $detail[0] ); ?></strong>
							<span id="sp-handoff-detail-<?php echo esc_attr( $key ); ?>-hint"><?php echo esc_html( $detail[1] ); ?></span>
						</div>
						<div class="sp-answer-handoff-seg">
							<?php foreach ( $modes as $mode => $mode_label ) : ?>
								<label class="sp-answer-handoff-seg__option">
									<input type="radio" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $mode ); ?>"<?php checked( $fields[ $key ], $mode ); ?> />
									<span><?php echo esc_html( $mode_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="sp-answer-handoff-details__actions">
					<button class="sp-btn sp-btn--r8" type="submit" data-sp-handoff-details-save><?php esc_html_e( 'Save details', 'ai-site-assistant-autopilot' ); ?></button>
					<p class="sp-answer-handoff-form__status" data-sp-handoff-details-status role="status" aria-live="polite"></p>
				</div>
			</form>
		</section>
		<?php
	}

	private function card_start( string $id, string $title, string $description, string $summary, string $icon, bool $enabled, bool $configured ): void {
		$toggle_label = sprintf(
			/* translators: %s: human handoff channel name. */
			__( 'Toggle %s handoff', 'ai-site-assistant-autopilot' ),
			$title
		);
		?>
		<article class="sp-answer-handoff-card<?php echo esc_attr( $this->card_class( $enabled, $configured ) ); ?>" data-sp-handoff-card data-sp-handoff-channel="<?php echo esc_attr( $id ); ?>" data-sp-handoff-enabled="<?php echo $enabled ? '1' : '0'; ?>" data-sp-handoff-configured="<?php echo $configured ? '1' : '0'; ?>">
			<div class="sp-answer-handoff-card__head">
				<span class="sp-answer-handoff-card__icon" aria-hidden="true"><?php echo Ui::icon( $icon, 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
				<div class="sp-answer-handoff-card__body">
					<div class="sp-answer-handoff-card__title"><strong><?php echo esc_html( $title ); ?></strong><span class="sp-answer-handoff-state<?php echo esc_attr( self::state_class( $enabled, $configured ) ); ?>" data-sp-handoff-state><?php echo esc_html( self::state_label( $enabled, $configured ) ); ?></span></div>
					<p><?php echo esc_html( $description ); ?></p>
					<span class="sp-answer-handoff-card__summary" data-sp-handoff-summary><?php echo esc_html( $summary ); ?></span>
				</div>
				<div class="sp-answer-handoff-card__actions">
					<button class="sp-answer-widget-switch<?php echo $enabled ? ' is-on' : ''; ?>" type="button" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>" data-sp-handoff-toggle aria-label="<?php echo esc_attr( $toggle_label ); ?>"><span aria-hidden="true"></span></button>
					<button class="sp-btn sp-btn--ghost sp-btn--r8 sp-answer-handoff-edit" type="button" data-sp-handoff-edit><?php echo esc_html( $enabled || $configured ? __( 'Edit', 'ai-site-assistant-autopilot' ) : __( 'Connect', 'ai-site-assistant-autopilot' ) ); ?></button>
				</div>
			</div>
			<form class="sp-answer-handoff-card__form" data-sp-handoff-form hidden>
		<?php
	}

	private function card_end( string $channel, bool $can_test ): void {
		?>
			<?php $this->card_actions( $channel, $can_test ); ?>
			</form>
		</article>
		<?php
	}

	private function card_actions( string $channel, bool $can_test ): void {
		$test_url = 'telegram' === $channel
			? rest_url( RestRoutes::NAMESPACE . '/widget/test/telegram' )
			: ( 'twilio' === $channel ? rest_url( RestRoutes::NAMESPACE . '/widget/test/twilio' ) : '' );
		?>
		<div class="sp-answer-handoff-form__actions">
			<button class="sp-btn sp-btn--r8" type="submit" data-sp-handoff-save><?php esc_html_e( 'Save configuration', 'ai-site-assistant-autopilot' ); ?></button>
			<button class="sp-btn sp-btn--ghost sp-btn--r8" type="button" data-sp-handoff-cancel><?php esc_html_e( 'Cancel', 'ai-site-assistant-autopilot' ); ?></button>
		<?php if ( '' !== $test_url ) : ?>
			<button class="sp-answer-handoff-test" type="button" data-sp-handoff-test data-sp-handoff-test-url="<?php echo esc_url( $test_url ); ?>"<?php disabled( ! $can_test ); ?>><?php echo Ui::icon( 'send', 14, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?><?php esc_html_e( 'Check connection', 'ai-site-assistant-autopilot' ); ?></button>
		<?php endif; ?>
		</div>
		<p class="sp-answer-handoff-form__note"><?php esc_html_e( 'A connection check contacts the provider but does not send a visitor handoff.', 'ai-site-assistant-autopilot' ); ?></p>
		<p class="sp-answer-handoff-form__status" data-sp-handoff-status role="status" aria-live="polite"></p>
		<?php
	}

	private function card_class( bool $enabled, bool $configured ): string {
		return ' ' . ltrim( self::state_class( $enabled, $configured ), ' ' );
	}

	private static function state_class( bool $enabled, bool $configured ): string {
		if ( $enabled && $configured ) {
			return ' is-ready';
		}
		if ( $enabled ) {
			return ' is-needs-setup';
		}
		return $configured ? ' is-paused' : ' is-off';
	}

	private static function state_label( bool $enabled, bool $configured ): string {
		if ( $enabled && $configured ) {
			return __( 'Ready', 'ai-site-assistant-autopilot' );
		}
		if ( $enabled ) {
			return __( 'Needs setup', 'ai-site-assistant-autopilot' );
		}
		return $configured ? __( 'Paused', 'ai-site-assistant-autopilot' ) : __( 'Not configured', 'ai-site-assistant-autopilot' );
	}
}
