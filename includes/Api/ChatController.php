<?php
declare(strict_types=1);

namespace SitePilot\Api;

use SitePilot\Chat\ChatEngine;
use SitePilot\Chat\ConversationManager;

/**
 * REST controller for chat conversations.
 */
final class ChatController {

	/**
	 * POST /chat/ask — Ask a question (with existing conversation context).
	 */
	public function ask( \WP_REST_Request $request ): \WP_REST_Response {
		$question = sanitize_text_field( (string) $request->get_param( 'question' ) );
		if ( '' === trim( $question ) ) {
			return $this->respond( array( 'message' => __( 'Please enter a question.', 'sitepilot-ai' ) ), false, 400 );
		}

		$conv_id = absint( $request->get_param( 'conversation_id' ) );
		$manager = new ConversationManager();

		// Create or get conversation.
		if ( $conv_id <= 0 ) {
			$conv_id = $manager->create();
		}

		// Get conversation context.
		$context = $manager->get_recent_context( $conv_id );

		// Run RAG engine.
		$engine = new ChatEngine();
		$result = $engine->ask( $question, $context );

		// Save messages.
		$manager->add_message( $conv_id, array(
			'role'    => 'user',
			'content' => $question,
		) );
		$manager->add_message( $conv_id, array(
			'role'    => 'assistant',
			'content' => $result['answer'],
			'sources' => $result['sources'],
			'usage'   => $result['usage'],
		) );

		$result['conversation_id'] = $conv_id;

		return $this->respond( $result, true );
	}

	/**
	 * POST /chat/create — Create a new empty conversation.
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		$title  = sanitize_text_field( (string) $request->get_param( 'title' ) );
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
			return $this->respond( array( 'message' => __( 'Conversation not found.', 'sitepilot-ai' ) ), false, 404 );
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

		return $this->respond( array( 'message' => __( 'Conversation deleted.', 'sitepilot-ai' ) ), true );
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
