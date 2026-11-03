<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Security\ClientAddress;
use PHPUnit\Framework\TestCase;

final class ClientAddressTest extends TestCase {

	/** @var array<string,mixed> */
	private array $server;

	protected function setUp(): void {
		$this->server = $_SERVER;
	}

	protected function tearDown(): void {
		$_SERVER = $this->server;
	}

	public function test_remote_addr_is_used_when_no_proxy_is_involved(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		self::assertSame( '203.0.113.7', ClientAddress::get() );
	}

	/** A visitor cannot pick their own rate-limit bucket by writing the header themselves. */
	public function test_cloudflare_header_is_ignored_from_outside_cloudflare(): void {
		$_SERVER['REMOTE_ADDR']           = '203.0.113.7';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.2';
		self::assertSame( '203.0.113.7', ClientAddress::get() );
	}

	/** Behind Cloudflare every visitor arrives from an edge server; the header names the visitor. */
	public function test_cloudflare_edge_passes_on_the_visitor_address(): void {
		$_SERVER['REMOTE_ADDR']           = '172.70.1.2';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.1';
		self::assertSame( '198.51.100.1', ClientAddress::get() );

		$_SERVER['REMOTE_ADDR']           = '2606:4700:10::ac43:1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '2001:db8::5';
		self::assertSame( '2001:db8::5', ClientAddress::get() );
	}

	public function test_an_invalid_header_falls_back_to_the_edge_address(): void {
		$_SERVER['REMOTE_ADDR']           = '104.16.0.9';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '<script>';
		self::assertSame( '104.16.0.9', ClientAddress::get() );
	}

	public function test_missing_or_invalid_remote_address_is_unknown(): void {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'] );
		self::assertSame( 'unknown', ClientAddress::get() );
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		self::assertSame( 'unknown', ClientAddress::get() );
	}

	public function test_cidr_matching_respects_partial_bytes(): void {
		self::assertTrue( ClientAddress::in_ranges( '173.245.63.255', array( '173.245.48.0/20' ) ) );
		self::assertFalse( ClientAddress::in_ranges( '173.245.64.0', array( '173.245.48.0/20' ) ) );
		self::assertTrue( ClientAddress::in_ranges( '2a06:98c7::1', array( '2a06:98c0::/29' ) ) );
		self::assertFalse( ClientAddress::in_ranges( '2a06:98c8::1', array( '2a06:98c0::/29' ) ) );
		self::assertFalse( ClientAddress::in_ranges( '173.245.48.1', array( '2400:cb00::/32' ) ) );
	}
}
