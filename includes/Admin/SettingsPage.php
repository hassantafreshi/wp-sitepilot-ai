<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Plugin;
use SitePilot\Core\Settings;
use SitePilot\Core\WpConnectors;

/**
 * Settings screen: connect an AI provider (BYOK), choose model, and defaults.
 */
final class SettingsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$saved = $this->maybe_save();
		$s     = Settings::all();
		$wp_connectors_available = WpConnectors::is_available();
		$active_key_mode = WpConnectors::get_active_mode();

		$kb             = new \SitePilot\Content\KnowledgeBase();
		$embedded_count = $kb->table_exists() ? $kb->count_with_embeddings() : 0;
		?>
		<div class="wrap sitepilot-wrap sitepilot-wrap--narrow">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Settings', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sitepilot-ai' ); ?></p></div>
			<?php endif; ?>

			<div class="sitepilot-banner sitepilot-banner--info">
				<span class="dashicons dashicons-shield"></span>
				<span>
					<?php
					printf(
						/* translators: %s: key source label. */
						esc_html__( 'API key security: %s. Your key is never sent anywhere except your chosen AI provider.', 'sitepilot-ai' ),
						esc_html( WpConnectors::get_key_source_label() )
					);
					?>
				</span>
			</div>

			<form method="post" class="sitepilot-card sitepilot-settings-form">
				<?php wp_nonce_field( 'sitepilot_settings_save', 'sitepilot_settings_nonce' ); ?>

				<!-- Key Management Mode -->
				<div class="sitepilot-field">
					<label><?php esc_html_e( 'API Key Management', 'sitepilot-ai' ); ?></label>
					<div class="sitepilot-key-modes">
						<label class="sitepilot-key-mode <?php echo 'keyvault' === $active_key_mode ? 'sitepilot-key-mode--active' : ''; ?>">
							<input type="radio" name="key_management" value="keyvault"
								<?php checked( $s['key_management'] ?? 'keyvault', 'keyvault' ); ?> />
							<span class="sitepilot-key-mode__icon">🔐</span>
							<span class="sitepilot-key-mode__body">
								<strong><?php esc_html_e( 'SitePilot Encrypted Vault', 'sitepilot-ai' ); ?></strong>
								<small><?php esc_html_e( 'API key encrypted with XSalsa20-Poly1305 / AES-256-GCM and stored on this site.', 'sitepilot-ai' ); ?></small>
							</span>
						</label>

						<?php if ( $wp_connectors_available ) : ?>
							<label class="sitepilot-key-mode <?php echo 'wp_connectors' === $active_key_mode ? 'sitepilot-key-mode--active' : ''; ?>">
								<input type="radio" name="key_management" value="wp_connectors"
									<?php checked( $s['key_management'] ?? 'keyvault', 'wp_connectors' ); ?> />
								<span class="sitepilot-key-mode__icon">🔗</span>
								<span class="sitepilot-key-mode__body">
									<strong><?php esc_html_e( 'WordPress Connectors', 'sitepilot-ai' ); ?></strong>
									<small>
										<?php esc_html_e( 'Use the native WordPress 7 key manager.', 'sitepilot-ai' ); ?>
										<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-connectors' ) ); ?>">
											<?php esc_html_e( 'Settings → Connectors', 'sitepilot-ai' ); ?>
										</a>
									</small>
								</span>
							</label>
						<?php else : ?>
							<label class="sitepilot-key-mode sitepilot-key-mode--disabled">
								<input type="radio" disabled />
								<span class="sitepilot-key-mode__icon">🔗</span>
								<span class="sitepilot-key-mode__body">
									<strong><?php esc_html_e( 'WordPress Connectors', 'sitepilot-ai' ); ?></strong>
									<small><?php esc_html_e( 'Available in WordPress 7.0+. Update WordPress to enable this option.', 'sitepilot-ai' ); ?></small>
								</span>
							</label>
						<?php endif; ?>
					</div>
				</div>

				<hr class="sitepilot-divider" />

				<div class="sitepilot-field">
					<label for="sp-provider"><?php esc_html_e( 'AI Provider', 'sitepilot-ai' ); ?></label>
					<select id="sp-provider" name="provider">
						<?php foreach ( Settings::presets() as $key => $preset ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['provider'], $key ); ?>>
								<?php echo esc_html( $preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Choose a provider preset. Fields below auto-fill; you can override them for a custom endpoint.', 'sitepilot-ai' ); ?></p>
				</div>

				<div class="sitepilot-field">
					<label for="sp-base-url"><?php esc_html_e( 'API Base URL', 'sitepilot-ai' ); ?></label>
					<input type="url" id="sp-base-url" name="base_url" class="regular-text" value="<?php echo esc_attr( $s['base_url'] ); ?>" placeholder="https://api.deepseek.com/v1" />
				</div>

				<div class="sitepilot-field">
					<label for="sp-model"><?php esc_html_e( 'Model', 'sitepilot-ai' ); ?></label>
					<input type="text" id="sp-model" name="model" class="regular-text" value="<?php echo esc_attr( $s['model'] ); ?>" placeholder="deepseek-chat" />
				</div>

				<?php if ( 'wp_connectors' !== ( $s['key_management'] ?? 'keyvault' ) || ! $wp_connectors_available ) : ?>
					<div class="sitepilot-field">
						<label for="sp-api-key"><?php esc_html_e( 'API Key', 'sitepilot-ai' ); ?></label>
						<input type="password" id="sp-api-key" name="api_key" class="regular-text" autocomplete="off"
							placeholder="<?php echo Settings::has_api_key() ? esc_attr__( '•••••••• (a key is saved — leave blank to keep it)', 'sitepilot-ai' ) : esc_attr__( 'Paste your API key here', 'sitepilot-ai' ); ?>" />
						<p class="description">
							<?php
							$preset = Settings::presets()[ $s['provider'] ] ?? null;
							if ( $preset && ! empty( $preset['key_url'] ) ) {
								printf(
									/* translators: %s: URL to get API key. */
									esc_html__( 'Get your key from %s', 'sitepilot-ai' ),
									'<a href="' . esc_url( $preset['key_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $preset['label'] ) . '</a>'
								);
							}
							?>
						</p>
						<?php if ( Settings::has_api_key() ) : ?>
							<label class="sitepilot-checkbox-inline">
								<input type="checkbox" name="clear_api_key" value="1" />
								<?php esc_html_e( 'Remove saved API key', 'sitepilot-ai' ); ?>
							</label>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="sitepilot-banner sitepilot-banner--info">
						<span class="dashicons dashicons-info"></span>
						<span>
							<?php esc_html_e( 'API key is managed by WordPress Connectors.', 'sitepilot-ai' ); ?>
							<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-connectors' ) ); ?>">
								<?php esc_html_e( 'Manage key in Settings → Connectors', 'sitepilot-ai' ); ?>
							</a>
						</span>
					</div>
				<?php endif; ?>

				<div class="sitepilot-field">
					<label for="sp-temperature"><?php esc_html_e( 'Temperature', 'sitepilot-ai' ); ?></label>
					<input type="number" id="sp-temperature" name="temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Lower = more deterministic. Recommended: 0.2', 'sitepilot-ai' ); ?></p>
				</div>

				<hr class="sitepilot-divider" />

				<h2 class="sitepilot-section-title"><?php esc_html_e( 'Semantic Search (Embeddings)', 'sitepilot-ai' ); ?></h2>
				<p class="description" style="margin-bottom: 12px;">
					<?php esc_html_e( 'When enabled, SitePilot ranks knowledge by meaning instead of keywords — so a question in one language finds the right page even if it was written in another. Requires a provider with an /embeddings endpoint (e.g. OpenAI or Qwen). DeepSeek and Groq do not offer embeddings.', 'sitepilot-ai' ); ?>
				</p>

				<div class="sitepilot-field">
					<label class="sitepilot-checkbox-inline">
						<input type="checkbox" name="embeddings_enabled" value="1" <?php checked( ! empty( $s['embeddings_enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable semantic search', 'sitepilot-ai' ); ?>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %d: number of indexed items that already have an embedding. */
							esc_html__( '%d indexed items currently have an embedding. After enabling, run indexing on the Knowledge Base page to build the missing vectors.', 'sitepilot-ai' ),
							(int) $embedded_count
						);
						?>
					</p>
				</div>

				<div class="sitepilot-field">
					<label for="sp-embedding-model"><?php esc_html_e( 'Embedding Model', 'sitepilot-ai' ); ?></label>
					<input type="text" id="sp-embedding-model" name="embedding_model" class="regular-text" value="<?php echo esc_attr( (string) ( $s['embedding_model'] ?? '' ) ); ?>" placeholder="text-embedding-3-small" />
					<p class="description"><?php esc_html_e( 'e.g. text-embedding-3-small (OpenAI) or text-embedding-v3 (Qwen). Changing the model requires re-indexing.', 'sitepilot-ai' ); ?></p>
				</div>

				<div class="sitepilot-field">
					<label for="sp-embedding-base-url"><?php esc_html_e( 'Embedding Base URL (optional)', 'sitepilot-ai' ); ?></label>
					<input type="url" id="sp-embedding-base-url" name="embedding_base_url" class="regular-text" value="<?php echo esc_attr( (string) ( $s['embedding_base_url'] ?? '' ) ); ?>" placeholder="https://api.openai.com/v1" />
					<p class="description"><?php esc_html_e( 'Leave blank to use the same endpoint as your chat provider above.', 'sitepilot-ai' ); ?></p>
				</div>

				<div class="sitepilot-field">
					<label for="sp-embedding-key"><?php esc_html_e( 'Embedding API Key (optional)', 'sitepilot-ai' ); ?></label>
					<input type="password" id="sp-embedding-key" name="embedding_api_key" class="regular-text" autocomplete="off"
						placeholder="<?php echo Settings::has_embedding_key() ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'sitepilot-ai' ) : esc_attr__( 'Leave blank to reuse your provider API key', 'sitepilot-ai' ); ?>" />
					<?php if ( Settings::has_embedding_key() ) : ?>
						<label class="sitepilot-checkbox-inline">
							<input type="checkbox" name="clear_embedding_key" value="1" />
							<?php esc_html_e( 'Remove saved embedding key', 'sitepilot-ai' ); ?>
						</label>
					<?php endif; ?>
				</div>

				<div class="sitepilot-field">
					<label><?php esc_html_e( 'Default form status', 'sitepilot-ai' ); ?></label>
					<label class="sitepilot-checkbox-inline">
						<input type="radio" name="default_form_status" value="draft" <?php checked( $s['default_form_status'], 'draft' ); ?> />
						<?php esc_html_e( 'Draft (recommended)', 'sitepilot-ai' ); ?>
					</label>
					<label class="sitepilot-checkbox-inline">
						<input type="radio" name="default_form_status" value="publish" <?php checked( $s['default_form_status'], 'publish' ); ?> />
						<?php esc_html_e( 'Publish immediately', 'sitepilot-ai' ); ?>
					</label>
				</div>

				<div class="sitepilot-field">
					<label class="sitepilot-checkbox-inline">
						<input type="checkbox" name="prefer_efb" value="1" <?php checked( $s['prefer_efb'] ); ?> />
						<?php esc_html_e( 'Prefer Easy Form Builder when available', 'sitepilot-ai' ); ?>
					</label>
				</div>

				<div class="sitepilot-settings-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'sitepilot-ai' ); ?></button>
					<button type="button" id="sp-test" class="button"><?php esc_html_e( 'Test connection', 'sitepilot-ai' ); ?></button>
					<span id="sp-test-result" class="sitepilot-status" role="status" aria-live="polite"></span>
				</div>
			</form>

			<div class="sitepilot-card">
				<h2><?php esc_html_e( 'Form connectors', 'sitepilot-ai' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Connector', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Status', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'sitepilot-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						/** @var ConnectorRegistry $registry */
						$registry = Plugin::instance()->container()->get( ConnectorRegistry::class );
						foreach ( $registry->all() as $connector ) :
							$health = $connector->get_health();
							?>
							<tr>
								<td><?php echo esc_html( $connector->get_name() ); ?></td>
								<td>
									<?php if ( $health['available'] ) : ?>
										<span class="sitepilot-pill sitepilot-pill--ok"><?php esc_html_e( 'Available', 'sitepilot-ai' ); ?></span>
									<?php else : ?>
										<span class="sitepilot-pill sitepilot-pill--muted"><?php esc_html_e( 'Not active', 'sitepilot-ai' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $health['notes'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function maybe_save(): bool {
		if ( empty( $_POST['sitepilot_settings_nonce'] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sitepilot_settings_nonce'] ) ), 'sitepilot_settings_save' ) ) {
			return false;
		}

		$provider = sanitize_key( wp_unslash( $_POST['provider'] ?? 'deepseek' ) );
		if ( ! array_key_exists( $provider, Settings::presets() ) ) {
			$provider = 'deepseek';
		}

		$status = ( 'publish' === ( $_POST['default_form_status'] ?? 'draft' ) ) ? 'publish' : 'draft';

		$key_mgmt = sanitize_key( wp_unslash( $_POST['key_management'] ?? 'keyvault' ) );
		if ( ! in_array( $key_mgmt, array( 'keyvault', 'wp_connectors' ), true ) ) {
			$key_mgmt = 'keyvault';
		}

		Settings::update(
			array(
				'provider'            => $provider,
				'base_url'            => esc_url_raw( wp_unslash( $_POST['base_url'] ?? '' ) ),
				'model'               => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
				'temperature'         => max( 0.0, min( 1.0, (float) ( $_POST['temperature'] ?? 0.2 ) ) ),
				'default_form_status' => $status,
				'prefer_efb'          => ! empty( $_POST['prefer_efb'] ),
				'key_management'      => $key_mgmt,
				'embeddings_enabled'  => ! empty( $_POST['embeddings_enabled'] ),
				'embedding_model'     => sanitize_text_field( wp_unslash( $_POST['embedding_model'] ?? '' ) ),
				'embedding_base_url'  => esc_url_raw( wp_unslash( $_POST['embedding_base_url'] ?? '' ) ),
			)
		);

		if ( ! empty( $_POST['clear_api_key'] ) ) {
			Settings::set_api_key( '' );
		} else {
			$api_key = (string) wp_unslash( $_POST['api_key'] ?? '' );
			if ( '' !== trim( $api_key ) ) {
				Settings::set_api_key( $api_key );
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

		return true;
	}
}
