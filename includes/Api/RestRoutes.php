<?php
declare(strict_types=1);

namespace AISAAutoPilot\Api;

use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Core\Settings;
/**
 * Registers the plugin REST routes under aisaautopilot/v1.
 */
final class RestRoutes {
	public const NAMESPACE = 'aisaautopilot/v1';

	public function register(): void {
		$copilot = new CopilotController();
		register_rest_route(
			self::NAMESPACE,
			'/forms/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $copilot, 'plan' ),
				'permission_callback' => array( $this, 'can_manage_form_composer' ),
				'args'                => array(
					'prompt'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'revise_of' => array( 'type' => 'string' ),
					'selected'  => array( 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/forms/edit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $copilot, 'edit' ),
				'permission_callback' => array( $this, 'can_manage_form_composer' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'ops'       => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/forms/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $copilot, 'execute' ),
				'permission_callback' => array( $this, 'can_manage_form_composer' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		$content = new ContentController();
		register_rest_route(
			self::NAMESPACE,
			'/content/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $content, 'plan' ),
				'permission_callback' => array( $this, 'can_manage_content_studio' ),
				'args'                => array(
					'prompt' => array(
						'type'     => 'string',
						'required' => true,
					),
					// site | formal | plain, checked by the controller (400 invalid_tone).
					'tone'   => array(
						'type'    => 'string',
						'default' => 'site',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/content/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $content, 'execute' ),
				'permission_callback' => array( $this, 'can_manage_content_studio' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/provider/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $copilot, 'test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/provider/models',
			array(
				'methods'             => 'POST',
				'callback'            => array( $copilot, 'models' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		$health = new HealthController();
		register_rest_route(
			self::NAMESPACE,
			'/health/diagnostics',
			array(
				'methods'             => 'POST',
				'callback'            => array( $health, 'diagnostics' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		$activity = new ActivityController();
		register_rest_route(
			self::NAMESPACE,
			'/activity',
			array(
				'methods'             => 'GET',
				'callback'            => array( $activity, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'limit'  => array( 'type' => 'integer' ),
					'level'  => array( 'type' => 'string' ),
					'search' => array( 'type' => 'string' ),
				),
			)
		);
		$inbox = new HandoffInboxController();
		register_rest_route(
			self::NAMESPACE,
			'/handoffs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $inbox, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/handoffs/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $inbox, 'get' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/handoffs/(?P<id>\\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $inbox, 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/handoffs/(?P<id>\\d+)/archive',
			array(
				'methods'             => 'POST',
				'callback'            => array( $inbox, 'archive' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'archived' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/handoffs/archive-resolved',
			array(
				'methods'             => 'POST',
				'callback'            => array( $inbox, 'archive_resolved' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		$conversations = new VisitorConversationController();
		register_rest_route(
			self::NAMESPACE,
			'/widget/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $conversations, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/conversations/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $conversations, 'get' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/ai/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( new AiStatusController(), 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/privacy/retention/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( new RetentionController(), 'run' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$delivery = new FormDeliveryController();
		register_rest_route(
			self::NAMESPACE,
			'/forms/delivery/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $delivery, 'test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		$outbox = new FormOutboxController();
		foreach ( array( 'resend', 'delete' ) as $outbox_action ) {
			register_rest_route(
				self::NAMESPACE,
				'/forms/outbox/(?P<id>\\d+)/' . $outbox_action,
				array(
					'methods'             => 'POST',
					'callback'            => array( $outbox, $outbox_action ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				)
			);
		}

		// WooCommerce Phase 1: read-only dashboard and an explicitly approved
		// simple product draft. Order mutations, product editing, and bulk tools
		// are intentionally not registered before Phase 2.
		$woo       = new WooCommerceController();
		$analytics = new WooCommerceAnalyticsController();
		$orders    = new WooCommerceOrderController();
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $woo, 'check' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $woo, 'categories' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $woo, 'plan' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
				'args'                => array(
					'prompt' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $woo, 'execute' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/smart-search',
			array(
				'methods'             => 'POST',
				'callback'            => array( $woo, 'smart_search' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
				'args'                => array(
					'query' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/analytics/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $analytics, 'dashboard' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/orders/recent',
			array(
				'methods'             => 'GET',
				'callback'            => array( $orders, 'recent' ),
				'permission_callback' => array( $this, 'can_manage_woocommerce' ),
			)
		);
		$efb = new EfbCopilotController();
		register_rest_route(
			self::NAMESPACE,
			'/efb/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'plan' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'create' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'chat' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/help',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'help' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/customize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'customize' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'client_log' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'save' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);

		$voice = new VoiceController();
		register_rest_route(
			self::NAMESPACE,
			'/forms/voice/transcribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $voice, 'transcribe_form_builder' ),
				'permission_callback' => array( $this, 'can_manage_form_composer' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/efb/voice/transcribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $voice, 'transcribe_efb' ),
				'permission_callback' => array( $this, 'can_manage_efb_copilot' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/chat/voice/transcribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $voice, 'transcribe_chat' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		$public_chat = new PublicChatController();
		register_rest_route(
			self::NAMESPACE,
			'/public/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $public_chat, 'ask' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/public/voice',
			array(
				'methods'             => 'POST',
				'callback'            => array( $public_chat, 'transcribe' ),
				'permission_callback' => '__return_true',
			)
		);

		$kb = new KnowledgeBaseController();
		register_rest_route(
			self::NAMESPACE,
			'/kb/index/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'index_batch' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/truncate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'truncate' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $kb, 'list_entries' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $kb, 'stats' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'delete_entry' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'chat' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/gaps',
			array(
				'methods'             => 'GET',
				'callback'            => array( $kb, 'gaps' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/integrations/conflicts/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( new IntegrationsController(), 'dismiss_conflicts' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'plugins' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/gaps/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'gaps_dismiss' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/answers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $kb, 'answers' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $kb, 'answers_save' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/answers/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'answers_delete' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/usage/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( $kb, 'usage_report' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/usage/budget',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'set_budget' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/usage/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $kb, 'reset_usage' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$chat = new ChatController();
		register_rest_route(
			self::NAMESPACE,
			'/chat/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( $chat, 'ask' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'question'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'conversation_id' => array( 'type' => 'integer' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/chat/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $chat, 'create' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/chat/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $chat, 'list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/chat/get',
			array(
				'methods'             => 'GET',
				'callback'            => array( $chat, 'get' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/chat/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $chat, 'delete' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		$admin_widget = new AdminWidgetSettingsController();
		register_rest_route(
			self::NAMESPACE,
			'/widget/admin-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $admin_widget, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$admin_handoff = new AdminHandoffSettingsController();
		register_rest_route(
			self::NAMESPACE,
			'/handoff/admin-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $admin_handoff, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/handoff/visitor-fields',
			array(
				'methods'             => 'POST',
				'callback'            => array( $admin_handoff, 'update_fields' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$admin_voice = new AdminVoiceSettingsController();
		register_rest_route(
			self::NAMESPACE,
			'/voice/admin-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $admin_voice, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$admin_store = new AdminStoreSettingsController();
		// Reviewed AI descriptions for existing products (off until the store manager turns them on).
		$descriptions = new ProductDescriptionsController();
		foreach (
			array(
				'/woocommerce/descriptions'       => array( 'GET', 'products' ),
				'/woocommerce/descriptions/draft' => array( 'POST', 'draft' ),
				'/woocommerce/descriptions/apply' => array( 'POST', 'apply' ),
				'/woocommerce/descriptions/undo'  => array( 'POST', 'undo' ),
			) as $route => $handler
		) {
			register_rest_route(
				self::NAMESPACE,
				$route,
				array(
					'methods'             => $handler[0],
					'callback'            => array( $descriptions, $handler[1] ),
					'permission_callback' => array( $this, 'can_manage_woocommerce' ),
				)
			);
		}
		// Abandoned-cart reminders: the store manager sends the one reminder or deletes a cart.
		$carts = new CartRecoveryController();
		foreach ( array( 'remind', 'delete' ) as $cart_action ) {
			register_rest_route(
				self::NAMESPACE,
				'/woocommerce/carts/(?P<id>\d+)/' . $cart_action,
				array(
					'methods'             => 'POST',
					'callback'            => array( $carts, $cart_action ),
					'permission_callback' => array( $this, 'can_manage_woocommerce' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				)
			);
		}
		register_rest_route(
			self::NAMESPACE,
			'/woocommerce/admin-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $admin_store, 'update' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		$widget = new ChatWidgetController();
		register_rest_route(
			self::NAMESPACE,
			'/widget/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $widget, 'settings' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( $widget, 'ask' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'question' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/conversation',
			array(
				'methods'             => 'POST',
				'callback'            => array( $widget, 'conversation' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/handoff',
			array(
				'methods'             => 'POST',
				'callback'            => array( $widget, 'handoff' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/handoff/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $widget, 'handoff_status' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/test/telegram',
			array(
				'methods'             => 'GET',
				'callback'            => array( $widget, 'test_telegram' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/test/twilio',
			array(
				'methods'             => 'GET',
				'callback'            => array( $widget, 'test_twilio' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/widget/test/(?P<channel>whatsapp|slack|signal)',
			array(
				// POST: the Slack check posts a line to the channel, so it is not a safe GET.
				'methods'             => 'POST',
				'callback'            => array( $widget, 'test_channel' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function can_manage(): bool {

		return current_user_can( 'manage_options' );
	}

	public function can_manage_form_composer(): bool {

		return $this->can_manage() && Settings::get( 'form_composer_enabled', true );
	}

	public function can_manage_content_studio(): bool {

		return $this->can_manage() && Settings::get( 'content_studio_enabled', true );
	}

	public function can_manage_efb_copilot(): bool {

		return $this->can_manage() && Settings::get( 'efb_copilot_enabled', true );
	}

	public function can_manage_woocommerce(): bool {

		return $this->can_manage()
			&& WooCommerceConnector::is_active()
			&& WooCommerceConnector::is_ai_enabled()
			&& WooCommerceConnector::current_user_can_manage();
	}
}
