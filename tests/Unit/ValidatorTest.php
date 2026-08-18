<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Skills\ProductPlanValidator;

/**
 * Unit tests for ProductPlanValidator.
 */
class ValidatorTest extends TestCase {

	private ProductPlanValidator $validator;

	protected function setUp(): void {
		$this->validator = new ProductPlanValidator();
	}

	/**
	 * Test valid plan passes validation.
	 */
	public function test_valid_plan(): void {
		$plan = array(
			'name'   => 'Test Product',
			'price'  => '29.99',
			'status' => 'draft',
		);

		$result = $this->validator->validate( $plan );

		$this->assertTrue( $result['ok'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test plan without name fails.
	 */
	public function test_invalid_plan_no_name(): void {
		$plan = array(
			'price'  => '29.99',
			'status' => 'draft',
		);

		$result = $this->validator->validate( $plan );

		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test plan with invalid price fails.
	 */
	public function test_invalid_plan_bad_price(): void {
		$plan = array(
			'name'   => 'Test Product',
			'price'  => 'abc',
			'status' => 'draft',
		);

		$result = $this->validator->validate( $plan );

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Test plan with invalid status fails.
	 */
	public function test_invalid_plan_bad_status(): void {
		$plan = array(
			'name'   => 'Test Product',
			'price'  => '29.99',
			'status' => 'invalid_status',
		);

		$result = $this->validator->validate( $plan );

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Test plan with invalid type fails.
	 */
	public function test_invalid_plan_bad_type(): void {
		$plan = array(
			'name'   => 'Test Product',
			'price'  => '29.99',
			'status' => 'draft',
			'type'   => 'invalid_type',
		);

		$result = $this->validator->validate( $plan );

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Test sanitize removes HTML.
	 */
	public function test_sanitize_html(): void {
		$raw = array(
			'name' => '<script>alert(1)</script>Test Product',
		);

		$clean = $this->validator->sanitize( $raw );

		$this->assertEquals( 'Test Product', $clean['name'] );
		$this->assertStringNotContainsString( '<script>', $clean['name'] );
	}

	/**
	 * Test sanitize trims price.
	 */
	public function test_sanitize_price(): void {
		$raw = array(
			'price' => '  29.99  ',
		);

		$clean = $this->validator->sanitize( $raw );

		$this->assertEquals( '29.99', $clean['price'] );
	}

	/**
	 * Test sanitize filters categories.
	 */
	public function test_sanitize_categories(): void {
		$raw = array(
			'categories' => array( '1', 'abc', '3' ),
		);

		$clean = $this->validator->sanitize( $raw );

		$this->assertEquals( array( 1, 3 ), $clean['categories'] );
	}
}
