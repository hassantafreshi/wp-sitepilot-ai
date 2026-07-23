<?php
declare(strict_types=1);

namespace SitePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SitePilot\Connectors\WooCommerceOrderSkill;

/**
 * Unit tests for WooCommerceOrderSkill.
 */
class OrderSkillTest extends TestCase {

	private WooCommerceOrderSkill $skill;

	protected function setUp(): void {
		$this->skill = new WooCommerceOrderSkill();
	}

	/**
	 * Test is_available returns false when WooCommerce is not loaded.
	 */
	public function test_is_available(): void {
		$this->assertFalse( $this->skill->is_available() );
	}

	/**
	 * Test search_orders returns error when WooCommerce is not active.
	 */
	public function test_search_orders_fallback(): void {
		$result = $this->skill->search_orders();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test recent_orders returns error when WooCommerce is not active.
	 */
	public function test_recent_orders_fallback(): void {
		$result = $this->skill->recent_orders();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_stats returns error when WooCommerce is not active.
	 */
	public function test_get_stats_fallback(): void {
		$result = $this->skill->get_stats();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}
}
