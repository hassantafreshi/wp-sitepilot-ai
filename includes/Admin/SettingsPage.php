<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Core\Plugin;
use SitePilot\Core\Settings;

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
		?>
		<div class="wrap sitepilot-wrap">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'SitePilot AI — Settings', 'sitepilot-ai' ); ?>
			</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'sitepilot-ai' ); ?></p></div>
			<?php endif; ?>

			<div class="sitepilot-banner sitepilot-banner--info">
				<span class="dashicons dashicons-shield"></span>
				<span><?php esc_html_e( 'Temporary standalone mode: your key is stored encrypted on this site and used to call your AI provider directly. It is never sent anywhere else.', 'sitepilot-ai' ); ?></span>
			</div>

			<form method="post" class="sitepilot-card sitepilot-settings-form">
				<?php wp_nonce_field( 'sitepilot_settings_save', 'sitepilot_settings_nonce' ); ?>

				<div class="sitepilot-field">
					<label for="sp-provider"><?php esc_html_e( 'AI Provider', 'sitepilot-ai' ); ?></label>
					<select id="sp-provider" name="provider">
						<?php foreach ( Settings::presets() as $key => $preset ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['provider'], $key ); ?>>
								<?php echo esc_html( $preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Choose a provider preset. Fields below auto-fill; you can override them for a custom endpoint (e.g. cloud.ai).', 'sitepilot-ai' ); ?></p>
				</div>

				<div class="sitepilot-field">
					<label for="sp-base-url"><?php esc_html_e( 'API Base URL', 'sitepilot-ai' ); ?></label>
					<input type="url" id="sp-base-url" name="base_url" class="regular-text" value="<?php echo esc_attr( $s['base_url'] ); ?>" placeholder="https://api.deepseek.com/v1" />
				</div>

				<div class="sitepilot-field">
					<label for="sp-model"><?php esc_html_e( 'Model', 'sitepilot-ai' ); ?></label>
					<input type="text" id="sp-model" name="model" class="regular-text" value="<?php echo esc_attr( $s['model'] ); ?>" placeholder="deepseek-chat" />
				</div>

				<div class="sitepilot-field">
					<label for="sp-api-key"><?php esc_html_e( 'API Key', 'sitepilot-ai' ); ?></label>
					<input type="password" id="sp-api-key" name="api_key" class="regular-text" autocomplete="off"
						placeholder="<?php echo Settings::has_api_key() ? esc_attr__( '•••••••• (a key is saved — leave blank to keep it)', 'sitepilot-ai' ) : esc_attr__( 'Paste your provider API key', 'sitepilot-ai' ); ?>" />
					<?php if ( Settings::has_api_key() ) : ?>
						<label class="sitepilot-inline-check">
							<input type="checkbox" name="clear_api_key" value="1" />
							<?php esc_html_e( 'Remove the saved key', 'sitepilot-ai' ); ?>
						</label>
					<?php endif; ?>
					<p class="description" id="sp-key-help"></p>
				</div>

				<div class="sitepilot-field">
					<label><?php esc_html_e( 'When a form is approved, create it as', 'sitepilot-ai' ); ?></label>
					<label class="sitepilot-inline-check"><input type="radio" name="default_form_status" value="draft" <?php checked( $s['default_form_status'], 'draft' ); ?> /> <?php esc_html_e( 'Draft (recommended)', 'sitepilot-ai' ); ?></label>
					<label class="sitepilot-inline-check"><input type="radio" name="default_form_status" value="publish" <?php checked( $s['default_form_status'], 'publish' ); ?> /> <?php esc_html_e( 'Published', 'sitepilot-ai' ); ?></label>
				</div>

				<div class="sitepilot-field">
					<label for="sp-temperature"><?php esc_html_e( 'Creativity (temperature)', 'sitepilot-ai' ); ?></label>
					<input type="number" step="0.1" min="0" max="1" id="sp-temperature" name="temperature" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" class="small-text" />
					<span class="description"><?php esc_html_e( 'Lower is more predictable. 0.2 is a good default for forms.', 'sitepilot-ai' ); ?></span>
				</div>

				<div class="sitepilot-field">
					<label class="sitepilot-inline-check">
						<input type="checkbox" name="prefer_efb" value="1" <?php checked( (bool) $s['prefer_efb'] ); ?> />
						<?php esc_html_e( 'Use Easy Form Builder when available (otherwise use built-in forms)', 'sitepilot-ai' ); ?>
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

		Settings::update(
			array(
				'provider'            => $provider,
				'base_url'            => esc_url_raw( wp_unslash( $_POST['base_url'] ?? '' ) ),
				'model'               => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
				'temperature'         => max( 0.0, min( 1.0, (float) ( $_POST['temperature'] ?? 0.2 ) ) ),
				'default_form_status' => $status,
				'prefer_efb'          => ! empty( $_POST['prefer_efb'] ),
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

		return true;
	}
}
