<?php
/**
 * Local WordPress contracts for the advanced Settings alias.
 *
 * Proves the page shows the AI service read-only and that its save writes only search, voice,
 * forms and public-assistant settings: the provider, endpoint, model, key storage, temperature
 * and the saved connection verification stay untouched. The settings option, the verification
 * option, the embedding key option, the request globals, the user and the locale are restored
 * in finally. The embedding key is only ever compared, never printed.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Core\Settings;

$missing       = new stdClass();
$snapshots     = array();
$option_names  = array( Settings::OPTION, SettingsPage::VERIFIED_OPTION, Settings::EMBED_KEY_OPTION );
$original_user = get_current_user_id();
$original_get  = $_GET;
$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- snapshot for restore.
$checks        = array();
foreach ( $option_names as $option_name ) {
	$snapshots[ $option_name ] = get_option( $option_name, $missing );
}

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function ( array $post = array() ): string {
	$_GET  = array( 'page' => AdminMenu::SLUG_SETTINGS );
	$_POST = $post;
	ob_start();
	( new SettingsPage() )->render();
	$html  = (string) ob_get_clean();
	$_POST = array();
	return $html;
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	Settings::update(
		array(
			'embeddings_enabled'       => false,
			'voice_model'              => 'whisper-1',
			'voice_responses_enabled'  => true,
			'prefer_efb'               => false,
			'public_assistant_enabled' => true,
			'default_form_status'      => 'draft',
			'form_notification_email'  => 'owner@example.com',
		)
	);
	$before = Settings::all();
	update_option( SettingsPage::VERIFIED_OPTION, 1234567, false );

	$html = $render();
	$check(
		false !== strpos( $html, 'data-sp-advanced-form' )
		&& false !== strpos( $html, 'name="advanced_settings" value="1"' )
		&& 6 === substr_count( $html, 'href="#sp-adv-' ),
		'Advanced settings render the scoped form and its six-section page navigation'
	);
	$check(
		false !== strpos( $html, 'sp-form-notification-email' ) && false !== strpos( $html, 'sp-test-form-delivery' ),
		'Advanced settings keep the notification email and delivery test controls'
	);
	$check(
		false === strpos( $html, 'name="provider"' )
		&& false === strpos( $html, 'name="api_key"' )
		&& false === strpos( $html, 'name="base_url"' )
		&& false === strpos( $html, 'name="model"' )
		&& false === strpos( $html, 'name="key_management"' )
		&& false === strpos( $html, 'name="temperature"' ),
		'The AI service, its key, storage and temperature are shown read-only and edited only in Setup'
	);
	$check(
		false !== strpos( $html, 'data-sp-adv-readonly' )
		&& false !== strpos( $html, 'Open the setup wizard' )
		&& false !== strpos( $html, 'where the connection is checked before it can be saved' )
		&& false !== strpos( $html, 'Unsaved changes on this page' ),
		'The connection section is labelled read-only, links to the setup wizard, and the save bar names this page'
	);

	$saved_html = $render(
		array(
			'aisaautopilot_settings_nonce' => wp_create_nonce( 'aisaautopilot_settings_save' ),
			'advanced_settings'            => '1',
			'embeddings_enabled'           => '1',
			'embedding_model'              => 'text-embedding-3-large',
			'embedding_base_url'           => 'https://api.openai.com/v1',
			'embedding_api_key'            => 'integration-test-embedding-key',
			'voice_model'                  => '',
			'voice_responses_enabled'      => '0',
			'prefer_efb'                   => '1',
			'public_assistant_enabled'     => '1',
			'default_form_status'          => 'unexpected',
			'form_notification_email'      => 'not-an-email',
			// Fields the advanced form never sends must not change anything even if present.
			'provider'                     => 'openai',
			'model'                        => 'should-not-save',
		)
	);
	$after = Settings::all();
	$check( false !== strpos( $saved_html, 'Settings saved.' ), 'A valid advanced save confirms it on the page' );
	$check(
		true === $after['embeddings_enabled']
		&& 'text-embedding-3-large' === $after['embedding_model']
		&& 'https://api.openai.com/v1' === $after['embedding_base_url']
		&& false === $after['voice_responses_enabled']
		&& true === $after['prefer_efb']
		&& Settings::has_embedding_key(),
		'Search, voice and form switches and the embedding key are stored'
	);
	$check(
		'whisper-1' === $after['voice_model'] && 'draft' === $after['default_form_status'] && '' === $after['form_notification_email'],
		'An empty speech model keeps the saved one, an unknown form status falls back to draft and an invalid email is not stored'
	);
	$check(
		$before['provider'] === $after['provider']
		&& $before['base_url'] === $after['base_url']
		&& $before['model'] === $after['model']
		&& $before['key_management'] === $after['key_management']
		&& (float) $before['temperature'] === (float) $after['temperature']
		&& 1234567 === (int) get_option( SettingsPage::VERIFIED_OPTION, 0 ),
		'The provider, endpoint, model, key storage, temperature and connection verification stay untouched'
	);

	$render(
		array(
			'aisaautopilot_settings_nonce' => wp_create_nonce( 'aisaautopilot_settings_save' ),
			'advanced_settings'            => '1',
			'clear_embedding_key'          => '1',
			'embeddings_enabled'           => '0',
		)
	);
	$check( ! Settings::has_embedding_key() && false === Settings::all()['embeddings_enabled'], 'Removing the saved embedding key and switching search off both work' );

	$render(
		array(
			'aisaautopilot_settings_nonce' => 'not-a-valid-nonce',
			'advanced_settings'            => '1',
			'prefer_efb'                   => '0',
		)
	);
	$check( true === Settings::all()['prefer_efb'], 'A request without a valid nonce changes nothing' );

	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa = $render();
		restore_previous_locale();
		$check( 1 === preg_match( '/<h1 class="sp-pagebar__title" dir="auto">[^<]*[\x{0600}-\x{06FF}]/u', $fa ), 'fa_IR renders the advanced settings in Persian' );
	} else {
		$check( false, 'fa_IR is available for the advanced settings copy check' );
	}
} catch ( Throwable $error ) {
	$check( false, 'Advanced settings contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	foreach ( $snapshots as $option_name => $value ) {
		if ( $value === $missing ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $value, false );
		}
	}
	$_GET  = $original_get;
	$_POST = $original_post;
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
