<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Chat\ConversationManager;

/**
 * REST controller for chat conversations.
 */
final class ChatController {

	/**
	 * POST /chat/ask — Ask a question (with existing conversation context).
	 */
	public function ask( \WP_REST_Request $request ): \WP_REST_Response {
		$question = trim( sanitize_textarea_field( (string) $request->get_param( 'question' ) ) );
		if ( '' === $question || mb_strlen( $question ) > 2000 ) {
			return $this->respond( array( 'message' => __( 'Please enter a question of up to 2,000 characters.', 'ai-site-assistant-autopilot' ) ), false, 400 );
		}

		$conv_id = absint( $request->get_param( 'conversation_id' ) );
		$manager = new ConversationManager();

		// Create or get conversation.
		if ( $conv_id <= 0 ) {
			$conv_id = $manager->create();
		} elseif ( ! $manager->get( $conv_id ) ) {
			return $this->respond( array( 'message' => __( 'Conversation not found.', 'ai-site-assistant-autopilot' ) ), false, 404 );
		}

		// Get conversation context.
		$context = $manager->get_recent_context( $conv_id );

		// Run RAG engine.
		$engine = new ChatEngine();
		$result = $engine->ask( $question, $context );
		if ( 'usage_cap_reached' === (string) ( $result['status'] ?? '' ) ) {
			$result['conversation_id'] = $conv_id;

			return new \WP_REST_Response(
				array_merge(
					array(
						'ok'      => false,
						'code'    => 'usage_cap_reached',
						'message' => (string) ( $result['answer'] ?? '' ),
					),
					$result
				),
				429
			);
		}

		// Save messages.
		$manager->add_message(
			$conv_id,
			array(
				'role'    => 'user',
				'content' => $question,
			)
		);
		$manager->add_message(
			$conv_id,
			array(
				'role'    => 'assistant',
				'content' => $result['answer'],
				'sources' => $result['sources'],
				'usage'   => $result['usage'],
				// Kept so a reopened conversation can still mark answers the content did not cover.
				'status'  => (string) ( $result['status'] ?? 'answered' ),
			)
		);

		$result['conversation_id'] = $conv_id;

		return $this->respond( $result, true );
	}

	/**
	 * POST /chat/create — Create a new empty conversation.
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		$title   = mb_substr( sanitize_text_field( (string) $request->get_param( 'title' ) ), 0, 120 );
		$manager = new ConversationManager();
		$conv_id = $manager->create( $title );

		return $this->respond( array( 'conversation_id' => $conv_id ), true );
	}

	/**
	 * GET /chat/list — List conversations.
	 */
	public function list( \WP_REST_Request $request ): \WP_REST_Response {
		$manager = new ConversationManager();
		$convs   = $manager->list( 50, 0 );

		return $this->respond( array( 'conversations' => $convs ), true );
	}

	/**
	 * GET /chat/get — Get a single conversation.
	 */
	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$conv_id = absint( $request->get_param( 'id' ) );
		$manager = new ConversationManager();
		$conv    = $manager->get( $conv_id );

		if ( ! $conv ) {
			return $this->respond( array( 'message' => __( 'Conversation not found.', 'ai-site-assistant-autopilot' ) ), false, 404 );
		}

		// Decode messages for the client.
		$conv['messages'] = json_decode( $conv['messages'], true ) ?? array();

		return $this->respond( array( 'conversation' => $conv ), true );
	}

	/**
	 * POST /chat/delete — Delete a conversation.
	 */
	public function delete( \WP_REST_Request $request ): \WP_REST_Response {
		$conv_id = absint( $request->get_param( 'id' ) );
		$manager = new ConversationManager();
		$manager->delete( $conv_id );

		return $this->respond( array( 'message' => __( 'Conversation deleted.', 'ai-site-assistant-autopilot' ) ), true );
	}

	/**
	 * Uniform response envelope.
	 *
	 * @param array<string,mixed> $data
	 */
	private function respond( array $data, bool $ok, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( array_merge( array( 'ok' => $ok ), $data ), $status );
	}
}
