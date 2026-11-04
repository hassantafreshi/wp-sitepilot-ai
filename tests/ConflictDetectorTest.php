<?php
declare(strict_types=1);

use AISAAutoPilot\Core\ConflictDetector;
use PHPUnit\Framework\TestCase;

/**
 * The matcher behind the Integrations "another chat or AI plugin is active" warning (T31).
 */
final class ConflictDetectorTest extends TestCase {

	public function test_matches_by_wordpress_org_folder_whatever_the_main_file(): void {
		$known = array(
			'tidio-live-chat' => ConflictDetector::KIND_CHAT,
			'ai-engine'       => ConflictDetector::KIND_AI,
		);
		$found = ConflictDetector::match(
			array( 'woocommerce/woocommerce.php', 'tidio-live-chat/tidio-elements.php', 'ai-engine/ai-engine.php', 'hello.php' ),
			$known
		);

		$this->assertSame(
			array(
				'tidio-live-chat/tidio-elements.php' => ConflictDetector::KIND_CHAT,
				'ai-engine/ai-engine.php'            => ConflictDetector::KIND_AI,
			),
			$found
		);
	}

	public function test_ignores_single_file_plugins_lookalike_folders_and_itself(): void {
		$known = array(
			'chatbot'                     => ConflictDetector::KIND_AI,
			'ai-site-assistant-autopilot' => ConflictDetector::KIND_AI,
		);
		$found = ConflictDetector::match(
			array( 'chatbot.php', 'chatbot-pro/chatbot.php', 'my-chatbot/chatbot.php', AISAAUTOPILOT_BASENAME ),
			$known
		);

		$this->assertSame( array(), $found );
	}

	public function test_folder_match_is_case_insensitive(): void {
		$found = ConflictDetector::match( array( 'Chaty/cht-icons.php' ), array( 'chaty' => ConflictDetector::KIND_CHAT ) );

		$this->assertSame( array( 'Chaty/cht-icons.php' => ConflictDetector::KIND_CHAT ), $found );
	}

	public function test_known_list_holds_only_valid_kinds_and_slugs(): void {
		$known = ConflictDetector::known();

		$this->assertGreaterThanOrEqual( 10, count( $known ) );
		$this->assertArrayHasKey( 'social-chat-buttons', $known );
		$this->assertArrayNotHasKey( 'ai-site-assistant-autopilot', $known );
		foreach ( $known as $slug => $kind ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/', $slug );
			$this->assertContains( $kind, array( ConflictDetector::KIND_CHAT, ConflictDetector::KIND_AI ) );
		}
	}
}
