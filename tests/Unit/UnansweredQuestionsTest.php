<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\SavedAnswers;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

/**
 * Pure logic behind unanswered questions: reasons, the time window, older stored entries and how a
 * saved answer matches a question.
 */
final class UnansweredQuestionsTest extends TestCase {

	public function test_every_reason_has_its_own_label_and_an_unknown_reason_says_it_predates_reasons(): void {
		$labels = array_map( array( KbGaps::class, 'reason_label' ), KbGaps::REASONS );

		$this->assertCount( 4, array_unique( $labels ) );
		$this->assertSame( 'Recorded before reasons were kept.', KbGaps::reason_label( '' ) );
		$this->assertSame( 'Recorded before reasons were kept.', KbGaps::reason_label( 'invented' ) );
	}

	public function test_the_seven_day_window_keeps_recent_questions_and_all_keeps_everything(): void {
		$now = strtotime( '2026-09-16T12:00:00+00:00' );

		$this->assertTrue( KbGaps::in_window( '2026-09-10T12:00:00+00:00', '7d', $now ) );
		$this->assertFalse( KbGaps::in_window( '2026-09-08T11:59:59+00:00', '7d', $now ) );
		$this->assertFalse( KbGaps::in_window( '', '7d', $now ) );
		$this->assertTrue( KbGaps::in_window( '2020-01-01T00:00:00+00:00', 'all', $now ) );
	}

	public function test_an_entry_stored_before_reasons_is_read_in_the_current_shape(): void {
		$entry = KbGaps::normalize_entry(
			array(
				'question' => 'Do you ship to Canada?',
				'count'    => '9',
				'first'    => '2026-09-01 08:00:00',
				'last'     => '2026-09-15 10:30:00',
				'status'   => 'open',
			)
		);

		$this->assertSame( 9, $entry['count'] );
		$this->assertSame( '2026-09-01T08:00:00+00:00', $entry['first_seen'] );
		$this->assertSame( '2026-09-15T10:30:00+00:00', $entry['last_seen'] );
		$this->assertSame( '', $entry['reason'] );
		$this->assertSame( 0, $entry['draft_post_id'] );
	}

	public function test_unanswered_questions_and_saved_answers_compare_questions_the_same_way(): void {
		$this->assertSame( KbGaps::key( 'Do you ship to Canada?' ), KbGaps::key( 'do you ship to canada' ) );
		$this->assertSame( KbGaps::key( 'هزینهٔ ارسال به کرج چقدر است؟' ), KbGaps::key( 'هزينهٔ ارسال به كرج چقدر است' ) );
		$this->assertSame( SavedAnswers::key( 'Do you ship to Canada?' ), KbGaps::key( 'Do you ship to Canada?' ) );
		$this->assertNotSame( KbGaps::key( 'Do you ship to Canada?' ), KbGaps::key( 'Do you ship to Mexico?' ) );
		$this->assertSame( '', KbGaps::key( '?!…' ) );
	}

	public function test_a_saved_answer_matches_regardless_of_case_spacing_punctuation_and_letter_forms(): void {
		$key = SavedAnswers::key( 'Do you ship to Canada?' );

		$this->assertSame( $key, SavedAnswers::key( '  do YOU ship to canada ' ) );
		$this->assertSame( $key, SavedAnswers::key( 'Do you ship to Canada?!' ) );
		$this->assertNotSame( $key, SavedAnswers::key( 'Can you ship to Canada?' ) );

		$persian = SavedAnswers::key( 'هزینهٔ ارسال به کرج چقدر است؟' );
		$this->assertSame( $persian, SavedAnswers::key( 'هزينهٔ ارسال به كرج چقدر است' ) );
		$this->assertSame( SavedAnswers::key( 'ساعت کاری ۹ تا ۵' ), SavedAnswers::key( 'ساعت کاری 9 تا 5' ) );
		$this->assertSame( SavedAnswers::key( 'می‌خواهم سفارش بدهم' ), SavedAnswers::key( 'می خواهم سفارش بدهم' ) );
		$this->assertSame( SavedAnswers::key( 'Tea & coffee?' ), SavedAnswers::key( 'Tea &amp; coffee' ) );
	}

	public function test_a_question_with_nothing_left_to_match_has_no_key(): void {
		$this->assertSame( '', SavedAnswers::key( '?!  …' ) );
	}
}
