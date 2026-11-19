<?php
/**
 * Local WordPress integration checks for the WhatsApp Business, Slack and Signal handoff channels.
 *
 * Every provider is intercepted by pre_http_request, so nothing leaves the machine. The checks
 * prove what the settings endpoint accepts and refuses, that credentials are stored encrypted
 * and never returned, exactly what each channel sends, how provider errors are explained, what
 * the connection checks do, and that logs never keep a Slack webhook path or an access token.
 * The settings, the encrypted handoff credentials and the log are restored in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\AdminHandoffSettingsController;
use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffService;

$missing   = new stdClass();
$checks    = array();
$options   = array( Settings::OPTION, Settings::HANDOFF_SECRET_OPTION, 'aisaautopilot_logs' );
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}
$original_user_id = get_current_user_id();
$requests         = array();
$replies          = array();
$http_filter      = null;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $value, false );
};

$save = static function ( array $params ): \WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/handoff/admin-settings' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return ( new AdminHandoffSettingsController() )->update( $request );
};

$reply = static function ( int $code, $body ): array {
	return array(
		'headers'  => array(),
		'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
		'response' => array(
			'code'    => $code,
			'message' => 'Mock',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};

$test_channel = static function ( string $channel ): array {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/test/' . $channel );
	$request->set_param( 'channel', $channel );
	return (array) ( new ChatWidgetController() )->test_channel( $request )->get_data();
};

// A fake token shaped like Meta's (EAA…), assembled so no scanner reads it as a leaked credential.
$token   = 'EAA' . 'Gm0PX4ZCpsBAKqZBZA' . 'integrationTokenValue123';
$webhook = 'https://hooks.slack.com/services/T0INTEGR/B0INTEGR/integrationWebhookSecret';

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array(
				'enabled'                  => true,
				'handoff_enabled'          => false,
				'handoff_email_enabled'    => false,
				'handoff_telegram_enabled' => false,
				'handoff_twilio_enabled'   => false,
				'handoff_whatsapp_enabled' => false,
				'handoff_slack_enabled'    => false,
				'handoff_signal_enabled'   => false,
			),
		)
	);
	foreach ( array( 'handoff_whatsapp_token', 'handoff_slack_webhook', 'handoff_signal_token' ) as $key ) {
		Settings::set_handoff_secret( $key, '' );
	}

	// Saving: what is refused, what is stored, what comes back.
	$bad_whatsapp = $save(
		array(
			'channel'           => 'whatsapp',
			'enabled'           => true,
			'whatsapp_phone_id' => 'not-an-id',
			'whatsapp_to'       => '+1 555 019 4471',
		)
	);
	$bad_slack    = $save(
		array(
			'channel'       => 'slack',
			'enabled'       => true,
			'slack_webhook' => 'https://evil.test/services/T0/B0/x',
		)
	);
	$bad_signal   = $save(
		array(
			'channel'     => 'signal',
			'enabled'     => true,
			'signal_url'  => 'http://127.0.0.1:8099',
			'signal_from' => '5550000000',
			'signal_to'   => '+15550194471',
		)
	);
	$check(
		'handoff_whatsapp_number_invalid' === ( $bad_whatsapp->get_data()['code'] ?? '' )
		&& 'handoff_slack_webhook_invalid' === ( $bad_slack->get_data()['code'] ?? '' )
		&& 'handoff_signal_field_invalid' === ( $bad_signal->get_data()['code'] ?? '' )
		&& 400 === $bad_slack->get_status()
		&& ! Settings::has_handoff_secret( 'handoff_slack_webhook' ),
		'A malformed phone number ID, a non-Slack webhook and a sender without "+" are refused and nothing is stored'
	);

	$whatsapp = $save(
		array(
			'channel'           => 'whatsapp',
			'enabled'           => true,
			'whatsapp_token'    => $token,
			'whatsapp_phone_id' => '106540352242922',
			'whatsapp_to'       => '+1 (555) 019-4471',
			'whatsapp_template' => 'Support_Handoff',
			'whatsapp_language' => 'fa',
		)
	);
	$slack    = $save(
		array(
			'channel'       => 'slack',
			'enabled'       => true,
			'slack_webhook' => $webhook,
		)
	);
	$signal   = $save(
		array(
			'channel'      => 'signal',
			'enabled'      => true,
			'signal_url'   => 'http://127.0.0.1:8099/',
			'signal_from'  => '+1 555 000 0000',
			'signal_to'    => '+15550194471',
			'signal_token' => 'signal-proxy-token',
		)
	);
	$bodies   = wp_json_encode( array( $whatsapp->get_data(), $slack->get_data(), $signal->get_data() ) );
	$widget   = (array) Settings::get( 'widget', array() );
	$check(
		true === ( $whatsapp->get_data()['channel']['configured'] ?? false )
		&& true === ( $whatsapp->get_data()['channel']['has_token'] ?? false )
		&& true === ( $slack->get_data()['channel']['has_webhook'] ?? false )
		&& true === ( $signal->get_data()['channel']['configured'] ?? false )
		&& true === ( $signal->get_data()['handoff_available'] ?? false )
		&& 'support_handoff' === $widget['handoff_whatsapp_template']
		&& 'http://127.0.0.1:8099' === $widget['handoff_signal_url']
		&& '+15550000000' === $widget['handoff_signal_from'],
		'Valid settings are stored normalised (lowercase template, no trailing slash, number without spaces) and the channels are ready'
	);
	$stored_secrets = (string) wp_json_encode( get_option( Settings::HANDOFF_SECRET_OPTION ) );
	$check(
		false === strpos( (string) $bodies, $token )
		&& false === strpos( (string) $bodies, 'integrationWebhookSecret' )
		&& false === strpos( (string) $bodies, 'signal-proxy-token' )
		&& false === strpos( (string) wp_json_encode( Settings::all() ), 'integrationWebhookSecret' )
		&& false === strpos( $stored_secrets, $token )
		&& false === strpos( $stored_secrets, 'integrationWebhookSecret' )
		&& Settings::get_handoff_secret( 'handoff_slack_webhook' ) === $webhook,
		'Credentials are stored encrypted, never in the settings option, and never returned by the endpoint'
	);

	// Delivery: exactly what each provider receives.
	$http_filter = static function ( $preempt, array $args, string $url ) use ( &$requests, &$replies, $reply ) {
		foreach ( array( 'graph.facebook.com', 'hooks.slack.com', '127.0.0.1:8099' ) as $host ) {
			if ( false !== strpos( $url, $host ) ) {
				$requests[] = array(
					'url'    => $url,
					'method' => (string) ( $args['method'] ?? 'GET' ),
					'auth'   => (string) ( $args['headers']['Authorization'] ?? '' ),
					'body'   => (string) ( $args['body'] ?? '' ),
				);
				return $replies[ $host ] ?? $reply( 500, '' );
			}
		}
		return $preempt;
	};
	add_filter( 'pre_http_request', $http_filter, 10, 3 );

	$replies = array(
		'graph.facebook.com' => $reply( 200, array( 'messages' => array( array( 'id' => 'wamid.TEST' ) ) ) ),
		'hooks.slack.com'    => $reply( 200, 'ok' ),
		'127.0.0.1:8099'     => $reply( 201, array( 'timestamp' => '1700000000000' ) ),
	);
	$result  = ( new HandoffService() )->handoff(
		array(
			array(
				'role'    => 'user',
				'content' => 'قیمت <@channel> چقدر است؟ & thanks',
			),
		),
		array(
			'name'  => 'سارا',
			'email' => 'sara@example.test',
		)
	);
	$by_host = array();
	foreach ( $requests as $sent ) {
		foreach ( array( 'graph.facebook.com', 'hooks.slack.com', '127.0.0.1:8099' ) as $host ) {
			if ( false !== strpos( $sent['url'], $host ) ) {
				$by_host[ $host ][] = $sent;
			}
		}
	}
	$wa      = json_decode( $by_host['graph.facebook.com'][0]['body'] ?? '', true );
	$wa_text = (string) ( $wa['template']['components'][0]['parameters'][0]['text'] ?? '' );
	$check(
		true === ( $result['channels']['whatsapp']['ok'] ?? false )
		&& 1 === count( $by_host['graph.facebook.com'] ?? array() )
		&& 'https://graph.facebook.com/v26.0/106540352242922/messages' === ( $by_host['graph.facebook.com'][0]['url'] ?? '' )
		&& 'Bearer ' . $token === ( $by_host['graph.facebook.com'][0]['auth'] ?? '' )
		&& '15550194471' === ( $wa['to'] ?? '' )
		&& 'template' === ( $wa['type'] ?? '' )
		&& 'support_handoff' === ( $wa['template']['name'] ?? '' )
		&& 'fa' === ( $wa['template']['language']['code'] ?? '' )
		&& false === strpos( $wa_text, "\n" )
		&& false !== strpos( $wa_text, 'سارا' )
		&& mb_strlen( $wa_text ) <= 900,
		'WhatsApp sends one template message to the number in digits, with the request as its single one-line variable'
	);
	$slack_body = json_decode( $by_host['hooks.slack.com'][0]['body'] ?? '', true );
	$check(
		true === ( $result['channels']['slack']['ok'] ?? false )
		&& $webhook === ( $by_host['hooks.slack.com'][0]['url'] ?? '' )
		&& false !== strpos( (string) ( $slack_body['text'] ?? '' ), '&lt;@channel&gt;' )
		&& false !== strpos( (string) ( $slack_body['text'] ?? '' ), '&amp; thanks' )
		&& false !== strpos( (string) ( $slack_body['text'] ?? '' ), 'سارا' ),
		'Slack receives the request with <, > and & escaped, so a visitor cannot ping @channel or add links'
	);
	$signal_body = json_decode( $by_host['127.0.0.1:8099'][0]['body'] ?? '', true );
	$check(
		true === ( $result['channels']['signal']['ok'] ?? false )
		&& 'http://127.0.0.1:8099/v2/send' === ( $by_host['127.0.0.1:8099'][0]['url'] ?? '' )
		&& 'Bearer signal-proxy-token' === ( $by_host['127.0.0.1:8099'][0]['auth'] ?? '' )
		&& '+15550000000' === ( $signal_body['number'] ?? '' )
		&& array( '+15550194471' ) === ( $signal_body['recipients'] ?? null )
		&& false !== strpos( (string) ( $signal_body['message'] ?? '' ), 'قیمت <@channel>' ),
		'Signal posts the request to the owner\'s server from the saved number to the saved recipient'
	);
	$check(
		true === ( $result['ok'] ?? false ) && true === ( $result['channels']['email']['skipped'] ?? false ),
		'Switched-off channels are skipped and the handoff counts as delivered'
	);

	// Errors: explained, per channel, and the other channels still deliver.
	$requests = array();
	$replies  = array(
		'graph.facebook.com' => $reply(
			400,
			array(
				'error' => array(
					'code'    => 131047,
					'message' => 'Re-engagement message',
				),
			)
		),
		'hooks.slack.com'    => $reply( 404, 'no_service' ),
		'127.0.0.1:8099'     => $reply( 201, array( 'timestamp' => '1' ) ),
	);
	update_option( 'aisaautopilot_logs', array(), false );
	$failed = ( new HandoffService() )->handoff(
		array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		)
	);
	$logs   = (string) wp_json_encode( get_option( 'aisaautopilot_logs', array() ) );
	$check(
		false === ( $failed['channels']['whatsapp']['ok'] ?? true )
		&& false !== strpos( (string) ( $failed['channels']['whatsapp']['error'] ?? '' ), '24 hours' )
		&& false === ( $failed['channels']['slack']['ok'] ?? true )
		&& false !== strpos( (string) ( $failed['channels']['slack']['error'] ?? '' ), 'no_service' )
		&& true === ( $failed['ok'] ?? false ),
		'Provider errors say what to do (24-hour window: add a template; removed webhook) and one working channel still delivers'
	);
	$check(
		'' !== $logs && false === strpos( $logs, 'integrationWebhookSecret' ) && false === strpos( $logs, $token ),
		'The log records the failures without the Slack webhook path or the WhatsApp token'
	);

	// Plain text without a template, split into WhatsApp-sized messages.
	Settings::update( array( 'widget' => array( 'handoff_whatsapp_template' => '' ) ) );
	$requests = array();
	$replies['graph.facebook.com'] = $reply( 200, array( 'messages' => array( array( 'id' => 'wamid.TXT' ) ) ) );
	$long = ( new HandoffService() )->handoff(
		array(
			array(
				'role'    => 'user',
				'content' => str_repeat( 'سلام دنیا ', 600 ),
			),
		)
	);
	$texts = array();
	foreach ( $requests as $sent ) {
		if ( false !== strpos( $sent['url'], 'graph.facebook.com' ) ) {
			$texts[] = json_decode( $sent['body'], true );
		}
	}
	$check(
		true === ( $long['channels']['whatsapp']['ok'] ?? false )
		&& count( $texts ) >= 2
		&& array() === array_filter( $texts, static fn( $t ): bool => 'text' !== ( $t['type'] ?? '' ) || mb_strlen( (string) ( $t['text']['body'] ?? '' ) ) > 4096 || ! mb_check_encoding( (string) ( $t['text']['body'] ?? '' ), 'UTF-8' ) ),
		'Without a template, a long Persian request goes as plain text messages of at most 4,096 characters, none cut inside a letter'
	);

	// Connection checks.
	$requests                      = array();
	$replies['graph.facebook.com'] = $reply(
		200,
		array(
			'verified_name'        => 'Coffee Roaster',
			'display_phone_number' => '+1 555-000-0000',
		)
	);
	$replies['127.0.0.1:8099']     = $reply( 200, array( '+15550000000' ) );
	$replies['hooks.slack.com']    = $reply( 200, 'ok' );
	$wa_check                      = $test_channel( 'whatsapp' );
	$signal_check                  = $test_channel( 'signal' );
	$slack_check                   = $test_channel( 'slack' );
	$check(
		true === ( $wa_check['ok'] ?? false ) && false !== strpos( (string) ( $wa_check['message'] ?? '' ), 'Coffee Roaster' )
		&& 'GET' === ( $requests[0]['method'] ?? '' ) && false !== strpos( (string) ( $requests[0]['url'] ?? '' ), '?fields=display_phone_number,verified_name' )
		&& true === ( $signal_check['ok'] ?? false ) && false !== strpos( (string) ( $requests[1]['url'] ?? '' ), '/v1/accounts' )
		&& true === ( $slack_check['ok'] ?? false ) && 3 === count( $requests ),
		'Connection checks read the WhatsApp business name and the Signal accounts without sending, and Slack posts one check line'
	);
	$replies['127.0.0.1:8099'] = $reply( 200, array( '+15559999999' ) );
	$wrong_sender              = $test_channel( 'signal' );
	$unknown                   = $test_channel( 'discord' );
	$check(
		false === ( $wrong_sender['ok'] ?? true ) && false !== strpos( (string) ( $wrong_sender['message'] ?? '' ), 'not registered' )
		&& false === ( $unknown['ok'] ?? true ),
		'The Signal check says when the sending number is not on the server, and an unknown channel is refused'
	);

	// Only one new channel ready is enough for visitors to be offered a person.
	$save(
		array(
			'channel'           => 'whatsapp',
			'enabled'           => false,
			'whatsapp_phone_id' => '106540352242922',
			'whatsapp_to'       => '+15550194471',
		)
	);
	$save(
		array(
			'channel'     => 'signal',
			'enabled'     => false,
			'signal_url'  => 'http://127.0.0.1:8099',
			'signal_from' => '+15550000000',
			'signal_to'   => '+15550194471',
		)
	);
	$only_slack = Settings::answer_desk_handoff_available();
	$cleared    = $save(
		array(
			'channel'             => 'slack',
			'enabled'             => true,
			'clear_slack_webhook' => '1',
		)
	);
	$check(
		$only_slack
		&& false === ( $cleared->get_data()['channel']['configured'] ?? true )
		&& false === ( $cleared->get_data()['handoff_available'] ?? true )
		&& ! Settings::answer_desk_handoff_available(),
		'Slack alone makes handoff available; removing its webhook takes the offer away again'
	);
} catch ( Throwable $error ) {
	$check( false, 'Handoff channels contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	if ( null !== $http_filter ) {
		remove_filter( 'pre_http_request', $http_filter, 10 );
	}
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user_id );
}

$failed_checks = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed_checks ) . ' failed' . PHP_EOL;
exit( array() === $failed_checks ? 0 : 1 );
