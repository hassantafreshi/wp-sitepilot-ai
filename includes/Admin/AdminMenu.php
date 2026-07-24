<?php
declare(strict_types=1);
namespace SitePilot\Admin;
use SitePilot\Api\RestRoutes;
use SitePilot\Connectors\WooCommerceConnector;
use SitePilot\Core\Settings;
use SitePilot\Frontend\ChatWidgetSettings;
use SitePilot\Usage\UsageTracker;

final class AdminMenu {
	public const SLUG_COPILOT = 'sitepilot-ai';
	public const SLUG_CHAT = 'sitepilot-ai-chat';
	public const SLUG_KB = 'sitepilot-ai-kb';
	public const SLUG_USAGE = 'sitepilot-ai-usage';
	public const SLUG_SETTINGS = 'sitepilot-ai-settings';
	public const SLUG_LOGS = 'sitepilot-ai-logs';
	public const SLUG_WIDGET = 'sitepilot-ai-widget';
	public const SLUG_WC = 'sitepilot-ai-woocommerce';
	public const SLUG_WC_DASHBOARD = 'sitepilot-ai-wc-dashboard';

	public function register(): void {
		$cap = 'manage_options';
		add_menu_page( 'SitePilot AI', 'SitePilot AI', $cap, self::SLUG_COPILOT, array(new CopilotPage(),'render'), 'dashicons-superhero', 58 );
		$pages = array(
			array('Copilot',self::SLUG_COPILOT,new CopilotPage()),
			array('AI Chat',self::SLUG_CHAT,new ChatPage()),
			array('Knowledge Base',self::SLUG_KB,new KnowledgeBasePage()),
			array('Usage',self::SLUG_USAGE,new UsagePage()),
			array('Widget',self::SLUG_WIDGET,new ChatWidgetSettings()),
			array('WC Dashboard',self::SLUG_WC_DASHBOARD,new WooCommerceDashboardPage()),
			array('WooCommerce AI',self::SLUG_WC,new WooCommerceSettings()),
			array('Settings',self::SLUG_SETTINGS,new SettingsPage()),
			array('Logs',self::SLUG_LOGS,new LogsPage()),
		);
		foreach($pages as $p){add_submenu_page(self::SLUG_COPILOT,$p[0],$p[0],$cap,$p[1],array($p[2],'render'));}
	}

	/**
	 * Per-screen CSS files (in addition to the shared admin.css).
	 * key = page slug, value = filename under assets/admin/.
	 */
	private const SCREEN_STYLES = array(
		self::SLUG_CHAT         => 'chat.css',
		self::SLUG_KB           => 'knowledge-base.css',
		self::SLUG_USAGE        => 'usage.css',
		self::SLUG_WIDGET       => 'widget-settings.css',
		self::SLUG_WC_DASHBOARD => 'dashboard.css',
		self::SLUG_LOGS         => 'logs.css',
	);

	/**
	 * Per-screen scripts. key = page slug, value = list of [filename, localized-object-name].
	 * Each script is registered, enqueued, and localized with the REST config it reads.
	 */
	private const SCREEN_SCRIPTS = array(
		self::SLUG_COPILOT  => array( array( 'copilot.js', 'SitePilotAI' ) ),
		self::SLUG_CHAT     => array( array( 'chat.js', 'SitePilotChat' ), array( 'voice.js', 'SitePilotAI' ) ),
		self::SLUG_KB       => array( array( 'knowledge-base.js', 'SitePilotKB' ) ),
		self::SLUG_USAGE    => array( array( 'usage.js', 'SitePilotAI' ) ),
		self::SLUG_SETTINGS => array( array( 'settings.js', 'SitePilotAI' ) ),
		self::SLUG_LOGS     => array( array( 'logs.js', 'SitePilotAI' ) ),
		self::SLUG_WIDGET   => array( array( 'widget-settings.js', 'SitePilotAI' ) ),
	);

	public function enqueue_assets(string $hook):void{
		unset($hook); // Slug is resolved from the request, not the hook suffix (which is ambiguous across our slugs).
		$slug = $this->current_screen_slug();
		if($slug===null){return;}

		wp_enqueue_style('sitepilot-admin',SITEPILOT_AI_URL.'assets/admin/admin.css',array(),SITEPILOT_AI_VERSION);

		if(isset(self::SCREEN_STYLES[$slug])){
			wp_enqueue_style('sitepilot-'.$slug,SITEPILOT_AI_URL.'assets/admin/'.self::SCREEN_STYLES[$slug],array('sitepilot-admin'),SITEPILOT_AI_VERSION);
		}

		$tracker=new UsageTracker();
		$conf=array('restUrl'=>rest_url(RestRoutes::NAMESPACE.'/'),'nonce'=>wp_create_nonce('wp_rest'),'usage'=>$tracker->get_threshold_warning());

		foreach(self::SCREEN_SCRIPTS[$slug] ?? array() as $script){
			list($file,$object)=$script;
			$handle='sitepilot-'.pathinfo($file,PATHINFO_FILENAME);
			wp_enqueue_script($handle,SITEPILOT_AI_URL.'assets/admin/'.$file,array(),SITEPILOT_AI_VERSION,true);
			wp_localize_script($handle,$object,$conf);
		}

		// WooCommerce dashboard needs a distinct payload (WC availability + its own object name).
		if(self::SLUG_WC_DASHBOARD===$slug){
			wp_enqueue_script('sitepilot-dashboard',SITEPILOT_AI_URL.'assets/admin/dashboard.js',array(),SITEPILOT_AI_VERSION,true);
			wp_localize_script('sitepilot-dashboard','SitePilotDashboard',array(
				'restUrl'=>$conf['restUrl'],
				'nonce'=>$conf['nonce'],
				'wcActive'=>WooCommerceConnector::is_active(),
			));
		}
	}

	/**
	 * Resolve the exact SitePilot admin page slug from the request, or null if not one of ours.
	 */
	private function current_screen_slug():?string{
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only asset routing, no state change.
		$known = array(self::SLUG_COPILOT,self::SLUG_CHAT,self::SLUG_KB,self::SLUG_USAGE,self::SLUG_WIDGET,self::SLUG_WC,self::SLUG_WC_DASHBOARD,self::SLUG_SETTINGS,self::SLUG_LOGS);
		return in_array($page,$known,true) ? $page : null;
	}

	public function can_manage():bool{return current_user_can('manage_options');}
}
