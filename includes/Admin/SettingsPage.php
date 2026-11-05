<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Forms\Turnstile;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Support\Retention;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * Setup & Settings — one screen.
 *
 * This used to be two pages: a four-step first-run wizard and a settings form
 * that repeated most of it. They are merged here because every wizard step was
 * just a subset of these fields, and the split meant an administrator changing a
 * provider had to guess which page owned the change. What survives from the
 * wizard is the progress strip, now derived from real state rather than a stored
 * cursor, so it stays honest if something is undone later.
 */
final class SettingsPage {

	/** Set once a connection test has actually succeeded. */
	public const VERIFIED_OPTION = 'aisaautopilot_provider_verified';

	/** A successful, not-yet-saved provider test is bound to its administrator. */
	private const PROVIDER_TEST_TRANSIENT_PREFIX = 'aisaautopilot_provider_test_';

	/** A pending test is only useful while the setup form is still being completed. */
	private const PROVIDER_TEST_TTL = 15 * MINUTE_IN_SECONDS;

	/** Save feedback reserved for the visible, server-validated setup flow. */
	private string $save_error = '';

	/** The six services the Setup mockup lists as popular with WordPress sites. */
	private const POPULAR_PROVIDERS = array( 'openai', 'anthropic', 'gemini', 'xai', 'deepseek', 'openrouter', 'groq' );

	/** Documented key prefixes, used only to explain a rejected key. */
	private const KEY_PREFIXES = array(
		'openai'     => 'sk-',
		'anthropic'  => 'sk-ant-',
		'gemini'     => 'AIza',
		'deepseek'   => 'sk-',
		'openrouter' => 'sk-or-',
		'groq'       => 'gsk_',
		'qwen'       => 'sk-',
		'xai'        => 'xai-',
	);

	/**
	 * Remember a successful test until the same administrator saves exactly the
	 * configuration that was tested. The raw API key is never stored in the
	 * transient: it is part of an HMAC only.
	 *
	 * A failed test deliberately leaves an existing saved verification intact.
	 * An administrator may test a new, unsaved key without invalidating the
	 * provider that is currently powering the site.
	 *
	 * @param array<string, mixed> $config Tested provider configuration.
	 */
	public static function record_provider_test( array $config, bool $successful ): void {
		$key = self::provider_test_transient_key();
		if ( '' === $key ) {
			return;
		}

		if ( ! $successful ) {
			delete_transient( $key );
			return;
		}

		$signature = self::provider_test_signature( $config );
		if ( '' === $signature ) {
			delete_transient( $key );
			return;
		}

		set_transient( $key, $signature, self::PROVIDER_TEST_TTL );
	}

	/**
	 * Promote a recent test only when its exact endpoint, model and credential
	 * have just been saved. The one-time record prevents a stale browser tab from
	 * later certifying a changed configuration.
	 *
	 * @param array<string, mixed> $config Saved provider configuration.
	 */
	public static function consume_matching_provider_test( array $config ): bool {
		if ( ! self::has_matching_provider_test( $config ) ) {
			return false;
		}

		$key = self::provider_test_transient_key();
		delete_transient( $key );
		return true;
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private static function has_matching_provider_test( array $config ): bool {
		$key       = self::provider_test_transient_key();
		$signature = self::provider_test_signature( $config );
		if ( '' === $key || '' === $signature ) {
			return false;
		}

		$tested = get_transient( $key );
		if ( ! is_string( $tested ) ) {
			return false;
		}
		if ( ! hash_equals( $tested, $signature ) ) {
			delete_transient( $key );
			return false;
		}

		return true;
	}

	/**
	 * @return array{provider:string,base_url:string,model:string,api_key:string}
	 */
	private static function saved_provider_test_config(): array {
		return array(
			'provider' => (string) Settings::get( 'provider', '' ),
			'base_url' => (string) Settings::get( 'base_url', '' ),
			'model'    => (string) Settings::get( 'model', '' ),
			'api_key'  => WpConnectors::get_effective_api_key(),
		);
	}

	private static function provider_test_transient_key(): string {
		$user_id = get_current_user_id();
		return $user_id > 0 ? self::PROVIDER_TEST_TRANSIENT_PREFIX . $user_id : '';
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private static function provider_test_signature( array $config ): string {
		$provider = sanitize_key( (string) ( $config['provider'] ?? '' ) );
		$base_url = esc_url_raw( (string) ( $config['base_url'] ?? '' ) );
		$model    = sanitize_text_field( (string) ( $config['model'] ?? '' ) );
		$api_key  = trim( (string) ( $config['api_key'] ?? '' ) );

		if ( '' === $provider || '' === $base_url || '' === $model || '' === $api_key ) {
			return '';
		}

		$payload = wp_json_encode(
			array(
				'provider' => $provider,
				'base_url' => $base_url,
				'model'    => $model,
				'api_key'  => $api_key,
			),
			JSON_UNESCAPED_SLASHES
		);

		return is_string( $payload ) ? hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ) : '';
	}

	/**
	 * The visible Setup route is the mockup's guided four-step flow. The hidden
	 * legacy Settings alias deliberately retains the detailed configuration
	 * surface so existing controls and bookmarked URLs do not disappear.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		if ( $this->is_advanced_route() ) {
			$this->render_advanced();
			return;
		}

		$this->render_wizard();
	}

	private function is_advanced_route(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing: picks which page to render and writes nothing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return AdminMenu::SLUG_SETTINGS === $page;
	}

	private function render_advanced(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved            = $this->maybe_save();
		$s                = Settings::all();
		$kb               = new KnowledgeBase();
		$embedded         = $kb->table_exists() ? $kb->count_with_embeddings() : 0;
		$kb_total         = $kb->table_exists() ? (int) ( $kb->get_stats()['total'] ?? 0 ) : 0;
		$has_emb_key      = Settings::has_embedding_key();
		$presets          = Settings::presets();
		$provider         = isset( $presets[ $s['provider'] ] ) ? (string) $s['provider'] : 'custom';
		$preset           = Settings::preset( $provider );
		$embeddings       = (array) ( $preset['embeddings'] ?? array() );
		$efb_health       = ( new EasyFormBuilderConnector() )->get_health();
		$efb_ready        = ! empty( $efb_health['available'] );
		$configured       = ( new ProviderManager() )->is_configured();
		$endpoint         = (string) wp_parse_url( (string) $s['base_url'], PHP_URL_HOST );
		$emb_on           = ! empty( $s['embeddings_enabled'] );
		$no_embeds        = array() === $embeddings && '' === trim( (string) ( $s['embedding_base_url'] ?? '' ) );
		$setup_url        = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$kb_url           = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$sections         = array(
			'sp-adv-connection' => __( 'Connection', 'ai-site-assistant-autopilot' ),
			'sp-adv-search'     => __( 'Semantic search', 'ai-site-assistant-autopilot' ),
			'sp-adv-voice'      => __( 'Voice', 'ai-site-assistant-autopilot' ),
			'sp-adv-forms'      => __( 'Forms and delivery', 'ai-site-assistant-autopilot' ),
			'sp-adv-public'     => __( 'Public site assistant', 'ai-site-assistant-autopilot' ),
			'sp-adv-retention'  => __( 'Data retention', 'ai-site-assistant-autopilot' ),
		);
		$retention        = Retention::periods();
		$retention_fields = array(
			'conversations_days' => array(
				'label' => __( 'Recorded visitor conversations', 'ai-site-assistant-autopilot' ),
				'help'  => __( 'Counted from the last message. Conversations are only recorded when recording is on in Answer Desk.', 'ai-site-assistant-autopilot' ),
			),
			'handoffs_days'      => array(
				'label' => __( 'Requests to talk to a person', 'ai-site-assistant-autopilot' ),
				'help'  => __( 'Counted from the last change, with the contact details the visitor left.', 'ai-site-assistant-autopilot' ),
			),
			'questions_days'     => array(
				'label' => __( 'Unanswered questions', 'ai-site-assistant-autopilot' ),
				'help'  => __( 'Counted from the last time someone asked. Cached answers older than this go too.', 'ai-site-assistant-autopilot' ),
			),
			'undelivered_days'   => array(
				'label' => __( 'Undelivered form submissions', 'ai-site-assistant-autopilot' ),
				'help'  => __( 'Counted from when the visitor sent the form. Kept only when "Keep a submission when its email cannot be sent" is on under Forms and delivery.', 'ai-site-assistant-autopilot' ),
			),
			'carts_days'         => array(
				'label' => __( 'Abandoned carts', 'ai-site-assistant-autopilot' ),
				'help'  => __( 'Counted from the last change to the cart, with the email the shopper typed at checkout. Kept only when Abandoned cart reminders is on in Store Copilot.', 'ai-site-assistant-autopilot' ),
			),
		);

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-settings data-sp-advanced-settings>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Advanced settings', 'ai-site-assistant-autopilot' ),
					__( 'Search, voice, forms and the public assistant. The AI service itself is set up in Setup & Connection.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Back to Setup', 'ai-site-assistant-autopilot' ),
							'url'   => $setup_url,
						),
					)
				);
				?>
				<div class="sp-pagebody">

				<?php if ( $saved ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<div class="sp-adv">
					<nav class="sp-adv__nav" aria-labelledby="sp-adv-nav-title">
						<span class="sp-adv__nav-title" id="sp-adv-nav-title"><?php esc_html_e( 'On this page', 'ai-site-assistant-autopilot' ); ?></span>
						<?php foreach ( $sections as $section_id => $section_label ) : ?>
							<a href="#<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $section_label ); ?></a>
						<?php endforeach; ?>
					</nav>

					<form method="post" class="sp-adv__main" data-sp-advanced-form>
						<?php wp_nonce_field( 'aisaautopilot_settings_save', 'aisaautopilot_settings_nonce' ); ?>
						<input type="hidden" name="advanced_settings" value="1" />

						<section class="sp-wz-card" id="sp-adv-connection" aria-labelledby="sp-adv-connection-title">
							<div class="sp-wz-card__head sp-adv-connection__head">
								<h2 class="sp-wz-card__title" id="sp-adv-connection-title"><?php esc_html_e( 'Connection', 'ai-site-assistant-autopilot' ); ?></h2>
								<span class="sp-adv-readonly" data-sp-adv-readonly><?php esc_html_e( 'Read-only', 'ai-site-assistant-autopilot' ); ?></span>
								<a class="sp-adv-button" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Open the setup wizard', 'ai-site-assistant-autopilot' ); ?></a>
							</div>
							<p class="sp-adv-lead"><?php esc_html_e( 'Your service, model and key are set in the setup wizard, where the connection is checked before it can be saved. They cannot be edited here.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php if ( ! $configured ) : ?>
								<p class="sp-adv-notice"><?php esc_html_e( 'No AI service is connected yet. Search, voice and form drafting need one, so connect it in Setup first.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php endif; ?>
							<dl class="sp-adv-facts">
								<div>
									<dt><?php esc_html_e( 'Service', 'ai-site-assistant-autopilot' ); ?></dt>
									<dd><?php echo esc_html( (string) ( $preset['label'] ?? $provider ) ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Model', 'ai-site-assistant-autopilot' ); ?></dt>
									<dd dir="ltr"><?php echo esc_html( '' !== (string) $s['model'] ? (string) $s['model'] : '—' ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Endpoint', 'ai-site-assistant-autopilot' ); ?></dt>
									<dd dir="ltr"><?php echo esc_html( '' !== $endpoint ? $endpoint : '—' ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Key stored in', 'ai-site-assistant-autopilot' ); ?></dt>
									<dd><?php echo esc_html( WpConnectors::get_key_source_label() ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Temperature', 'ai-site-assistant-autopilot' ); ?></dt>
									<dd dir="ltr"><?php echo esc_html( number_format_i18n( (float) $s['temperature'], 1 ) ); ?></dd>
								</div>
							</dl>
						</section>

						<section class="sp-wz-card" id="sp-adv-search" aria-labelledby="sp-adv-search-title">
							<div class="sp-wz-switch-row sp-adv-switch-head">
								<input type="hidden" name="embeddings_enabled" value="0" />
								<input type="checkbox" class="sp-wz-switch" id="sp-adv-embeddings" role="switch" name="embeddings_enabled" value="1" aria-describedby="sp-adv-search-text" data-sp-adv-toggle="search" <?php checked( $emb_on ); ?> />
								<div class="sp-wz-switch-row__body">
									<h2 class="sp-wz-card__title" id="sp-adv-search-title"><label for="sp-adv-embeddings"><?php esc_html_e( 'Semantic search', 'ai-site-assistant-autopilot' ); ?></label></h2>
									<p class="sp-wz-switch-row__note" id="sp-adv-search-text"><?php esc_html_e( 'Finds answers by meaning, so a question in one language can match a page written in another. It needs a service with an embeddings endpoint.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>
							<?php if ( $no_embeds ) : ?>
								<p class="sp-adv-notice" data-sp-adv-no-embeddings>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: AI service name, such as DeepSeek. */
										__( '%s has no embeddings endpoint. Add a separate endpoint and key below (for example OpenAI), or keep this off to match on keywords.', 'ai-site-assistant-autopilot' ),
										(string) ( $preset['label'] ?? $provider )
									)
								);
								?>
								</p>
							<?php endif; ?>
							<div class="sp-wz-card__grid<?php echo $emb_on ? '' : ' is-muted'; ?>" data-sp-adv-fields="search">
								<label class="sp-wz-field">
									<span class="sp-wz-field__label"><?php esc_html_e( 'Embedding model', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="text" id="sp-embedding-model" name="embedding_model" class="sp-input sp-input--sm sp-mono" dir="ltr" maxlength="100" value="<?php echo esc_attr( (string) ( $s['embedding_model'] ?? '' ) ); ?>" placeholder="text-embedding-3-small" list="sp-embedding-models" />
									<datalist id="sp-embedding-models">
										<?php foreach ( $embeddings as $embedding_id => $embedding_label ) : ?>
											<option value="<?php echo esc_attr( (string) $embedding_id ); ?>"><?php echo esc_html( (string) $embedding_label ); ?></option>
										<?php endforeach; ?>
									</datalist>
								</label>
								<label class="sp-wz-field">
									<span class="sp-wz-field__label"><?php esc_html_e( 'Endpoint (optional)', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="url" name="embedding_base_url" class="sp-input sp-input--sm sp-mono" dir="ltr" value="<?php echo esc_attr( (string) ( $s['embedding_base_url'] ?? '' ) ); ?>" placeholder="https://api.openai.com/v1" />
									<span class="sp-wz-field__help"><?php esc_html_e( 'Leave blank to use the same endpoint as your AI service.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-wz-field">
									<label class="sp-wz-field__label" for="sp-adv-embedding-key"><?php esc_html_e( 'Key (optional)', 'ai-site-assistant-autopilot' ); ?></label>
									<input type="password" id="sp-adv-embedding-key" name="embedding_api_key" class="sp-input sp-input--sm" autocomplete="new-password" dir="ltr" placeholder="<?php echo $has_emb_key ? esc_attr__( 'Saved securely — enter a new key to replace it', 'ai-site-assistant-autopilot' ) : esc_attr__( 'Leave blank to reuse your key', 'ai-site-assistant-autopilot' ); ?>" />
									<?php if ( $has_emb_key ) : ?>
										<label class="sp-adv-check">
											<input type="checkbox" name="clear_embedding_key" value="1" />
											<span><?php esc_html_e( 'Remove the saved embedding key', 'ai-site-assistant-autopilot' ); ?></span>
										</label>
									<?php endif; ?>
								</div>
							</div>
							<p class="sp-wz-card__note">
								<?php
								printf(
									/* translators: 1: indexed items that have vectors, 2: all indexed items. */
									esc_html( _n( '%1$s of %2$s indexed item has vectors.', '%1$s of %2$s indexed items have vectors.', $kb_total, 'ai-site-assistant-autopilot' ) ),
									esc_html( number_format_i18n( $embedded ) ),
									esc_html( number_format_i18n( $kb_total ) )
								);
								?>
								<?php esc_html_e( 'Re-index after turning this on to build the rest.', 'ai-site-assistant-autopilot' ); ?>
								<a href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Open Site Knowledge', 'ai-site-assistant-autopilot' ); ?></a>
							</p>
						</section>

						<section class="sp-wz-card" id="sp-adv-voice" aria-labelledby="sp-adv-voice-title">
							<h2 class="sp-wz-card__title" id="sp-adv-voice-title"><?php esc_html_e( 'Voice', 'ai-site-assistant-autopilot' ); ?></h2>
							<div class="sp-wz-card__grid">
								<label class="sp-wz-field">
									<span class="sp-wz-field__label"><?php esc_html_e( 'Speech-to-text model', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="text" name="voice_model" class="sp-input sp-input--sm sp-mono" dir="ltr" maxlength="100" value="<?php echo esc_attr( (string) $s['voice_model'] ); ?>" placeholder="whisper-1" />
									<span class="sp-wz-field__help"><?php esc_html_e( 'Audio is sent to your AI service only to transcribe it. AutoPilot does not store it.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-wz-switch-row">
									<input type="hidden" name="voice_responses_enabled" value="0" />
									<input type="checkbox" class="sp-wz-switch" id="sp-adv-voice-aloud" role="switch" name="voice_responses_enabled" value="1" aria-describedby="sp-adv-voice-aloud-note" <?php checked( ! empty( $s['voice_responses_enabled'] ) ); ?> />
									<div class="sp-wz-switch-row__body">
										<label class="sp-wz-switch-row__title" for="sp-adv-voice-aloud"><?php esc_html_e( 'Read replies aloud', 'ai-site-assistant-autopilot' ); ?></label>
										<p class="sp-wz-switch-row__note" id="sp-adv-voice-aloud-note"><?php esc_html_e( 'Uses the browser’s own voice in the site assistant and the Easy Form Builder copilot, where the browser supports it.', 'ai-site-assistant-autopilot' ); ?></p>
									</div>
								</div>
							</div>
						</section>

						<section class="sp-wz-card" id="sp-adv-forms" aria-labelledby="sp-adv-forms-title">
							<h2 class="sp-wz-card__title" id="sp-adv-forms-title"><?php esc_html_e( 'Forms and delivery', 'ai-site-assistant-autopilot' ); ?></h2>
							<div class="sp-adv-connectors">
								<div class="sp-adv-connector is-info">
									<div class="sp-adv-connector__head">
										<strong><?php esc_html_e( 'AutoPilot Forms', 'ai-site-assistant-autopilot' ); ?></strong>
										<span><?php esc_html_e( 'Active', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<p><?php esc_html_e( 'Built in. Simple forms whose submissions are emailed to you.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
								<div class="sp-adv-connector <?php echo $efb_ready ? 'is-ok' : 'is-warn'; ?>" data-sp-adv-efb="<?php echo $efb_ready ? 'active' : 'inactive'; ?>">
									<div class="sp-adv-connector__head">
										<strong>Easy Form Builder</strong>
										<span><?php echo esc_html( $efb_ready ? __( 'Active', 'ai-site-assistant-autopilot' ) : __( 'Not active', 'ai-site-assistant-autopilot' ) ); ?></span>
									</div>
									<p>
									<?php
									echo esc_html(
										$efb_ready
											? sprintf(
												/* translators: %s: Easy Form Builder version. */
												__( 'Version %s. Its forms open in its own editor.', 'ai-site-assistant-autopilot' ),
												(string) ( $efb_health['version'] ?? '' )
											)
											: __( 'Install or activate Easy Form Builder 4.1 or newer for advanced fields, logic and multi-step forms.', 'ai-site-assistant-autopilot' )
									);
									?>
									</p>
								</div>
							</div>
							<div class="sp-wz-switch-row">
								<input type="hidden" name="prefer_efb" value="0" />
								<input type="checkbox" class="sp-wz-switch" id="sp-adv-prefer-efb" role="switch" name="prefer_efb" value="1" aria-describedby="sp-adv-prefer-efb-note" <?php checked( ! empty( $s['prefer_efb'] ) ); ?> />
								<div class="sp-wz-switch-row__body">
									<label class="sp-wz-switch-row__title" for="sp-adv-prefer-efb"><?php esc_html_e( 'Prefer Easy Form Builder when it is available', 'ai-site-assistant-autopilot' ); ?></label>
									<p class="sp-wz-switch-row__note" id="sp-adv-prefer-efb-note"><?php esc_html_e( 'New forms are created in Easy Form Builder while it is active. Simple native forms stay available.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>
							<div class="sp-wz-card__grid">
								<label class="sp-wz-field">
									<span class="sp-wz-field__label"><?php esc_html_e( 'New forms start as', 'ai-site-assistant-autopilot' ); ?></span>
									<select name="default_form_status" class="sp-select">
										<option value="draft" <?php selected( (string) $s['default_form_status'], 'draft' ); ?>><?php esc_html_e( 'Draft (recommended)', 'ai-site-assistant-autopilot' ); ?></option>
										<option value="publish" <?php selected( (string) $s['default_form_status'], 'publish' ); ?>><?php esc_html_e( 'Publish immediately', 'ai-site-assistant-autopilot' ); ?></option>
									</select>
									<span class="sp-wz-field__help"><?php esc_html_e( 'Publishing is a higher-risk action and always asks again.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<label class="sp-wz-field">
									<span class="sp-wz-field__label"><?php esc_html_e( 'Send submissions to', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="email" id="sp-form-notification-email" name="form_notification_email" class="sp-input sp-input--sm" dir="ltr" value="<?php echo esc_attr( Settings::form_notification_email() ); ?>" />
									<span class="sp-wz-field__help"><?php esc_html_e( 'Submissions are delivered only to this address. Leave blank to use the site administration email.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
							</div>
							<div class="sp-wz-switch-row">
								<input type="hidden" name="forms_keep_undelivered" value="0" />
								<input type="checkbox" class="sp-wz-switch" id="sp-adv-keep-undelivered" role="switch" name="forms_keep_undelivered" value="1" aria-describedby="sp-adv-keep-undelivered-note" <?php checked( ! empty( $s['forms_keep_undelivered'] ) ); ?> />
								<div class="sp-wz-switch-row__body">
									<label class="sp-wz-switch-row__title" for="sp-adv-keep-undelivered"><?php esc_html_e( 'Keep a submission when its email cannot be sent', 'ai-site-assistant-autopilot' ); ?></label>
									<p class="sp-wz-switch-row__note" id="sp-adv-keep-undelivered-note"><?php esc_html_e( 'Off: nothing is saved in WordPress, and a visitor whose email fails is asked to try again. On: the answers are kept on this site, retried every hour up to 5 times, and listed under Your forms to resend; they are deleted once sent or after the period under Data retention.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>
							<div class="sp-adv-turnstile" data-sp-turnstile>
								<h3 class="sp-adv-subtitle"><?php esc_html_e( 'Spam check (optional): Cloudflare Turnstile', 'ai-site-assistant-autopilot' ); ?></h3>
								<p class="sp-wz-card__note"><?php esc_html_e( 'Free, and most visitors never see a puzzle. In your Cloudflare dashboard, open Turnstile and add a widget for this site, then paste its two keys here. While both are set, every native form shows the check and its answer is confirmed with Cloudflare before the email is sent; leave them empty to keep forms as they are.', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-wz-card__grid">
									<label class="sp-wz-field">
										<span class="sp-wz-field__label"><?php esc_html_e( 'Site key', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="text" name="turnstile_site_key" class="sp-input sp-input--sm" dir="ltr" autocomplete="off" spellcheck="false" value="<?php echo esc_attr( Turnstile::site_key() ); ?>" />
									</label>
									<label class="sp-wz-field">
										<span class="sp-wz-field__label"><?php esc_html_e( 'Secret key', 'ai-site-assistant-autopilot' ); ?></span>
										<input type="password" name="turnstile_secret" class="sp-input sp-input--sm" dir="ltr" autocomplete="new-password" spellcheck="false" value="" placeholder="<?php echo esc_attr( Turnstile::has_secret() ? __( 'Saved securely - enter a new key to replace it', 'ai-site-assistant-autopilot' ) : '' ); ?>" />
									</label>
								</div>
								<?php if ( Turnstile::has_secret() ) : ?>
									<label class="sp-adv-check"><input type="checkbox" name="clear_turnstile_secret" value="1" /> <?php esc_html_e( 'Remove the saved secret key (turns the check off)', 'ai-site-assistant-autopilot' ); ?></label>
								<?php endif; ?>
								<p class="sp-wz-card__note" data-sp-turnstile-state><?php echo esc_html( Turnstile::enabled() ? __( 'On: native forms ask Cloudflare to confirm each visitor.', 'ai-site-assistant-autopilot' ) : __( 'Off: native forms use their built-in honeypot and rate limit only.', 'ai-site-assistant-autopilot' ) ); ?></p>
							</div>
							<div class="sp-adv-delivery">
								<button type="button" id="sp-test-form-delivery" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Send a delivery test', 'ai-site-assistant-autopilot' ); ?></button>
								<span id="sp-form-delivery-test-result" class="sp-status-text" role="status" aria-live="polite"></span>
							</div>
							<p class="sp-wz-card__note"><?php esc_html_e( 'The test goes to the saved address. Save first if you changed it.', 'ai-site-assistant-autopilot' ); ?></p>
						</section>

						<section class="sp-wz-card" id="sp-adv-public" aria-labelledby="sp-adv-public-title">
							<div class="sp-wz-switch-row sp-adv-switch-head">
								<input type="hidden" name="public_assistant_enabled" value="0" />
								<input type="checkbox" class="sp-wz-switch" id="sp-adv-public-toggle" role="switch" name="public_assistant_enabled" value="1" aria-describedby="sp-adv-public-note" <?php checked( ! empty( $s['public_assistant_enabled'] ) ); ?> />
								<div class="sp-wz-switch-row__body">
									<h2 class="sp-wz-card__title" id="sp-adv-public-title"><label for="sp-adv-public-toggle"><?php esc_html_e( 'Public site assistant', 'ai-site-assistant-autopilot' ); ?></label></h2>
									<p class="sp-wz-switch-row__note" id="sp-adv-public-note"><?php esc_html_e( 'The same switch as Answer Desk in Integrations. Visitors can only ask; the assistant never edits your site, and requests are rate-limited.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
							</div>
						</section>

						<section class="sp-wz-card" id="sp-adv-retention" aria-labelledby="sp-adv-retention-title" data-sp-retention>
							<h2 class="sp-wz-card__title" id="sp-adv-retention-title"><?php esc_html_e( 'Data retention', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-adv-lead"><?php esc_html_e( 'Visitor data older than these periods is deleted once a day. Enter 0 to keep it until you delete it yourself.', 'ai-site-assistant-autopilot' ); ?></p>
							<div class="sp-wz-card__grid sp-adv-retention__grid">
								<?php foreach ( $retention_fields as $retention_key => $retention_field ) : ?>
									<label class="sp-wz-field">
										<span class="sp-wz-field__label"><?php echo esc_html( $retention_field['label'] ); ?></span>
										<span class="sp-adv-days">
											<input type="number" name="retention_<?php echo esc_attr( $retention_key ); ?>" class="sp-input sp-input--sm" dir="ltr" min="0" max="<?php echo esc_attr( (string) Retention::MAX_DAYS ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( (string) $retention[ $retention_key ] ); ?>" aria-describedby="sp-adv-retention-<?php echo esc_attr( $retention_key ); ?>-help" />
											<span class="sp-adv-days__unit"><?php esc_html_e( 'days', 'ai-site-assistant-autopilot' ); ?></span>
										</span>
										<span class="sp-wz-field__help" id="sp-adv-retention-<?php echo esc_attr( $retention_key ); ?>-help">
										<?php
										echo esc_html(
											$retention_field['help'] . ' ' . sprintf(
												/* translators: %s: number of days. */
												__( 'Default: %s.', 'ai-site-assistant-autopilot' ),
												number_format_i18n( Retention::DEFAULTS[ $retention_key ] )
											)
										);
										?>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="sp-adv-retention__status">
								<p data-sp-retention-summary role="status" aria-live="polite"><?php echo esc_html( Retention::summary( Retention::last_run() ) ); ?></p>
								<?php
								$next_cleanup = Retention::next_run();
								$cleanup_when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cleanup );
								if ( 0 === $next_cleanup ) {
									$cleanup_note = __( 'The daily cleanup is not queued yet; WordPress queues it on the next page load.', 'ai-site-assistant-autopilot' );
								} elseif ( $next_cleanup < time() - HOUR_IN_SECONDS ) {
									// Long overdue means WP-Cron is not running on this site; say so instead of showing a past date as "next".
									/* translators: %s: date and time. */
									$cleanup_note = sprintf( __( 'The daily cleanup was due on %s and has not run yet, so WordPress\'s scheduler is not running on this site. Add the server cron job below.', 'ai-site-assistant-autopilot' ), $cleanup_when );
								} else {
									/* translators: %s: date and time. */
									$cleanup_note = sprintf( __( 'Next cleanup: %s, or later if nobody visits the site then.', 'ai-site-assistant-autopilot' ), $cleanup_when );
								}
								?>
								<p data-sp-retention-next><?php echo esc_html( $cleanup_note ); ?></p>
								<div class="sp-adv-delivery">
									<button type="button" class="sp-btn sp-btn--ghost sp-btn--r8" data-sp-retention-run><?php esc_html_e( 'Delete expired data now', 'ai-site-assistant-autopilot' ); ?></button>
									<span class="sp-status-text" data-sp-retention-result role="status" aria-live="polite"></span>
								</div>
								<p class="sp-wz-card__note"><?php esc_html_e( 'Uses the saved periods. Save first if you changed them.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<?php if ( Retention::wp_cron_disabled() ) : ?>
								<p class="sp-adv-notice" data-sp-retention-cron-off><?php esc_html_e( 'WordPress’s own scheduler is turned off on this site (DISABLE_WP_CRON in wp-config.php). The cleanup only runs if a server cron job calls WordPress, as shown below, or when you click the button above.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php endif; ?>
							<details class="sp-adv-cron"<?php echo Retention::wp_cron_disabled() ? ' open' : ''; ?>>
								<summary><?php esc_html_e( 'Run it on time with a server cron job', 'ai-site-assistant-autopilot' ); ?></summary>
								<p><?php esc_html_e( 'WordPress starts scheduled jobs only when someone visits the site, so on a quiet site the cleanup can run late. To run it on time, add a cron job on your server. In cPanel it is under Advanced › Cron Jobs; in Plesk under Tools & Settings › Scheduled Tasks. Choose every 15 minutes and paste this command:', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-adv-cron__cmd">
									<pre dir="ltr"><code data-sp-copy-source><?php echo esc_html( '*/15 * * * * wget -q -O - "' . site_url( 'wp-cron.php?doing_wp_cron' ) . '" >/dev/null 2>&1' ); ?></code></pre>
									<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-sp-copy><?php esc_html_e( 'Copy', 'ai-site-assistant-autopilot' ); ?></button>
								</div>
								<p><?php esc_html_e( 'If your host has WP-CLI, this does the same without a web request:', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-adv-cron__cmd">
									<pre dir="ltr"><code data-sp-copy-source><?php echo esc_html( '*/15 * * * * wp cron event run --due-now --path=' . untrailingslashit( ABSPATH ) . ' >/dev/null 2>&1' ); ?></code></pre>
									<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-sp-copy><?php esc_html_e( 'Copy', 'ai-site-assistant-autopilot' ); ?></button>
								</div>
								<p><?php esc_html_e( 'When the server job works, you can add this line to wp-config.php so visits stop starting the scheduler:', 'ai-site-assistant-autopilot' ); ?> <code dir="ltr">define( 'DISABLE_WP_CRON', true );</code></p>
								<p><?php esc_html_e( 'The command only starts WordPress’s scheduler. It runs this cleanup once a day and any other job that is due; it changes nothing else.', 'ai-site-assistant-autopilot' ); ?></p>
							</details>
						</section>

						<div class="sp-wz-actions">
							<button type="submit" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Save changes', 'ai-site-assistant-autopilot' ); ?></button>
						</div>

						<div class="sp-wz-savebar" data-sp-savebar hidden>
							<div class="sp-wz-savebar__inner" role="region" aria-label="<?php esc_attr_e( 'Unsaved changes', 'ai-site-assistant-autopilot' ); ?>">
								<span class="sp-wz-savebar__dot" aria-hidden="true"></span>
								<span class="sp-wz-savebar__text"><?php esc_html_e( 'Unsaved changes on this page', 'ai-site-assistant-autopilot' ); ?></span>
								<button type="button" class="sp-wz-savebar__discard" data-sp-discard><?php esc_html_e( 'Discard', 'ai-site-assistant-autopilot' ); ?></button>
								<button type="submit" class="sp-wz-savebar__save"><?php esc_html_e( 'Save changes', 'ai-site-assistant-autopilot' ); ?></button>
							</div>
						</div>
					</form>
				</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-adv-i18n',
			array(
				'discardTitle'     => __( 'Discard your changes?', 'ai-site-assistant-autopilot' ),
				'discardBody'      => __( 'Advanced settings go back to their last saved values.', 'ai-site-assistant-autopilot' ),
				'discardCta'       => __( 'Discard changes', 'ai-site-assistant-autopilot' ),
				'retentionRunning' => __( 'Deleting expired data…', 'ai-site-assistant-autopilot' ),
				'retentionDone'    => __( 'Done.', 'ai-site-assistant-autopilot' ),
				'copied'           => __( 'Copied', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	/**
	 * The guided Setup & Connection surface from the canonical admin mockup.
	 *
	 * All panels stay in one form so changing a choice while moving backward
	 * never discards a value. JavaScript controls visibility; the form remains
	 * readable and has a complete server-side save contract.
	 */
	private function render_wizard(): void {
		$saved         = $this->maybe_save();
		$settings      = Settings::all();
		$presets       = Settings::presets();
		$provider      = isset( $presets[ $settings['provider'] ] ) ? (string) $settings['provider'] : 'custom';
		$preset        = Settings::preset( $provider );
		$wp_connectors = WpConnectors::is_available();
		$key_mode      = (string) ( $settings['key_management'] ?? 'keyvault' );
		if ( ! $wp_connectors || 'wp_connectors' !== $key_mode ) {
			$key_mode = 'keyvault';
		}
		$wp_version    = (string) get_bloginfo( 'version' );
		$has_vault_key = Settings::has_api_key();
		$is_verified   = (int) get_option( self::VERIFIED_OPTION, 0 ) > 0;
		$efb_ready     = ( new EasyFormBuilderConnector() )->is_available();
		$woo_available = WooCommerceConnector::is_active();
		$woo_settings  = (array) ( $settings['woocommerce'] ?? array() );
		$woo_enabled   = ! array_key_exists( 'enabled', $woo_settings ) || ! empty( $woo_settings['enabled'] );
		$saved_model   = (string) $settings['model'];
		$advanced_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$budget        = ( new UsageTracker() )->get_budget();
		$cards         = $this->wizard_integrations( $settings, $efb_ready, $woo_available, $woo_enabled );
		$enabled_names = array();
		foreach ( $cards as $card ) {
			if ( ! empty( $card['on'] ) ) {
				$enabled_names[] = (string) $card['name'];
			}
		}
		$step_labels = array(
			__( 'Connection', 'ai-site-assistant-autopilot' ),
			__( 'AI service', 'ai-site-assistant-autopilot' ),
			__( 'Key & model', 'ai-site-assistant-autopilot' ),
			__( 'Integrations', 'ai-site-assistant-autopilot' ),
		);
		$temperature = (float) $settings['temperature'];

		Ui::grad_defs();
		?>
		<div
			class="wrap sp-app sp-app--tight sp-wizard"
			data-sp-setup-wizard
			data-saved="<?php echo $saved ? '1' : '0'; ?>"
			data-verified="<?php echo $is_verified ? '1' : '0'; ?>"
			data-wp-connectors="<?php echo $wp_connectors ? '1' : '0'; ?>"
			data-has-vault-key="<?php echo $has_vault_key ? '1' : '0'; ?>"
		>
			<hr class="wp-header-end" />
			<section class="sp-screen sp-wizard__frame">
				<header class="sp-wizard__head">
					<span class="sp-wizard__mark" aria-hidden="true"><?php echo Ui::icon( 'shield-check', 21, '#fff', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
					<div class="sp-wizard__titles">
						<h1 class="sp-wizard__title"><?php esc_html_e( 'Set up AutoPilot AI', 'ai-site-assistant-autopilot' ); ?></h1>
						<p class="sp-wizard__sub"><?php esc_html_e( 'Four short steps. Everything after this is optional.', 'ai-site-assistant-autopilot' ); ?></p>
					</div>
					<div class="sp-wizard__counter" data-sp-setup-counter aria-live="polite">
						<?php
						/* translators: 1: current setup step, 2: number of setup steps. */
						echo esc_html( sprintf( __( 'Step %1$s of %2$s', 'ai-site-assistant-autopilot' ), number_format_i18n( 1 ), number_format_i18n( 4 ) ) );
						?>
					</div>
				</header>

				<?php if ( '' !== $this->save_error ) : ?>
					<?php Ui::alert( 'err', 'alert-circle', $this->save_error ); ?>
				<?php endif; ?>
				<?php PluginDependency::render_result_notice(); ?>

				<form method="post" data-sp-setup-form novalidate>
					<?php wp_nonce_field( 'aisaautopilot_settings_save', 'aisaautopilot_settings_nonce' ); ?>
					<input type="hidden" name="setup_wizard" value="1" />

					<ol class="sp-wizard-steps" aria-label="<?php esc_attr_e( 'Setup progress', 'ai-site-assistant-autopilot' ); ?>" data-sp-setup-progress>
						<?php foreach ( $step_labels as $index => $label ) : ?>
							<li class="sp-wizard-steps__item<?php echo 0 === $index ? ' is-current' : ''; ?>" data-sp-setup-progress-step="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
								<span class="sp-wizard-steps__dot" aria-hidden="true">
									<span class="sp-wizard-steps__num"><?php echo esc_html( number_format_i18n( $index + 1 ) ); ?></span>
									<?php echo Ui::icon( 'tick', 13, 'currentColor', 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								</span>
								<span class="sp-wizard-steps__label"><?php echo esc_html( $label ); ?></span>
								<?php if ( $index < 3 ) : ?>
									<span class="sp-wizard-steps__line" aria-hidden="true"></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>

					<section class="sp-wizard__panel" data-sp-setup-step="1" aria-labelledby="sp-setup-h1">
						<h2 class="sp-wizard__h2" id="sp-setup-h1"><?php esc_html_e( 'Where should your API key live?', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wizard__lead"><?php esc_html_e( 'Both options keep the key on your own server. Pick the first one if you are not sure.', 'ai-site-assistant-autopilot' ); ?></p>

						<div class="sp-wizard-choices" role="radiogroup" aria-labelledby="sp-setup-h1">
							<label class="sp-wizard-choice<?php echo 'keyvault' === $key_mode ? ' is-selected' : ''; ?>" data-sp-key-mode="keyvault">
								<input class="sp-sr" type="radio" name="key_management" value="keyvault" <?php checked( 'keyvault', $key_mode ); ?> />
								<span class="sp-wizard-choice__icon" aria-hidden="true"><?php echo Ui::icon( 'shield-key', 19, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-wizard-choice__body">
									<span class="sp-wizard-choice__title-row">
										<span class="sp-wizard-choice__title"><?php esc_html_e( 'Managed by AutoPilot', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-wizard-tag sp-wizard-tag--brand"><?php esc_html_e( 'Recommended', 'ai-site-assistant-autopilot' ); ?></span>
									</span>
									<span class="sp-wizard-choice__desc"><?php esc_html_e( 'You paste an API key once. We encrypt it in your database and only this plugin can read it.', 'ai-site-assistant-autopilot' ); ?></span>
									<span class="sp-wizard-choice__req is-ok">
										<?php echo Ui::icon( 'tick', 13, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<?php
										/* translators: %s: minimum WordPress version. */
										echo esc_html( sprintf( __( 'Works on WordPress %s and up', 'ai-site-assistant-autopilot' ), '6.2' ) );
										?>
									</span>
								</span>
								<span class="sp-wizard-radio" aria-hidden="true"></span>
							</label>

							<label class="sp-wizard-choice<?php echo 'wp_connectors' === $key_mode ? ' is-selected' : ''; ?><?php echo $wp_connectors ? '' : ' is-disabled'; ?>" data-sp-key-mode="wp_connectors">
								<input class="sp-sr" type="radio" name="key_management" value="wp_connectors" <?php checked( 'wp_connectors', $key_mode ); ?> <?php disabled( ! $wp_connectors ); ?> />
								<span class="sp-wizard-choice__icon" aria-hidden="true"><?php echo Ui::icon( 'connector', 19, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-wizard-choice__body">
									<span class="sp-wizard-choice__title-row">
										<span class="sp-wizard-choice__title"><?php esc_html_e( 'WordPress Connector', 'ai-site-assistant-autopilot' ); ?></span>
										<?php if ( $wp_connectors ) : ?>
											<span class="sp-wizard-tag"><?php esc_html_e( 'WP 7.0+', 'ai-site-assistant-autopilot' ); ?></span>
										<?php endif; ?>
									</span>
									<span class="sp-wizard-choice__desc"><?php esc_html_e( 'The key lives in Settings → Connectors, where WordPress shares it with any plugin you allow.', 'ai-site-assistant-autopilot' ); ?></span>
									<?php if ( ! $wp_connectors ) : ?>
										<span class="sp-wizard-choice__req is-warn">
											<?php echo Ui::icon( 'triangle', 13, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
											<?php
											/* translators: %s: the WordPress version this site runs. */
											echo esc_html( sprintf( __( 'Needs WordPress 7.0 — this site runs %s', 'ai-site-assistant-autopilot' ), $wp_version ) );
											?>
										</span>
									<?php elseif ( WpConnectors::has_api_key() ) : ?>
										<span class="sp-wizard-choice__req is-ok">
											<?php echo Ui::icon( 'tick', 13, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
											<?php esc_html_e( 'A key is already saved in Settings → Connectors', 'ai-site-assistant-autopilot' ); ?>
										</span>
									<?php else : ?>
										<span class="sp-wizard-choice__req is-ok">
											<?php echo Ui::icon( 'tick', 13, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
											<?php
											/* translators: %s: the WordPress version this site runs. */
											echo esc_html( sprintf( __( 'Detected WordPress %s on this site', 'ai-site-assistant-autopilot' ), $wp_version ) );
											?>
										</span>
									<?php endif; ?>
								</span>
								<span class="sp-wizard-radio" aria-hidden="true"></span>
							</label>
						</div>
						<?php if ( $wp_connectors ) : ?>
							<p class="sp-wizard__foot-note" data-sp-connector-note <?php echo 'wp_connectors' === $key_mode ? '' : 'hidden'; ?>>
								<?php esc_html_e( 'Save the provider key in Settings → Connectors first, then come back and check it here.', 'ai-site-assistant-autopilot' ); ?>
								<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Open Connectors', 'ai-site-assistant-autopilot' ); ?></a>
							</p>
						<?php endif; ?>
					</section>

					<section class="sp-wizard__panel" data-sp-setup-step="2" aria-labelledby="sp-setup-h2" hidden>
						<h2 class="sp-wizard__h2" id="sp-setup-h2"><?php esc_html_e( 'Which AI service will you use?', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wizard__lead"><?php esc_html_e( 'You need an account with one of them. Search by name, or pick a popular one below.', 'ai-site-assistant-autopilot' ); ?></p>

						<label class="sp-wizard-search">
							<span class="sp-sr"><?php esc_html_e( 'Search AI services', 'ai-site-assistant-autopilot' ); ?></span>
							<?php echo Ui::icon( 'magnify', 17, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<input
								type="search"
								id="sp-setup-provider-search"
								autocomplete="off"
								placeholder="<?php echo esc_attr( sprintf( /* translators: %s: number of AI services. */ __( 'Search %s services — OpenAI, Claude, Gemini, DeepSeek…', 'ai-site-assistant-autopilot' ), number_format_i18n( count( $presets ) ) ) ); ?>"
							/>
						</label>

						<div class="sp-wizard-eyebrow" data-sp-provider-results aria-live="polite"><?php esc_html_e( 'Popular with WordPress sites', 'ai-site-assistant-autopilot' ); ?></div>
						<input type="hidden" id="sp-provider" name="provider" value="<?php echo esc_attr( $provider ); ?>" />
						<div class="sp-wizard-providers" data-sp-provider-grid>
							<?php foreach ( $presets as $key => $entry ) : ?>
								<?php
								$key     = (string) $key;
								$popular = in_array( $key, self::POPULAR_PROVIDERS, true );
								$badge   = $this->provider_badge( $key );
								?>
								<button
									type="button"
									class="sp-wizard-provider<?php echo $provider === $key ? ' is-selected' : ''; ?>"
									data-sp-provider-card
									data-provider="<?php echo esc_attr( $key ); ?>"
									data-popular="<?php echo $popular ? '1' : '0'; ?>"
									aria-pressed="<?php echo $provider === $key ? 'true' : 'false'; ?>"
								>
									<span class="sp-wizard-provider__row">
										<span class="sp-wizard-provider__mark" aria-hidden="true"><?php echo BrandMarks::svg( $key, 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG path data. ?></span>
										<span class="sp-wizard-provider__copy">
											<span class="sp-wizard-provider__name"><?php echo esc_html( (string) $entry['label'] ); ?></span>
											<span class="sp-wizard-provider__trait"><?php echo esc_html( $this->provider_trait( $key, $entry ) ); ?></span>
										</span>
										<span class="sp-wizard-provider__check" aria-hidden="true"><?php echo Ui::icon( 'tick', 17, 'currentColor', 2.6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
									</span>
									<?php if ( '' !== $badge ) : ?>
										<span class="sp-wizard-tag<?php echo 'openai' === $key ? ' sp-wizard-tag--ok' : ' sp-wizard-tag--brand'; ?>"><?php echo esc_html( $badge ); ?></span>
									<?php endif; ?>
								</button>
							<?php endforeach; ?>
						</div>

						<div class="sp-wizard-empty" data-sp-provider-empty hidden>
							<div class="sp-wizard-empty__title" data-sp-provider-empty-title></div>
							<p class="sp-wizard-empty__text"><?php esc_html_e( 'If it speaks the OpenAI API, add it as a custom endpoint.', 'ai-site-assistant-autopilot' ); ?></p>
							<button type="button" class="sp-wizard-btn sp-wizard-btn--primary" data-sp-provider-custom><?php esc_html_e( 'Use a custom endpoint', 'ai-site-assistant-autopilot' ); ?></button>
						</div>

						<button type="button" class="sp-wizard-link sp-wizard-expand" data-sp-provider-filter="all" aria-expanded="false">
							<?php echo Ui::icon( 'chev-down', 15, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span data-sp-provider-toggle-label>
								<?php
								/* translators: %s: number of AI services. */
								echo esc_html( sprintf( __( 'See all %s services', 'ai-site-assistant-autopilot' ), number_format_i18n( count( $presets ) ) ) );
								?>
							</span>
						</button>
					</section>

					<section class="sp-wizard__panel" data-sp-setup-step="3" aria-labelledby="sp-setup-h3" hidden>
						<h2 class="sp-wizard__h2" id="sp-setup-h3" data-sp-setup-key-heading><?php esc_html_e( 'Connect your key', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wizard__lead" data-sp-setup-key-lead></p>

						<div class="sp-wizard-card sp-wizard-key">
							<div class="sp-wizard-key__label-row">
								<label class="sp-wizard-label" for="sp-api-key"><?php esc_html_e( 'API key', 'ai-site-assistant-autopilot' ); ?></label>
								<a class="sp-wizard-key__get" id="sp-key-link" href="<?php echo esc_url( (string) $preset['key_url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo '' === (string) $preset['key_url'] ? 'hidden' : ''; ?>>
									<?php esc_html_e( 'Get a key', 'ai-site-assistant-autopilot' ); ?>
									<?php echo Ui::icon( 'external', 12, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span class="sp-sr"><?php esc_html_e( '(opens in a new tab)', 'ai-site-assistant-autopilot' ); ?></span>
								</a>
							</div>
							<div class="sp-wizard-key__row">
								<input type="password" id="sp-api-key" name="api_key" class="sp-wizard-input sp-mono" autocomplete="off" spellcheck="false" dir="ltr" aria-describedby="sp-setup-key-hint" />
								<button type="button" class="sp-wizard-btn sp-wizard-btn--primary sp-wizard-check" id="sp-test" data-sp-setup-test>
									<span class="sp-wizard-check__spin" aria-hidden="true"></span>
									<span data-sp-setup-test-label><?php esc_html_e( 'Check connection', 'ai-site-assistant-autopilot' ); ?></span>
								</button>
							</div>
							<p class="sp-wizard-hint" id="sp-setup-key-hint" data-sp-setup-key-source><?php esc_html_e( 'Nothing is saved until the check passes.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php if ( $has_vault_key ) : ?>
								<label class="sp-wizard-inline-check"><input type="checkbox" name="clear_api_key" value="1" /> <span><?php esc_html_e( 'Remove the saved vault key', 'ai-site-assistant-autopilot' ); ?></span></label>
							<?php endif; ?>

							<div class="sp-wizard-endpoint" data-sp-setup-endpoint>
								<label class="sp-wizard-label" for="sp-base-url"><?php esc_html_e( 'API base URL', 'ai-site-assistant-autopilot' ); ?></label>
								<input type="url" id="sp-base-url" name="base_url" class="sp-wizard-input sp-mono" dir="ltr" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" placeholder="https://api.example.com/v1" />
								<p class="sp-wizard-hint"><?php esc_html_e( 'Paste the base URL only, up to /v1 — not the full path and not a trailing slash.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<button type="button" class="sp-wizard-link" data-sp-setup-endpoint-toggle aria-expanded="false"><?php esc_html_e( 'Change the endpoint', 'ai-site-assistant-autopilot' ); ?></button>
						</div>

						<div class="sp-wizard-result sp-testbox" id="sp-setup-testbox" data-sp-setup-testbox <?php echo $is_verified ? '' : 'hidden'; ?>>
							<span class="sp-wizard-result__icon" id="sp-setup-testmark" aria-hidden="true"><?php echo Ui::icon( 'tick', 17, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div class="sp-wizard-result__body">
								<div class="sp-wizard-result__title" id="sp-setup-testtitle"><?php echo $is_verified ? esc_html__( 'Previously verified', 'ai-site-assistant-autopilot' ) : ''; ?></div>
								<p class="sp-wizard-result__text" id="sp-setup-testbody"><?php echo $is_verified ? esc_html__( 'The saved provider passed a previous connection check. Changing these values requires another test.', 'ai-site-assistant-autopilot' ) : ''; ?></p>
								<ul class="sp-wizard-checklist" data-sp-setup-checklist hidden>
									<li data-stage="reach"><span class="sp-wizard-checklist__icon" aria-hidden="true"></span><span data-stage-label><?php esc_html_e( 'Reaching the provider', 'ai-site-assistant-autopilot' ); ?></span></li>
									<li data-stage="key"><span class="sp-wizard-checklist__icon" aria-hidden="true"></span><span data-stage-label><?php esc_html_e( 'Checking the key', 'ai-site-assistant-autopilot' ); ?></span></li>
									<li data-stage="test"><span class="sp-wizard-checklist__icon" aria-hidden="true"></span><span data-stage-label><?php esc_html_e( 'Sending a one-word test', 'ai-site-assistant-autopilot' ); ?></span></li>
									<li data-stage="models"><span class="sp-wizard-checklist__icon" aria-hidden="true"></span><span data-stage-label><?php esc_html_e( 'Listing available models', 'ai-site-assistant-autopilot' ); ?></span></li>
								</ul>
								<div class="sp-wizard-fix" data-sp-setup-fix hidden>
									<div class="sp-wizard-fix__label"><?php esc_html_e( 'How to fix it', 'ai-site-assistant-autopilot' ); ?></div>
									<div class="sp-wizard-fix__text" data-sp-setup-fix-text></div>
								</div>
								<div class="sp-wizard-result__actions" data-sp-setup-actions></div>
								<div class="sp-wizard-result__meta" id="sp-setup-testmeta"></div>
							</div>
						</div>
						<span id="sp-test-result" class="sp-sr" role="status" aria-live="polite"></span>

						<div class="sp-wizard-card sp-wizard-models" data-sp-setup-models>
							<div class="sp-wizard-models__head">
								<div>
									<div class="sp-wizard-models__title"><?php esc_html_e( 'Model', 'ai-site-assistant-autopilot' ); ?></div>
									<div class="sp-wizard-models__sub" id="sp-models-status" aria-live="polite"></div>
								</div>
								<span class="sp-wizard-pill" data-sp-model-chosen hidden><?php esc_html_e( 'Chosen for you', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<div class="sp-wizard-models__list" id="sp-model-select" data-sp-model-list role="radiogroup" aria-label="<?php esc_attr_e( 'Model', 'ai-site-assistant-autopilot' ); ?>"></div>
							<button type="button" class="sp-wizard-models__more" data-sp-model-more hidden></button>
							<div class="sp-wizard-models__custom">
								<label class="sp-wizard-label" for="sp-model"><?php esc_html_e( 'Model name', 'ai-site-assistant-autopilot' ); ?></label>
								<div class="sp-wizard-key__row">
									<input type="text" id="sp-model" name="model" class="sp-wizard-input sp-mono" dir="ltr" spellcheck="false" value="<?php echo esc_attr( $saved_model ); ?>" />
									<button type="button" id="sp-refresh-models" class="sp-wizard-btn sp-wizard-btn--ghost"><?php echo Ui::icon( 'refresh', 14, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Refresh from provider', 'ai-site-assistant-autopilot' ); ?></span></button>
								</div>
							</div>
						</div>

						<div class="sp-wizard-card sp-wizard-accordion">
							<button type="button" class="sp-wizard-accordion__toggle" data-sp-accordion aria-expanded="false" aria-controls="sp-setup-advanced-3">
								<?php echo Ui::icon( 'sliders', 16, '#5b5e7d', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span class="sp-wizard-accordion__title"><?php esc_html_e( 'Advanced', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-wizard-accordion__meta"><?php esc_html_e( 'Temperature, reply length, semantic search', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-wizard-accordion__chev" aria-hidden="true"><?php echo Ui::icon( 'chev-down', 16, '#5b5e7d', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							</button>
							<div class="sp-wizard-accordion__body" id="sp-setup-advanced-3" hidden>
								<div class="sp-wizard-adv-grid">
									<div>
										<div class="sp-wizard-adv-label">
											<label for="sp-temperature"><?php esc_html_e( 'Temperature', 'ai-site-assistant-autopilot' ); ?></label>
											<output class="sp-mono" for="sp-temperature" data-sp-temperature-value><?php echo esc_html( number_format_i18n( $temperature, 2 ) ); ?></output>
										</div>
										<input type="range" id="sp-temperature" name="temperature" min="0" max="1" step="0.05" value="<?php echo esc_attr( (string) $temperature ); ?>" class="sp-wizard-range" />
										<div class="sp-wizard-range__ends"><span><?php esc_html_e( 'Predictable', 'ai-site-assistant-autopilot' ); ?></span><span><?php esc_html_e( 'Creative', 'ai-site-assistant-autopilot' ); ?></span></div>
										<p class="sp-wizard-hint" data-sp-temperature-note></p>
									</div>
									<div>
										<div class="sp-wizard-adv-label"><span><?php esc_html_e( 'Reply length', 'ai-site-assistant-autopilot' ); ?></span><span class="sp-wizard-tag"><?php esc_html_e( 'Not available yet', 'ai-site-assistant-autopilot' ); ?></span></div>
										<div class="sp-wizard-segmented" aria-disabled="true">
											<button type="button" disabled><?php esc_html_e( 'Short', 'ai-site-assistant-autopilot' ); ?></button>
											<button type="button" disabled class="is-on"><?php esc_html_e( 'Standard', 'ai-site-assistant-autopilot' ); ?></button>
											<button type="button" disabled><?php esc_html_e( 'Long', 'ai-site-assistant-autopilot' ); ?></button>
										</div>
										<p class="sp-wizard-hint"><?php esc_html_e( 'Replies use the provider’s own length today. A per-reply cap needs a change to the provider requests, so this stays locked.', 'ai-site-assistant-autopilot' ); ?></p>
									</div>
								</div>
								<div class="sp-wizard-adv-row">
									<label class="sp-wizard-switch">
										<input type="hidden" name="embeddings_enabled" value="0" />
										<input type="checkbox" role="switch" name="embeddings_enabled" value="1" data-sp-embeddings <?php checked( ! empty( $settings['embeddings_enabled'] ) ); ?> />
										<span class="sp-wizard-switch__track" aria-hidden="true"></span>
										<span class="sp-wizard-switch__copy">
											<span class="sp-wizard-switch__title"><?php esc_html_e( 'Semantic search', 'ai-site-assistant-autopilot' ); ?></span>
											<span class="sp-wizard-switch__desc"><?php esc_html_e( 'Ranks your content by meaning instead of matching words. Needs a provider with an embeddings endpoint.', 'ai-site-assistant-autopilot' ); ?></span>
										</span>
									</label>
									<div class="sp-wizard-warn" data-sp-embeddings-warning hidden></div>
								</div>
								<p class="sp-wizard-hint sp-wizard-adv-more">
									<?php esc_html_e( 'Embedding provider, voice model and form delivery live in the full settings.', 'ai-site-assistant-autopilot' ); ?>
									<a href="<?php echo esc_url( $advanced_url ); ?>"><?php esc_html_e( 'Open advanced settings', 'ai-site-assistant-autopilot' ); ?></a>
								</p>
							</div>
						</div>
					</section>

					<section class="sp-wizard__panel" data-sp-setup-step="4" aria-labelledby="sp-setup-h4" hidden>
						<h2 class="sp-wizard__h2" id="sp-setup-h4"><?php esc_html_e( 'Turn on what you need', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wizard__lead"><?php esc_html_e( 'We checked your site and pre-selected what fits. Change any of it later.', 'ai-site-assistant-autopilot' ); ?></p>

						<div class="sp-wizard-integrations" data-sp-setup-integrations>
							<?php foreach ( $cards as $card ) : ?>
								<?php $this->render_wizard_integration( $card ); ?>
							<?php endforeach; ?>
						</div>

						<?php
						// First setup proposes a cap (a surprise bill is the most common complaint about AI plugins);
						// a site set up earlier without one sees the suggestion switched off, one click away.
						$cap_on    = $budget > 0 || ! $is_verified;
						$cap_value = $budget > 0 ? $budget : UsageTracker::SUGGESTED_BUDGET;
						?>
						<div class="sp-wizard-card sp-wizard-cap" data-sp-setup-cap>
							<div class="sp-wizard-cap__head">
								<span class="sp-wizard-integration__icon" aria-hidden="true"><?php echo Ui::icon( 'money', 18, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<div class="sp-wizard-integration__body">
									<span class="sp-wizard-integration__name" id="sp-setup-cap-title"><?php esc_html_e( 'Monthly spend cap', 'ai-site-assistant-autopilot' ); ?></span>
									<p class="sp-wizard-integration__desc" id="sp-setup-cap-note"><?php esc_html_e( 'Protects you from a surprise bill: once this month passes the tokens below, every AI feature pauses until next month and the Overview tells you. 2,000,000 tokens is roughly 700 to 1,000 assistant answers; what that costs depends on your provider\'s price per million tokens.', 'ai-site-assistant-autopilot' ); ?></p>
								</div>
								<label class="sp-wizard-switch sp-wizard-switch--main">
									<input type="hidden" name="monthly_budget_enabled" value="0" />
									<input type="checkbox" role="switch" name="monthly_budget_enabled" value="1" aria-labelledby="sp-setup-cap-title" aria-describedby="sp-setup-cap-note" data-sp-setup-cap-switch <?php checked( $cap_on ); ?> />
									<span class="sp-wizard-switch__track" aria-hidden="true"></span>
								</label>
							</div>
							<label class="sp-wizard-cap__field" data-sp-setup-cap-field<?php echo $cap_on ? '' : ' hidden'; ?>>
								<span><?php esc_html_e( 'Tokens per month', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="number" name="monthly_budget" class="sp-wizard-input" dir="ltr" inputmode="numeric" min="10000" max="<?php echo esc_attr( (string) UsageTracker::MAX_BUDGET ); ?>" step="10000" value="<?php echo esc_attr( (string) $cap_value ); ?>" />
							</label>
						</div>

						<div class="sp-wizard-card sp-wizard-accordion">
							<button type="button" class="sp-wizard-accordion__toggle" data-sp-accordion aria-expanded="false" aria-controls="sp-setup-advanced-4">
								<?php echo Ui::icon( 'cog', 16, '#5b5e7d', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span class="sp-wizard-accordion__title"><?php esc_html_e( 'Advanced', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-wizard-accordion__meta"><?php esc_html_e( 'Approval before publishing', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-wizard-accordion__chev" aria-hidden="true"><?php echo Ui::icon( 'chev-down', 16, '#5b5e7d', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							</button>
							<div class="sp-wizard-accordion__body sp-wizard-guards" id="sp-setup-advanced-4" hidden>
								<div class="sp-wizard-guard">
									<span class="sp-wizard-switch__track is-on is-locked" aria-hidden="true"></span>
									<div>
										<div class="sp-wizard-switch__title"><?php esc_html_e( 'Ask me before publishing', 'ai-site-assistant-autopilot' ); ?> <span class="sp-wizard-tag"><?php esc_html_e( 'Always on', 'ai-site-assistant-autopilot' ); ?></span></div>
										<p class="sp-wizard-switch__desc"><?php esc_html_e( 'AutoPilot drafts the change and waits for your approval instead of writing it live. This version cannot switch it off.', 'ai-site-assistant-autopilot' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</section>

					<section class="sp-wizard-done" data-sp-setup-complete hidden>
						<span class="sp-wizard-done__icon" aria-hidden="true"><?php echo Ui::icon( 'tick', 26, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<h2 class="sp-wizard-done__title"><?php esc_html_e( 'AutoPilot is live', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-wizard-done__text">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: AI provider label, 2: model ID, 3: number of enabled features, 4: comma-separated feature names. */
									_n( '%1$s is connected on %2$s. %3$s feature is on: %4$s.', '%1$s is connected on %2$s. %3$s features are on: %4$s.', count( $enabled_names ), 'ai-site-assistant-autopilot' ),
									(string) $preset['label'],
									'' !== $saved_model ? $saved_model : __( 'the recommended model', 'ai-site-assistant-autopilot' ),
									number_format_i18n( count( $enabled_names ) ),
									implode( wp_get_list_item_separator(), $enabled_names )
								)
							);
							?>
						</p>
						<div class="sp-wizard-done__actions">
							<a class="sp-wizard-btn sp-wizard-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME ) ); ?>"><?php esc_html_e( 'Open the dashboard', 'ai-site-assistant-autopilot' ); ?></a>
							<a class="sp-wizard-btn sp-wizard-btn--outline" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS ) ); ?>"><?php esc_html_e( 'Review integrations', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					</section>

					<footer class="sp-wizard__footer" data-sp-setup-footer>
						<button type="button" class="sp-wizard-btn sp-wizard-btn--ghost sp-wizard__back" data-sp-setup-back hidden>
							<?php echo Ui::icon( 'chev-left', 15, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Back', 'ai-site-assistant-autopilot' ); ?></span>
						</button>
						<div class="sp-wizard__footer-end">
							<span class="sp-wizard__note" data-sp-setup-block-note role="status" aria-live="polite"></span>
							<button type="button" class="sp-wizard-btn sp-wizard-btn--primary sp-wizard__next" data-sp-setup-next>
								<span><?php esc_html_e( 'Next', 'ai-site-assistant-autopilot' ); ?></span>
								<?php echo Ui::icon( 'chev-right', 15, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
							<button type="submit" class="sp-wizard-btn sp-wizard-btn--primary sp-wizard__next" data-sp-setup-save hidden>
								<span><?php esc_html_e( 'Finish setup', 'ai-site-assistant-autopilot' ); ?></span>
								<?php echo Ui::icon( 'chev-right', 15, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</button>
						</div>
					</footer>
				</form>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script( 'sp-provider-catalog', $this->catalog() );
	}

	/**
	 * Step 4 cards in mockup order. Each switch writes the same setting the
	 * Integrations screen toggles, so the two screens can never disagree.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private function wizard_integrations( array $settings, bool $efb_ready, bool $woo_available, bool $woo_enabled ): array {
		$widget  = (array) ( $settings['widget'] ?? array() );
		$woo_dep = PluginDependency::status( PluginDependency::WOOCOMMERCE, AdminMenu::SLUG_SETUP );
		$efb_dep = PluginDependency::status( PluginDependency::EFB, AdminMenu::SLUG_SETUP );

		return array(
			array(
				'id'   => 'site-knowledge',
				'name' => __( 'Site Knowledge', 'ai-site-assistant-autopilot' ),
				'icon' => 'book-open',
				'desc' => __( 'Keeps answers grounded in your published site content and its cited sources.', 'ai-site-assistant-autopilot' ),
				'core' => true,
				'on'   => true,
				'sub'  => array(
					'name'  => 'kb_auto_index',
					'label' => __( 'Re-read content automatically when it is published', 'ai-site-assistant-autopilot' ),
					'on'    => ! empty( $settings['kb_auto_index'] ),
				),
			),
			array(
				'id'    => 'answer-desk',
				'name'  => __( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				'icon'  => 'bubble',
				'desc'  => __( 'Lets visitors ask grounded questions through the read-only public assistant.', 'ai-site-assistant-autopilot' ),
				'field' => 'public_assistant_enabled',
				'on'    => ! empty( $settings['public_assistant_enabled'] ),
				'sub'   => array(
					'name'  => 'widget[enabled]',
					'label' => __( 'Show the chat launcher to visitors', 'ai-site-assistant-autopilot' ),
					'on'    => ! empty( $widget['enabled'] ),
				),
			),
			array(
				'id'       => 'store-copilot',
				'name'     => __( 'Store Copilot', 'ai-site-assistant-autopilot' ),
				'icon'     => 'cart-mock',
				'desc'     => $woo_available
					? __( 'Creates reviewed simple-product drafts and presents safe store insights.', 'ai-site-assistant-autopilot' )
					: PluginDependency::why( $woo_dep ) . ' ' . __( 'Store Copilot needs it to read products and create drafts.', 'ai-site-assistant-autopilot' ),
				'field'    => 'woocommerce[enabled]',
				'on'       => $woo_available && $woo_enabled,
				'requires' => $woo_available ? '' : 'WooCommerce',
				'next'     => $woo_dep['action'],
				'note'     => $woo_dep['note'],
			),
			array(
				'id'    => 'form-composer',
				'name'  => __( 'Form Studio', 'ai-site-assistant-autopilot' ),
				'icon'  => 'form-lines',
				'desc'  => __( 'Creates reviewable native forms with email-only delivery and server-side validation.', 'ai-site-assistant-autopilot' ),
				'field' => 'form_composer_enabled',
				'on'    => ! empty( $settings['form_composer_enabled'] ),
			),
			array(
				'id'       => 'efb-copilot',
				'name'     => __( 'Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ),
				'icon'     => 'layers',
				'desc'     => $efb_ready
					? __( 'Adds an approved AI helper to a compatible Easy Form Builder editor.', 'ai-site-assistant-autopilot' )
					: PluginDependency::why( $efb_dep ) . ' ' . __( 'The helper needs Easy Form Builder 4.1 or newer.', 'ai-site-assistant-autopilot' ),
				'field'    => 'efb_copilot_enabled',
				'on'       => $efb_ready && ! empty( $settings['efb_copilot_enabled'] ),
				'requires' => $efb_ready ? '' : 'Easy Form Builder',
				'next'     => $efb_dep['action'],
				'note'     => $efb_dep['note'],
			),
			array(
				'id'    => 'content-studio',
				'name'  => __( 'Content Studio', 'ai-site-assistant-autopilot' ),
				'icon'  => 'pencil',
				'desc'  => __( 'Creates reviewable WordPress page and post drafts; publishing always stays with you.', 'ai-site-assistant-autopilot' ),
				'field' => 'content_studio_enabled',
				'on'    => ! empty( $settings['content_studio_enabled'] ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $card One entry from wizard_integrations().
	 */
	private function render_wizard_integration( array $card ): void {
		$core     = ! empty( $card['core'] );
		$requires = (string) ( $card['requires'] ?? '' );
		$on       = ! empty( $card['on'] );
		$id       = (string) $card['id'];
		if ( $core ) {
			$state = __( 'Always on', 'ai-site-assistant-autopilot' );
		} elseif ( '' !== $requires ) {
			/* translators: %s: required plugin name. */
			$state = sprintf( __( 'Needs %s', 'ai-site-assistant-autopilot' ), $requires );
		} else {
			$state = $on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' );
		}
		$tone = $core ? 'neutral' : ( '' !== $requires ? 'warn' : ( $on ? 'ok' : 'neutral' ) );
		?>
		<div class="sp-wizard-integration<?php echo $on ? ' is-on' : ''; ?>" data-sp-setup-integration="<?php echo esc_attr( $id ); ?>">
			<span class="sp-wizard-integration__icon" aria-hidden="true"><?php echo Ui::icon( (string) $card['icon'], 18, 'currentColor', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<div class="sp-wizard-integration__body">
				<div class="sp-wizard-choice__title-row">
					<span class="sp-wizard-integration__name" id="sp-setup-int-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $card['name'] ); ?></span>
					<span class="sp-wizard-tag sp-wizard-tag--<?php echo esc_attr( $tone ); ?>" data-sp-integration-state><?php echo esc_html( $state ); ?></span>
				</div>
				<p class="sp-wizard-integration__desc"><?php echo esc_html( (string) $card['desc'] ); ?></p>
				<?php if ( '' !== $requires ) : ?>
					<div class="sp-wizard-choice__req is-warn">
						<?php echo Ui::icon( 'triangle', 13, 'currentColor', 2.2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<?php
						/* translators: %s: required plugin name. */
						echo esc_html( sprintf( __( '%s is not active on this site', 'ai-site-assistant-autopilot' ), $requires ) );
						?>
					</div>
					<?php if ( '' !== (string) ( $card['note'] ?? '' ) ) : ?>
						<p class="sp-wizard-integration__desc" data-sp-dependency-note><?php echo esc_html( (string) $card['note'] ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( ! empty( $card['sub'] ) && ( $core || $on || '' === $requires ) ) : ?>
					<?php $sub = (array) $card['sub']; ?>
					<label class="sp-wizard-switch sp-wizard-switch--sub">
						<input type="hidden" name="<?php echo esc_attr( (string) $sub['name'] ); ?>" value="0" />
						<input type="checkbox" role="switch" name="<?php echo esc_attr( (string) $sub['name'] ); ?>" value="1" <?php checked( ! empty( $sub['on'] ) ); ?> />
						<span class="sp-wizard-switch__track sp-wizard-switch__track--sm" aria-hidden="true"></span>
						<span class="sp-wizard-switch__desc"><?php echo esc_html( (string) $sub['label'] ); ?></span>
					</label>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $requires ) : ?>
				<?php $next = is_array( $card['next'] ?? null ) ? $card['next'] : null; ?>
				<?php if ( null !== $next ) : ?>
					<a class="sp-wizard-btn sp-wizard-btn--outline sp-wizard-btn--sm" href="<?php echo esc_url( (string) $next['url'] ); ?>" data-sp-dependency-action="<?php echo esc_attr( (string) $next['kind'] ); ?>"><?php echo esc_html( (string) $next['label'] ); ?></a>
				<?php endif; ?>
			<?php elseif ( ! $core ) : ?>
				<label class="sp-wizard-switch sp-wizard-switch--main">
					<input type="hidden" name="<?php echo esc_attr( (string) $card['field'] ); ?>" value="0" />
					<input type="checkbox" role="switch" name="<?php echo esc_attr( (string) $card['field'] ); ?>" value="1" aria-labelledby="sp-setup-int-<?php echo esc_attr( $id ); ?>" data-sp-integration-switch <?php checked( $on ); ?> />
					<span class="sp-wizard-switch__track" aria-hidden="true"></span>
				</label>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The provider catalogue the Settings script drives its dropdowns from.
	 *
	 * @return array<string, mixed>
	 */
	private function catalog(): array {
		$catalog = array();

		foreach ( Settings::presets() as $key => $preset ) {
			$key             = (string) $key;
			$host            = wp_parse_url( (string) $preset['base_url'], PHP_URL_HOST );
			$catalog[ $key ] = array(
				'label'      => (string) $preset['label'],
				'baseUrl'    => (string) $preset['base_url'],
				'host'       => is_string( $host ) ? $host : '',
				'model'      => (string) $preset['model'],
				'keyUrl'     => (string) $preset['key_url'],
				'keyPrefix'  => self::KEY_PREFIXES[ $key ] ?? '',
				'api'        => (string) $preset['api'],
				'group'      => (string) $preset['group'],
				'note'       => (string) $preset['note'],
				'models'     => (array) $preset['models'],
				'embeddings' => (array) $preset['embeddings'],
				'popular'    => in_array( $key, self::POPULAR_PROVIDERS, true ),
			);
		}

		return array(
			'providers' => $catalog,
			'total'     => count( $catalog ),
			'i18n'      => $this->wizard_strings(),
		);
	}

	/**
	 * Every string the Setup script shows. Kept server-side so the wizard is
	 * translated with the rest of the plugin.
	 *
	 * @return array<string, string>
	 */
	private function wizard_strings(): array {
		return array(
			'other'                 => __( 'Other — type a model name', 'ai-site-assistant-autopilot' ),
			'keyLink'               => __( 'Get a key', 'ai-site-assistant-autopilot' ),
			'loading'               => __( 'Loading models…', 'ai-site-assistant-autopilot' ),
			'saved'                 => __( 'Saved model', 'ai-site-assistant-autopilot' ),
			'endpoint'              => 'anthropic' === Settings::provider_api() ? 'messages' : 'chat/completions',
			/* translators: {step}: current step number. */
			'stepOf'                => __( 'Step {step} of 4', 'ai-site-assistant-autopilot' ),
			'done'                  => __( 'Done', 'ai-site-assistant-autopilot' ),
			'pickStorage'           => __( 'Pick one to continue', 'ai-site-assistant-autopilot' ),
			'pickService'           => __( 'Pick a service to continue', 'ai-site-assistant-autopilot' ),
			'needKey'               => __( 'Connect a working key to continue', 'ai-site-assistant-autopilot' ),
			'needModel'             => __( 'Choose a model to continue', 'ai-site-assistant-autopilot' ),
			'needRecheck'           => __( 'Check the connection with this model to continue', 'ai-site-assistant-autopilot' ),
			'resultsPopular'        => __( 'Popular with WordPress sites', 'ai-site-assistant-autopilot' ),
			/* translators: {count}: number of AI services. */
			'resultsAll'            => __( 'All {count} services', 'ai-site-assistant-autopilot' ),
			/* translators: {count}: number of matching AI services. */
			'resultsMatches'        => __( '{count} matches', 'ai-site-assistant-autopilot' ),
			'resultsMatch'          => __( '1 match', 'ai-site-assistant-autopilot' ),
			/* translators: {count}: number of AI services. */
			'seeAll'                => __( 'See all {count} services', 'ai-site-assistant-autopilot' ),
			'showFewer'             => __( 'Show fewer', 'ai-site-assistant-autopilot' ),
			/* translators: {query}: what the administrator searched for. */
			'noMatch'               => __( 'No service called “{query}”', 'ai-site-assistant-autopilot' ),
			/* translators: {provider}: AI service name. */
			'keyHeading'            => __( 'Connect your {provider} key', 'ai-site-assistant-autopilot' ),
			/* translators: {host}: provider API host name. */
			'keyLeadVault'          => __( 'We store it encrypted on this site and never send it anywhere except {host}.', 'ai-site-assistant-autopilot' ),
			/* translators: {host}: provider API host name. */
			'keyLeadConnector'      => __( 'The key stays in Settings → Connectors. AutoPilot sends it only to {host}.', 'ai-site-assistant-autopilot' ),
			'yourEndpoint'          => __( 'your endpoint', 'ai-site-assistant-autopilot' ),
			'keyHint'               => __( 'Nothing is saved until the check passes.', 'ai-site-assistant-autopilot' ),
			'keySavedPlaceholder'   => __( '•••••••• saved — leave blank to keep it', 'ai-site-assistant-autopilot' ),
			'keyPlaceholder'        => __( 'Paste your key', 'ai-site-assistant-autopilot' ),
			'connectorKeyHint'      => __( 'This choice uses the key saved in Settings → Connectors.', 'ai-site-assistant-autopilot' ),
			'check'                 => __( 'Check connection', 'ai-site-assistant-autopilot' ),
			'checking'              => __( 'Checking', 'ai-site-assistant-autopilot' ),
			'checkAgain'            => __( 'Check again', 'ai-site-assistant-autopilot' ),
			/* translators: {label}: name of the check that failed. */
			'stageFailed'           => __( '{label} — failed', 'ai-site-assistant-autopilot' ),
			'checkingTitle'         => __( 'Checking your key…', 'ai-site-assistant-autopilot' ),
			/* translators: {host}: provider API host name. */
			'checkingBody'          => __( 'A short test request is on its way to {host}.', 'ai-site-assistant-autopilot' ),
			'okTitle'               => __( 'Connected', 'ai-site-assistant-autopilot' ),
			/* translators: {count}: number of models the key can use. */
			'okBody'                => __( 'Your key works and {count} models are available on this account.', 'ai-site-assistant-autopilot' ),
			'okBodyNoList'          => __( 'Your key works. This provider does not publish a model list, so type the model you want below.', 'ai-site-assistant-autopilot' ),
			'titleNoKey'            => __( 'Add an API key', 'ai-site-assistant-autopilot' ),
			'titleUnauthorized'     => __( 'That key was rejected', 'ai-site-assistant-autopilot' ),
			'titlePayment'          => __( 'The key is valid, the account is empty', 'ai-site-assistant-autopilot' ),
			'titleRateLimit'        => __( 'Too many requests right now', 'ai-site-assistant-autopilot' ),
			'titleUnreachable'      => __( 'Your server could not reach the provider', 'ai-site-assistant-autopilot' ),
			'titleNotFound'         => __( 'Wrong endpoint or model', 'ai-site-assistant-autopilot' ),
			'titleIncomplete'       => __( 'Connection details are incomplete', 'ai-site-assistant-autopilot' ),
			'titleNoModels'         => __( 'The key works, but no models came back', 'ai-site-assistant-autopilot' ),
			'titleFailed'           => __( 'The check did not pass', 'ai-site-assistant-autopilot' ),
			'fixNoKey'              => __( 'Paste the key from your provider dashboard, then check again.', 'ai-site-assistant-autopilot' ),
			/* translators: 1: {provider} AI service name, 2: {prefix} documented key prefix. */
			'fixUnauthorizedPrefix' => __( 'Keys for {provider} start with {prefix}. Copy the whole line, including that prefix, and make sure no space slipped in at the end.', 'ai-site-assistant-autopilot' ),
			'fixUnauthorized'       => __( 'Copy the key again from the provider dashboard — a trailing space is the usual cause.', 'ai-site-assistant-autopilot' ),
			/* translators: {provider}: AI service name. */
			'fixPayment'            => __( 'Add credit in your {provider} billing page, then check again. Nothing is wrong with your setup.', 'ai-site-assistant-autopilot' ),
			'fixRateLimit'          => __( 'Wait a moment and check again. New accounts often start on a low tier that a first payment lifts.', 'ai-site-assistant-autopilot' ),
			/* translators: {host}: provider API host name. */
			'fixUnreachable'        => __( 'Usually a host firewall or WP_HTTP_BLOCK_EXTERNAL in wp-config.php. Ask your host to allow outbound HTTPS to {host}, or add it to WP_ACCESSIBLE_HOSTS.', 'ai-site-assistant-autopilot' ),
			'fixNotFound'           => __( 'Paste the base URL only, up to /v1 — not the full path and not a trailing slash. If the endpoint is right, pick a model this account can use.', 'ai-site-assistant-autopilot' ),
			'fixIncomplete'         => __( 'Add the base URL and a model name, then check again.', 'ai-site-assistant-autopilot' ),
			'fixNoModels'           => __( 'Enable at least one chat model for this project in your provider dashboard, or type the model name by hand.', 'ai-site-assistant-autopilot' ),
			'actionPasteAgain'      => __( 'Paste it again', 'ai-site-assistant-autopilot' ),
			/* translators: {provider}: AI service name. */
			'actionOpenKeys'        => __( 'Open {provider} keys', 'ai-site-assistant-autopilot' ),
			'actionPickAnother'     => __( 'Pick another service', 'ai-site-assistant-autopilot' ),
			'actionRetry'           => __( 'Retry', 'ai-site-assistant-autopilot' ),
			'actionNetworkTest'     => __( 'Run the full network test', 'ai-site-assistant-autopilot' ),
			'actionEditEndpoint'    => __( 'Edit the endpoint', 'ai-site-assistant-autopilot' ),
			'actionTypeModel'       => __( 'Type a model name', 'ai-site-assistant-autopilot' ),
			'healthUrl'             => admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS ),
			/* translators: {count}: number of models. */
			'modelsFound'           => __( '{count} models available — we picked the one that fits a WordPress site', 'ai-site-assistant-autopilot' ),
			'modelsPreset'          => __( 'Models this service is known to offer. Refresh to read your account’s own list.', 'ai-site-assistant-autopilot' ),
			'modelsNone'            => __( 'This service has no fixed list. Type the model or deployment name below.', 'ai-site-assistant-autopilot' ),
			'modelRecommended'      => __( 'Recommended', 'ai-site-assistant-autopilot' ),
			/* translators: {count}: number of models. */
			'showAllModels'         => __( 'Show all {count} models', 'ai-site-assistant-autopilot' ),
			'showFewerModels'       => __( 'Show fewer models', 'ai-site-assistant-autopilot' ),
			'modelChanged'          => __( 'You picked a different model. Check the connection again to confirm this account can use it.', 'ai-site-assistant-autopilot' ),
			'modelsUnsupported'     => __( 'This provider does not publish a model list. Type its model name above.', 'ai-site-assistant-autopilot' ),
			'tempLow'               => __( 'Low: the same question gets almost the same answer. Best for support replies.', 'ai-site-assistant-autopilot' ),
			'tempMid'               => __( 'Balanced: reliable for support, still varied enough for writing.', 'ai-site-assistant-autopilot' ),
			'tempHigh'              => __( 'High: more inventive wording, and more likely to wander off.', 'ai-site-assistant-autopilot' ),
			/* translators: {provider}: AI service name. */
			'embedWarning'          => __( '{provider} has no embeddings endpoint. Add a separate embedding key in advanced settings, or leave semantic search off and AutoPilot matches on keywords.', 'ai-site-assistant-autopilot' ),
			'networkError'          => __( 'Network error. Please try again.', 'ai-site-assistant-autopilot' ),
			'endpointChange'        => __( 'Change the endpoint', 'ai-site-assistant-autopilot' ),
			'endpointHide'          => __( 'Hide the endpoint', 'ai-site-assistant-autopilot' ),
			'stateOn'               => __( 'On', 'ai-site-assistant-autopilot' ),
			'stateOff'              => __( 'Off', 'ai-site-assistant-autopilot' ),
		);
	}

	/** A short uppercase tag some services carry in the mockup grid. */
	private function provider_badge( string $key ): string {
		if ( 'openai' === $key ) {
			return __( 'Recommended', 'ai-site-assistant-autopilot' );
		}
		if ( 'openrouter' === $key ) {
			return __( 'One key, many models', 'ai-site-assistant-autopilot' );
		}

		return '';
	}

	/**
	 * Short provider-card descriptors from the setup mockup. Filtered providers
	 * use a neutral, truthful description instead of inheriting a misleading
	 * trait from a preset they merely resemble.
	 *
	 * @param array<string, mixed> $entry
	 */
	private function provider_trait( string $key, array $entry ): string {
		// Mockup trait lines where they are verifiable facts; neutral wording
		// replaces marketing claims ("best", "lowest cost") nobody can check.
		$traits = array(
			'openai'      => __( 'Most widely supported', 'ai-site-assistant-autopilot' ),
			'anthropic'   => __( 'Claude models', 'ai-site-assistant-autopilot' ),
			'gemini'      => __( 'Generous free tier', 'ai-site-assistant-autopilot' ),
			'deepseek'    => __( 'Reasoning focused', 'ai-site-assistant-autopilot' ),
			'qwen'        => __( 'Alibaba Cloud models', 'ai-site-assistant-autopilot' ),
			'mistral'     => __( 'EU-hosted', 'ai-site-assistant-autopilot' ),
			'groq'        => __( 'Fast inference', 'ai-site-assistant-autopilot' ),
			'openrouter'  => __( 'One key, every vendor', 'ai-site-assistant-autopilot' ),
			'azure'       => __( 'Your own Azure deployments', 'ai-site-assistant-autopilot' ),
			'xai'         => __( 'Grok models', 'ai-site-assistant-autopilot' ),
			'cohere'      => __( 'Enterprise and multilingual', 'ai-site-assistant-autopilot' ),
			'zai'         => __( 'GLM models', 'ai-site-assistant-autopilot' ),
			'moonshot'    => __( 'Kimi models', 'ai-site-assistant-autopilot' ),
			'minimax'     => __( 'MiniMax models', 'ai-site-assistant-autopilot' ),
			'cerebras'    => __( 'Fast inference', 'ai-site-assistant-autopilot' ),
			'sambanova'   => __( 'Fast inference', 'ai-site-assistant-autopilot' ),
			'together'    => __( 'Open-weight models', 'ai-site-assistant-autopilot' ),
			'fireworks'   => __( 'Open-weight models', 'ai-site-assistant-autopilot' ),
			'huggingface' => __( 'Many inference providers, one token', 'ai-site-assistant-autopilot' ),
			'nvidia'      => __( 'NVIDIA-hosted models', 'ai-site-assistant-autopilot' ),
			'aimlapi'     => __( '1000+ models, one key', 'ai-site-assistant-autopilot' ),
			'novita'      => __( 'Open-weight models', 'ai-site-assistant-autopilot' ),
			'edenai'      => __( 'Multi-vendor gateway', 'ai-site-assistant-autopilot' ),
			'custom'      => __( 'Any OpenAI-compatible API', 'ai-site-assistant-autopilot' ),
		);

		if ( isset( $traits[ $key ] ) ) {
			return $traits[ $key ];
		}

		return 'gateway' === (string) ( $entry['group'] ?? '' )
			? __( 'Provider gateway', 'ai-site-assistant-autopilot' )
			: __( 'Direct provider endpoint', 'ai-site-assistant-autopilot' );
	}

	/**
	 * Saves only what the advanced settings form shows. The AI service, its key, the key
	 * storage and the temperature belong to Setup and are never changed from here.
	 */
	private function save_advanced(): bool {
		// Nonce and capability were verified by maybe_save(); each value is validated below.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$existing = Settings::all();
		$update   = array();

		foreach ( array( 'embeddings_enabled', 'voice_responses_enabled', 'prefer_efb', 'public_assistant_enabled', 'forms_keep_undelivered' ) as $switch ) {
			if ( array_key_exists( $switch, $_POST ) ) {
				$update[ $switch ] = ! empty( $_POST[ $switch ] );
			}
		}
		if ( array_key_exists( 'embedding_model', $_POST ) ) {
			$update['embedding_model'] = mb_substr( sanitize_text_field( wp_unslash( $_POST['embedding_model'] ) ), 0, 100 );
		}
		if ( array_key_exists( 'embedding_base_url', $_POST ) ) {
			$update['embedding_base_url'] = esc_url_raw( wp_unslash( $_POST['embedding_base_url'] ) );
		}
		if ( array_key_exists( 'voice_model', $_POST ) ) {
			$voice_model           = mb_substr( sanitize_text_field( wp_unslash( $_POST['voice_model'] ) ), 0, 100 );
			$update['voice_model'] = '' !== $voice_model ? $voice_model : (string) ( $existing['voice_model'] ?? 'whisper-1' );
		}
		if ( array_key_exists( 'default_form_status', $_POST ) ) {
			$update['default_form_status'] = 'publish' === sanitize_key( wp_unslash( $_POST['default_form_status'] ) ) ? 'publish' : 'draft';
		}
		if ( array_key_exists( 'form_notification_email', $_POST ) ) {
			$email                             = sanitize_email( (string) wp_unslash( $_POST['form_notification_email'] ) );
			$update['form_notification_email'] = is_email( $email ) ? $email : '';
		}
		if ( array_key_exists( 'turnstile_site_key', $_POST ) ) {
			$update['turnstile_site_key'] = Turnstile::clean_key( (string) wp_unslash( $_POST['turnstile_site_key'] ) );
		}
		if ( ! empty( $_POST['clear_turnstile_secret'] ) ) {
			Turnstile::set_secret( '' );
		} elseif ( '' !== trim( (string) wp_unslash( $_POST['turnstile_secret'] ?? '' ) ) ) {
			Turnstile::set_secret( (string) wp_unslash( $_POST['turnstile_secret'] ) );
		}
		if ( array_key_exists( 'retention_conversations_days', $_POST ) ) {
			$retention = array();
			foreach ( array_keys( Retention::DEFAULTS ) as $retention_key ) {
				$retention[ $retention_key ] = Retention::days( wp_unslash( $_POST[ 'retention_' . $retention_key ] ?? Retention::DEFAULTS[ $retention_key ] ) );
			}
			$update['retention'] = $retention;
		}
		if ( array() !== $update ) {
			Settings::update( $update );
		}

		if ( ! empty( $_POST['clear_embedding_key'] ) ) {
			Settings::set_embedding_key( '' );
		} else {
			$embed_key = trim( (string) wp_unslash( $_POST['embedding_api_key'] ?? '' ) );
			if ( '' !== $embed_key ) {
				Settings::set_embedding_key( $embed_key );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return true;
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['aisaautopilot_settings_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aisaautopilot_settings_nonce'] ) ), 'aisaautopilot_settings_save' ) ) {
			return false;
		}
		// The advanced settings form saves only its own fields, never the AI service or its key.
		if ( ! empty( $_POST['advanced_settings'] ) && empty( $_POST['setup_wizard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified just above.
			return $this->save_advanced();
		}

		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'deepseek' ) );
		if ( ! array_key_exists( $provider, Settings::presets() ) ) {
			$provider = 'deepseek';
		}

		// Nonce and capability are verified at the top of this method; each value
		// below is validated against a fixed set or sanitised on the next line.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$is_setup_wizard = ! empty( $_POST['setup_wizard'] );
		$existing        = Settings::all();
		$status          = ( 'publish' === sanitize_key( wp_unslash( $_POST['default_form_status'] ?? $existing['default_form_status'] ?? 'draft' ) ) ) ? 'publish' : 'draft';
		$email           = sanitize_email( (string) wp_unslash( $_POST['form_notification_email'] ?? $existing['form_notification_email'] ?? '' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}
		$key_mgmt = sanitize_key( wp_unslash( $_POST['key_management'] ?? 'keyvault' ) );
		if ( ! in_array( $key_mgmt, array( 'keyvault', 'wp_connectors' ), true ) ) {
			$key_mgmt = 'keyvault';
		}

		$base_url                     = esc_url_raw( wp_unslash( $_POST['base_url'] ?? '' ) );
		$model                        = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );
		$api_key                      = trim( (string) wp_unslash( $_POST['api_key'] ?? '' ) );
		$clear_key                    = ! empty( $_POST['clear_api_key'] );
		$previous_config              = self::saved_provider_test_config();
		$config_changed               = $previous_config['base_url'] !== $base_url
			|| $previous_config['model'] !== $model
			|| $previous_config['provider'] !== $provider
			|| (string) Settings::get( 'key_management', 'keyvault' ) !== $key_mgmt
			|| '' !== $api_key
			|| $clear_key;
		$submitted_config             = array(
			'provider' => $provider,
			'base_url' => $base_url,
			'model'    => $model,
			'api_key'  => '' !== $api_key ? $api_key : ( $clear_key ? '' : $previous_config['api_key'] ),
		);
		$can_reuse_saved_verification = ! $config_changed && (int) get_option( self::VERIFIED_OPTION, 0 ) > 0;
		if ( $is_setup_wizard && ! $can_reuse_saved_verification && ! self::has_matching_provider_test( $submitted_config ) ) {
			$this->save_error = __( 'Test this exact provider, key and model before saving Setup.', 'ai-site-assistant-autopilot' );
			return false;
		}

		$temperature              = ( $is_setup_wizard && ! array_key_exists( 'temperature', $_POST ) )
			? (float) ( $existing['temperature'] ?? 0.2 )
			: max( 0.0, min( 1.0, (float) wp_unslash( $_POST['temperature'] ?? 0.2 ) ) );
		$embeddings_enabled       = ( $is_setup_wizard && ! array_key_exists( 'embeddings_enabled', $_POST ) )
			? ! empty( $existing['embeddings_enabled'] )
			: ! empty( $_POST['embeddings_enabled'] );
		$embedding_model          = ( $is_setup_wizard && ! array_key_exists( 'embedding_model', $_POST ) )
			? (string) ( $existing['embedding_model'] ?? '' )
			: sanitize_text_field( wp_unslash( $_POST['embedding_model'] ?? '' ) );
		$embedding_base_url       = ( $is_setup_wizard && ! array_key_exists( 'embedding_base_url', $_POST ) )
			? (string) ( $existing['embedding_base_url'] ?? '' )
			: esc_url_raw( wp_unslash( $_POST['embedding_base_url'] ?? '' ) );
		$voice_model              = ( $is_setup_wizard && ! array_key_exists( 'voice_model', $_POST ) )
			? (string) ( $existing['voice_model'] ?? 'whisper-1' )
			: sanitize_text_field( wp_unslash( $_POST['voice_model'] ?? 'whisper-1' ) );
		$voice_responses_enabled  = ( $is_setup_wizard && ! array_key_exists( 'voice_responses_enabled', $_POST ) )
			? ! empty( $existing['voice_responses_enabled'] )
			: ! empty( $_POST['voice_responses_enabled'] );
		$public_assistant_enabled = ( $is_setup_wizard && ! array_key_exists( 'public_assistant_enabled', $_POST ) )
			? ! empty( $existing['public_assistant_enabled'] )
			: ! empty( $_POST['public_assistant_enabled'] );
		$prefer_efb               = ( $is_setup_wizard && ! array_key_exists( 'prefer_efb', $_POST ) )
			? ! empty( $existing['prefer_efb'] )
			: ! empty( $_POST['prefer_efb'] );

		$settings_update = array(
			'provider'                 => $provider,
			'base_url'                 => $base_url,
			'model'                    => $model,
			'temperature'              => $temperature,
			'default_form_status'      => $status,
			'form_notification_email'  => $email,
			'key_management'           => $key_mgmt,
			'embeddings_enabled'       => $embeddings_enabled,
			'embedding_model'          => $embedding_model,
			'embedding_base_url'       => $embedding_base_url,
			'voice_model'              => $voice_model,
			'voice_responses_enabled'  => $voice_responses_enabled,
			'public_assistant_enabled' => $public_assistant_enabled,
			'prefer_efb'               => $prefer_efb,
		);
		if ( array_key_exists( 'kb_auto_index', $_POST ) ) {
			$settings_update['kb_auto_index'] = ! empty( $_POST['kb_auto_index'] );
		}
		// Step 4 switches share their settings with the Integrations cards. A
		// switch that was not rendered (dependency missing) keeps its value.
		foreach ( array( 'form_composer_enabled', 'content_studio_enabled' ) as $integration_flag ) {
			if ( array_key_exists( $integration_flag, $_POST ) ) {
				$settings_update[ $integration_flag ] = ! empty( $_POST[ $integration_flag ] );
			}
		}
		if ( array_key_exists( 'efb_copilot_enabled', $_POST ) && ( new EasyFormBuilderConnector() )->is_available() ) {
			$settings_update['efb_copilot_enabled'] = ! empty( $_POST['efb_copilot_enabled'] );
		}
		if ( isset( $_POST['widget'] ) && is_array( $_POST['widget'] ) && array_key_exists( 'enabled', $_POST['widget'] ) ) {
			$settings_update['widget'] = array( 'enabled' => ! empty( $_POST['widget']['enabled'] ) );
		}
		if ( WooCommerceConnector::is_active() && isset( $_POST['woocommerce'] ) && is_array( $_POST['woocommerce'] ) && array_key_exists( 'enabled', $_POST['woocommerce'] ) ) {
			$woocommerce                    = (array) ( $existing['woocommerce'] ?? array() );
			$woocommerce['enabled']         = ! empty( $_POST['woocommerce']['enabled'] );
			$settings_update['woocommerce'] = $woocommerce;
		}

		Settings::update( $settings_update );

		// Step 4: the spend cap Setup proposes. Off means no cap (0); the number is clamped like Usage & Cost does.
		if ( $is_setup_wizard && array_key_exists( 'monthly_budget_enabled', $_POST ) ) {
			$cap = ! empty( $_POST['monthly_budget_enabled'] ) ? absint( wp_unslash( $_POST['monthly_budget'] ?? UsageTracker::SUGGESTED_BUDGET ) ) : 0;
			( new UsageTracker() )->set_budget( $cap > 0 ? max( 10000, $cap ) : 0 );
		}

		if ( $clear_key ) {
			Settings::set_api_key( '' );
		} else {
			if ( '' !== $api_key ) {
				Settings::set_api_key( $api_key );
			}
		}

		// A test of an unsaved key cannot certify the live provider. Only promote
		// the check after the exact tested configuration has been persisted.
		if ( self::consume_matching_provider_test( self::saved_provider_test_config() ) ) {
			update_option( self::VERIFIED_OPTION, time(), false );
		} elseif ( $config_changed ) {
			delete_option( self::VERIFIED_OPTION );
		}

		if ( ! empty( $_POST['clear_embedding_key'] ) ) {
			Settings::set_embedding_key( '' );
		} else {
			$embed_key = (string) wp_unslash( $_POST['embedding_api_key'] ?? '' );
			if ( '' !== trim( $embed_key ) ) {
				Settings::set_embedding_key( $embed_key );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return true;
	}
}
