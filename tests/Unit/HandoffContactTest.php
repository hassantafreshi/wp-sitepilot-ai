<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests\Unit;

use AISAAutoPilot\Support\HandoffContact;
use PHPUnit\Framework\TestCase;

/**
 * Validation of the contact details a visitor leaves with a handoff request.
 */
class HandoffContactTest extends TestCase {

	public function test_phone_accepts_common_formats_and_persian_digits(): void {
		$this->assertSame( '+1 555 010 2030', HandoffContact::phone( '+1 555 010 2030' ) );
		$this->assertSame( '+98 (912) 345-6789', HandoffContact::phone( '+98 (912) 345-6789' ) );
		$this->assertSame( '0912 345 6789', HandoffContact::phone( '۰۹۱۲  ۳۴۵ ۶۷۸۹' ) );
		$this->assertSame( '0912 345 6789', HandoffContact::phone( '٠٩١٢ ٣٤٥ ٦٧٨٩' ) );
	}

	public function test_phone_refuses_text_short_long_and_double_plus(): void {
		$this->assertNull( HandoffContact::phone( 'call me' ) );
		$this->assertNull( HandoffContact::phone( '12345' ) );
		$this->assertNull( HandoffContact::phone( '1234567890123456' ) );
		$this->assertNull( HandoffContact::phone( '++98 912 345 6789' ) );
	}

	public function test_website_adds_https_and_keeps_ports_and_paths(): void {
		$this->assertSame( 'https://example.com', HandoffContact::website( 'example.com' ) );
		$this->assertSame( 'https://example.com:8080/shop', HandoffContact::website( 'example.com:8080/shop' ) );
		$this->assertSame( 'http://shop.example.co.uk/a?b=1', HandoffContact::website( 'http://shop.example.co.uk/a?b=1' ) );
	}

	public function test_website_refuses_other_schemes_credentials_spaces_and_bare_hosts(): void {
		$this->assertNull( HandoffContact::website( 'javascript:alert(1)' ) );
		$this->assertNull( HandoffContact::website( 'ftp://example.com' ) );
		$this->assertNull( HandoffContact::website( 'https://user:secret@example.com' ) );
		$this->assertNull( HandoffContact::website( 'exa mple.com' ) );
		$this->assertNull( HandoffContact::website( 'localhost:3000' ) );
	}

	public function test_collect_reads_only_details_that_are_switched_on(): void {
		$result = HandoffContact::collect(
			array(
				'name'    => 'required',
				'email'   => 'optional',
				'phone'   => 'off',
				'website' => 'optional',
			),
			array(
				'name'    => 'Sam',
				'email'   => 'sam@example.com',
				'phone'   => '+1 555 010 2030',
				'website' => '',
			)
		);

		$this->assertSame(
			array(
				'name'    => 'Sam',
				'email'   => 'sam@example.com',
				'phone'   => '',
				'website' => '',
			),
			$result['values']
		);
		$this->assertSame( array(), $result['missing'] );
		$this->assertSame( array(), $result['invalid'] );
	}

	public function test_collect_names_missing_required_and_invalid_details(): void {
		$result = HandoffContact::collect(
			array(
				'name'    => 'required',
				'email'   => 'optional',
				'phone'   => 'required',
				'website' => 'optional',
			),
			array(
				'name'    => '   ',
				'email'   => 'not-an-email',
				'phone'   => '',
				'website' => array( 'https://example.com' ),
			)
		);

		$this->assertSame( array( 'name', 'phone' ), $result['missing'] );
		$this->assertSame( array( 'email' ), $result['invalid'] );
		$this->assertSame( '', $result['values']['website'] );
	}

	public function test_an_unknown_mode_counts_as_off(): void {
		$result = HandoffContact::collect( array( 'name' => 'always' ), array( 'name' => '' ) );

		$this->assertSame( array(), $result['missing'] );
		$this->assertSame( '', $result['values']['name'] );
	}
}
