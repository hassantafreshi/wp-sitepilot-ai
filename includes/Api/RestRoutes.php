<?php
declare(strict_types=1);

namespace SitePilot\Api;

/**
 * Registers the plugin REST routes under sitepilot/v1.
 * Total endpoints: 22 (core 10 + efb 5 + kb 6 + usage 3 + voice 2 + chat 5)
 */
final class RestRoutes {

	public const NAMESPACE = 'sitepilot/v1';

	public function register(): void {
		$copilot = new CopilotController();
		$kb      = new KnowledgeBaseController();
		$voice   = new VoiceController();
		$chat    = new ChatController();

		// ── Core Copilot ──
		register_rest_route( self::NAMESPACE, '/forms/plan', array(
			'methods' => 'POST', 'callback' => array( $copilot, 'plan' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'prompt' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/forms/execute', array(
			'methods' => 'POST', 'callback' => array( $copilot, 'execute' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'action_id' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/provider/test', array(
			'methods' => 'POST', 'callback' => array( $copilot, 'test' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		// ── EFB Copilot ──
		$efb = new EfbCopilotController();
		foreach ( array( 'plan', 'create', 'chat', 'help', 'customize', 'log', 'save' ) as $action ) {
			register_rest_route( self::NAMESPACE, '/efb/' . $action, array(
				'methods' => 'POST', 'callback' => array( $efb, $action ),
				'permission_callback' => array( $this, 'can_manage' ),
			) );
		}

		// ── Knowledge Base ──
		register_rest_route( self::NAMESPACE, '/kb/index/batch', array(
			'methods' => 'POST', 'callback' => array( $kb, 'index_batch' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'offset' => array( 'type' => 'integer', 'default' => 0 ) ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/truncate', array(
			'methods' => 'POST', 'callback' => array( $kb, 'truncate' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/list', array(
			'methods' => 'GET', 'callback' => array( $kb, 'list_entries' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/stats', array(
			'methods' => 'GET', 'callback' => array( $kb, 'stats' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/delete', array(
			'methods' => 'POST', 'callback' => array( $kb, 'delete_entry' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'post_id' => array( 'type' => 'integer', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/chat', array(
			'methods' => 'POST', 'callback' => array( $kb, 'chat' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'question' => array( 'type' => 'string', 'required' => true ) ),
		) );

		// ── Usage ──
		register_rest_route( self::NAMESPACE, '/usage/report', array(
			'methods' => 'GET', 'callback' => array( $kb, 'usage_report' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/usage/budget', array(
			'methods' => 'POST', 'callback' => array( $kb, 'set_budget' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'budget' => array( 'type' => 'integer', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/usage/reset', array(
			'methods' => 'POST', 'callback' => array( $kb, 'reset_usage' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		// ── Voice (STT + TTS) ──
		register_rest_route( self::NAMESPACE, '/voice/transcribe', array(
			'methods' => 'POST', 'callback' => array( $voice, 'transcribe' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array(
				'audio'  => array( 'type' => 'string', 'required' => true ),
				'format' => array( 'type' => 'string', 'default' => 'webm' ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/voice/speak', array(
			'methods' => 'POST', 'callback' => array( $voice, 'synthesize' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array(
				'text'  => array( 'type' => 'string', 'required' => true ),
				'voice' => array( 'type' => 'string', 'default' => 'alloy' ),
			),
		) );

		// ── Chat (AI RAG conversations) ──
		register_rest_route( self::NAMESPACE, '/chat/ask', array(
			'methods' => 'POST', 'callback' => array( $chat, 'ask' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array(
				'question'       => array( 'type' => 'string', 'required' => true ),
				'conversation_id' => array( 'type' => 'integer', 'default' => 0 ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/chat/create', array(
			'methods' => 'POST', 'callback' => array( $chat, 'create' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/list', array(
			'methods' => 'GET', 'callback' => array( $chat, 'list' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/get', array(
			'methods' => 'GET', 'callback' => array( $chat, 'get' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/delete', array(
			'methods' => 'POST', 'callback' => array( $chat, 'delete' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args' => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
		) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
