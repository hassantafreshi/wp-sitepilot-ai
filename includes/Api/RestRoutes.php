<?php
declare(strict_types=1);

namespace SitePilot\Api;

/**
 * Registers the plugin REST routes under sitepilot/v1.
 *
 * Total: 22 endpoints (12 existing + 10 new KB/chat/usage).
 */
final class RestRoutes {

	public const NAMESPACE = 'sitepilot/v1';

	public function register(): void {
		$controller = new CopilotController();
		$kb_controller = new KnowledgeBaseController();
		$chat_controller = new ChatController();

		/* ── Existing Form Copilot Endpoints (unchanged) ── */
		register_rest_route( self::NAMESPACE, '/forms/plan', array(
			'methods'             => 'POST',
			'callback'            => array( $controller, 'plan' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array( 'prompt' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/forms/execute', array(
			'methods'             => 'POST',
			'callback'            => array( $controller, 'execute' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array( 'action_id' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/provider/test', array(
			'methods'             => 'POST',
			'callback'            => array( $controller, 'test' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		/* ── Easy Form Builder Endpoints (unchanged) ── */
		$efb = new EfbCopilotController();
		register_rest_route( self::NAMESPACE, '/efb/plan', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'plan' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array( 'prompt' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/create', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'create' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/chat', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'chat' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/help', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'help' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/customize', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'customize' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/log', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'log' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/efb/save', array(
			'methods'             => 'POST',
			'callback'            => array( $efb, 'save' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		/* ── Knowledge Base Endpoints ── */
		register_rest_route( self::NAMESPACE, '/kb/index/batch', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'index_batch' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/truncate', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'truncate' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/list', array(
			'methods'             => 'GET',
			'callback'            => array( $kb_controller, 'list_entries' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/stats', array(
			'methods'             => 'GET',
			'callback'            => array( $kb_controller, 'stats' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/delete', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'delete_entry' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/kb/chat', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'chat' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		/* ── Usage / Budget Endpoints ── */
		register_rest_route( self::NAMESPACE, '/usage/report', array(
			'methods'             => 'GET',
			'callback'            => array( $kb_controller, 'usage_report' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/usage/budget', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'set_budget' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/usage/reset', array(
			'methods'             => 'POST',
			'callback'            => array( $kb_controller, 'reset_usage' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );

		/* ── RAG Chat Endpoints ── */
		register_rest_route( self::NAMESPACE, '/chat/ask', array(
			'methods'             => 'POST',
			'callback'            => array( $chat_controller, 'ask' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array( 'question' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/create', array(
			'methods'             => 'POST',
			'callback'            => array( $chat_controller, 'create' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/list', array(
			'methods'             => 'GET',
			'callback'            => array( $chat_controller, 'list' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/get', array(
			'methods'             => 'GET',
			'callback'            => array( $chat_controller, 'get' ),
			'permission_callback' => array( $this, 'can_manage' ),
			'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
		) );
		register_rest_route( self::NAMESPACE, '/chat/delete', array(
			'methods'             => 'POST',
			'callback'            => array( $chat_controller, 'delete' ),
			'permission_callback' => array( $this, 'can_manage' ),
		) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
