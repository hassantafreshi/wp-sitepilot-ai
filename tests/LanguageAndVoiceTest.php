<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Core\SiteLanguage;
use SitePilot\Voice\VoiceTranscriber;

final class LanguageAndVoiceTest extends TestCase {
	public function test_normalizes_wordpress_locales_to_bcp47(): void {
		self::assertSame( 'fa-ir', SiteLanguage::normalize( 'fa_IR' ) );
		self::assertSame( 'en', SiteLanguage::primary( 'en_US' ) );
		self::assertSame( 'en', SiteLanguage::normalize( 'not a locale' ) );
	}

	public function test_language_rule_keeps_site_default_but_follows_user_language(): void {
		$rule = SiteLanguage::response_rule( 'fa_IR' );
		self::assertStringContainsString( 'fa-ir', $rule );
		self::assertStringContainsString( 'another language', $rule );
	}

	public function test_voice_upload_guard_rejects_invalid_size_and_duration(): void {
		self::assertSame( 'invalid_audio', VoiceTranscriber::validate_file( array(), 1000 ) );
		self::assertSame( 'audio_too_large', VoiceTranscriber::validate_file( array( 'tmp_name' => __FILE__, 'size' => VoiceTranscriber::MAX_BYTES + 1 ), 1000 ) );
		self::assertSame( 'audio_too_long', VoiceTranscriber::validate_file( array( 'tmp_name' => __FILE__, 'size' => 100 ), VoiceTranscriber::MAX_DURATION_MS + 1 ) );
	}
}
