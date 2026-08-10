<?php
declare(strict_types=1);

namespace SitePilot\Api;

/**
 * Registers the plugin REST routes under sitepilot/v1.
 */
final class RestRoutes {
	public const NAMESPACE = 'sitepilot/v1';

	public function register(): void {
		$copilot = new CopilotController();
		register_rest_route( self::NAMESPACE, '/forms/plan', array( 'methods' => 'POST', 'callback' => array( $copilot, 'plan' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'prompt' => array( 'type' => 'string', 'required' => true ) ) ) );
		register_rest_route( self::NAMESPACE, '/forms/execute', array( 'methods' => 'POST', 'callback' => array( $copilot, 'execute' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'action_id' => array( 'type' => 'string', 'required' => true ) ) ) );
		register_rest_route( self::NAMESPACE, '/provider/test', array( 'methods' => 'POST', 'callback' => array( $copilot, 'test' ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		$efb = new EfbCopilotController();
		register_rest_route( self::NAMESPACE, '/efb/plan', array( 'methods' => 'POST', 'callback' => array( $efb, 'plan' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/create', array( 'methods' => 'POST', 'callback' => array( $efb, 'create' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/chat', array( 'methods' => 'POST', 'callback' => array( $efb, 'chat' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/help', array( 'methods' => 'POST', 'callback' => array( $efb, 'help' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/customize', array( 'methods' => 'POST', 'callback' => array( $efb, 'customize' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/log', array( 'methods' => 'POST', 'callback' => array( $efb, 'log' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/efb/save', array( 'methods' => 'POST', 'callback' => array( $efb, 'save' ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		$voice = new VoiceController();
		register_rest_route( self::NAMESPACE, '/efb/voice/transcribe', array( 'methods' => 'POST', 'callback' => array( $voice, 'transcribe_efb' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/voice/transcribe', array( 'methods' => 'POST', 'callback' => array( $voice, 'transcribe' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/voice/speak', array( 'methods' => 'POST', 'callback' => array( $voice, 'speak' ), 'permission_callback' => '__return_true' ) );

		$public_chat = new PublicChatController();
		register_rest_route( self::NAMESPACE, '/public/chat', array( 'methods' => 'POST', 'callback' => array( $public_chat, 'ask' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/public/voice', array( 'methods' => 'POST', 'callback' => array( $public_chat, 'transcribe' ), 'permission_callback' => '__return_true' ) );

		$kb = new KnowledgeBaseController();
		register_rest_route( self::NAMESPACE, '/kb/index/batch', array( 'methods' => 'POST', 'callback' => array( $kb, 'index_batch' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/truncate', array( 'methods' => 'POST', 'callback' => array( $kb, 'truncate' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/list', array( 'methods' => 'GET', 'callback' => array( $kb, 'list_entries' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/stats', array( 'methods' => 'GET', 'callback' => array( $kb, 'stats' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/delete', array( 'methods' => 'POST', 'callback' => array( $kb, 'delete_entry' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/chat', array( 'methods' => 'POST', 'callback' => array( $kb, 'chat' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/gaps', array( 'methods' => 'GET', 'callback' => array( $kb, 'gaps' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/kb/gaps/dismiss', array( 'methods' => 'POST', 'callback' => array( $kb, 'gaps_dismiss' ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		register_rest_route( self::NAMESPACE, '/usage/report', array( 'methods' => 'GET', 'callback' => array( $kb, 'usage_report' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/usage/budget', array( 'methods' => 'POST', 'callback' => array( $kb, 'set_budget' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/usage/reset', array( 'methods' => 'POST', 'callback' => array( $kb, 'reset_usage' ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		$chat = new ChatController();
		register_rest_route( self::NAMESPACE, '/chat/ask', array( 'methods' => 'POST', 'callback' => array( $chat, 'ask' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'question' => array( 'type' => 'string', 'required' => true ), 'conversation_id' => array( 'type' => 'integer' ) ) ) );
		register_rest_route( self::NAMESPACE, '/chat/create', array( 'methods' => 'POST', 'callback' => array( $chat, 'create' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/chat/list', array( 'methods' => 'GET', 'callback' => array( $chat, 'list' ), 'permission_callback' => array( $this, 'can_manage' ) ) );
		register_rest_route( self::NAMESPACE, '/chat/get', array( 'methods' => 'GET', 'callback' => array( $chat, 'get' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'id' => array( 'type' => 'integer', 'required' => true ) ) ) );
		register_rest_route( self::NAMESPACE, '/chat/delete', array( 'methods' => 'POST', 'callback' => array( $chat, 'delete' ), 'permission_callback' => array( $this, 'can_manage' ), 'args' => array( 'id' => array( 'type' => 'integer', 'required' => true ) ) ) );

		$widget = new ChatWidgetController();
		register_rest_route( self::NAMESPACE, '/widget/settings', array( 'methods' => 'GET', 'callback' => array( $widget, 'settings' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/widget/ask', array( 'methods' => 'POST', 'callback' => array( $widget, 'ask' ), 'permission_callback' => '__return_true', 'args' => array( 'question' => array( 'type' => 'string', 'required' => true ) ) ) );
		register_rest_route( self::NAMESPACE, '/widget/conversation', array( 'methods' => 'POST', 'callback' => array( $widget, 'conversation' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/widget/handoff', array( 'methods' => 'POST', 'callback' => array( $widget, 'handoff' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/widget/handoff/status', array( 'methods' => 'GET', 'callback' => array( $widget, 'handoff_status' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/widget/test/telegram', array( 'methods' => 'GET', 'callback' => array( $widget, 'test_telegram' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/widget/test/twilio', array( 'methods' => 'GET', 'callback' => array( $widget, 'test_twilio' ), 'permission_callback' => '__return_true' ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
