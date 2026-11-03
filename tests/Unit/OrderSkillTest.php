<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Connectors\WooCommerceOrderSkill;

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
	 * Only the exact "wc-" prefix is removed: ltrim( $s, 'wc-' ) turned
	 * "completed" into "ompleted" and "cancelled" into "ancelled".
	 */
	public function test_status_slug_keeps_the_status_name_whole(): void {
		$this->assertSame( 'completed', WooCommerceOrderSkill::status_slug( 'completed' ) );
		$this->assertSame( 'completed', WooCommerceOrderSkill::status_slug( 'wc-completed' ) );
		$this->assertSame( 'cancelled', WooCommerceOrderSkill::status_slug( 'Cancelled' ) );
		$this->assertSame( 'on-hold', WooCommerceOrderSkill::status_slug( 'wc-on-hold' ) );
		$this->assertSame( '', WooCommerceOrderSkill::status_slug( 'wc-' ) );
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
