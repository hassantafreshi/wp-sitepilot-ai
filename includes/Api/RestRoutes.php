<?php
declare(strict_types=1);

namespace SitePilot\Api;

/**
 * Registers the plugin REST routes under sitepilot/v1.
 */
final class RestRoutes {

	public const NAMESPACE = 'sitepilot/v1';

	public function register(): void {
		$controller = new CopilotController();

		register_rest_route(
			self::NAMESPACE,
			'/forms/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $controller, 'plan' ),
				'permission_callback' => array( $this, 'can_manage' ),
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
			'/forms/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $controller, 'execute' ),
				'permission_callback' => array( $this, 'can_manage' ),
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
				'callback'            => array( $controller, 'test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Easy Form Builder Copilot (floating button on EFB admin pages).
		$efb = new EfbCopilotController();

		register_rest_route(
			self::NAMESPACE,
			'/efb/plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'plan' ),
				'permission_callback' => array( $this, 'can_manage' ),
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
			'/efb/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'create' ),
				'permission_callback' => array( $this, 'can_manage' ),
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
			'/efb/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'chat' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/efb/help',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'help' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/efb/customize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'customize' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Client-side execution reports.
		register_rest_route(
			self::NAMESPACE,
			'/efb/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'log' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/efb/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'save' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// ── Knowledge Base endpoints ──
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
				'args'                => array(
					'question' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		// ── Usage / Credit endpoints ──
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
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
