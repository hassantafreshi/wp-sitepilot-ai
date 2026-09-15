<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Providers\ProviderManager;

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

	/** The quick-pick chips above the full provider dropdown. */
	private const QUICK_PICKS = array( 'openai', 'anthropic', 'gemini', 'deepseek', 'qwen', 'mistral', 'groq', 'openrouter' );

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
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return AdminMenu::SLUG_SETTINGS === $page;
	}

	private function render_advanced(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$saved       = $this->maybe_save();
		$s           = Settings::all();
		$wp_conn     = WpConnectors::is_available();
		$key_mode    = (string) ( $s['key_management'] ?? 'keyvault' );
		$kb          = new KnowledgeBase();
		$embedded    = $kb->table_exists() ? $kb->count_with_embeddings() : 0;
		$kb_total    = $kb->table_exists() ? (int) ( $kb->get_stats()['total'] ?? 0 ) : 0;
		$indexable   = ( new ContentIndexer() )->get_total_posts();
		$has_key     = Settings::has_api_key();
		$has_emb_key = Settings::has_embedding_key();
		$presets     = Settings::presets();
		$provider    = isset( $presets[ $s['provider'] ] ) ? (string) $s['provider'] : 'custom';
		$preset      = Settings::preset( $provider );
		$efb_ready   = ( new EasyFormBuilderConnector() )->is_available();
		$steps       = $this->steps( $kb_total );
		$kb_url      = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app sp-app--narrow" data-sp-settings>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Setup & Connection', 'ai-site-assistant-autopilot' ),
					'',
					array( 'chip' => ConnectionChip::derive() )
				);
				?>
				<div class="sp-pagebody">

				<?php if ( $saved ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Settings saved.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>

				<?php $this->render_progress( $steps ); ?>

				<?php
				Ui::alert(
					'ok',
					'shield',
					sprintf(
						/* translators: %s: key storage label. */
						esc_html__( 'API key security: %s.', 'ai-site-assistant-autopilot' ),
						esc_html( WpConnectors::get_key_source_label() )
					)
				);
				?>

				<form method="post">
					<?php wp_nonce_field( 'aisaautopilot_settings_save', 'aisaautopilot_settings_nonce' ); ?>

					<div class="sp-card sp-card--24" style="margin-bottom:16px;display:flex;flex-direction:column;gap:18px">

						<div>
							<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( '1. Connect an AI provider', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub" style="max-width:700px;font-size:13px"><?php esc_html_e( 'Pick a provider and the endpoint fills itself in. Everything here except Anthropic speaks the OpenAI Chat Completions format; Claude uses its own Messages API, which AISAAutoPilot handles for you.', 'ai-site-assistant-autopilot' ); ?></p>

							<div class="sp-row-wrap sp-row-wrap--sm" style="margin:12px 0 14px" data-sp-provider-chips>
								<?php foreach ( self::QUICK_PICKS as $key ) : ?>
									<?php if ( ! isset( $presets[ $key ] ) ) : ?>
										<?php continue; ?>
									<?php endif; ?>
									<button type="button" class="sp-choice sp-choice--brand<?php echo $provider === $key ? ' is-on' : ''; ?>" data-provider="<?php echo esc_attr( $key ); ?>">
										<?php echo BrandMarks::svg( (string) $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed path data, escaped in svg(). ?>
										<span><?php echo esc_html( (string) $presets[ $key ]['label'] ); ?></span>
									</button>
								<?php endforeach; ?>
								<button type="button" class="sp-choice sp-choice--brand<?php echo 'custom' === $provider ? ' is-on' : ''; ?>" data-provider="custom">
									<?php echo BrandMarks::svg( 'custom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
									<span><?php esc_html_e( 'Custom endpoint', 'ai-site-assistant-autopilot' ); ?></span>
								</button>
							</div>
						</div>

						<div class="sp-tiles sp-tiles--240">
							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'AI Provider', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-brandline">
									<?php // A select cannot hold markup, so the chosen provider's mark sits beside it; the script keeps it in step. ?>
									<span class="sp-brandbox" id="sp-provider-brand"><?php echo BrandMarks::svg( $provider, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed path data, escaped in svg(). ?></span>
								<select id="sp-provider" name="provider" class="sp-select">
									<?php foreach ( Settings::preset_groups() as $group => $group_label ) : ?>
										<optgroup label="<?php echo esc_attr( $group_label ); ?>">
											<?php foreach ( $presets as $key => $entry ) : ?>
												<?php if ( ( $entry['group'] ?? 'direct' ) !== $group ) : ?>
													<?php continue; ?>
												<?php endif; ?>
												<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $provider, $key ); ?>><?php echo esc_html( (string) $entry['label'] ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
								</span>
								<span class="sp-help" id="sp-provider-note"><?php echo esc_html( (string) $preset['note'] ); ?></span>
							</label>

							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'API Base URL', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="url" id="sp-base-url" name="base_url" class="sp-input sp-mono" value="<?php echo esc_attr( (string) $s['base_url'] ); ?>" placeholder="https://api.openai.com/v1" />
								<span class="sp-help" id="sp-key-link"></span>
							</label>

							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Model', 'ai-site-assistant-autopilot' ); ?></span>
								<?php
								/*
								 * Rendered server-side as well as by the script, so the picker is
								 * populated on first paint and still usable if the script fails.
								 * The saved model is always offered, even when the preset has
								 * never heard of it.
								 */
								$saved_model = (string) $s['model'];
								$model_list  = (array) $preset['models'];
								?>
								<select id="sp-model-select" class="sp-select">
									<?php if ( '' !== $saved_model && ! array_key_exists( $saved_model, $model_list ) ) : ?>
										<option value="<?php echo esc_attr( $saved_model ); ?>" selected><?php echo esc_html( sprintf( '%s — %s', $saved_model, __( 'Saved model', 'ai-site-assistant-autopilot' ) ) ); ?></option>
									<?php endif; ?>
									<?php foreach ( $model_list as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( (string) $model_id ); ?>" <?php selected( $saved_model, (string) $model_id ); ?>><?php echo esc_html( (string) $model_label ); ?></option>
									<?php endforeach; ?>
									<option value="__custom" <?php selected( '', $saved_model ); ?>><?php esc_html_e( 'Other — type a model name', 'ai-site-assistant-autopilot' ); ?></option>
								</select>
								<span class="sp-help"><?php esc_html_e( 'Pick from the provider’s models, or choose “Other” to type an exact model name.', 'ai-site-assistant-autopilot' ); ?></span>
							</label>

							<label class="sp-field" id="sp-model-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Model ID sent to the provider', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="text" id="sp-model" name="model" class="sp-input sp-mono" value="<?php echo esc_attr( (string) $s['model'] ); ?>" placeholder="gpt-5.6-luna" />
								<span class="sp-row-wrap sp-row-wrap--sm" style="margin-top:8px">
									<button type="button" id="sp-refresh-models" class="sp-btn sp-btn--ghost sp-btn--r8" style="padding:7px 12px;font-size:12.5px">
										<?php echo Ui::icon( 'refresh', 14, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<span><?php esc_html_e( 'Refresh from provider', 'ai-site-assistant-autopilot' ); ?></span>
									</button>
									<span id="sp-models-status" class="sp-status-text" role="status" aria-live="polite"></span>
								</span>
							</label>

							<?php if ( 'wp_connectors' !== $key_mode || ! $wp_conn ) : ?>
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'API Key', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="password" id="sp-api-key" name="api_key" class="sp-input" autocomplete="off" dir="ltr"
										placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (a key is saved — leave blank to keep it)', 'ai-site-assistant-autopilot' ) : esc_attr__( 'Paste your API key here', 'ai-site-assistant-autopilot' ); ?>" />
									<?php if ( $has_key ) : ?>
										<label class="sp-check" style="font-size:12.5px">
											<input type="checkbox" name="clear_api_key" value="1" />
											<span><?php esc_html_e( 'Remove saved API key', 'ai-site-assistant-autopilot' ); ?></span>
										</label>
									<?php endif; ?>
								</label>
							<?php endif; ?>

							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Temperature', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="number" id="sp-temperature" name="temperature" class="sp-input sp-mono" min="0" max="1" step="0.1" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" />
								<span class="sp-help"><?php esc_html_e( 'Lower = more deterministic. Recommended: 0.2. Ignored by models that no longer accept a sampling temperature.', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
						</div>

						<div>
							<div class="sp-label sp-label--14" style="margin-bottom:8px"><?php esc_html_e( 'Where should the key live?', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-tiles sp-tiles--250">
								<label class="sp-check sp-check--top sp-check--card<?php echo 'keyvault' === $key_mode ? ' is-on' : ''; ?>">
									<input type="radio" name="key_management" value="keyvault" <?php checked( 'keyvault', $key_mode ); ?> />
									<span class="sp-check__body">
										<strong><?php esc_html_e( 'AISAAutoPilot Encrypted Vault', 'ai-site-assistant-autopilot' ); ?></strong>
										<small><?php esc_html_e( 'API key encrypted with XSalsa20-Poly1305 / AES-256-GCM and stored on this site.', 'ai-site-assistant-autopilot' ); ?></small>
									</span>
								</label>
								<label class="sp-check sp-check--top sp-check--card<?php echo 'wp_connectors' === $key_mode ? ' is-on' : ''; ?><?php echo $wp_conn ? '' : ' is-off'; ?>">
									<input type="radio" name="key_management" value="wp_connectors" <?php checked( 'wp_connectors', $key_mode ); ?> <?php disabled( ! $wp_conn ); ?> />
									<span class="sp-check__body">
										<strong>WordPress Connectors</strong>
										<small>
											<?php if ( $wp_conn ) : ?>
												<?php esc_html_e( 'Use the native WordPress key manager.', 'ai-site-assistant-autopilot' ); ?>
												<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-connectors' ) ); ?>"><?php esc_html_e( 'Settings → Connectors', 'ai-site-assistant-autopilot' ); ?></a>
											<?php else : ?>
												<?php esc_html_e( 'Available in WordPress 7.0+. Update WordPress to enable this option.', 'ai-site-assistant-autopilot' ); ?>
											<?php endif; ?>
										</small>
									</span>
								</label>
							</div>
							<p class="sp-help sp-help--12" style="margin-top:8px"><?php esc_html_e( 'For your security the key is never displayed or copyable again — you can only replace it.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<div class="sp-hr"></div>

						<div>
							<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( '2. Test the connection', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub" style="max-width:700px;font-size:13px"><?php esc_html_e( 'One short request confirms the key, endpoint and model work together. Unsaved values in the fields above are used, so you can verify before saving.', 'ai-site-assistant-autopilot' ); ?></p>

							<div class="sp-testbox" id="sp-setup-testbox">
								<span style="flex:0 0 auto;margin-top:2px;display:flex">
									<span class="sp-mark" id="sp-setup-testmark" style="background:#1d2327">?</span>
								</span>
								<div style="min-width:0">
									<div class="sp-testbox__title" id="sp-setup-testtitle"><?php esc_html_e( 'Not tested yet', 'ai-site-assistant-autopilot' ); ?></div>
									<div class="sp-testbox__body" id="sp-setup-testbody"><?php esc_html_e( 'Run a short test to confirm the key and endpoint.', 'ai-site-assistant-autopilot' ); ?></div>
									<div class="sp-testbox__meta" id="sp-setup-testmeta"><?php echo esc_html( sprintf( '%s · %s', (string) $preset['label'], 'anthropic' === $preset['api'] ? 'messages' : 'chat/completions' ) ); ?></div>
								</div>
							</div>

							<div class="sp-row-wrap" style="margin-top:12px">
								<button type="button" id="sp-test" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Test connection', 'ai-site-assistant-autopilot' ); ?></button>
								<span id="sp-test-result" class="sp-status-text" role="status" aria-live="polite"></span>
							</div>
						</div>

						<div class="sp-hr"></div>

						<div>
							<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( '3. Semantic search (embeddings)', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub" style="max-width:700px;font-size:13px"><?php esc_html_e( 'When enabled, AISAAutoPilot ranks knowledge by meaning instead of keywords. It needs a provider with an /embeddings endpoint — OpenAI, Gemini, Qwen and Mistral have one; Anthropic, DeepSeek, Groq and Perplexity do not, so point the fields below at a provider that does.', 'ai-site-assistant-autopilot' ); ?></p>
							<label class="sp-check">
								<input type="checkbox" name="embeddings_enabled" value="1" <?php checked( ! empty( $s['embeddings_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Enable semantic search', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<p class="sp-help" style="margin-top:6px;padding-inline-start:26px">
								<?php
								printf(
									/* translators: %d: number of indexed items that already have an embedding. */
									esc_html__( '%d indexed items currently have an embedding. After enabling, run indexing on the Knowledge Base page to build the missing vectors.', 'ai-site-assistant-autopilot' ),
									(int) $embedded
								);
								?>
							</p>

							<div class="sp-tiles sp-tiles--240" style="margin-top:14px">
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Embedding Model', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="text" id="sp-embedding-model" name="embedding_model" class="sp-input sp-mono" value="<?php echo esc_attr( (string) ( $s['embedding_model'] ?? '' ) ); ?>" placeholder="text-embedding-3-small" list="sp-embedding-models" />
									<datalist id="sp-embedding-models"></datalist>
								</label>
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Embedding Base URL (optional)', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="url" name="embedding_base_url" class="sp-input sp-mono" value="<?php echo esc_attr( (string) ( $s['embedding_base_url'] ?? '' ) ); ?>" placeholder="https://api.openai.com/v1" />
									<span class="sp-help"><?php esc_html_e( 'Leave blank to use the same endpoint as your chat provider.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Embedding API Key (optional)', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="password" name="embedding_api_key" class="sp-input" autocomplete="off" dir="ltr"
										placeholder="<?php echo $has_emb_key ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'ai-site-assistant-autopilot' ) : esc_attr__( 'Leave blank to reuse your provider API key', 'ai-site-assistant-autopilot' ); ?>" />
									<?php if ( $has_emb_key ) : ?>
										<label class="sp-check" style="font-size:12.5px">
											<input type="checkbox" name="clear_embedding_key" value="1" />
											<span><?php esc_html_e( 'Remove saved embedding key', 'ai-site-assistant-autopilot' ); ?></span>
										</label>
									<?php endif; ?>
								</label>
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Speech-to-text model', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="text" name="voice_model" class="sp-input sp-mono" value="<?php echo esc_attr( (string) $s['voice_model'] ); ?>" placeholder="whisper-1" />
									<span class="sp-help"><?php esc_html_e( 'Audio is sent only for transcription and is never stored by AISAAutoPilot.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
							</div>
						</div>

						<div class="sp-hr"></div>

						<div>
							<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( '4. Forms: connector and delivery', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub" style="max-width:700px;font-size:13px"><?php esc_html_e( 'The connector is what actually creates the form in WordPress.', 'ai-site-assistant-autopilot' ); ?></p>

							<div class="sp-tiles sp-tiles--md" style="margin:12px 0 16px">
								<div class="sp-conn-card sp-conn-card--on">
									<div class="sp-conn-card__head">
										<span class="sp-conn-card__name">AISAAutoPilot Forms</span>
										<?php echo Ui::pill( __( 'Active', 'ai-site-assistant-autopilot' ), 'ok', true, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
									</div>
									<p class="sp-conn-card__note"><?php esc_html_e( 'Built in. Always available, no extra plugin.', 'ai-site-assistant-autopilot' ); ?></p>
									<div class="sp-tags">
										<span class="sp-tag"><?php esc_html_e( 'create form', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-tag"><?php esc_html_e( 'draft', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
								</div>
								<div class="sp-conn-card <?php echo $efb_ready ? 'sp-conn-card--ok' : 'sp-conn-card--warn'; ?>">
									<div class="sp-conn-card__head">
										<span class="sp-conn-card__name">Easy Form Builder</span>
										<?php echo Ui::pill( $efb_ready ? __( 'Active', 'ai-site-assistant-autopilot' ) : __( 'Not installed', 'ai-site-assistant-autopilot' ), $efb_ready ? 'ok' : 'warn', true, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
									</div>
									<p class="sp-conn-card__note"><?php echo esc_html( $efb_ready ? __( 'Installed and compatible. Generated forms are editable directly in EFB.', 'ai-site-assistant-autopilot' ) : __( 'Install Easy Form Builder for advanced fields, logic, files and multi-step forms.', 'ai-site-assistant-autopilot' ) ); ?></p>
									<div class="sp-tags">
										<span class="sp-tag"><?php esc_html_e( 'create form', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-tag"><?php esc_html_e( 'edit in EFB', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-tag">shortcode</span>
									</div>
								</div>
							</div>

							<div class="sp-tiles sp-tiles--240">
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Default form status', 'ai-site-assistant-autopilot' ); ?></span>
									<select name="default_form_status" class="sp-select">
										<option value="draft" <?php selected( $s['default_form_status'], 'draft' ); ?>><?php esc_html_e( 'Draft (recommended)', 'ai-site-assistant-autopilot' ); ?></option>
										<option value="publish" <?php selected( $s['default_form_status'], 'publish' ); ?>><?php esc_html_e( 'Publish immediately', 'ai-site-assistant-autopilot' ); ?></option>
									</select>
									<span class="sp-help"><?php esc_html_e( 'Publishing is a higher-risk action and always asks again.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Notification email', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="email" id="sp-form-notification-email" name="form_notification_email" class="sp-input" dir="ltr" value="<?php echo esc_attr( Settings::form_notification_email() ); ?>" />
									<span class="sp-help"><?php esc_html_e( 'Every submission is delivered only to this address and is not saved in WordPress. Leave blank to use the site Administration Email Address.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
							</div>
							<div class="sp-row-wrap" style="margin-top:10px">
								<button type="button" id="sp-test-form-delivery" class="sp-btn sp-btn--ghost sp-btn--r8"><?php esc_html_e( 'Send delivery test', 'ai-site-assistant-autopilot' ); ?></button>
								<span id="sp-form-delivery-test-result" class="sp-status-text" role="status" aria-live="polite"></span>
							</div>
						</div>

						<div class="sp-hr"></div>

						<div class="sp-stack sp-stack--md">
							<h2 class="sp-h2 sp-h2--md sp-mb0"><?php esc_html_e( '5. Site assistant', 'ai-site-assistant-autopilot' ); ?></h2>
							<label class="sp-check">
								<input type="checkbox" name="public_assistant_enabled" value="1" <?php checked( ! empty( $s['public_assistant_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Enable the public site assistant (text and optional voice input)', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<span class="sp-help" style="padding-inline-start:26px"><?php esc_html_e( 'The visitor assistant is read-only and rate-limited. It never edits site content.', 'ai-site-assistant-autopilot' ); ?></span>
							<label class="sp-check">
								<input type="checkbox" name="voice_responses_enabled" value="1" <?php checked( ! empty( $s['voice_responses_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Read AI replies aloud', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<label class="sp-check">
								<input type="checkbox" name="prefer_efb" value="1" <?php checked( ! empty( $s['prefer_efb'] ) ); ?> />
								<span><?php esc_html_e( 'Prefer Easy Form Builder when available', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
						</div>

						<div class="sp-form-actions">
							<button type="submit" class="sp-btn sp-btn--r8"><?php esc_html_e( 'Save settings', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
					</div>
				</form>

				<div class="sp-card sp-card--24" style="margin-bottom:16px">
					<h2 class="sp-h2 sp-h2--md" style="margin-bottom:6px !important"><?php esc_html_e( '6. Prepare the knowledge base', 'ai-site-assistant-autopilot' ); ?></h2>
					<p class="sp-sub" style="max-width:700px;font-size:13px">
						<?php
						printf(
							/* translators: 1: number of indexed items, 2: number of public posts and pages. */
							esc_html__( 'Only published, public content is indexed. %1$s of about %2$s public items are indexed so far. Building forms does not need the knowledge base.', 'ai-site-assistant-autopilot' ),
							esc_html( number_format_i18n( $kb_total ) ),
							esc_html( number_format_i18n( $indexable ) )
						);
						?>
					</p>
					<a class="sp-btn sp-btn--ghost sp-btn--r8" style="margin-top:10px" href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Open the Knowledge Base', 'ai-site-assistant-autopilot' ); ?></a>
				</div>

				<div class="sp-card sp-card--24">
					<h2 class="sp-h2 sp-h2--md" style="margin-bottom:14px !important"><?php esc_html_e( 'Form connectors', 'ai-site-assistant-autopilot' ); ?></h2>
					<div class="sp-tr sp-tr--head" style="--sp-cols:1.4fr .7fr 2fr;background:transparent;padding:0 0 10px;border-block-end:1px solid var(--sp-line-soft)">
						<span><?php esc_html_e( 'Connector', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Notes', 'ai-site-assistant-autopilot' ); ?></span>
					</div>
					<?php
					/** @var ConnectorRegistry $registry */
					$registry = Plugin::instance()->container()->get( ConnectorRegistry::class );
					foreach ( $registry->all() as $connector ) :
						$health = $connector->get_health();
						?>
						<div class="sp-tr" style="--sp-cols:1.4fr .7fr 2fr;padding:13px 0">
							<span class="sp-td sp-td--strong"><?php echo esc_html( $connector->get_name() ); ?></span>
							<span class="sp-td">
								<?php
								echo wp_kses_post(
									Ui::pill(
										$health['available'] ? __( 'Available', 'ai-site-assistant-autopilot' ) : __( 'Not active', 'ai-site-assistant-autopilot' ),
										$health['available'] ? 'ok' : 'off',
										false
									)
								);
								?>
							</span>
							<span class="sp-td sp-td--muted"><?php echo esc_html( (string) $health['notes'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script( 'sp-provider-catalog', $this->catalog() );
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
		$key_mode      = (string) ( $settings['key_management'] ?? 'keyvault' );
		$wp_connectors = WpConnectors::is_available();
		$has_key       = '' !== WpConnectors::get_effective_api_key();
		$is_verified   = (int) get_option( self::VERIFIED_OPTION, 0 ) > 0;
		$efb_ready     = ( new EasyFormBuilderConnector() )->is_available();
		$woo_available = WooCommerceConnector::is_active();
		$woo_settings  = (array) ( $settings['woocommerce'] ?? array() );
		$woo_enabled   = ! array_key_exists( 'enabled', $woo_settings ) || ! empty( $woo_settings['enabled'] );
		$model_list    = (array) $preset['models'];
		$saved_model   = (string) $settings['model'];
		$advanced_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS );
		$kb_url        = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$answer_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS );
		$store_url     = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WC_DASHBOARD );

		Ui::grad_defs();
		?>
		<div
			class="wrap sp-app sp-app--tight"
			data-sp-setup-wizard
			data-saved="<?php echo $saved ? '1' : '0'; ?>"
			data-verified="<?php echo $is_verified ? '1' : '0'; ?>"
			data-wp-connectors="<?php echo $wp_connectors ? '1' : '0'; ?>"
		>
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Set up AutoPilot AI', 'ai-site-assistant-autopilot' ),
					__( 'Four short steps to connect a provider and choose what AutoPilot can do on this site.', 'ai-site-assistant-autopilot' ),
					array( 'chip' => ConnectionChip::derive() )
				);
				?>

				<?php if ( $saved ) : ?>
					<?php Ui::alert( 'ok', 'check-circle', esc_html__( 'Setup saved. AutoPilot will use the settings below.', 'ai-site-assistant-autopilot' ) ); ?>
				<?php endif; ?>
				<?php if ( '' !== $this->save_error ) : ?>
					<?php Ui::alert( 'err', 'alert-circle', $this->save_error ); ?>
				<?php endif; ?>

				<form method="post" data-sp-setup-form>
					<?php wp_nonce_field( 'aisaautopilot_settings_save', 'aisaautopilot_settings_nonce' ); ?>
					<input type="hidden" name="setup_wizard" value="1" />

					<ol class="sp-setup-progress" aria-label="<?php esc_attr_e( 'Setup progress', 'ai-site-assistant-autopilot' ); ?>" data-sp-setup-progress>
						<?php
						$wizard_steps = array(
							__( 'Connection', 'ai-site-assistant-autopilot' ),
							__( 'AI service', 'ai-site-assistant-autopilot' ),
							__( 'Key & model', 'ai-site-assistant-autopilot' ),
							__( 'Integrations', 'ai-site-assistant-autopilot' ),
						);
						foreach ( $wizard_steps as $index => $label ) :
							?>
							<li class="sp-setup-progress__item<?php echo 0 === $index ? ' is-current' : ''; ?>" data-sp-setup-progress-step="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
								<span class="sp-setup-progress__number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
								<span><?php echo esc_html( $label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>

					<section class="sp-setup-panel" data-sp-setup-step="1">
						<div class="sp-setup-panel__head">
							<span class="sp-setup-panel__icon"><?php echo Ui::icon( 'lock', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div>
								<h2 class="sp-h2"><?php esc_html_e( 'Choose where the API key lives', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-sub sp-mb0"><?php esc_html_e( 'The provider key stays on this WordPress site and is sent only to the service you choose.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
						</div>

						<div class="sp-setup-choices">
							<label class="sp-setup-choice<?php echo 'keyvault' === $key_mode ? ' is-selected' : ''; ?>" data-sp-key-mode="keyvault">
								<input type="radio" name="key_management" value="keyvault" <?php checked( 'keyvault', $key_mode ); ?> />
								<span class="sp-setup-choice__mark"><?php echo Ui::icon( 'shield', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-choice__body">
									<strong><?php esc_html_e( 'Encrypted vault', 'ai-site-assistant-autopilot' ); ?></strong>
									<small><?php esc_html_e( 'Recommended. The key is encrypted with this site’s WordPress keys before it is stored.', 'ai-site-assistant-autopilot' ); ?></small>
								</span>
								<span class="sp-pill sp-pill--ok"><span class="sp-dot"></span><?php esc_html_e( 'Recommended', 'ai-site-assistant-autopilot' ); ?></span>
							</label>

							<label class="sp-setup-choice<?php echo 'wp_connectors' === $key_mode ? ' is-selected' : ''; ?><?php echo $wp_connectors ? '' : ' is-disabled'; ?>" data-sp-key-mode="wp_connectors">
								<input type="radio" name="key_management" value="wp_connectors" <?php checked( 'wp_connectors', $key_mode ); ?> <?php disabled( ! $wp_connectors ); ?> />
								<span class="sp-setup-choice__mark"><?php echo Ui::icon( 'plug', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-choice__body">
									<strong>WordPress Connectors</strong>
									<small>
										<?php
										if ( $wp_connectors ) {
											esc_html_e( 'Use WordPress’s native connector key manager for this provider credential.', 'ai-site-assistant-autopilot' );
										} else {
											esc_html_e( 'Available when the WordPress Connectors API is present on this site.', 'ai-site-assistant-autopilot' );
										}
										?>
									</small>
								</span>
								<?php if ( $wp_connectors ) : ?>
									<a class="sp-setup-choice__link" href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-connectors' ) ); ?>"><?php esc_html_e( 'Open connectors', 'ai-site-assistant-autopilot' ); ?></a>
								<?php endif; ?>
							</label>
						</div>
						<p class="sp-setup-note"><?php esc_html_e( 'For security, a saved key is never shown again. You can replace or remove it, but cannot copy it back out.', 'ai-site-assistant-autopilot' ); ?></p>
					</section>

					<section class="sp-setup-panel" data-sp-setup-step="2" hidden>
						<div class="sp-setup-panel__head">
							<span class="sp-setup-panel__icon"><?php echo Ui::icon( 'spark', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div>
								<h2 class="sp-h2"><?php esc_html_e( 'Pick an AI service', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-sub sp-mb0"><?php esc_html_e( 'Choose a service and AutoPilot fills in its endpoint and recommended model. You can still use a custom OpenAI-compatible endpoint.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
						</div>

						<div class="sp-setup-provider-tools">
							<label class="sp-setup-search">
								<span class="sp-sr"><?php esc_html_e( 'Search AI services', 'ai-site-assistant-autopilot' ); ?></span>
								<?php echo Ui::icon( 'search', 17, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<input type="search" id="sp-setup-provider-search" placeholder="<?php esc_attr_e( 'Search services', 'ai-site-assistant-autopilot' ); ?>" autocomplete="off" />
							</label>
							<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-sp-provider-filter="popular"><?php esc_html_e( 'Popular', 'ai-site-assistant-autopilot' ); ?></button>
							<button type="button" class="sp-btn sp-btn--ghost sp-btn--sm" data-sp-provider-filter="all">
							<?php
								/* translators: %d: number of available AI services. */
								printf( esc_html__( 'All %d services', 'ai-site-assistant-autopilot' ), count( $presets ) );
							?>
								</button>
						</div>

						<input type="hidden" id="sp-provider" name="provider" value="<?php echo esc_attr( $provider ); ?>" />
						<div class="sp-setup-providers" data-sp-provider-grid>
							<?php foreach ( $presets as $key => $entry ) : ?>
								<?php
								$key     = (string) $key;
								$popular = in_array( $key, self::QUICK_PICKS, true );
								?>
								<button
									type="button"
									class="sp-setup-provider<?php echo $provider === $key ? ' is-selected' : ''; ?>"
									data-sp-provider-card
									data-provider="<?php echo esc_attr( $key ); ?>"
									data-popular="<?php echo $popular ? '1' : '0'; ?>"
									aria-pressed="<?php echo $provider === $key ? 'true' : 'false'; ?>"
								>
									<span class="sp-setup-provider__mark"><?php echo BrandMarks::svg( $key, 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG path data. ?></span>
									<span class="sp-setup-provider__body">
										<strong><?php echo esc_html( (string) $entry['label'] ); ?></strong>
										<small><?php echo esc_html( $this->provider_trait( $key, $entry ) ); ?></small>
									</span>
									<?php if ( 'openai' === $key ) : ?>
										<span class="sp-pill sp-pill--ok"><?php esc_html_e( 'Recommended', 'ai-site-assistant-autopilot' ); ?></span>
									<?php endif; ?>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="sp-empty" data-sp-provider-empty hidden>
							<span class="sp-empty__icon">⌕</span>
							<?php esc_html_e( 'No matching service. Choose Custom endpoint to enter a compatible URL yourself.', 'ai-site-assistant-autopilot' ); ?>
						</div>
					</section>

					<section class="sp-setup-panel" data-sp-setup-step="3" hidden>
						<div class="sp-setup-panel__head">
							<span class="sp-setup-panel__icon"><?php echo Ui::icon( 'lock', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div>
								<h2 class="sp-h2"><?php esc_html_e( 'Add a key and test it', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-sub sp-mb0"><?php esc_html_e( 'The test uses the values on this screen before they are saved. A successful test is required before continuing.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
						</div>

						<div class="sp-tiles sp-tiles--240">
							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'API key', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="password" id="sp-api-key" name="api_key" class="sp-input sp-mono" autocomplete="off" dir="ltr" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'ai-site-assistant-autopilot' ) : esc_attr__( 'Paste an API key', 'ai-site-assistant-autopilot' ); ?>" />
								<span class="sp-help" data-sp-setup-key-source>
									<?php
									printf(
										/* translators: %s: AI provider label. */
										esc_html__( 'Get a key from %s.', 'ai-site-assistant-autopilot' ),
										esc_html( (string) $preset['label'] )
									);
									?>
									<?php if ( '' !== (string) $preset['key_url'] ) : ?>
										<a id="sp-key-link" href="<?php echo esc_url( (string) $preset['key_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open key page', 'ai-site-assistant-autopilot' ); ?></a>
									<?php endif; ?>
								</span>
								<?php if ( Settings::has_api_key() ) : ?>
									<label class="sp-check sp-setup-clear-key"><input type="checkbox" name="clear_api_key" value="1" /> <span><?php esc_html_e( 'Remove the saved vault key', 'ai-site-assistant-autopilot' ); ?></span></label>
								<?php endif; ?>
							</label>

							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'API base URL', 'ai-site-assistant-autopilot' ); ?></span>
								<input type="url" id="sp-base-url" name="base_url" class="sp-input sp-mono" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" placeholder="https://api.openai.com/v1" />
								<span class="sp-help"><?php esc_html_e( 'Enter only the provider base URL, not a full chat-completions path.', 'ai-site-assistant-autopilot' ); ?></span>
							</label>

							<label class="sp-field">
								<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Model', 'ai-site-assistant-autopilot' ); ?></span>
								<select id="sp-model-select" class="sp-select">
									<?php if ( '' !== $saved_model && ! array_key_exists( $saved_model, $model_list ) ) : ?>
										<option value="<?php echo esc_attr( $saved_model ); ?>" selected><?php echo esc_html( sprintf( '%s — %s', $saved_model, __( 'Saved model', 'ai-site-assistant-autopilot' ) ) ); ?></option>
									<?php endif; ?>
									<?php foreach ( $model_list as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( (string) $model_id ); ?>" <?php selected( $saved_model, (string) $model_id ); ?>><?php echo esc_html( (string) $model_label ); ?></option>
									<?php endforeach; ?>
									<option value="__custom"><?php esc_html_e( 'Other — type a model name', 'ai-site-assistant-autopilot' ); ?></option>
								</select>
								<input type="text" id="sp-model" name="model" class="sp-input sp-mono sp-mt12" value="<?php echo esc_attr( $saved_model ); ?>" placeholder="gpt-5.6-luna" />
								<span class="sp-row-wrap sp-row-wrap--sm sp-mt12">
									<button type="button" id="sp-refresh-models" class="sp-btn sp-btn--ghost sp-btn--sm"><?php echo Ui::icon( 'refresh', 14, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Refresh models', 'ai-site-assistant-autopilot' ); ?></span></button>
									<span id="sp-models-status" class="sp-status-text" role="status" aria-live="polite"></span>
								</span>
							</label>
						</div>

						<div class="sp-testbox" id="sp-setup-testbox" data-sp-setup-testbox>
							<span class="sp-mark" id="sp-setup-testmark" style="background:#1d2327">?</span>
							<div>
								<div class="sp-testbox__title" id="sp-setup-testtitle"><?php echo $is_verified ? esc_html__( 'Previously verified', 'ai-site-assistant-autopilot' ) : esc_html__( 'Not tested yet', 'ai-site-assistant-autopilot' ); ?></div>
								<div class="sp-testbox__body" id="sp-setup-testbody"><?php echo $is_verified ? esc_html__( 'The saved provider passed a previous connection check. Changing these values requires another test.', 'ai-site-assistant-autopilot' ) : esc_html__( 'Run a short connection test before continuing.', 'ai-site-assistant-autopilot' ); ?></div>
								<div class="sp-testbox__meta" id="sp-setup-testmeta"><?php echo esc_html( (string) $preset['label'] . ' · ' . ( 'anthropic' === $preset['api'] ? 'messages' : 'chat/completions' ) ); ?></div>
							</div>
						</div>
						<div class="sp-row-wrap">
							<button type="button" class="sp-btn sp-btn--ghost" id="sp-test" data-sp-setup-test><?php esc_html_e( 'Test connection', 'ai-site-assistant-autopilot' ); ?></button>
							<span id="sp-test-result" class="sp-status-text" role="status" aria-live="polite"></span>
						</div>

						<details class="sp-setup-advanced">
							<summary><?php esc_html_e( 'Advanced connection controls', 'ai-site-assistant-autopilot' ); ?></summary>
							<div class="sp-tiles sp-tiles--240 sp-mt12">
								<label class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Temperature', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="number" id="sp-temperature" name="temperature" class="sp-input sp-mono" min="0" max="1" step="0.1" value="<?php echo esc_attr( (string) $settings['temperature'] ); ?>" />
									<span class="sp-help"><?php esc_html_e( 'Lower values make replies more consistent. 0.2 is the recommended default.', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-field">
									<span class="sp-label sp-label--14 sp-mb0"><?php esc_html_e( 'Reply length', 'ai-site-assistant-autopilot' ); ?></span>
									<div class="sp-setup-disabled-control" aria-disabled="true">
										<span><?php esc_html_e( 'Response-length controls are not available in this release.', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<span class="sp-help"><?php esc_html_e( 'This mockup control stays visible but disabled until the provider request contract supports it.', 'ai-site-assistant-autopilot' ); ?></span>
								</div>
							</div>
							<label class="sp-check sp-mt12">
								<input type="hidden" name="embeddings_enabled" value="0" />
								<input type="checkbox" name="embeddings_enabled" value="1" <?php checked( ! empty( $settings['embeddings_enabled'] ) ); ?> />
								<span><?php esc_html_e( 'Use semantic search for Site Knowledge', 'ai-site-assistant-autopilot' ); ?></span>
							</label>
							<p class="sp-help sp-mt12" data-sp-setup-embeddings-note><?php echo esc_html( array() === (array) $preset['embeddings'] ? __( 'This provider does not publish an embeddings endpoint. Configure a separate embedding provider in Advanced Settings if you enable semantic search.', 'ai-site-assistant-autopilot' ) : __( 'This provider supports embeddings. You can configure a separate embedding key in Advanced Settings when needed.', 'ai-site-assistant-autopilot' ) ); ?> <a href="<?php echo esc_url( $advanced_url ); ?>"><?php esc_html_e( 'Open Advanced Settings', 'ai-site-assistant-autopilot' ); ?></a></p>
						</details>
					</section>

					<section class="sp-setup-panel" data-sp-setup-step="4" hidden>
						<div class="sp-setup-panel__head">
							<span class="sp-setup-panel__icon"><?php echo Ui::icon( 'plug', 22, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<div>
								<h2 class="sp-h2"><?php esc_html_e( 'Choose your integrations', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-sub sp-mb0"><?php esc_html_e( 'Start with the useful features for this site. You can change every choice later from the relevant page.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
						</div>

						<div class="sp-setup-integrations" data-sp-setup-integrations>
							<label class="sp-setup-integration">
								<input type="hidden" name="kb_auto_index" value="0" />
								<input class="sp-setup-integration__toggle" type="checkbox" name="kb_auto_index" value="1" <?php checked( ! empty( $settings['kb_auto_index'] ) ); ?> />
								<span class="sp-setup-integration__icon"><?php echo Ui::icon( 'book', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-integration__body"><strong><?php esc_html_e( 'Site Knowledge', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'Index new published content automatically so replies can use it.', 'ai-site-assistant-autopilot' ); ?></small><a href="<?php echo esc_url( $kb_url ); ?>"><?php esc_html_e( 'Open Site Knowledge', 'ai-site-assistant-autopilot' ); ?></a></span>
							</label>

							<label class="sp-setup-integration">
								<input type="hidden" name="public_assistant_enabled" value="0" />
								<input class="sp-setup-integration__toggle" type="checkbox" name="public_assistant_enabled" value="1" <?php checked( ! empty( $settings['public_assistant_enabled'] ) ); ?> />
								<span class="sp-setup-integration__icon"><?php echo Ui::icon( 'chat', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-integration__body"><strong><?php esc_html_e( 'Answer Desk', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'Enable the read-only public assistant for visitor questions.', 'ai-site-assistant-autopilot' ); ?></small><a href="<?php echo esc_url( $answer_url ); ?>"><?php esc_html_e( 'Open Answer Desk', 'ai-site-assistant-autopilot' ); ?></a></span>
							</label>

							<label class="sp-setup-integration">
								<input type="hidden" name="widget[enabled]" value="0" />
								<input class="sp-setup-integration__toggle" type="checkbox" name="widget[enabled]" value="1" <?php checked( ! empty( $settings['widget']['enabled'] ) ); ?> />
								<span class="sp-setup-integration__icon"><?php echo Ui::icon( 'globe', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-integration__body"><strong><?php esc_html_e( 'Website chat widget', 'ai-site-assistant-autopilot' ); ?></strong><small><?php esc_html_e( 'Show the visitor assistant launcher on the public site.', 'ai-site-assistant-autopilot' ); ?></small><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIDGET ) ); ?>"><?php esc_html_e( 'Customize widget', 'ai-site-assistant-autopilot' ); ?></a></span>
							</label>

							<label class="sp-setup-integration<?php echo $woo_available ? '' : ' is-disabled'; ?>">
								<?php if ( $woo_available ) : ?>
									<input type="hidden" name="woocommerce[enabled]" value="0" />
								<?php endif; ?>
								<input class="sp-setup-integration__toggle" type="checkbox" name="woocommerce[enabled]" value="1" <?php checked( $woo_enabled ); ?> <?php disabled( ! $woo_available ); ?> />
								<span class="sp-setup-integration__icon"><?php echo Ui::icon( 'cart', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-integration__body">
									<strong><?php esc_html_e( 'Store Copilot', 'ai-site-assistant-autopilot' ); ?></strong>
									<small><?php echo esc_html( $woo_available ? __( 'Create reviewed simple-product drafts and view safe store insights.', 'ai-site-assistant-autopilot' ) : __( 'WooCommerce is not active, so Store Copilot remains unavailable.', 'ai-site-assistant-autopilot' ) ); ?></small>
									<?php if ( $woo_available ) : ?>
										<a href="<?php echo esc_url( $store_url ); ?>"><?php esc_html_e( 'Open Store Copilot', 'ai-site-assistant-autopilot' ); ?></a>
									<?php endif; ?>
								</span>
							</label>

							<label class="sp-setup-integration<?php echo $efb_ready ? '' : ' is-disabled'; ?>">
								<?php if ( $efb_ready ) : ?>
									<input type="hidden" name="prefer_efb" value="0" />
								<?php endif; ?>
								<input class="sp-setup-integration__toggle" type="checkbox" name="prefer_efb" value="1" <?php checked( ! empty( $settings['prefer_efb'] ) ); ?> <?php disabled( ! $efb_ready ); ?> />
								<span class="sp-setup-integration__icon"><?php echo Ui::icon( 'form', 19, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-setup-integration__body"><strong>Easy Form Builder</strong><small><?php echo esc_html( $efb_ready ? __( 'Use it as the preferred form connector when a form is generated.', 'ai-site-assistant-autopilot' ) : __( 'Install and activate Easy Form Builder to enable this connector.', 'ai-site-assistant-autopilot' ) ); ?></small></span>
							</label>
						</div>

						<div class="sp-setup-guardrails">
							<span><?php echo Ui::icon( 'shield', 18, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
							<p><strong><?php esc_html_e( 'Safe by default.', 'ai-site-assistant-autopilot' ); ?></strong> <?php esc_html_e( 'Content and products are proposed as drafts for review; the public assistant is read-only.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>
					</section>

					<section class="sp-setup-complete" data-sp-setup-complete hidden>
						<span class="sp-done__icon"><?php echo Ui::icon( 'check', 24, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
						<h2 class="sp-h2"><?php esc_html_e( 'AutoPilot AI is ready', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-sub">
						<?php
							printf(
								/* translators: 1: AI provider label, 2: selected model ID. */
								esc_html__( '%1$s is configured on %2$s. Your integration choices were saved.', 'ai-site-assistant-autopilot' ),
								esc_html( (string) $preset['label'] ),
								esc_html( $saved_model )
							);
						?>
							</p>
						<div class="sp-btn-group">
							<a class="sp-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME ) ); ?>"><?php esc_html_e( 'Go to Overview', 'ai-site-assistant-autopilot' ); ?></a>
							<a class="sp-btn sp-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS ) ); ?>"><?php esc_html_e( 'Review integrations', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					</section>

					<footer class="sp-setup-footer" data-sp-setup-footer>
						<button type="button" class="sp-btn sp-btn--ghost" data-sp-setup-back disabled><?php esc_html_e( 'Back', 'ai-site-assistant-autopilot' ); ?></button>
						<span class="sp-setup-footer__note" data-sp-setup-block-note role="status" aria-live="polite"></span>
						<button type="button" class="sp-btn" data-sp-setup-next><?php esc_html_e( 'Next', 'ai-site-assistant-autopilot' ); ?></button>
						<button type="submit" class="sp-btn" data-sp-setup-save hidden><?php esc_html_e( 'Save and finish', 'ai-site-assistant-autopilot' ); ?></button>
					</footer>
				</form>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script( 'sp-provider-catalog', $this->catalog() );
	}

	/**
	 * The provider catalogue the Settings script drives its dropdowns from.
	 *
	 * @return array<string, mixed>
	 */
	private function catalog(): array {
		$catalog = array();

		foreach ( Settings::presets() as $key => $preset ) {
			$catalog[ (string) $key ] = array(
				'label'      => (string) $preset['label'],
				'baseUrl'    => (string) $preset['base_url'],
				'model'      => (string) $preset['model'],
				'keyUrl'     => (string) $preset['key_url'],
				'api'        => (string) $preset['api'],
				'note'       => (string) $preset['note'],
				'models'     => (array) $preset['models'],
				'embeddings' => (array) $preset['embeddings'],
			);
		}

		return array(
			'providers' => $catalog,
			'i18n'      => array(
				'other'    => __( 'Other — type a model name', 'ai-site-assistant-autopilot' ),
				'keyLink'  => __( 'Get an API key', 'ai-site-assistant-autopilot' ),
				'loading'  => __( 'Loading models…', 'ai-site-assistant-autopilot' ),
				'saved'    => __( 'Saved model', 'ai-site-assistant-autopilot' ),
				'endpoint' => 'anthropic' === Settings::provider_api() ? 'messages' : 'chat/completions',
			),
		);
	}

	/**
	 * Short provider-card descriptors from the setup mockup. Filtered providers
	 * use a neutral, truthful description instead of inheriting a misleading
	 * trait from a preset they merely resemble.
	 *
	 * @param array<string, mixed> $entry
	 */
	private function provider_trait( string $key, array $entry ): string {
		$traits = array(
			'openai'     => __( 'Most widely supported', 'ai-site-assistant-autopilot' ),
			'anthropic'  => __( 'Claude models', 'ai-site-assistant-autopilot' ),
			'gemini'     => __( 'Google AI Studio', 'ai-site-assistant-autopilot' ),
			'deepseek'   => __( 'Reasoning focused', 'ai-site-assistant-autopilot' ),
			'qwen'       => __( 'Alibaba Cloud models', 'ai-site-assistant-autopilot' ),
			'mistral'    => __( 'European AI models', 'ai-site-assistant-autopilot' ),
			'groq'       => __( 'Fast inference', 'ai-site-assistant-autopilot' ),
			'openrouter' => __( 'Multi-provider gateway', 'ai-site-assistant-autopilot' ),
			'custom'     => __( 'OpenAI-compatible endpoint', 'ai-site-assistant-autopilot' ),
		);

		if ( isset( $traits[ $key ] ) ) {
			return $traits[ $key ];
		}

		return 'gateway' === (string) ( $entry['group'] ?? '' )
			? __( 'Provider gateway', 'ai-site-assistant-autopilot' )
			: __( 'Direct provider endpoint', 'ai-site-assistant-autopilot' );
	}

	/**
	 * The four things that have to be true before the plugin is fully useful.
	 *
	 * Derived from live state rather than a stored wizard cursor, so removing a
	 * key or emptying the index honestly reopens the step.
	 *
	 * @return array<int, array{label:string, done:bool}>
	 */
	private function steps( int $kb_total ): array {
		return array(
			array(
				'label' => __( 'Connect AI', 'ai-site-assistant-autopilot' ),
				'done'  => ( new ProviderManager() )->is_configured(),
			),
			array(
				'label' => __( 'Test connection', 'ai-site-assistant-autopilot' ),
				'done'  => (int) get_option( self::VERIFIED_OPTION, 0 ) > 0,
			),
			array(
				'label' => __( 'Form delivery', 'ai-site-assistant-autopilot' ),
				'done'  => '' !== Settings::form_notification_email(),
			),
			array(
				'label' => __( 'Knowledge base', 'ai-site-assistant-autopilot' ),
				'done'  => $kb_total > 0,
			),
		);
	}

	/**
	 * @param array<int, array{label:string, done:bool}> $steps
	 */
	private function render_progress( array $steps ): void {
		$remaining = 0;
		foreach ( $steps as $step ) {
			if ( ! $step['done'] ) {
				++$remaining;
			}
		}

		if ( 0 === $remaining ) {
			return;
		}

		$first_open = true;
		?>
		<div class="sp-card sp-card--tight sp-card--dashed" style="margin-bottom:16px">
			<div class="sp-h3" style="font-size:13px"><?php esc_html_e( 'Getting started', 'ai-site-assistant-autopilot' ); ?></div>
			<p class="sp-help sp-help--12" style="margin-bottom:10px">
				<?php
				printf(
					/* translators: %s: number of setup steps still outstanding. */
					esc_html__( 'Steps remaining: %s. You can leave and come back — nothing here is lost.', 'ai-site-assistant-autopilot' ),
					esc_html( number_format_i18n( $remaining ) )
				);
				?>
			</p>
			<ol class="sp-steps">
				<?php
				foreach ( $steps as $step ) :
					$state = '';
					if ( $step['done'] ) {
						$state = 'is-done';
					} elseif ( $first_open ) {
						$state      = 'is-current';
						$first_open = false;
					}
					?>
					<li class="sp-steps__item <?php echo esc_attr( $state ); ?>">
						<span class="sp-steps__dot"><?php echo $step['done'] ? '&#10003;' : '&bull;'; ?></span>
						<span class="sp-steps__label"><?php echo esc_html( $step['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['aisaautopilot_settings_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aisaautopilot_settings_nonce'] ) ), 'aisaautopilot_settings_save' ) ) {
			return false;
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
		if ( isset( $_POST['widget'] ) && is_array( $_POST['widget'] ) && array_key_exists( 'enabled', $_POST['widget'] ) ) {
			$settings_update['widget'] = array( 'enabled' => ! empty( $_POST['widget']['enabled'] ) );
		}
		if ( WooCommerceConnector::is_active() && isset( $_POST['woocommerce'] ) && is_array( $_POST['woocommerce'] ) && array_key_exists( 'enabled', $_POST['woocommerce'] ) ) {
			$woocommerce                    = (array) ( $existing['woocommerce'] ?? array() );
			$woocommerce['enabled']         = ! empty( $_POST['woocommerce']['enabled'] );
			$settings_update['woocommerce'] = $woocommerce;
		}

		Settings::update( $settings_update );

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
