<?php
/**
 * Creates and restores the isolated visitor chat widget browser-QA fixture.
 *
 * The widget renders on the public site, so its language is the site language (WPLANG), not
 * the user's locale meta. Prepare snapshots the whole general settings option and WPLANG,
 * including whether each existed, before changing anything; restore writes both back exactly
 * and is a no-op without a snapshot. Nothing else is created: no posts, rows, users or keys.
 *
 * Environment:
 *   AISAAUTOPILOT_BROWSER_LOCALE          en_US (default) or any installed locale, e.g. fa_IR.
 *   AISAAUTOPILOT_FRONT_WIDGET_SCENARIO   main (default): an e-mail handoff channel is ready
 *                                         (recipient on .invalid, name optional, e-mail required)
 *                                         and voice input is on with read-aloud off.
 *                                         auto: as main, with automatic read-aloud on.
 *                                         quiet: no handoff channel and no voice input.
 *
 * No provider is configured or touched. Browser checks answer /widget/ask, /widget/handoff and
 * /public/voice with page.route, and the only recipient this fixture writes cannot deliver.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_front_chat_widget_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;

const FRONT_WIDGET_FIXTURE_OPTION = 'aisaautopilot_front_chat_widget_browser_fixture';

$restore = static function (): void {
	$record = get_option( FRONT_WIDGET_FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here.
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'had_settings', $record ) || ! array_key_exists( 'had_wplang', $record ) ) {
		throw new RuntimeException( 'Front chat widget fixture snapshot is invalid; live settings were left untouched.' );
	}
	if ( ! empty( $record['had_settings'] ) ) {
		update_option( Settings::OPTION, $record['settings'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	if ( ! empty( $record['had_wplang'] ) ) {
		update_option( 'WPLANG', $record['wplang'] );
	} else {
		delete_option( 'WPLANG' );
	}
	delete_option( FRONT_WIDGET_FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Front chat widget browser fixture removed.\n";
	exit( 0 );
}

// A crashed earlier run leaves its snapshot behind: put that state back first.
$restore();

$locale   = getenv( 'AISAAUTOPILOT_BROWSER_LOCALE' ) ?: 'en_US';
$scenario = getenv( 'AISAAUTOPILOT_FRONT_WIDGET_SCENARIO' ) ?: 'main';
if ( ! preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/', $locale ) || ! in_array( $scenario, array( 'main', 'auto', 'quiet' ), true ) ) {
	fwrite( STDERR, "Invalid locale or scenario.\n" );
	exit( 64 );
}

$missing  = new stdClass();
$settings = get_option( Settings::OPTION, $missing );
$wplang   = get_option( 'WPLANG', $missing );

// Snapshot first, before any change.
update_option(
	FRONT_WIDGET_FIXTURE_OPTION,
	array(
		'had_settings' => $settings !== $missing,
		'settings'     => $settings !== $missing ? $settings : array(),
		'had_wplang'   => $wplang !== $missing,
		'wplang'       => $wplang !== $missing ? $wplang : '',
	),
	false
);

try {
	update_option( 'WPLANG', 'en_US' === $locale ? '' : $locale );

	$persian     = 0 === strpos( $locale, 'fa' );
	$suggestions = $persian
		? array( 'ساعت کاری شما چیست؟', 'ارسال چقدر طول می‌کشد؟', 'چطور سفارشم را پیگیری کنم؟' )
		: array( 'What are your opening hours?', 'How long does shipping take?', 'How do I track my order?' );

	$ready   = 'quiet' !== $scenario;
	$current = (array) ( Settings::all()['widget'] ?? array() );
	$widget  = array_merge(
		$current,
		array(
			'enabled'                      => true,
			'position'                     => 'bottom-right',
			'theme'                        => 'light',
			'color'                        => '#202a8d',
			'custom_colors'                => false,
			'colors'                       => array(),
			'auto_open'                    => 0,
			'suggestions'                  => $suggestions,
			'conversation_logging_enabled' => false,
			'handoff_enabled'              => $ready,
			'handoff_email_enabled'        => $ready,
			// Never a deliverable address, and delivery is mocked in the browser anyway.
			'handoff_email_to'             => $ready ? 'qa-front-widget@example.invalid' : '',
			'handoff_telegram_enabled'     => false,
			'handoff_twilio_enabled'       => false,
			'handoff_text'                 => '',
			'handoff_hook'                 => '',
			'handoff_fields'               => array(
				'name'    => $ready ? 'optional' : 'off',
				'email'   => $ready ? 'required' : 'off',
				'phone'   => 'off',
				'website' => 'off',
			),
			'voice_enabled'                => 'quiet' !== $scenario,
			'voice_auto'                   => 'auto' === $scenario,
			'voice_name'                   => '',
		)
	);
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => $widget,
		)
	);
	$saved = (array) ( Settings::all()['widget'] ?? array() );
	echo wp_json_encode(
		array(
			'home'          => home_url( '/' ),
			'locale'        => $locale,
			'scenario'      => $scenario,
			'suggestions'   => count( (array) ( $saved['suggestions'] ?? array() ) ),
			'handoff'       => Settings::answer_desk_handoff_available(),
			'voice_enabled' => ! empty( $saved['voice_enabled'] ),
			'voice_auto'    => ! empty( $saved['voice_auto'] ),
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
