<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Core\AssetVersion;
use PHPUnit\Framework\TestCase;

final class AssetVersionTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/aisa-assets-' . uniqid() . '/';
		mkdir( $this->dir . 'assets', 0777, true );
		file_put_contents( $this->dir . 'assets/a.js', 'one' );
	}

	protected function tearDown(): void {
		unlink( $this->dir . 'assets/a.js' );
		rmdir( $this->dir . 'assets' );
		rmdir( $this->dir );
	}

	public function test_a_changed_file_gets_a_new_address_and_an_unchanged_one_keeps_it(): void {
		$url   = 'https://site.example/wp-content/plugins/p/';
		$first = AssetVersion::versioned( $url . 'assets/a.js?ver=0.0.1', $url, $this->dir, '0.0.1' );
		$this->assertMatchesRegularExpression( '#assets/a\.js\?ver=0\.0\.1-[a-f0-9]{8}$#', $first );
		$this->assertSame( $first, AssetVersion::versioned( $url . 'assets/a.js?ver=0.0.1', $url, $this->dir, '0.0.1' ) );

		file_put_contents( $this->dir . 'assets/a.js', 'two, and longer' );
		clearstatcache();
		$this->assertNotSame( $first, AssetVersion::versioned( $url . 'assets/a.js?ver=0.0.1', $url, $this->dir, '0.0.1' ) );
	}

	public function test_other_addresses_are_left_alone(): void {
		$url = 'https://site.example/wp-content/plugins/p/';
		foreach ( array(
			'https://fonts.googleapis.com/css2?family=X&ver=0.0.1',
			'https://site.example/wp-includes/js/jquery.js?ver=3.7',
			$url . 'assets/missing.js?ver=0.0.1',
			$url . '../other/assets/a.js?ver=0.0.1',
		) as $src ) {
			$this->assertSame( $src, AssetVersion::versioned( $src, $url, $this->dir, '0.0.1' ) );
		}
		// Scheme differences and a missing version are handled.
		$this->assertMatchesRegularExpression( '#\?x=1&ver=0\.0\.1-[a-f0-9]{8}$#', AssetVersion::versioned( 'http://site.example/wp-content/plugins/p/assets/a.js?x=1', $url, $this->dir, '0.0.1' ) );
	}
}
