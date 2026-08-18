<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\RebrandMigrator;

/**
 * Guards the pure mapping logic behind the sitepilot-ai → ai-site-assistant-autopilot
 * data migration.
 *
 * A mistake here is silent and destructive: the migration deletes the legacy
 * option after copying it, so a key that maps to the wrong name takes the user's
 * settings — including their encrypted provider key — with it.
 */
final class RebrandMigratorTest extends TestCase {

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	private function call( string $method, ...$args ) {
		$ref = new \ReflectionMethod( RebrandMigrator::class, $method );
		$ref->setAccessible( true );

		return $ref->invoke( null, ...$args );
	}

	/**
	 * The `sitepilot_ai_` prefix collapses onto the new prefix rather than
	 * leaving a stray `ai_` behind.
	 *
	 * @dataProvider provide_ai_prefixed_keys
	 */
	public function test_ai_prefixed_options_drop_the_redundant_segment( string $old, string $expected ): void {
		$this->assertSame( $expected, $this->call( 'rename_key', $old ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_ai_prefixed_keys(): array {
		return array(
			'settings'      => array( 'sitepilot_ai_settings', 'aisaautopilot_settings' ),
			'provider key'  => array( 'sitepilot_ai_provider_key', 'aisaautopilot_provider_key' ),
			'usage totals'  => array( 'sitepilot_ai_usage_totals', 'aisaautopilot_usage_totals' ),
			'handoff'       => array( 'sitepilot_ai_handoff_secrets', 'aisaautopilot_handoff_secrets' ),
			'connector key' => array( 'sitepilot_ai_wp_connector_key', 'aisaautopilot_wp_connector_key' ),
		);
	}

	/**
	 * Keys that were never `_ai_` prefixed keep their whole tail.
	 *
	 * @dataProvider provide_plain_prefixed_keys
	 */
	public function test_plain_prefixed_options_keep_their_tail( string $old, string $expected ): void {
		$this->assertSame( $expected, $this->call( 'rename_key', $old ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_plain_prefixed_keys(): array {
		return array(
			'kb gaps'    => array( 'sitepilot_kb_gaps', 'aisaautopilot_kb_gaps' ),
			'kb version' => array( 'sitepilot_kb_version', 'aisaautopilot_kb_version' ),
			'kb cache'   => array( 'sitepilot_kb_answer_cache', 'aisaautopilot_kb_answer_cache' ),
		);
	}

	/**
	 * A key that merely starts with similar text must be left alone, otherwise
	 * the migration would rewrite another plugin's options.
	 *
	 * @dataProvider provide_foreign_keys
	 */
	public function test_foreign_options_are_ignored( string $foreign ): void {
		$this->assertNull( $this->call( 'rename_key', $foreign ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_foreign_keys(): array {
		return array(
			'other plugin'   => array( 'woocommerce_settings' ),
			'no underscore'  => array( 'sitepilot-ai-settings' ),
			'already new'    => array( 'aisaautopilot_settings' ),
			'core option'    => array( 'siteurl' ),
			'similar prefix' => array( 'sitepilotx_settings' ),
		);
	}

	/**
	 * Every spelling WordPress uses for an autoloaded option must read as true;
	 * treating one as false would quietly stop the option being autoloaded.
	 *
	 * @dataProvider provide_autoload_values
	 */
	public function test_autoload_column_is_interpreted( string $raw, bool $expected ): void {
		$this->assertSame( $expected, $this->call( 'is_autoloaded', $raw ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function provide_autoload_values(): array {
		return array(
			'legacy yes' => array( 'yes', true ),
			'legacy no'  => array( 'no', false ),
			'modern on'  => array( 'on', true ),
			'modern off' => array( 'off', false ),
			'auto'       => array( 'auto', true ),
			'auto-on'    => array( 'auto-on', true ),
			'auto-off'   => array( 'auto-off', false ),
		);
	}

	/**
	 * WordPress refuses to register a post type whose name exceeds 20 characters,
	 * and it fails by returning a WP_Error that nothing here inspects — so the
	 * breakage would only show up as forms that silently stop saving.
	 */
	public function test_post_type_names_fit_within_the_wordpress_limit(): void {
		foreach ( array( NativeFormConnector::POST_TYPE, NativeFormConnector::SUBMISSION_TYPE ) as $type ) {
			$this->assertLessThanOrEqual(
				20,
				strlen( $type ),
				sprintf( 'Post type "%s" is %d characters; register_post_type() allows 20.', $type, strlen( $type ) )
			);
		}
	}

	/**
	 * The legacy post types the migration looks for must be the ones the old
	 * build actually registered, or existing forms stay invisible.
	 */
	public function test_legacy_post_types_are_mapped_to_current_ones(): void {
		$ref = new \ReflectionClass( RebrandMigrator::class );
		$map = $ref->getConstant( 'POST_TYPES' );

		$this->assertSame(
			array(
				'sitepilot_form'       => NativeFormConnector::POST_TYPE,
				'sitepilot_submission' => NativeFormConnector::SUBMISSION_TYPE,
			),
			$map
		);
	}
}
