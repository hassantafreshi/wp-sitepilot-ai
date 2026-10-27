<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use AISAAutoPilot\Support\HandoffSignal;
use AISAAutoPilot\Support\HandoffSlack;
use AISAAutoPilot\Support\HandoffText;
use PHPUnit\Framework\TestCase;

/**
 * The pure parts of the handoff channels: how the summary is cut and flattened, and which
 * Slack and Signal addresses are accepted.
 */
final class HandoffChannelsTest extends TestCase {

	public function test_long_persian_text_is_split_by_characters_without_breaking_a_letter(): void {
		$line   = str_repeat( 'سلام دنیا ', 300 );
		$chunks = HandoffText::split( "Name: Sara\n" . $line, 1000 );

		$this->assertGreaterThan( 2, count( $chunks ) );
		foreach ( $chunks as $chunk ) {
			$this->assertLessThanOrEqual( 1000, mb_strlen( $chunk ) );
			$this->assertTrue( mb_check_encoding( $chunk, 'UTF-8' ) );
		}
		$this->assertSame( 'Name: Sara', $chunks[0] );
		$this->assertSame( "Name: Sara\n" . $line, $chunks[0] . "\n" . implode( '', array_slice( $chunks, 1 ) ) );
		// The byte-based split this replaced cut letters in half.
		$this->assertFalse( mb_check_encoding( str_split( $line, 1000 )[0], 'UTF-8' ) );
	}

	public function test_short_text_stays_one_piece_and_lines_are_kept_together(): void {
		$this->assertSame( array( "a\nb" ), HandoffText::split( "a\nb", 10 ) );
		$this->assertSame( array( "aaaa\nbbbb", 'cccc' ), HandoffText::split( "aaaa\nbbbb\ncccc", 9 ) );
	}

	public function test_one_line_joins_lines_collapses_spaces_and_cuts_with_an_ellipsis(): void {
		$this->assertSame( 'Name: Sara · Email: s@example.com · Hi there', HandoffText::one_line( "Name: Sara\n\nEmail: s@example.com\r\nHi \t   there", 100 ) );
		$cut = HandoffText::one_line( str_repeat( 'پ', 50 ), 10 );
		$this->assertSame( 10, mb_strlen( $cut ) );
		$this->assertStringEndsWith( '…', $cut );
		$this->assertStringNotContainsString( "\n", HandoffText::one_line( "a\nb\tc", 10 ) );
	}

	public function test_only_slack_incoming_webhook_urls_are_accepted(): void {
		$this->assertTrue( HandoffSlack::is_webhook_url( 'https://hooks.slack.com/services/T000/B000/XXXXyyyy' ) );
		$this->assertTrue( HandoffSlack::is_webhook_url( 'https://hooks.slack-gov.com/services/T1/B2/abc_DEF-9' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'http://hooks.slack.com/services/T000/B000/XXXX' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'https://hooks.slack.com.evil.test/services/T000/B000/XXXX' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'https://evil.test/hooks.slack.com/services/T000' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'https://hooks.slack.com/triggers/T000/123/abc' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'https://user:pass@hooks.slack.com/services/T000/B000/XXXX' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( 'https://hooks.slack.com:8443/services/T000/B000/XXXX' ) );
		$this->assertFalse( HandoffSlack::is_webhook_url( '' ) );
	}

	public function test_slack_markup_characters_in_a_visitor_message_are_escaped(): void {
		$this->assertSame( 'a &lt;@channel&gt; &amp; &lt;https://x.test|click&gt;', HandoffSlack::escape( 'a <@channel> & <https://x.test|click>' ) );
	}

	public function test_signal_server_numbers_and_groups_are_validated(): void {
		$this->assertTrue( HandoffSignal::is_server_url( 'http://127.0.0.1:8080' ) );
		$this->assertTrue( HandoffSignal::is_server_url( 'https://signal.example.com/api' ) );
		$this->assertFalse( HandoffSignal::is_server_url( 'ftp://signal.example.com' ) );
		$this->assertFalse( HandoffSignal::is_server_url( 'https://user:pass@signal.example.com' ) );
		$this->assertFalse( HandoffSignal::is_server_url( 'https://signal.example.com/?next=evil' ) );
		$this->assertFalse( HandoffSignal::is_server_url( 'signal.example.com' ) );

		$this->assertTrue( HandoffSignal::is_number( '+15550194471' ) );
		$this->assertFalse( HandoffSignal::is_number( '15550194471' ) );
		$this->assertFalse( HandoffSignal::is_number( '+1 555 019' ) );

		$this->assertTrue( HandoffSignal::is_recipient( '+989121234567' ) );
		$this->assertTrue( HandoffSignal::is_recipient( 'group.ZmFrZWdyb3VwaWQxMjM0NTY3OA==' ) );
		$this->assertFalse( HandoffSignal::is_recipient( 'group.' ) );
		$this->assertFalse( HandoffSignal::is_recipient( 'someone' ) );
	}
}
