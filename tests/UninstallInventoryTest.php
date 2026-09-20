<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every option and table the plugin declares as a constant must be removed by
 * uninstall.php (T20). Visitor conversations and handoff requests hold personal
 * data, so a table added without an uninstall line would outlive the plugin.
 */
final class UninstallInventoryTest extends TestCase {

	private const ROOT = __DIR__ . '/..';

	/**
	 * Storage constants in includes/: option and table names, keyed by "File::CONST".
	 *
	 * @return array<string,string>
	 */
	private static function declared_storage(): array {
		$found = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::ROOT . '/includes', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			preg_match_all( "/const\s+(\w*(?:OPTION|OPT|TABLE)\w*)\s*=\s*'(aisaautopilot_[a-z0-9_]+)'/", $code, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				$found[ $file->getBasename( '.php' ) . '::' . $match[1] ] = $match[2];
			}
		}
		return $found;
	}

	public function test_scan_finds_the_known_storage(): void {
		$declared = self::declared_storage();
		$this->assertContains( 'aisaautopilot_settings', $declared );
		$this->assertContains( 'aisaautopilot_handoffs', $declared );
		$this->assertContains( 'aisaautopilot_widget_chats', $declared );
		$this->assertGreaterThanOrEqual( 25, count( $declared ) );
	}

	public function test_uninstall_removes_every_declared_option_and_table(): void {
		$uninstall = (string) file_get_contents( self::ROOT . '/uninstall.php' );
		$missing   = array();
		foreach ( self::declared_storage() as $where => $name ) {
			if ( ! str_contains( $uninstall, "'" . $name . "'" ) && ! str_contains( $uninstall, '}' . $name . '"' ) ) {
				$missing[] = $where . ' = ' . $name;
			}
		}
		$this->assertSame( array(), $missing, 'uninstall.php does not remove: ' . implode( ', ', $missing ) );
	}

	/**
	 * T21: the explicit list cannot name every row. RebrandMigrator renames legacy
	 * sitepilot_* options by prefix, and transients are stored under a hashed key,
	 * so uninstall also sweeps both by prefix.
	 */
	public function test_uninstall_sweeps_transients_and_the_frozen_prefix(): void {
		$uninstall = (string) file_get_contents( self::ROOT . '/uninstall.php' );
		$this->assertStringContainsString( "esc_like( '_transient_aisaautopilot_' )", $uninstall );
		$this->assertStringContainsString( "esc_like( '_transient_timeout_aisaautopilot_' )", $uninstall );
		$this->assertStringContainsString( "esc_like( 'aisaautopilot_' ) . '%'", $uninstall );
	}

	public function test_visitor_data_tables_are_dropped(): void {
		$uninstall = (string) file_get_contents( self::ROOT . '/uninstall.php' );
		$this->assertStringContainsString( 'DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_widget_chats', $uninstall );
		$this->assertStringContainsString( 'DROP TABLE IF EXISTS {$wpdb->prefix}aisaautopilot_handoffs', $uninstall );
	}
}
