<?php
declare(strict_types=1);

namespace SitePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SitePilot\Connectors\WooCommerceContentSkill;

/**
 * Unit tests for WooCommerceContentSkill.
 */
class ContentSkillTest extends TestCase {

	private WooCommerceContentSkill $skill;

	protected function setUp(): void {
		$this->skill = new WooCommerceContentSkill();
	}

	/**
	 * Test is_available returns false when WooCommerce is not loaded.
	 */
	public function test_is_available(): void {
		$this->assertFalse( $this->skill->is_available() );
	}

	/**
	 * Test generate_description returns error when WooCommerce is not active.
	 */
	public function test_generate_description_fallback(): void {
		$result = $this->skill->generate_description( 1 );

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test generate_seo returns error when WooCommerce is not active.
	 */
	public function test_generate_seo_fallback(): void {
		$result = $this->skill->generate_seo( 1 );

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Test get_templates returns templates.
	 */
	public function test_get_templates(): void {
		$result = $this->skill->get_templates();

		$this->assertTrue( $result['ok'] );
		$this->assertArrayHasKey( 'templates', $result );
		$this->assertGreaterThanOrEqual( 5, count( $result['templates'] ) );
	}
}
