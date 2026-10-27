<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use AISAAutoPilot\Chat\ChatEngine;
use AISAAutoPilot\Chat\ConversationHistory;
use AISAAutoPilot\Content\KnowledgeChunker;
use AISAAutoPilot\Content\KnowledgeExtractor;
use AISAAutoPilot\Content\KnowledgeText;
use PHPUnit\Framework\TestCase;

/**
 * The pure parts of the site knowledge index: how text is normalised and split into terms,
 * how a page becomes sections and passages, and how chat history reaches the search.
 */
final class KnowledgeIndexingTest extends TestCase {

	public function test_arabic_letter_forms_persian_digits_and_zwnj_fold_to_one_spelling(): void {
		$this->assertSame( 'قیمت کتاب', KnowledgeText::normalize( 'قيمت  كتاب' ) );
		$this->assertSame( '14.50', KnowledgeText::normalize( '۱۴٫۵۰' ) );
		$this->assertSame( 'کتاب ها', KnowledgeText::normalize( "کتاب\u{200C}ها" ) );
		$this->assertSame( 'hello', KnowledgeText::normalize( 'HELLO' ) );
	}

	public function test_the_same_word_typed_differently_gives_the_same_terms(): void {
		$this->assertSame( KnowledgeText::tokens( 'قیمت قهوه کلمبیا' ), KnowledgeText::tokens( 'قيمت قهوه كلمبيا' ) );
		$this->assertSame( KnowledgeText::tokens( 'کتابها' ), KnowledgeText::tokens( 'کتاب‌ها' ) );
		$this->assertSame( array( 'bean' ), KnowledgeText::tokens( 'the beans' ) );
		$this->assertSame( array( 'policy' ), KnowledgeText::tokens( 'policies' ) );
		$this->assertSame( array( 'روند' ), KnowledgeText::tokens( 'می‌روند' ) );
	}

	public function test_stop_words_are_dropped_and_numbers_are_kept(): void {
		$this->assertSame( array( 'time', 'open', 'saturday' ), KnowledgeText::tokens( 'What time do you open on Saturday?' ) );
		$this->assertSame( array(), KnowledgeText::tokens( 'and how much is it?' ) );
		$this->assertSame( array( 'ارسال', 'هزینه' ), array_values( array_intersect( array( 'ارسال', 'هزینه' ), KnowledgeText::tokens( 'هزینه ارسال چقدر است؟' ) ) ) );
		$this->assertContains( '9', KnowledgeText::tokens( 'Open from 9 to 5' ) );
		$this->assertNotContains( 'x', KnowledgeText::tokens( 'x marks the spot' ) );
	}

	public function test_frequencies_count_repeats_with_a_weight(): void {
		$this->assertSame(
			array(
				'coffee' => 6,
				'bag'    => 3,
			),
			KnowledgeText::frequencies( 'Coffee coffee bag', 3 )
		);
	}

	public function test_a_persian_word_with_an_attached_pronoun_offers_the_bare_word(): void {
		$this->assertContains( 'قیمت', KnowledgeText::variants( 'قیمتش' ) );
		$this->assertContains( 'کاری', KnowledgeText::variants( 'کاریتون' ) );
		$this->assertSame( array(), KnowledgeText::variants( 'price' ) );
		// Too short to carry a pronoun: never cut.
		$this->assertSame( array(), KnowledgeText::variants( 'دست' ) );
	}

	public function test_html_becomes_sections_under_their_headings_without_scripts(): void {
		$html = '<p>Welcome to the roastery.</p><script>var secret = 1;</script>'
			. '<h2>Opening <em>hours</em></h2><ul><li>Mon–Fri 8:00–18:00</li><li>Sat 9:30–14:00</li></ul>'
			. '<h3>Prices</h3><table><tr><th>Bag</th><th>Price</th></tr><tr><td>250 g</td><td>&euro;14.50</td></tr></table>';

		$sections = KnowledgeExtractor::html_to_sections( $html );

		$this->assertSame( '', $sections[0]['heading'] );
		$this->assertSame( 'Welcome to the roastery.', $sections[0]['text'] );
		$this->assertSame( 'Opening hours', $sections[1]['heading'] );
		$this->assertStringContainsString( '• Sat 9:30–14:00', $sections[1]['text'] );
		$this->assertSame( 'Prices', $sections[2]['heading'] );
		$this->assertStringContainsString( '250 g | €14.50', $sections[2]['text'] );
		$this->assertStringNotContainsString( 'secret', implode( ' ', array_column( $sections, 'text' ) ) );
	}

	public function test_passages_stay_within_bounds_and_shop_facts_come_first(): void {
		$sentence = str_repeat( 'The beans are roasted slowly in small batches every week. ', 3 );
		$sections = array(
			array(
				'heading' => 'Roasting',
				'text'    => implode( "\n", array_fill( 0, 12, trim( $sentence ) ) ),
			),
			array(
				'heading' => 'Storage',
				'text'    => str_repeat( 'Keep the bag sealed and away from light. ', 60 ),
			),
		);

		$chunks = KnowledgeChunker::chunk( $sections, 'Price: €14.50', 'A light roast.' );

		$this->assertSame( "Price: €14.50\nA light roast.", $chunks[0]['text'] );
		$this->assertGreaterThan( 3, count( $chunks ) );
		foreach ( $chunks as $chunk ) {
			$this->assertLessThanOrEqual( KnowledgeChunker::MAX_CHARS, mb_strlen( $chunk['text'] ) );
		}
		$this->assertSame( 'Roasting', $chunks[1]['heading'] );
		$this->assertContains( 'Storage', array_column( $chunks, 'heading' ) );
	}

	public function test_a_short_section_joins_the_next_one_instead_of_standing_alone(): void {
		$chunks = KnowledgeChunker::chunk(
			array(
				array(
					'heading' => 'Intro',
					'text'    => 'Short.',
				),
				array(
					'heading' => 'Details',
					'text'    => 'Also short.',
				),
			)
		);

		$this->assertCount( 1, $chunks );
		$this->assertSame( 'Intro', $chunks[0]['heading'] );
		$this->assertStringContainsString( "Details\nAlso short.", $chunks[0]['text'] );
	}

	public function test_history_keeps_the_last_six_turns_and_names_the_last_visitor_question(): void {
		$raw = array( 'not a turn' );
		for ( $i = 1; $i <= 5; $i++ ) {
			$raw[] = array(
				'role' => 'user',
				'text' => 'Question ' . $i,
			);
			$raw[] = array(
				'role' => 'assistant',
				'text' => 'Answer ' . $i,
			);
		}
		$raw[] = array(
			'role' => 'system',
			'text' => str_repeat( 'a', 2000 ),
		);

		$clean = ConversationHistory::clean( $raw );

		$this->assertCount( 6, $clean );
		$this->assertSame( 'user', $clean[5]['role'] );
		$this->assertSame( 1200, mb_strlen( $clean[5]['text'] ) );
		$this->assertSame( array(), ConversationHistory::clean( 'nope' ) );

		$context = ConversationHistory::context( array_slice( $clean, 0, 5 ), 'and the price?' );
		$this->assertStringContainsString( 'untrusted reference text', $context );
		$this->assertSame( 'Question 5', ChatEngine::last_user_turn( $context ) );
		$this->assertSame( '', ConversationHistory::context( array(), 'Hi' ) );
	}
}
