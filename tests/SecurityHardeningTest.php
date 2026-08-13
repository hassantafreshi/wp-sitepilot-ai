<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Providers\OpenAiCompatibleProvider;
use SitePilot\Security\RateLimiter;
use SitePilot\Security\Redactor;

final class SecurityHardeningTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['sitepilot_test_transients'] = array();
		$GLOBALS['sitepilot_test_user_id']    = 0;
		$_SERVER['REMOTE_ADDR']                = '203.0.113.20';
	}

	public function test_rate_limiter_applies_the_configured_limit(): void {
		self::assertTrue( RateLimiter::allow( 'public_text', 2, 60 ) );
		self::assertTrue( RateLimiter::allow( 'public_text', 2, 60 ) );
		self::assertFalse( RateLimiter::allow( 'public_text', 2, 60 ) );
	}

	public function test_rate_limiter_does_not_trust_forwarded_ip_headers(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.44';
		self::assertTrue( RateLimiter::allow( 'public_voice', 1, 60 ) );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
		self::assertFalse( RateLimiter::allow( 'public_voice', 1, 60 ) );
	}

	public function test_public_limit_does_not_change_when_the_visitor_is_logged_in(): void {
		$GLOBALS['sitepilot_test_user_id'] = 0;
		self::assertTrue( RateLimiter::allow( 'public_widget', 1, 60 ) );
		$GLOBALS['sitepilot_test_user_id'] = 99;
		self::assertFalse( RateLimiter::allow( 'public_widget', 1, 60 ) );
	}

	public function test_provider_rejects_http_and_credentialed_urls_by_default(): void {
		$provider = new OpenAiCompatibleProvider( 'https://api.example.test/v1', 'model', 'key' );
		$method   = new \ReflectionMethod( $provider, 'is_safe_endpoint' );
		$method->setAccessible( true );

		self::assertTrue( $method->invoke( $provider, 'https://api.example.test/v1/chat/completions' ) );
		self::assertFalse( $method->invoke( $provider, 'http://127.0.0.1/v1/chat/completions' ) );
		self::assertFalse( $method->invoke( $provider, 'https://user:pass@api.example.test/v1/chat/completions' ) );
	}

	public function test_delivery_credentials_are_redacted_from_text_and_context(): void {
		$telegram = '123456789:abcdefghijklmnopqrstuvwxyz';
		$twilio   = 'AC12345678901234567890123456789012';
		self::assertStringNotContainsString( $telegram, Redactor::text( 'token=' . $telegram ) );
		self::assertStringNotContainsString( $twilio, Redactor::text( 'sid=' . $twilio ) );
		$context = Redactor::array( array( 'account_sid' => $twilio, 'auth_token' => 'secret' ) );
		self::assertSame( '[REDACTED]', $context['account_sid'] );
		self::assertSame( '[REDACTED]', $context['auth_token'] );
	}
}
