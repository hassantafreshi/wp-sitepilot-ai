<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Security\Redactor;

final class RedactorTest extends TestCase {

	public function test_bearer_token_redacted(): void {
		$out = Redactor::text( 'Authorization: Bearer sk-abc123DEF456ghi' );
		$this->assertStringNotContainsString( 'sk-abc123DEF456ghi', $out );
		$this->assertStringContainsString( '[REDACTED]', $out );
	}

	public function test_sk_key_redacted(): void {
		$out = Redactor::text( 'my key is sk-1234567890abcdef please keep it' );
		$this->assertStringNotContainsString( 'sk-1234567890abcdef', $out );
	}

	public function test_secret_array_keys_redacted(): void {
		$out = Redactor::array(
			array(
				'api_key' => 'sk-secret-value',
				'model'   => 'deepseek-chat',
				'nested'  => array( 'password' => 'hunter2', 'ok' => 'value' ),
			)
		);
		$this->assertSame( '[REDACTED]', $out['api_key'] );
		$this->assertSame( 'deepseek-chat', $out['model'] );
		$this->assertSame( '[REDACTED]', $out['nested']['password'] );
		$this->assertSame( 'value', $out['nested']['ok'] );
	}

	public function test_plain_text_survives(): void {
		$this->assertSame( 'a normal message', Redactor::text( 'a normal message' ) );
	}
}
