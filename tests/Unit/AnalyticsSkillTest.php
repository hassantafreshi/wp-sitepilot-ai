<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Connectors\WooCommerceAnalyticsSkill;

/**
 * Unit tests for WooCommerceAnalyticsSkill.
 */
class AnalyticsSkillTest extends TestCase {

	private WooCommerceAnalyticsSkill $skill;

	protected function setUp(): void {
		$this->skill = new WooCommerceAnalyticsSkill();
	}

	/**
	 * Test is_available returns false when WooCommerce is not loaded.
	 */
	public function test_is_available(): void {
		$this->assertFalse( $this->skill->is_available() );
	}

	/**
	 * Test sales_report returns error when WooCommerce is not active.
	 */
	public function test_sales_report_fallback(): void {
		$result = $this->skill->sales_report();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test top_products returns error when WooCommerce is not active.
	 */
	public function test_top_products_fallback(): void {
		$result = $this->skill->top_products();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test dashboard returns error when WooCommerce is not active.
	 */
	public function test_dashboard_fallback(): void {
		$result = $this->skill->dashboard();

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}
}
