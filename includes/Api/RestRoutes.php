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

		register_rest_route(
			self::NAMESPACE,
			'/efb/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $efb, 'save' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Only administrators may use the Copilot. The REST cookie nonce (X-WP-Nonce)
	 * is verified automatically by WordPress for logged-in cookie auth.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
