<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Providers\OpenAiCompatibleProvider;
use SitePilot\Providers\ProviderException;

final class ProviderResponseParseTest extends TestCase {

	public function test_plain_json_object(): void {
		$out = OpenAiCompatibleProvider::extract_json( '{"intent":"create_form","form":{"title":"X"}}' );
		$this->assertSame( 'create_form', $out['intent'] );
		$this->assertSame( 'X', $out['form']['title'] );
	}

	public function test_fenced_json_block(): void {
		$content = "```json\n{\"a\": 1, \"b\": [1,2]}\n```";
		$out     = OpenAiCompatibleProvider::extract_json( $content );
		$this->assertSame( 1, $out['a'] );
		$this->assertSame( array( 1, 2 ), $out['b'] );
	}

	public function test_json_with_surrounding_prose(): void {
		$content = "Sure! Here is your form:\n{\"title\": \"Contact\"}\nHope that helps.";
		$out     = OpenAiCompatibleProvider::extract_json( $content );
		$this->assertSame( 'Contact', $out['title'] );
	}

	public function test_invalid_content_throws(): void {
		$this->expectException( ProviderException::class );
		OpenAiCompatibleProvider::extract_json( 'this is not json at all' );
	}
}
