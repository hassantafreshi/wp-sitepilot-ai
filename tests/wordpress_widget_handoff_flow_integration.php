<?php
/**
 * End-to-end contract of the visitor "Send this chat to our team" request through the real REST route.
 *
 * Delivery never leaves the machine: pre_wp_mail captures the email and pre_http_request answers the
 * Telegram API with a canned response, so the real controller, HandoffService, channels and inbox run
 * without mail, SMS or network traffic. Settings, handoff secrets, logs, this visitor's rate-limit counter
 * and REMOTE_ADDR are restored in finally, and only inbox rows returned by this run (and carrying its
 * marker) are deleted, as are this run's copies saved by a local mail-capture mu-plugin, if one is
 * installed. The fake bot token is never printed.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Security\RateLimiter;
use AISAAutoPilot\Support\HandoffInbox;

$missing       = new stdClass();
$option_names  = array( Settings::OPTION, Settings::HANDOFF_SECRET_OPTION, 'aisaautopilot_logs' );
$snapshots     = array();
foreach ( $option_names as $option_name ) {
	$snapshots[ $option_name ] = get_option( $option_name, $missing );
}
$original_addr = $_SERVER['REMOTE_ADDR'] ?? null;
$original_user = get_current_user_id();
$checks        = array();
$inbox         = new HandoffInbox();
$marker        = 'Handoff flow QA ' . wp_generate_password( 8, false, false );
$started       = time() - 1;
$references    = array();
$mails         = array();
$http_calls    = array();
$mail_result   = true;
$telegram_ok   = true;
$rate_key      = '';
$rate_before   = false;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$capture_mail = static function ( $short_circuit, array $atts ) use ( &$mails, &$mail_result ) {
	$mails[] = $atts;
	return $mail_result;
};

$fake_http = static function ( $pre, array $args, string $url ) use ( &$http_calls, &$telegram_ok ) {
	$http_calls[] = array(
		'url'  => $url,
		'body' => (string) ( $args['body'] ?? '' ),
	);
	if ( $telegram_ok && 0 === strpos( $url, 'https://api.telegram.org/bot' ) ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'ok'     => true,
					'result' => array( 'message_id' => 4242 ),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
	return new WP_Error( 'aisaautopilot_test_blocked', 'Outbound HTTP is blocked in this test.' );
};

// Every enabled channel with a real configuration, overridable per scenario.
$configure = static function ( array $overrides = array() ): void {
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array_merge(
				Settings::widget_defaults(),
				(array) Settings::get( 'widget', array() ),
				array(
					'enabled'                  => true,
					'handoff_enabled'          => true,
					'handoff_email_enabled'    => true,
					'handoff_email_to'         => 'handoff-flow@example.test',
					'handoff_telegram_enabled' => true,
					'handoff_telegram_chat'    => '-1001234567890',
					'handoff_twilio_enabled'   => false,
				),
				$overrides
			),
		)
	);
};

// Never name a global here after a WordPress global such as $post: shutdown hooks (LiteSpeed Cache calls
// get_the_ID()) read it after the checks and turn a green run into a fatal with exit code 255.
$send_handoff = static function ( array $body, bool $reset_rate = true ) use ( &$rate_key, &$references, &$mails, &$http_calls ): WP_REST_Response {
	if ( $reset_rate ) {
		delete_transient( $rate_key );
	}
	$mails      = array();
	$http_calls = array();
	$request    = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/handoff' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );
	$response = rest_do_request( $request );
	$data     = (array) $response->get_data();
	if ( ! empty( $data['reference'] ) ) {
		$references[] = (int) $data['reference'];
	}
	return $response;
};

$recorded_token = '';

// The transcript exactly as widget.js sends it: role and textContent, no name or email.
$transcript = array(
	array(
		'role'    => 'user',
		'content' => $marker . ' — Where is order 1042? Budget 3<4 & "fast" please.',
	),
	array(
		'role'    => 'assistant',
		'content' => 'I could not find that order in the site content.',
	),
	array(
		'role'    => 'user',
		'content' => 'Please ask a person. You can reach me at visitor-flow@example.test',
	),
);

try {
	wp_set_current_user( 0 );
	$_SERVER['REMOTE_ADDR'] = '192.0.2.44';
	$identity               = new ReflectionMethod( RateLimiter::class, 'identity_hash' );
	$identity->setAccessible( true );
	$rate_key    = 'aisaautopilot_rate_widget_handoff_' . $identity->invoke( null, 'widget_handoff' );
	$rate_before = get_transient( $rate_key );

	add_filter( 'pre_wp_mail', $capture_mail, PHP_INT_MAX, 2 );
	add_filter( 'pre_http_request', $fake_http, PHP_INT_MAX, 3 );
	Settings::set_handoff_secret( 'handoff_telegram_token', '123456789:abcdefghijklmnopqrstuv' );

	if ( ! $inbox->is_ready() ) {
		$check( false, 'The handoff inbox table exists on this site' );
		throw new RuntimeException( 'inbox table missing' );
	}

	// 1. Both channels ready: the widget request reaches both and lands in the inbox as new.
	$configure();
	$new_before = $inbox->count_new();
	$response   = $send_handoff( array( 'messages' => $transcript ) );
	$data       = (array) $response->get_data();
	$row        = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$telegram   = array_values( array_filter( $http_calls, static fn( array $call ): bool => 0 === strpos( $call['url'], 'https://api.telegram.org/bot' ) ) );
	$tg_body    = isset( $telegram[0] ) ? (array) json_decode( $telegram[0]['body'], true ) : array();
	$check(
		200 === $response->get_status() && true === ( $data['ok'] ?? null ) && 'sent' === ( $data['code'] ?? '' ) && (int) ( $data['reference'] ?? 0 ) > 0,
		'A ready handoff answers the widget with ok, code sent and an inbox reference'
	);
	$check(
		is_array( $row ) && 'new' === $row['status'] && 3 === count( $row['messages'] ) && $new_before + 1 === $inbox->count_new(),
		'The request is stored in the inbox as new with the whole transcript'
	);
	$check(
		1 === count( $mails )
		&& 'handoff-flow@example.test' === ( is_array( $mails[0]['to'] ) ? implode( ',', $mails[0]['to'] ) : (string) $mails[0]['to'] )
		&& false !== strpos( (string) $mails[0]['message'], 'Where is order 1042?' )
		&& false !== strpos( (string) $mails[0]['message'], 'I could not find that order' ),
		'The email channel receives one message with the transcript at the configured address'
	);
	$check(
		1 === count( $telegram ) && 1 === count( $http_calls )
		&& '-1001234567890' === (string) ( $tg_body['chat_id'] ?? '' )
		&& 'HTML' === ( $tg_body['parse_mode'] ?? '' )
		&& false !== strpos( (string) ( $tg_body['text'] ?? '' ), 'Where is order 1042?' ),
		'The Telegram channel receives one sendMessage call for the configured chat and no other request is made'
	);
	$check(
		false !== strpos( (string) ( $tg_body['text'] ?? '' ), 'Budget 3&lt;4 &amp; &quot;fast&quot; please.' ),
		'Telegram receives the visitor text escaped exactly once for HTML parse mode'
	);
	$check(
		false !== strpos( (string) $mails[0]['message'], 'Budget 3&lt;4 &amp; &quot;fast&quot; please.' ),
		'The email shows the visitor text escaped exactly once'
	);
	$check(
		is_array( $row ) && false !== strpos( (string) ( $row['messages'][0]['content'] ?? '' ), 'Budget 3<4 & "fast" please.' ),
		'The inbox stores the visitor text as it was typed, not as HTML entities'
	);

	// 2. Only email enabled: a disabled channel is skipped and never contacted.
	$configure( array( 'handoff_telegram_enabled' => false ) );
	$response = $send_handoff( array( 'messages' => $transcript ) );
	$check(
		true === ( ( (array) $response->get_data() )['ok'] ?? null ) && 1 === count( $mails ) && 0 === count( $http_calls ),
		'With Telegram switched off only the email is sent and Telegram is never called'
	);

	// 3. One channel fails, the other accepts: fan-out still counts as delivered.
	$configure();
	$mail_result = false;
	$response    = $send_handoff( array( 'messages' => $transcript ) );
	$data        = (array) $response->get_data();
	$row         = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$check(
		true === ( $data['ok'] ?? null ) && is_array( $row ) && 'new' === $row['status'],
		'When email fails but Telegram accepts, the request is delivered and stored as new'
	);

	// 4. Every channel fails: the visitor is told, and the thread is kept as delivery failed.
	$telegram_ok   = false;
	$failed_before = $inbox->count_failed();
	$response      = $send_handoff( array( 'messages' => $transcript ) );
	$data          = (array) $response->get_data();
	$row           = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$check(
		200 === $response->get_status() && false === ( $data['ok'] ?? null ) && 'handoff_failed' === ( $data['code'] ?? '' )
		&& is_array( $row ) && 'failed' === $row['status'] && $failed_before + 1 === $inbox->count_failed(),
		'When no channel accepts, the widget gets handoff_failed and the thread is kept as delivery failed'
	);
	$mail_result = true;
	$telegram_ok = true;

	// 5. Details the site owner did not ask for are ignored, even when a request carries them.
	$fields_off = array_fill_keys( Settings::HANDOFF_FIELD_KEYS, 'off' );
	$configure( array( 'handoff_fields' => $fields_off ) );
	$response = $send_handoff(
		array(
			'messages' => $transcript,
			'name'     => $marker,
			'email'    => 'visitor-flow@example.test',
			'phone'    => '+1 555 010 2030',
			'website'  => 'example.com',
		)
	);
	$data     = (array) $response->get_data();
	$row      = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$tg_text  = isset( $http_calls[0] ) ? (string) ( ( (array) json_decode( $http_calls[0]['body'], true ) )['text'] ?? '' ) : '';
	$check(
		true === ( $data['ok'] ?? null ) && is_array( $row )
		&& '' === $row['visitor_name'] && '' === $row['visitor_email'] && '' === $row['visitor_phone'] && '' === $row['visitor_website']
		&& 1 === count( $mails ) && ! in_array( 'Reply-To: visitor-flow@example.test', (array) ( $mails[0]['headers'] ?? array() ), true )
		&& false !== strpos( $tg_text, 'Contact: not provided' ) && false === strpos( $tg_text, '+1 555 010 2030' ),
		'While every detail is off, a name, email, phone or website in the request is neither stored nor delivered'
	);

	// 5a. Details the site owner asks for are validated, stored and delivered, with Persian digits made Latin.
	$asked = array(
		'name'    => 'required',
		'email'   => 'optional',
		'phone'   => 'required',
		'website' => 'optional',
	);
	$configure( array( 'handoff_fields' => $asked ) );
	$response = $send_handoff(
		array(
			'messages' => $transcript,
			'name'     => $marker,
			'email'    => 'visitor-flow@example.test',
			'phone'    => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
			'website'  => 'shop.example.com/contact',
		)
	);
	$data     = (array) $response->get_data();
	$row      = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$tg_text  = isset( $http_calls[0] ) ? (string) ( ( (array) json_decode( $http_calls[0]['body'], true ) )['text'] ?? '' ) : '';
	$check(
		true === ( $data['ok'] ?? null ) && is_array( $row )
		&& $marker === $row['visitor_name'] && 'visitor-flow@example.test' === $row['visitor_email']
		&& '0912 345 6789' === $row['visitor_phone'] && 'https://shop.example.com/contact' === $row['visitor_website'],
		'Asked-for details are stored with the thread: the phone with Latin digits and the website with https'
	);
	$check(
		1 === count( $mails )
		&& in_array( 'Reply-To: visitor-flow@example.test', (array) ( $mails[0]['headers'] ?? array() ), true )
		&& false !== strpos( (string) $mails[0]['message'], $marker )
		&& false !== strpos( (string) $mails[0]['message'], '0912 345 6789' )
		&& false !== strpos( (string) $mails[0]['message'], 'https://shop.example.com/contact' )
		&& false !== strpos( $tg_text, 'Mobile: 0912 345 6789' )
		&& false !== strpos( $tg_text, 'Website: https://shop.example.com/contact' ),
		'The email shows the details and replies go to the visitor; Telegram lists the mobile number and website'
	);
	$widget_html = ( new \AISAAutoPilot\Frontend\ChatWidget() )->render();
	$check(
		4 === substr_count( $widget_html, 'data-spw-handoff-field=' )
		&& 1 === preg_match( '/data-spw-handoff-field="name"[^>]* required aria-required="true"/', $widget_html )
		&& 1 === preg_match( '/data-spw-handoff-field="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel"[^>]* required aria-required="true"/', $widget_html )
		&& 1 === preg_match( '/data-spw-handoff-field="email"(?![^>]* required)[^>]*>/', $widget_html )
		&& 2 === substr_count( $widget_html, 'spw-handoff__label' ) - substr_count( $widget_html, '<em>' ),
		'The visitor handoff card renders the asked-for details with the right input types and marks only required ones required'
	);
	$configure( array( 'handoff_fields' => $fields_off ) );
	$check(
		0 === substr_count( ( new \AISAAutoPilot\Frontend\ChatWidget() )->render(), 'data-spw-handoff-field=' ),
		'With every detail off the handoff card asks for nothing'
	);

	// 5b. Markup a visitor types stays inert text everywhere, and Persian zero-width non-joiners survive.
	$persian  = "لطفاً پیگیری کنید؛ می\u{200C}خواهم وضعیت سفارش را بدانم";
	$response = $send_handoff(
		array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $marker . ' <script>alert(1)</script> <b>bold</b>',
				),
				array(
					'role'    => 'user',
					'content' => $persian,
				),
			),
		)
	);
	$data     = (array) $response->get_data();
	$row      = ! empty( $data['reference'] ) ? $inbox->get( (int) $data['reference'] ) : null;
	$tg_text  = '';
	foreach ( $http_calls as $call ) {
		if ( 0 === strpos( $call['url'], 'https://api.telegram.org/bot' ) ) {
			$tg_text .= (string) ( ( (array) json_decode( $call['body'], true ) )['text'] ?? '' );
		}
	}
	$check(
		is_array( $row )
		&& false !== strpos( (string) ( $row['messages'][0]['content'] ?? '' ), '<script>alert(1)</script> <b>bold</b>' )
		&& $persian === (string) ( $row['messages'][1]['content'] ?? '' ),
		'Typed markup is stored as plain text and Persian text keeps its zero-width non-joiners'
	);
	$check(
		1 === count( $mails )
		&& false === strpos( (string) $mails[0]['message'], '<script>alert(1)</script>' )
		&& false !== strpos( (string) $mails[0]['message'], '&lt;script&gt;alert(1)&lt;/script&gt;' )
		&& false === strpos( $tg_text, '<script>' )
		&& false !== strpos( $tg_text, '&lt;script&gt;alert(1)&lt;/script&gt;' )
		&& false !== strpos( $tg_text, $persian ),
		'The email and Telegram carry typed markup escaped, never as live HTML'
	);
	wp_set_current_user( 1 );
	ob_start();
	( new \AISAAutoPilot\Admin\HandoffInboxPage() )->render_inbox();
	$inbox_html = (string) ob_get_clean();
	wp_set_current_user( 0 );
	$check(
		false === strpos( $inbox_html, '<script>alert(1)</script>' ) && false === strpos( $inbox_html, '<b>bold</b>' ),
		'The admin inbox renders typed markup escaped, never as live HTML'
	);

	// 6. Refusals record nothing and contact nobody.
	$counts = array( $inbox->count_new(), $inbox->count_failed() );
	$configure( array( 'handoff_fields' => $asked ) );
	$missing_details = $send_handoff(
		array(
			'messages' => $transcript,
			'email'    => 'visitor-flow@example.test',
		)
	);
	$invalid         = $send_handoff(
		array(
			'messages' => $transcript,
			'name'     => $marker,
			'email'    => 'not-an-email',
			'phone'    => '12',
		)
	);
	$invalid_phone   = $send_handoff(
		array(
			'messages' => $transcript,
			'name'     => $marker,
			'phone'    => 'call me',
		)
	);
	$invalid_site    = $send_handoff(
		array(
			'messages' => $transcript,
			'name'     => $marker,
			'phone'    => '+1 555 010 2030',
			'website'  => 'javascript:alert(1)',
		)
	);
	$check(
		400 === $missing_details->get_status() && 'missing_contact' === ( ( (array) $missing_details->get_data() )['code'] ?? '' )
		&& array( 'name', 'phone' ) === ( ( (array) $missing_details->get_data() )['fields'] ?? null )
		&& 400 === $invalid->get_status() && 'invalid_email' === ( ( (array) $invalid->get_data() )['code'] ?? '' )
		&& array( 'email', 'phone' ) === ( ( (array) $invalid->get_data() )['fields'] ?? null )
		&& 'invalid_phone' === ( ( (array) $invalid_phone->get_data() )['code'] ?? '' )
		&& 'invalid_website' === ( ( (array) $invalid_site->get_data() )['code'] ?? '' ),
		'Missing required details and an invalid email, mobile number or website are refused with the fields named'
	);
	$configure( array( 'handoff_fields' => $fields_off ) );
	$empty = $send_handoff( array( 'messages' => array() ) );
	$configure(
		array(
			'handoff_email_enabled'    => false,
			'handoff_telegram_enabled' => false,
		)
	);
	$no_channel = $send_handoff( array( 'messages' => $transcript ) );
	$configure();
	set_transient( $rate_key, 5, HOUR_IN_SECONDS );
	$limited = $send_handoff( array( 'messages' => $transcript ), false );
	$check(
		400 === $empty->get_status() && 'no_messages' === ( ( (array) $empty->get_data() )['code'] ?? '' )
		&& 403 === $no_channel->get_status() && 'handoff_disabled' === ( ( (array) $no_channel->get_data() )['code'] ?? '' )
		&& 429 === $limited->get_status() && 'rate_limited' === ( ( (array) $limited->get_data() )['code'] ?? '' ),
		'An empty transcript, no ready channel and the hourly limit are refused with their codes'
	);
	$check(
		array( $inbox->count_new(), $inbox->count_failed() ) === $counts && 0 === count( $mails ) && 0 === count( $http_calls ),
		'Refused requests store nothing and contact no channel'
	);

	// With recording on, the recorded conversation is the transcript: a forged "assistant" line from the browser is not stored.
	$configure( array( 'conversation_logging_enabled' => true ) );
	$recorded_token = ( new VisitorConversationStore() )->append_turn( '', $marker . ' recorded question about order 1042', 'Recorded answer: order 1042 ships on Monday.', array() )['token'];
	$forged         = $send_handoff(
		array(
			'conversation_id' => $recorded_token,
			'messages'        => array(
				array(
					'role'    => 'user',
					'content' => $marker . ' forged question',
				),
				array(
					'role'    => 'assistant',
					'content' => 'The assistant promised a 90% discount.',
				),
			),
		)
	);
	$forged_row     = $inbox->get( (int) ( ( (array) $forged->get_data() )['reference'] ?? 0 ) );
	$forged_text    = (string) wp_json_encode( $forged_row['messages'] ?? array() );
	$unknown        = $send_handoff(
		array(
			'conversation_id' => '00000000-0000-4000-8000-000000000000',
			'messages'        => $transcript,
		)
	);
	$unknown_row    = $inbox->get( (int) ( ( (array) $unknown->get_data() )['reference'] ?? 0 ) );
	$check(
		false !== strpos( $forged_text, 'ships on Monday' ) && false === strpos( $forged_text, '90% discount' )
		&& false !== strpos( (string) wp_json_encode( $unknown_row['messages'] ?? array() ), 'Where is order 1042' ),
		'With recording on, a handoff stores the recorded conversation instead of the browser\'s copy; an unknown id falls back to the browser\'s'
	);
} catch ( Throwable $error ) {
	if ( 'inbox table missing' !== $error->getMessage() ) {
		$check( false, 'Handoff flow contracts complete without a fatal error: ' . $error->getMessage() );
	}
} finally {
	remove_filter( 'pre_wp_mail', $capture_mail, PHP_INT_MAX );
	remove_filter( 'pre_http_request', $fake_http, PHP_INT_MAX );
	global $wpdb;
	if ( '' !== $recorded_token ) {
		$wpdb->delete( $wpdb->prefix . VisitorConversationStore::TABLE, array( 'session_token' => $recorded_token ), array( '%s' ) );
	}
	foreach ( array_unique( $references ) as $reference ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$inbox->table()} WHERE id = %d AND messages LIKE %s", $reference, '%' . $wpdb->esc_like( $marker ) . '%' ) ); // phpcs:ignore WordPress.DB -- removes only the rows this check created.
	}
	// A local debug mu-plugin may also save every wp_mail() into wp-content/efb-mail-capture: remove only this run's copies.
	$capture_dir = WP_CONTENT_DIR . '/efb-mail-capture';
	if ( is_dir( $capture_dir ) ) {
		foreach ( (array) glob( $capture_dir . '/*.html' ) as $capture_file ) {
			if ( is_string( $capture_file ) && filemtime( $capture_file ) >= $started && false !== strpos( (string) file_get_contents( $capture_file ), $marker ) ) {
				wp_delete_file( $capture_file );
			}
		}
	}
	foreach ( $snapshots as $option_name => $value ) {
		if ( $value === $missing ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $value, false );
		}
	}
	if ( '' !== $rate_key ) {
		if ( false === $rate_before ) {
			delete_transient( $rate_key );
		} else {
			set_transient( $rate_key, $rate_before, HOUR_IN_SECONDS );
		}
	}
	if ( null === $original_addr ) {
		unset( $_SERVER['REMOTE_ADDR'] );
	} else {
		$_SERVER['REMOTE_ADDR'] = $original_addr;
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
