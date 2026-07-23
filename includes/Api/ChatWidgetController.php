<?php
declare(strict_types=1);
namespace SitePilot\Api;
use SitePilot\Content\KnowledgeBase;
use SitePilot\Chat\ChatEngine;
use SitePilot\Core\Settings;
use SitePilot\Support\HandoffService;

final class ChatWidgetController {
	public function settings(): \WP_REST_Response {
		$s = Settings::all();
		return new \WP_REST_Response( array_merge( array( 'ok' => true, 'code' => 'ok' ), 
			$s['widget'] ?? Settings::widget_defaults() ) );
	}

	public function ask( \WP_REST_Request $req ): \WP_REST_Response {
		$q = sanitize_text_field( $req->get_param( 'question' ) ?? '' );
		if ( '' === trim( $q ) ) return new \WP_REST_Response( array( 'ok' => false, 'code' => 'empty_question', 'answer' => 'Please enter a question.' ), 400 );
		$kb = new KnowledgeBase(); $ce = new ChatEngine( $kb );
		$r = $ce->ask( $q );
		return new \WP_REST_Response( array_merge( array( 'ok' => true ), $r ) );
	}

	public function conversation( \WP_REST_Request $req ): \WP_REST_Response {
		return new \WP_REST_Response( array( 'ok' => true, 'messages' => $req->get_param( 'messages' ) ?? array() ) );
	}

	public function handoff( \WP_REST_Request $req ): \WP_REST_Response {
		$msgs = $req->get_param( 'messages' ) ?? array();
		$email = sanitize_email( $req->get_param( 'email' ) ?? '' );
		$name = sanitize_text_field( $req->get_param( 'name' ) ?? '' );
		$service = new HandoffService();
		$result = $service->send( $msgs, $email, $name );
		return new \WP_REST_Response( array_merge( array( 'ok' => $result ), 
			$result ? array( 'message' => 'Support request sent.' ) : array( 'message' => 'Failed to send.' ) ) );
	}

	public function handoff_status(): \WP_REST_Response {
		$s = Settings::all()['widget'] ?? Settings::widget_defaults();
		$channels = array();
		if ( ! empty( $s['handoff_enabled'] ) ) {
			if ( ! empty( $s['handoff_email_enabled'] ) ) $channels[] = 'email';
			if ( ! empty( $s['handoff_telegram_enabled'] ) ) $channels[] = 'telegram';
			if ( ! empty( $s['handoff_twilio_enabled'] ) ) $channels[] = 'twilio';
			if ( ! empty( $s['handoff_webhook_enabled'] ) ) $channels[] = 'webhook';
		}
		return new \WP_REST_Response( array( 'ok' => true, 'enabled' => ! empty( $s['handoff_enabled'] ), 'channels' => $channels ) );
	}
}
