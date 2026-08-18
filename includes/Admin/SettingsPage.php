<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
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

	/** The quick-pick chips above the full provider dropdown. */
	private const QUICK_PICKS = array( 'openai', 'anthropic', 'gemini', 'deepseek', 'qwen', 'mistral', 'groq', 'openrouter' );

	public function render(): void {
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
				<?php Ui::title( __( 'Setup & Settings', 'ai-site-assistant-autopilot' ), 'gear' ); ?>
				<p class="sp-lead" style="margin-bottom:16px !important"><?php esc_html_e( 'Connect an AI provider, choose a model, and set the safe defaults. Your key is never sent anywhere except the provider you choose.', 'ai-site-assistant-autopilot' ); ?></p>

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
		$status = ( 'publish' === sanitize_key( wp_unslash( $_POST['default_form_status'] ?? 'draft' ) ) ) ? 'publish' : 'draft';
		$email  = sanitize_email( (string) wp_unslash( $_POST['form_notification_email'] ?? '' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}
		$key_mgmt = sanitize_key( wp_unslash( $_POST['key_management'] ?? 'keyvault' ) );
		if ( ! in_array( $key_mgmt, array( 'keyvault', 'wp_connectors' ), true ) ) {
			$key_mgmt = 'keyvault';
		}

		$base_url = esc_url_raw( wp_unslash( $_POST['base_url'] ?? '' ) );
		$model    = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );

		// Changing where requests go invalidates an earlier "connection works"
		// result, so the progress strip reopens that step instead of vouching for
		// an endpoint nobody has tried.
		if ( (string) Settings::get( 'base_url' ) !== $base_url || (string) Settings::get( 'model' ) !== $model || (string) Settings::get( 'provider' ) !== $provider ) {
			delete_option( self::VERIFIED_OPTION );
		}

		Settings::update(
			array(
				'provider'                 => $provider,
				'base_url'                 => $base_url,
				'model'                    => $model,
				'temperature'              => max( 0.0, min( 1.0, (float) wp_unslash( $_POST['temperature'] ?? 0.2 ) ) ),
				'default_form_status'      => $status,
				'form_notification_email'  => $email,
				'key_management'           => $key_mgmt,
				'embeddings_enabled'       => ! empty( $_POST['embeddings_enabled'] ),
				'embedding_model'          => sanitize_text_field( wp_unslash( $_POST['embedding_model'] ?? '' ) ),
				'embedding_base_url'       => esc_url_raw( wp_unslash( $_POST['embedding_base_url'] ?? '' ) ),
				'voice_model'              => sanitize_text_field( wp_unslash( $_POST['voice_model'] ?? 'whisper-1' ) ),
				'voice_responses_enabled'  => ! empty( $_POST['voice_responses_enabled'] ),
				'public_assistant_enabled' => ! empty( $_POST['public_assistant_enabled'] ),
				'prefer_efb'               => ! empty( $_POST['prefer_efb'] ),
			)
		);

		if ( ! empty( $_POST['clear_api_key'] ) ) {
			Settings::set_api_key( '' );
			delete_option( self::VERIFIED_OPTION );
		} else {
			$api_key = (string) wp_unslash( $_POST['api_key'] ?? '' );
			if ( '' !== trim( $api_key ) ) {
				Settings::set_api_key( $api_key );
				delete_option( self::VERIFIED_OPTION );
			}
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
