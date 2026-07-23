<?php
declare(strict_types=1);
namespace SitePilot\Api;
use SitePilot\Content\KnowledgeBase;
use SitePilot\Chat\ChatEngine;
use SitePilot\Core\Settings;
use SitePilot\Support\HandoffService;

final class ChatWidgetController {
	public function settings(): \WP_REST_Response {
		$s=Settings::all();
		$w=$s['widget']??Settings::widget_defaults();
		return new \WP_REST_Response(array_merge(array('ok'=>true,'code'=>'ok'),$w));
	}
	public function ask(\WP_REST_Request $req):\WP_REST_Response{
		$q=sanitize_text_field($req->get_param('question')??'');
		if(''===trim($q)){return new \WP_REST_Response(array('ok'=>false,'code'=>'empty','answer'=>'Please enter a question.'),400);}
		try{
			$kb=new KnowledgeBase();$ce=new ChatEngine($kb);$r=$ce->ask($q);
			return new \WP_REST_Response(array_merge(array('ok'=>true),$r));
		}catch(\Throwable $e){
			return new \WP_REST_Response(array('ok'=>false,'code'=>'error','answer'=>$e->getMessage()),500);
		}
	}
	public function conversation(\WP_REST_Request $req):\WP_REST_Response{
		return new \WP_REST_Response(array('ok'=>true,'messages'=>$req->get_param('messages')??array()));
	}
	public function handoff(\WP_REST_Request $req):\WP_REST_Response{
		$msgs=$req->get_param('messages')??array();
		$name=sanitize_text_field($req->get_param('name')??'');
		$email=sanitize_email($req->get_param('email')??'');
		if(!is_array($msgs)||empty($msgs)){return new \WP_REST_Response(array('ok'=>false,'code'=>'no_messages','message'=>'No messages to send.'),400);}
		$svc=new HandoffService();$r=$svc->handoff($msgs,$name,$email);
		return new \WP_REST_Response(array_merge(array('ok'=>$r['ok']),$r));
	}
	public function handoff_status():\WP_REST_Response{
		$s=Settings::all()['widget']??Settings::widget_defaults();
		$channels=array();
		if(!empty($s['handoff_enabled'])){
			if(!empty($s['handoff_email_to']))$channels[]='email';
			if(!empty($s['handoff_telegram_token'])&&!empty($s['handoff_telegram_chat']))$channels[]='telegram';
			if(!empty($s['handoff_twilio_sid'])&&!empty($s['handoff_twilio_token']))$channels[]='twilio';
		}
		return new \WP_REST_Response(array('ok'=>true,'code'=>'ok','configured_channels'=>$channels));
	}
	public function test_telegram():\WP_REST_Response{
		$t=new \SitePilot\Support\HandoffTelegram();
		$r=$t->test_connection();
		return new \WP_REST_Response(array_merge(array('ok'=>$r['ok']),$r));
	}
	public function test_twilio():\WP_REST_Response{
		$t=new \SitePilot\Support\HandoffTwilio();
		$r=$t->test_connection();
		return new \WP_REST_Response(array_merge(array('ok'=>$r['ok']),$r));
	}
}
