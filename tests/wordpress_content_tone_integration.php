<?php
/**
 * Local WordPress checks for the Content Studio voice (T32): Standard, Formal
 * or Plain. No request leaves the site: `pre_http_request` answers the AI call with a canned
 * draft and keeps the request body, so the system prompt that would have been sent can be read.
 *
 * Proves: an unknown or malformed voice is refused (400 invalid_tone) before any AI request;
 * the default voice sends exactly the prompt an explicit "site" sends, with no voice line;
 * Formal and Plain each add their own line; the voice is frozen in the proposal and written
 * to the draft from it (a voice sent with the approval is ignored); the last choice is
 * remembered per administrator and per site and Write opens on it as a radio group; History
 * names the voice; and the proposal store turns an unknown voice into the default.
 * Settings, the API key, the usage totals, the activity log, the user option and the drafts
 * are restored or removed in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Admin\HistoryPage;
use AISAAutoPilot\Content\ContentStudioReadModel;
use AISAAutoPilot\Content\ContentTone;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Skills\ContentDraftSkill;

global $wpdb;
$missing   = new stdClass();
$options   = array( Settings::OPTION, Settings::KEY_OPTION, 'aisaautopilot_logs', 'aisaautopilot_usage_totals', 'aisaautopilot_usage_period', 'aisaautopilot_monthly_budget' );
$snapshots = array();
foreach ( $options as $option ) {
	$snapshots[ $option ] = get_option( $option, $missing );
}
$admin         = 1;
$original_tone = get_user_option( ContentTone::USER_OPTION, $admin );
$checks        = array();
$bodies        = array();
$created       = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
// Answers the AI call with a canned draft and records what would have been sent.
$canned = static function ( $pre, $args, $url ) use ( &$bodies ) {
	$bodies[] = json_decode( (string) ( $args['body'] ?? '' ), true );
	$draft    = array(
		'intent'     => 'content_draft',
		'content'    => array(
			'post_type'        => 'post',
			'title'            => 'AutoPilot QA voice draft ' . count( $bodies ),
			'excerpt'          => 'A short summary.',
			'body'             => '<p>We ship within ten working days.</p>',
			'meta_description' => 'Shipping times.',
			'slug'             => 'autopilot-qa-voice-draft-' . count( $bodies ),
		),
		'risk_level' => 'low',
		'notes'      => '',
	);
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => wp_json_encode( $draft ) ) ) ),
				'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20 ),
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
	);
};
$plan = static function ( array $params ): WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/content/plan' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};
$system = static function ( $body ): string {
	return is_array( $body ) ? (string) ( $body['messages'][0]['content'] ?? '' ) : '';
};
$render = static function ( callable $callback, array $get ): string {
	$_GET = $get;
	ob_start();
	$callback();
	$_GET = array();
	return (string) ob_get_clean();
};

add_filter( 'pre_http_request', $canned, 10, 3 );
try {
	wp_set_current_user( $admin );
	Settings::update(
		array(
			'content_studio_enabled' => true,
			'provider'               => 'deepseek',
			'base_url'               => 'https://content-tone-test.invalid/v1',
			'model'                  => 'content-tone-test',
			'key_management'         => 'keyvault',
		)
	);
	Settings::set_api_key( 'content-tone-test-key' ); // Inert; never printed or sent anywhere.
	delete_user_option( $admin, ContentTone::USER_OPTION );
	delete_option( 'aisaautopilot_usage_totals' );

	// 1. Unknown or malformed voice: 400 invalid_tone, before any AI request.
	$casual = $plan( array( 'prompt' => 'Write about shipping.', 'tone' => 'casual' ) );
	$array  = $plan( array( 'prompt' => 'Write about shipping.', 'tone' => array( 'formal' ) ) );
	$check(
		400 === $casual->get_status() && 'invalid_tone' === ( $casual->get_data()['code'] ?? '' )
		&& 400 === $array->get_status() && array() === $bodies && false === get_user_option( ContentTone::USER_OPTION, $admin ),
		'An unknown or malformed voice is refused with invalid_tone before any AI request, and nothing is remembered'
	);

	// 2. No voice and "site": the same prompt, with no voice line.
	$default  = $plan( array( 'prompt' => 'Write about shipping.' ) );
	$explicit = $plan( array( 'prompt' => 'Write about shipping.', 'tone' => 'site' ) );
	$check(
		200 === $default->get_status() && 200 === $explicit->get_status() && 2 === count( $bodies )
		&& $system( $bodies[0] ) === $system( $bodies[1] ) && false === strpos( $system( $bodies[0] ), 'Voice:' )
		&& 0 === strpos( $system( $bodies[0] ), 'You are AutoPilot AI Content Studio' )
		&& 'site' === ( $default->get_data()['preview']['tone'] ?? '' ),
		'Without a voice, and with "site", the AI gets the same prompt as before, with no voice line'
	);

	// 3. Formal and Plain add their own line; the preview names the voice.
	$formal = $plan( array( 'prompt' => 'Write about shipping.', 'tone' => 'formal' ) );
	$plain  = $plan( array( 'prompt' => 'Write about shipping.', 'tone' => 'plain' ) );
	$check(
		false !== strpos( $system( $bodies[2] ), ContentDraftSkill::tone_instruction( 'formal' ) ) && false === strpos( $system( $bodies[2] ), 'Voice: plain' )
		&& false !== strpos( $system( $bodies[3] ), ContentDraftSkill::tone_instruction( 'plain' ) ) && false === strpos( $system( $bodies[3] ), 'Voice: formal' )
		&& $system( $bodies[0] ) . "\n" . ContentDraftSkill::tone_instruction( 'formal' ) === $system( $bodies[2] )
		&& 'Formal' === ( $formal->get_data()['preview']['tone_label'] ?? '' ) && 'Plain' === ( $plain->get_data()['preview']['tone_label'] ?? '' ),
		'Formal and Plain each add only their own voice line to the same prompt, and the preview names the voice'
	);

	// 4. Frozen in the proposal; the draft takes the voice from it, not from the approval.
	$action_id = (string) ( $formal->get_data()['action_id'] ?? '' );
	$record    = ( new ActionStore() )->get( $action_id );
	$approve   = new WP_REST_Request( 'POST', '/aisaautopilot/v1/content/execute' );
	$approve->set_param( 'action_id', $action_id );
	$approve->set_param( 'tone', 'plain' );
	$done    = rest_do_request( $approve );
	$post_id = (int) ( $done->get_data()['result']['post_id'] ?? 0 );
	if ( $post_id > 0 ) {
		$created[] = $post_id;
	}
	$check(
		'formal' === ( $record['execution_context']['tone'] ?? '' ) && 200 === $done->get_status() && $post_id > 0
		&& 'formal' === get_post_meta( $post_id, ContentTone::POST_META, true ),
		'The voice is frozen in the proposal and written to the draft from it; a voice sent with the approval is ignored'
	);

	// 5. The last choice is remembered per administrator and per site; Write opens on it.
	$write = $render( array( new CopilotPage(), 'render' ), array( 'page' => 'aisaautopilot-form-builder', 'tab' => 'write' ) );
	$check(
		'plain' === ContentTone::preferred( $admin ) && 'plain' === get_user_meta( $admin, $wpdb->get_blog_prefix() . ContentTone::USER_OPTION, true )
		&& 1 === preg_match( '/role="radiogroup"[^>]*data-content-voice/', $write ) && 3 === substr_count( $write, 'role="radio"' )
		&& 1 === preg_match( '/data-content-voice-option="plain" aria-checked="true" tabindex="0"/', $write )
		&& 1 === preg_match( '/data-content-voice-option="site" aria-checked="false" tabindex="-1"/', $write ),
		'The last voice is remembered for this administrator on this site, and Write opens on it as a radio group'
	);

	// 6. History names the voice.
	$rows    = ( new ContentStudioReadModel() )->history( 20 );
	$row     = array_values( array_filter( $rows, static fn( array $r ): bool => $r['id'] === $post_id ) );
	$history = $render( array( new HistoryPage(), 'render' ), array( 'page' => 'aisaautopilot-history' ) );
	$check(
		isset( $row[0] ) && 'Formal' === $row[0]['voice'] && false !== strpos( $history, 'Voice: Formal' ),
		'History names the voice a draft was written in'
	);

	// 7. The proposal store accepts only a known voice.
	$store    = new ActionStore();
	$bogus_id = $store->create( array( 'title' => 'x' ), 'content_draft', array( 'tone' => 'shouty' ) );
	$check( 'site' === ( $store->get( $bogus_id )['execution_context']['tone'] ?? '' ), 'The proposal store turns an unknown voice into the default' );
} catch ( Throwable $error ) {
	$check( false, 'Unexpected ' . get_class( $error ) . ': ' . $error->getMessage() );
} finally {
	remove_filter( 'pre_http_request', $canned, 10 );
	foreach ( $created as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $snapshots as $option => $value ) {
		if ( $value === $missing ) {
			delete_option( $option );
		} else {
			update_option( $option, $value, false );
		}
	}
	if ( false === $original_tone ) {
		delete_user_option( $admin, ContentTone::USER_OPTION );
	} else {
		update_user_option( $admin, ContentTone::USER_OPTION, $original_tone );
	}
	wp_set_current_user( 0 );
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
