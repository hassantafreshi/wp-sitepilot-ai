<?php
/**
 * Local WordPress integration check for the Setup connection-verification flow.
 *
 * It proves that an unsaved provider test cannot mark the saved connection as
 * verified, and that only an exact subsequent save promotes the result.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Api\CopilotController;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;

wp_set_current_user( 1 );

$checks  = array();
$missing = new stdClass();

$test_transient = 'aisaautopilot_provider_test_' . get_current_user_id();
$option_names   = array(
	Settings::OPTION,
	Settings::KEY_OPTION,
	Settings::EMBED_KEY_OPTION,
	WpConnectors::WP_OPTION_KEY,
	SettingsPage::VERIFIED_OPTION,
	'_transient_' . $test_transient,
	'_transient_timeout_' . $test_transient,
);
$original_options = array();
foreach ( $option_names as $option_name ) {
	$original_options[ $option_name ] = get_option( $option_name, $missing );
}
$original_post = $_POST;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $value, false );
};

$candidate = array(
	'provider' => 'openai',
	'base_url' => 'https://api.openai.com/v1',
	'model'    => 'gpt-5.6-luna',
	'api_key'  => 'setup-verification-test-key',
);

$save = static function ( array $config, bool $wizard = false, array $extra = array() ): bool {
	$_POST = array(
		'aisaautopilot_settings_nonce' => wp_create_nonce( 'aisaautopilot_settings_save' ),
		'provider'                     => $config['provider'],
		'base_url'                     => $config['base_url'],
		'model'                        => $config['model'],
		'api_key'                      => $config['api_key'],
		'key_management'               => 'keyvault',
	);
	if ( $wizard ) {
		$_POST['setup_wizard'] = '1';
		$_POST['temperature']  = '0.2';
	} else {
		$_POST['temperature']         = '0.2';
		$_POST['default_form_status'] = 'draft';
		$_POST['voice_model']         = 'whisper-1';
	}
	foreach ( $extra as $key => $value ) {
		$_POST[ $key ] = $value;
	}
	$method = new ReflectionMethod( SettingsPage::class, 'maybe_save' );
	$method->setAccessible( true );

	return (bool) $method->invoke( new SettingsPage() );
};

try {
	$settings                            = Settings::defaults();
	$settings['provider']                = $candidate['provider'];
	$settings['base_url']                = $candidate['base_url'];
	$settings['model']                   = $candidate['model'];
	$settings['key_management']          = 'keyvault';
	$settings['default_form_status']     = 'publish';
	$settings['form_notification_email'] = 'preserve@example.test';
	$settings['embedding_model']         = 'preserve-embedding-model';
	$settings['embedding_base_url']      = 'https://preserve.example.test/v1';
	$settings['voice_model']             = 'preserve-voice-model';
	$settings['voice_responses_enabled'] = true;
	$settings['kb_auto_index']           = true;
	$settings['public_assistant_enabled'] = true;
	$settings['widget']['enabled']       = true;
	update_option( Settings::OPTION, $settings, false );
	Settings::set_api_key( 'previous-saved-key' );
	delete_option( SettingsPage::VERIFIED_OPTION );
	delete_transient( $test_transient );

	$check(
		! $save( $candidate, true )
		&& 'previous-saved-key' === Settings::get_api_key()
		&& false === get_option( SettingsPage::VERIFIED_OPTION, false ),
		'setup save is rejected server-side until the exact provider test succeeds'
	);

	SettingsPage::record_provider_test( $candidate, true );
	$check(
		false === get_option( SettingsPage::VERIFIED_OPTION, false ) && false !== get_transient( $test_transient ),
		'unsaved successful provider test stays private and does not certify the live configuration'
	);
	$check(
		SettingsPage::consume_matching_provider_test( $candidate ) && false === get_transient( $test_transient ),
		'pending provider test is bound to the exact configuration and is one-time'
	);

	SettingsPage::record_provider_test( $candidate, true );
	$wizard_toggles = array(
		'kb_auto_index'            => '0',
		'public_assistant_enabled' => '0',
		'embeddings_enabled'       => '0',
		'prefer_efb'               => '0',
		'widget'                   => array( 'enabled' => '0' ),
	);
	$check(
		$save( $candidate, true, $wizard_toggles )
		&& (int) get_option( SettingsPage::VERIFIED_OPTION, 0 ) > 0
		&& 'setup-verification-test-key' === Settings::get_api_key()
		&& ! Settings::get( 'kb_auto_index', true )
		&& ! Settings::get( 'public_assistant_enabled', true )
		&& empty( Settings::get( 'widget', array() )['enabled'] )
		&& ! Settings::get( 'embeddings_enabled', true )
		&& 'preserve@example.test' === Settings::get( 'form_notification_email' )
		&& 'preserve-embedding-model' === Settings::get( 'embedding_model' )
		&& 'https://preserve.example.test/v1' === Settings::get( 'embedding_base_url' )
		&& 'preserve-voice-model' === Settings::get( 'voice_model' )
		&& Settings::get( 'voice_responses_enabled', false ),
		'exact tested setup saves its integration toggles while preserving advanced settings outside the mockup panel'
	);

	delete_option( SettingsPage::VERIFIED_OPTION );
	SettingsPage::record_provider_test( $candidate, true );
	$mismatched          = $candidate;
	$mismatched['model'] = 'gpt-5.6-sol';
	$check(
		$save( $mismatched )
		&& false === get_option( SettingsPage::VERIFIED_OPTION, false )
		&& false === get_transient( $test_transient ),
		'a different save cannot consume a test for the previous model'
	);

	update_option( SettingsPage::VERIFIED_OPTION, 123456, false );
	SettingsPage::record_provider_test( $candidate, true );
	SettingsPage::record_provider_test( $candidate, false );
	$check(
		123456 === (int) get_option( SettingsPage::VERIFIED_OPTION, 0 ) && false === get_transient( $test_transient ),
		'failed draft test clears only its pending record and preserves the working saved verification'
	);

	Settings::set_api_key( 'saved-key-that-must-not-be-tested' );
	delete_option( WpConnectors::WP_OPTION_KEY );
	update_option( SettingsPage::VERIFIED_OPTION, 654321, false );
	$cleared_key_request = new WP_REST_Request( 'POST' );
	$cleared_key_request->set_param( 'clear_api_key', '1' );
	$response = ( new CopilotController() )->test( $cleared_key_request );
	$data     = $response->get_data();
	$check(
		400 === $response->get_status()
		&& 'no_api_key' === ( $data['diagnostic'] ?? '' )
		&& 654321 === (int) get_option( SettingsPage::VERIFIED_OPTION, 0 ),
		'provider endpoint honors an explicit clear-key test without using a saved credential or clearing its saved verification'
	);

	$custom_request = new WP_REST_Request( 'POST' );
	$custom_request->set_param( 'provider', 'custom' );
	$custom_request->set_param( 'base_url', '' );
	$custom_request->set_param( 'model', '' );
	$custom_request->set_param( 'api_key', 'explicit-invalid-config-test-key' );
	update_option( SettingsPage::VERIFIED_OPTION, 246810, false );
	$custom_response = ( new CopilotController() )->test( $custom_request );
	$custom_data     = $custom_response->get_data();
	$check(
		400 === $custom_response->get_status()
		&& 'invalid_config' === ( $custom_data['diagnostic'] ?? '' )
		&& 246810 === (int) get_option( SettingsPage::VERIFIED_OPTION, 0 ),
		'explicitly blank setup fields report invalid_config without falling back to the saved provider endpoint'
	);
} finally {
	$_POST = $original_post;
	foreach ( $original_options as $option_name => $value ) {
		$restore_option( $option_name, $value );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
