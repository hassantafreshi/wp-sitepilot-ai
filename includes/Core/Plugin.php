<?php
declare(strict_types=1);
namespace SitePilot\Core;
use SitePilot\Admin\AdminMenu;
use SitePilot\Api\RestRoutes;
use SitePilot\Connectors\ConnectorRegistry;
use SitePilot\Connectors\EasyFormBuilderConnector;
use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Content\ContentHooks;
use SitePilot\Efb\EfbBuilderButton;
use SitePilot\Frontend\FormShortcode;
use SitePilot\Frontend\ChatWidget;

final class Plugin {
	private static ?Plugin $instance = null;
	private Container $container;
	private bool $booted = false;
	private function __construct(){$this->container=new Container();}
	public static function instance():Plugin{if(null===self::$instance){self::$instance=new self();}return self::$instance;}
	public function container():Container{return $this->container;}

	public function boot():void{
		if($this->booted)return;$this->booted=true;
		$this->register_services();
		add_action('init',array($this,'on_init'));
		add_action('admin_menu',array($this->container->get(AdminMenu::class),'register'));
		add_action('admin_enqueue_scripts',array($this->container->get(AdminMenu::class),'enqueue_assets'));
		add_action('rest_api_init',array($this->container->get(RestRoutes::class),'register'));
		add_shortcode('sitepilot_chat',array(ChatWidget::class,'render_standalone'));
		add_action('wp_enqueue_scripts',array($this,'enqueue_widget_assets'));
		add_action('wp_footer',array($this,'render_widget'));
		if(is_admin()){(new EfbBuilderButton())->register();}
		if(Settings::get('kb_auto_index',true)){(new ContentHooks())->register();}
		WpConnectors::register();
		load_plugin_textdomain('sitepilot-ai',false,dirname(SITEPILOT_AI_BASENAME).'/languages');
	}

	private function register_services():void{
		$registry=new ConnectorRegistry();
		$registry->register(new EasyFormBuilderConnector());
		$registry->register(new NativeFormConnector());
		$this->container->set(ConnectorRegistry::class,$registry);
	}

	public function on_init():void{NativeFormConnector::register_post_types();(new FormShortcode())->register();}

	public function enqueue_widget_assets():void{
		$s=Settings::all();
		if(empty($s['widget']['enabled']))return;
		wp_enqueue_style('sitepilot-widget',SITEPILOT_AI_URL.'assets/frontend/widget.css',array(),SITEPILOT_AI_VERSION);
		wp_enqueue_script('sitepilot-widget',SITEPILOT_AI_URL.'assets/frontend/widget.js',array(),SITEPILOT_AI_VERSION,true);
		wp_localize_script('sitepilot-widget','SitePilotWidget',array(
			'restUrl'=>rest_url(RestRoutes::NAMESPACE.'/'),
			'nonce'=>wp_create_nonce('wp_rest'),
			'greeting'=>$s['widget']['greeting']??'Hi!',
			'voiceName'=>$s['widget']['voice_name']??'Google UK English Female',
			'voiceAuto'=>!empty($s['widget']['voice_auto']),
			'handoffText'=>$s['widget']['handoff_text']??'Talk to human',
			'handoffSending'=>'Connecting...',
			'handoffSent'=>'Support notified!',
			'handoffHook'=>$s['widget']['handoff_hook']??'',
		));
	}

	public function render_widget():void{
		$s=Settings::all();
		if(empty($s['widget']['enabled']))return;
		echo (new ChatWidget())->render();
	}
}
