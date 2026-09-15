<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use AISAAutoPilot\Skills\ContentPlanValidator;
use PHPUnit\Framework\TestCase;

final class ContentPlanValidatorTest extends TestCase {

	private ContentPlanValidator $validator;

	protected function setUp(): void {
		$this->validator = new ContentPlanValidator();
	}

	public function test_valid_post_plan_is_normalised_and_draft_only(): void {
		$result = $this->validator->validate(
			array(
				'content' => array(
					'post_type'        => 'post',
					'title'            => '<b>Solar consultation</b>',
					'body'             => '<p>A useful introduction.</p>',
					'meta_description' => 'A short description.',
				),
			)
		);
		$this->assertTrue( $result->ok );
		$this->assertSame( 'content_draft', $result->plan['intent'] );
		$this->assertTrue( $result->plan['requires_approval'] );
		$this->assertFalse( $result->plan['publish_permitted'] );
		$this->assertSame( 'Solar consultation', $result->plan['title'] );
	}

	public function test_rejects_unsupported_post_type_and_empty_body(): void {
		$result = $this->validator->validate(
			array(
				'content' => array(
					'post_type' => 'product',
					'title'     => 'Unsafe target',
					'body'      => '<script>alert(1)</script>',
				),
			)
		);
		$this->assertFalse( $result->ok );
		$this->assertNotEmpty( $result->errors );
	}
}
