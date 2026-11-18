<?php
/**
 * Plugin Name: AISA EFB e2e — AI provider mock (test site only)
 * Description: Answers the copilot's AI requests to https://ai-mock.invalid/ with replies queued by
 *              tests/efb_e2e/ctl.php and records each prompt, so the EFB end-to-end checks never call a real
 *              provider. Copy into wp-content/mu-plugins of a throwaway test site only.
 */

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'ai-mock.invalid' ) ) {
			return $pre;
		}
		$queue = get_option( 'aisa_e2e_ai_queue', array() );
		$queue = is_array( $queue ) ? $queue : array();
		$reply = array_shift( $queue );
		update_option( 'aisa_e2e_ai_queue', $queue, false );
		$body      = json_decode( (string) ( $args['body'] ?? '' ), true );
		$prompts   = get_option( 'aisa_e2e_ai_prompts', array() );
		$prompts   = is_array( $prompts ) ? $prompts : array();
		$prompts[] = array(
			'system' => (string) ( $body['messages'][0]['content'] ?? '' ),
			'user'   => (string) ( $body['messages'][1]['content'] ?? '' ),
		);
		update_option( 'aisa_e2e_ai_prompts', array_slice( $prompts, -20 ), false );

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => wp_json_encode( null === $reply ? array( 'reply' => 'No scripted reply.', 'action' => 'chat' ) : $reply ) ) ) ),
					'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20 ),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
