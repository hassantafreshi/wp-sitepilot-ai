<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Security\KeyVault;

final class KeyVaultTest extends TestCase {

	private function key(): string {
		return hash( 'sha256', 'unit-test-key', true ); // 32 bytes.
	}

	public function test_round_trip(): void {
		$vault  = new KeyVault( $this->key() );
		$secret = 'sk-test-1234567890-secret';

		$token = $vault->encrypt( $secret );
		$this->assertNotSame( $secret, $token );
		$this->assertStringNotContainsString( $secret, $token );
		$this->assertSame( $secret, $vault->decrypt( $token ) );
	}

	public function test_ciphertext_differs_each_time(): void {
		$vault = new KeyVault( $this->key() );
		$a     = $vault->encrypt( 'same-value' );
		$b     = $vault->encrypt( 'same-value' );
		$this->assertNotSame( $a, $b, 'Nonce should randomize ciphertext.' );
	}

	public function test_wrong_key_cannot_decrypt(): void {
		$vault = new KeyVault( $this->key() );
		$token = $vault->encrypt( 'top-secret' );

		$other = new KeyVault( hash( 'sha256', 'different-key', true ) );
		$this->expectException( \RuntimeException::class );
		$other->decrypt( $token );
	}

	public function test_bad_key_length_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new KeyVault( 'too-short' );
	}

	public function test_malformed_token_throws(): void {
		$vault = new KeyVault( $this->key() );
		$this->expectException( \RuntimeException::class );
		$vault->decrypt( 'not-a-valid-token' );
	}
}
