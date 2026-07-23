<?php
declare(strict_types=1);

namespace SitePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SitePilot\Connectors\WooCommerceConnector;

/**
 * Unit tests for WooCommerceConnector.
 */
class ConnectorTest extends TestCase {

	/**
	 * Test is_active returns false when WooCommerce is not loaded.
	 */
	public function test_is_active(): void {
		// WooCommerce is not loaded in unit tests.
		$this->assertFalse( WooCommerceConnector::is_active() );
	}

	/**
	 * Test get_version returns empty when WooCommerce is not loaded.
	 */
	public function test_get_version(): void {
		$this->assertEquals( '', WooCommerceConnector::get_version() );
	}

	/**
	 * Test get_health returns inactive status.
	 */
	public function test_get_health(): void {
		$health = WooCommerceConnector::get_health();

		$this->assertIsArray( $health );
		$this->assertFalse( $health['active'] );
		$this->assertArrayHasKey( 'version', $health );
		$this->assertArrayHasKey( 'capabilities', $health );
	}

	/**
	 * Test get_capabilities returns expected structure.
	 */
	public function test_get_capabilities(): void {
		$caps = WooCommerceConnector::get_capabilities();

		$this->assertIsArray( $caps );
		$this->assertArrayHasKey( 'products', $caps );
		$this->assertArrayHasKey( 'orders', $caps );
		$this->assertArrayHasKey( 'analytics', $caps );
		$this->assertArrayHasKey( 'content', $caps );
	}

	/**
	 * Test get_categories returns empty array when WooCommerce is not loaded.
	 */
	public function test_get_categories(): void {
		$cats = WooCommerceConnector::get_categories();

		$this->assertIsArray( $cats );
		$this->assertEmpty( $cats );
	}
}
