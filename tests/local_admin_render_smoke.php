<?php
declare(strict_types=1);

define( 'WP_ADMIN', true );
require 'C:/xampp/htdocs/wp/wp-load.php';

wp_set_current_user( 1 );

$tests = array(
	'home'                 => static function (): void {
		( new \AISAAutoPilot\Admin\HomePage() )->render();
	},
	'setup_wizard'         => static function (): void {
		$_GET['page'] = 'aisaautopilot-setup';
		( new \AISAAutoPilot\Admin\SettingsPage() )->render();
	},
	'setup_advanced_alias' => static function (): void {
		$_GET['page'] = 'aisaautopilot-settings';
		( new \AISAAutoPilot\Admin\SettingsPage() )->render();
	},
	'integrations'         => static function (): void {
		$_GET['page'] = 'aisaautopilot-integrations';
		( new \AISAAutoPilot\Admin\IntegrationsPage() )->render();
	},
	'kb_overview'          => static function (): void {
		$_GET['page'] = 'aisaautopilot-kb';
		( new \AISAAutoPilot\Admin\KnowledgeBasePage() )->render();
	},
	'kb_queue'             => static function (): void {
		$_GET['page'] = 'aisaautopilot-kb';
		$_GET['tab']  = 'queue';
		( new \AISAAutoPilot\Admin\KnowledgeBasePage() )->render();
	},
	'answer_conversations' => static function (): void {
		( new \AISAAutoPilot\Admin\AnswerConversationsPage() )->render();
	},
	'answer_widget'        => static function (): void {
		$_GET['tab'] = 'widget';
		( new \AISAAutoPilot\Admin\AnswerConversationsPage() )->render();
	},
	'answer_handoff'       => static function (): void {
		$_GET['tab'] = 'handoff';
		( new \AISAAutoPilot\Admin\AnswerConversationsPage() )->render();
	},
	'answer_voice'         => static function (): void {
		$_GET['tab'] = 'voice';
		( new \AISAAutoPilot\Admin\AnswerConversationsPage() )->render();
	},
	'handoff_inbox'        => static function (): void {
		( new \AISAAutoPilot\Admin\HandoffInboxPage() )->render();
	},
	'store_new_product'    => static function (): void {
		$_GET['tab'] = 'newproduct';
		( new \AISAAutoPilot\Admin\WooCommerceDashboardPage() )->render();
	},
	'store_capabilities'   => static function (): void {
		$_GET['tab'] = 'capabilities';
		( new \AISAAutoPilot\Admin\WooCommerceDashboardPage() )->render();
	},
	'store_products'       => static function (): void {
		$_GET['tab'] = 'products';
		( new \AISAAutoPilot\Admin\WooCommerceDashboardPage() )->render();
	},
	'store_orders'         => static function (): void {
		$_GET['tab'] = 'orders';
		( new \AISAAutoPilot\Admin\WooCommerceDashboardPage() )->render();
	},
	'studio_write'         => static function (): void {
		$_GET['mode'] = 'content';
		( new \AISAAutoPilot\Admin\CopilotPage() )->render();
	},
	'studio_templates'     => static function (): void {
		$_GET['mode'] = 'templates';
		( new \AISAAutoPilot\Admin\CopilotPage() )->render();
	},
	'studio_history'       => static function (): void {
		( new \AISAAutoPilot\Admin\HistoryPage() )->render();
	},
);

foreach ( $tests as $name => $render ) {
	$_GET = array();
	ob_start();
	try {
		$render();
		$html = (string) ob_get_clean();
		printf(
			"%s :: ok :: sp-app=%s :: tabs=%s\n",
			$name,
			false !== strpos( $html, 'sp-app' ) ? 'yes' : 'no',
			false !== strpos( $html, 'sp-content-tabs' ) ? 'yes' : 'no'
		);
	} catch ( \Throwable $e ) {
		ob_end_clean();
		printf( "%s :: error :: %s\n", $name, $e->getMessage() );
	}
}
