<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Voice\VoiceTranscriber;

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

	/** The four containers a browser MediaRecorder produces pass, whatever Content-Type the client claimed. */
	public function test_voice_upload_accepts_the_browser_containers_by_their_bytes(): void {
		$headers = array(
			'audio/webm' => "\x1A\x45\xDF\xA3" . str_repeat( "\x00", 20 ),
			'audio/ogg'  => 'OggS' . str_repeat( "\x00", 20 ),
			'audio/mp4'  => "\x00\x00\x00\x18ftypisom" . str_repeat( "\x00", 12 ),
			'audio/wav'  => 'RIFF' . "\x24\x00\x00\x00" . 'WAVEfmt ' . str_repeat( "\x00", 8 ),
		);
		foreach ( $headers as $mime => $bytes ) {
			$path = $this->temp_file( $bytes );
			try {
				self::assertSame( $mime, VoiceTranscriber::detect_mime( $path ) );
				self::assertNull( VoiceTranscriber::validate_file( array( 'tmp_name' => $path, 'size' => strlen( $bytes ), 'type' => 'application/octet-stream' ), 1000 ), $mime );
			} finally {
				unlink( $path );
			}
		}
	}

	/** A client-declared audio/* type does not rescue bytes that are not a recording. */
	public function test_voice_upload_rejects_bytes_that_are_not_a_recording(): void {
		$samples = array(
			'plain text'     => 'hello world, this is not audio at all',
			'php source'     => "<?php echo 'padded to more than twelve bytes';",
			'riff but avi'   => 'RIFF' . "\x24\x00\x00\x00" . 'AVI LIST' . str_repeat( "\x00", 8 ),
			'truncated ogg'  => 'Ogg',
			'png image'      => "\x89PNG\r\n\x1A\n" . str_repeat( "\x00", 16 ),
		);
		foreach ( $samples as $label => $bytes ) {
			$path = $this->temp_file( $bytes );
			try {
				self::assertNull( VoiceTranscriber::detect_mime( $path ), $label );
				self::assertSame( 'invalid_audio', VoiceTranscriber::validate_file( array( 'tmp_name' => $path, 'size' => strlen( $bytes ), 'type' => 'audio/webm' ), 1000 ), $label );
			} finally {
				unlink( $path );
			}
		}
	}

	private function temp_file( string $bytes ): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'aisa-voice-' );
		file_put_contents( $path, $bytes );
		return $path;
	}
}
