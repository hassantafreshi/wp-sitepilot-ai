<?php
declare(strict_types=1);
namespace SitePilot\Admin;
use SitePilot\Api\RestRoutes;
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

	public function register(): void {
		$cap = 'manage_options';
		add_menu_page( 'SitePilot AI', 'SitePilot AI', $cap, self::SLUG_COPILOT, array(new CopilotPage(),'render'), 'dashicons-superhero', 58 );
		$pages = array(
			array('Copilot',self::SLUG_COPILOT,new CopilotPage()),
			array('AI Chat',self::SLUG_CHAT,new ChatPage()),
			array('Knowledge Base',self::SLUG_KB,new KnowledgeBasePage()),
			array('Usage',self::SLUG_USAGE,new UsagePage()),
			array('Widget',self::SLUG_WIDGET,new ChatWidgetSettings()),
			array('Settings',self::SLUG_SETTINGS,new SettingsPage()),
			array('Logs',self::SLUG_LOGS,new LogsPage()),
		);
		foreach($pages as $p){add_submenu_page(self::SLUG_COPILOT,$p[0],$p[0],$cap,$p[1],array($p[2],'render'));}
	}

	public function enqueue_assets(string $hook):void{
		$is_sp = str_contains($hook,self::SLUG_COPILOT)||str_contains($hook,self::SLUG_CHAT)||str_contains($hook,self::SLUG_KB)||str_contains($hook,self::SLUG_USAGE)||str_contains($hook,self::SLUG_WIDGET)||str_contains($hook,self::SLUG_SETTINGS)||str_contains($hook,self::SLUG_LOGS);
		if(!$is_sp)return;
		$is_usage=str_contains($hook,self::SLUG_USAGE);
		$is_widget=str_contains($hook,self::SLUG_WIDGET);

		wp_enqueue_style('sitepilot-admin',SITEPILOT_AI_URL.'assets/admin/admin.css',array(),SITEPILOT_AI_VERSION);
		if($is_usage){
			wp_enqueue_style('sitepilot-usage',SITEPILOT_AI_URL.'assets/admin/usage.css',array('sitepilot-admin'),SITEPILOT_AI_VERSION);
			wp_enqueue_script('sitepilot-usage',SITEPILOT_AI_URL.'assets/admin/usage.js',array(),SITEPILOT_AI_VERSION,true);
		}

		$tracker=new UsageTracker();
		$conf=array('restUrl'=>rest_url(RestRoutes::NAMESPACE.'/'),'nonce'=>wp_create_nonce('wp_rest'),'usage'=>$tracker->get_threshold_warning());
		wp_localize_script('sitepilot-usage','SitePilotAI',$conf);
		wp_localize_script('sitepilot-copilot','SitePilotAI',$conf);
		wp_localize_script('sitepilot-kb','SitePilotKB',$conf);
		wp_localize_script('sitepilot-chat','SitePilotChat',$conf);
	}

	public function can_manage():bool{return current_user_can('manage_options');}
}
