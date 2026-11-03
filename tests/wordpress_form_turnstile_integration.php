<?php
/**
 * Local WordPress checks for the optional Cloudflare Turnstile check on native forms.
 * Cloudflare and wp_mail are faked (pre_http_request, pre_wp_mail); nothing leaves the site.
 *
 * Off (no keys): no widget, no script, submissions work as before. Keys saved in Advanced
 * settings: the secret is stored encrypted, the form shows the widget and loads
 * Cloudflare's script, and a submission is emailed only when Cloudflare confirms its
 * token; a missing token, a refusal or an unreachable Cloudflare send the visitor back.
 * Each submission ends in exit(), so it runs in a child process of this file
 * (`--submit <form_id> <token|none> <ok|fail|error>`).
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Forms\Turnstile;
use AISAAutoPilot\Frontend\FormShortcode;
use AISAAutoPilot\Support\PrivacyTools;

if ( isset( $argv[1] ) && '--submit' === $argv[1] ) {
	$form_id  = (int) ( $argv[2] ?? 0 );
	$token    = (string) ( $argv[3] ?? 'none' );
	$answer   = (string) ( $argv[4] ?? 'ok' );
	$location = '';
	$asked    = array();
	$mailed   = false;
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $answer, &$asked ) {
			if ( Turnstile::VERIFY_URL !== $url ) {
				return $pre;
			}
			$asked = (array) $args['body'];
			if ( 'error' === $answer ) {
				return new WP_Error( 'http_request_failed', 'unreachable' );
			}
			return array( 'headers' => array(), 'cookies' => array(), 'filename' => null, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( array( 'success' => 'ok' === $answer ) ) );
		},
		10,
		3
	);
	add_filter(
		'pre_wp_mail',
		static function () use ( &$mailed ) {
			$mailed = true;
			return true;
		}
	);
	add_filter(
		'wp_redirect',
		static function ( $to ) use ( &$location ) {
			$location = (string) $to;
			return $to;
		}
	);
	register_shutdown_function(
		static function () use ( &$location, &$asked, &$mailed ): void {
			echo wp_json_encode(
				array(
					'location' => $location,
					'mailed'   => $mailed,
					'secret'   => (string) ( $asked['secret'] ?? '' ),
					'token'    => (string) ( $asked['response'] ?? '' ),
					'remoteip' => (string) ( $asked['remoteip'] ?? '' ),
				)
			) . PHP_EOL;
		}
	);
	$_SERVER['HTTP_REFERER'] = home_url( '/turnstile-test/' );
	$_SERVER['REMOTE_ADDR']  = '203.0.113.' . wp_rand( 1, 250 );
	$_POST                   = array(
		'action'         => 'aisaautopilot_form_submit',
		'form_id'        => (string) $form_id,
		'sp_nonce'       => wp_create_nonce( 'aisaautopilot_form_submit_' . $form_id ),
		'sp_field_email' => 'ts.visitor@example.test',
	);
	if ( 'none' !== $token ) {
		$_POST[ Turnstile::RESPONSE_FIELD ] = $token;
	}
	( new FormShortcode() )->handle_submit();
	exit;
}

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$submit = static function ( int $form_id, string $token, string $answer ): array {
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --submit ' . $form_id . ' ' . escapeshellarg( $token ) . ' ' . $answer . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test harness.
	$json = json_decode( (string) strrchr( "\n" . trim( $out ), "\n" ), true );
	return is_array( $json ) ? $json : array( 'location' => 'ERROR ' . $out );
};
$render = static function ( int $form_id ): string {
	wp_dequeue_script( 'aisaautopilot-turnstile' );
	return ( new FormShortcode() )->render( array( 'id' => $form_id ) );
};
$save_advanced = static function ( array $fields ): void {
	$_POST = array_merge(
		array(
			'aisaautopilot_settings_nonce' => wp_create_nonce( 'aisaautopilot_settings_save' ),
			'advanced_settings'            => '1',
		),
		$fields
	);
	$method = new ReflectionMethod( SettingsPage::class, 'maybe_save' );
	$method->setAccessible( true );
	$method->invoke( new SettingsPage() );
	$_POST = array();
};

$original_settings = get_option( Settings::OPTION );
$original_secret   = get_option( Turnstile::SECRET_OPTION, null );
$original_logs     = get_option( 'aisaautopilot_logs', null );
wp_set_current_user( 1 );
$created = ( new NativeFormConnector() )->execute_action(
	array(
		'title'              => 'Turnstile QA form',
		'notification_email' => 'owner@example.test',
		'fields'             => array( array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ) ),
	),
	array( 'status' => 'publish' )
);
$form_id = (int) ( $created['form_id'] ?? 0 );

try {
	// 1. Off: no keys, no widget, no script; a submission without a token is emailed.
	Turnstile::set_secret( '' );
	Settings::update( array( 'turnstile_site_key' => '' ) );
	$html = $render( $form_id );
	$off  = $submit( $form_id, 'none', 'ok' );
	$check(
		! Turnstile::enabled() && false === strpos( $html, 'cf-turnstile' ) && ! wp_script_is( 'aisaautopilot-turnstile', 'enqueued' )
		&& false !== strpos( (string) $off['location'], 'sp_form_sent=' . $form_id ) && true === $off['mailed'] && '' === $off['token'],
		'Off by default: no widget, no Cloudflare script, and forms send as before without asking Cloudflare'
	);

	// 2. Advanced settings store the keys; the secret is encrypted and never shown back.
	$save_advanced(
		array(
			'turnstile_site_key' => ' 0x4AAAAAAA-site_key<script> ',
			'turnstile_secret'   => '0x4AAAAAAA-secret_key',
		)
	);
	$stored = (string) get_option( Turnstile::SECRET_OPTION, '' );
	$_GET['page'] = 'aisaautopilot-settings';
	ob_start();
	( new SettingsPage() )->render();
	$page = (string) ob_get_clean();
	unset( $_GET['page'] );
	$check(
		'0x4AAAAAAA-site_keyscript' === Turnstile::site_key() && Turnstile::enabled() && '' !== $stored && false === strpos( $stored, 'secret_key' )
		&& false === strpos( $page, '0x4AAAAAAA-secret_key' ) && false !== strpos( $page, 'Saved securely - enter a new key to replace it' ),
		'Advanced settings save a cleaned site key and an encrypted secret that the page never shows again'
	);

	// 3. On: the widget and Cloudflare's script are on the form page.
	$html = $render( $form_id );
	$check(
		false !== strpos( $html, 'class="aisaautopilot-form__turnstile cf-turnstile" data-sitekey="0x4AAAAAAA-site_keyscript"' )
		&& wp_script_is( 'aisaautopilot-turnstile', 'enqueued' ),
		'On: the form shows the Turnstile widget and loads Cloudflare\'s script'
	);

	// 4. Verification decides: no token, refused, unreachable -> back to the form; confirmed -> emailed.
	$none    = $submit( $form_id, 'none', 'ok' );
	$refused = $submit( $form_id, 'bad-token', 'fail' );
	$down    = $submit( $form_id, 'any-token', 'error' );
	$good    = $submit( $form_id, 'good-token', 'ok' );
	$check(
		false !== strpos( (string) $none['location'], 'sp_form_error=spam_check' ) && false === $none['mailed']
		&& false !== strpos( (string) $refused['location'], 'sp_form_error=spam_check' ) && false === $refused['mailed']
		&& false !== strpos( (string) $down['location'], 'sp_form_error=spam_check' ) && false === $down['mailed'],
		'A missing token, a token Cloudflare refuses, or an unreachable Cloudflare sends the visitor back and emails nothing'
	);
	$check(
		false !== strpos( (string) $good['location'], 'sp_form_sent=' . $form_id ) && true === $good['mailed']
		&& '0x4AAAAAAA-secret_key' === $good['secret'] && 'good-token' === $good['token'] && '' !== $good['remoteip'],
		'A token Cloudflare confirms is emailed; the check sends the secret, the token and the visitor address'
	);

	$policy_on = PrivacyTools::policy_text();

	// 5. Removing the secret turns it off.
	$save_advanced( array( 'clear_turnstile_secret' => '1' ) );
	$check( ! Turnstile::enabled() && ! Turnstile::has_secret(), 'Removing the saved secret key turns the check off' );
	$check(
		false !== strpos( $policy_on, 'Cloudflare Turnstile' ) && false === strpos( PrivacyTools::policy_text(), 'Cloudflare' ),
		'The suggested privacy-policy text names Cloudflare Turnstile only while it is on'
	);
} finally {
	if ( $form_id > 0 ) {
		wp_delete_post( $form_id, true );
	}
	update_option( Settings::OPTION, $original_settings );
	if ( null === $original_secret ) {
		delete_option( Turnstile::SECRET_OPTION );
	} else {
		update_option( Turnstile::SECRET_OPTION, $original_secret, false );
	}
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
