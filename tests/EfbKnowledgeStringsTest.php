<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbKnowledge;
use AISAAutoPilot\Efb\EfbKnowledgeStrings;
use PHPUnit\Framework\TestCase;

/**
 * QA5-008 and QA5-009: the help topics follow the current Easy Form Builder editor ("Done" only closes the docked panel;
 * the toolbar's Save stores the form) and every text of them is a gettext call (EfbKnowledgeStrings, generated from
 * efb-knowledge.json), so it reaches the .pot and is shown in the admin's language.
 */
final class EfbKnowledgeStringsTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['aisa_test_gettext'] );
	}

	public function test_the_generated_strings_file_matches_the_knowledge_file(): void {
		$out  = array();
		$code = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/scripts/efb-knowledge-strings.php' ) . ' --check', $out, $code );
		self::assertSame( 0, $code, implode( "\n", $out ) );
	}

	public function test_every_title_answer_step_and_feature_label_is_a_gettext_string(): void {
		$data = EfbKnowledge::data();
		foreach ( (array) $data['ui_pattern'] as $key => $text ) {
			self::assertSame( $text, EfbKnowledgeStrings::get( 'ui.' . $key ), 'ui.' . $key );
		}
		$pot = (string) file_get_contents( dirname( __DIR__ ) . '/languages/ai-site-assistant-autopilot.pot' );
		self::assertGreaterThanOrEqual( 40, count( EfbKnowledge::entries() ) );
		foreach ( EfbKnowledge::entries() as $entry ) {
			$id = (string) $entry['id'];
			self::assertSame( $entry['title'], EfbKnowledgeStrings::get( 'title.' . $id ), 'title.' . $id );
			self::assertSame( $entry['answer'], EfbKnowledgeStrings::get( 'answer.' . $id ), 'answer.' . $id );
			foreach ( (array) ( $entry['features'] ?? array() ) as $feature => $label ) {
				self::assertSame( $label, EfbKnowledgeStrings::get( 'feature.' . $id . '.' . $feature ), $id . ' ' . $feature );
			}
			// In the translation template, as gettext reads it.
			self::assertStringContainsString( 'msgid "' . addcslashes( (string) $entry['title'], '"\\' ) . '"', $pot, 'title of ' . $id . ' in the .pot' );
		}
	}

	public function test_an_answer_is_shown_in_the_admins_language(): void {
		$GLOBALS['aisa_test_gettext'] = array(
			'Make a field required'                                  => 'فیلد را اجباری کنید',
			EfbKnowledge::data()['ui_pattern']['open_field_settings'] => 'روی چرخ‌دنده بزنید.',
			EfbKnowledge::data()['ui_pattern']['save']               => 'Done فقط پنل را می‌بندد؛ Save نوار ابزار ذخیره می‌کند.',
		);
		foreach ( EfbKnowledge::entries() as $entry ) {
			if ( 'make_required' === $entry['id'] ) {
				$GLOBALS['aisa_test_gettext'][ (string) $entry['answer'] ] = 'کلید Required را روشن کنید.';
			}
		}
		$answer = EfbKnowledge::answer( 'how do I make a field required?' );
		self::assertSame( 'فیلد را اجباری کنید', $answer['title'] );
		self::assertSame( "روی چرخ‌دنده بزنید.\n\nکلید Required را روشن کنید.\n\nDone فقط پنل را می‌بندد؛ Save نوار ابزار ذخیره می‌کند.", $answer['answer'] );
		// The translated title also finds the topic, and the topic list is translated.
		self::assertSame( 'make_required', EfbKnowledge::answer( 'فیلد را اجباری کنید' )['id'] );
		$titles = array_column( EfbKnowledge::answer( 'zzz' )['suggestions'], 'title', 'id' );
		self::assertSame( 'فیلد را اجباری کنید', $titles['make_required'] );
	}

	public function test_the_save_step_matches_the_current_builder(): void {
		$answer = EfbKnowledge::answer( 'How do I make a field required?' )['answer'];
		self::assertStringContainsString( '"Done"', $answer );
		self::assertStringContainsString( 'it only closes the panel', $answer );
		self::assertStringContainsString( "click Save in the editor's toolbar", $answer );
		self::assertStringContainsString( '"Edit Field" panel', $answer );
		$form = EfbKnowledge::answer( 'turn on email notifications' )['answer'];
		self::assertStringContainsString( '"Form Settings" in the editor\'s toolbar', $form );
		foreach ( EfbKnowledge::entries() as $entry ) {
			$text = EfbKnowledge::answer( (string) $entry['title'] )['answer'] ?? '';
			foreach ( array( 'green', 'bi-gear', 'LEFT', 'RIGHT', 'Save button (✓)', 'top of the settings panel to store' ) as $old ) {
				self::assertStringNotContainsString( $old, (string) $text, $entry['id'] . ': ' . $old );
			}
		}
	}
}
