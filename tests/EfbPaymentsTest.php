<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbPayments;
use PHPUnit\Framework\TestCase;

final class EfbPaymentsTest extends TestCase {

	public function test_a_gateway_is_found_under_any_common_spelling(): void {
		foreach ( array( 'Stripe', 'stripe', ' STRIPE ' ) as $name ) {
			self::assertSame( 'stripe', EfbPayments::resolve_gateway( $name ) );
		}
		self::assertSame( 'paypal', EfbPayments::resolve_gateway( 'Pay-Pal' ) );
		self::assertSame( 'zarinpal', EfbPayments::resolve_gateway( 'Zarin Pal' ) );
		self::assertSame( 'zarinpal', EfbPayments::resolve_gateway( 'persiaPay' ) );
		self::assertNull( EfbPayments::resolve_gateway( 'bitcoin' ) );
		self::assertNull( EfbPayments::resolve_gateway( '' ) );
	}

	public function test_gateway_fields_map_back_to_their_gateway(): void {
		self::assertSame( 'stripe', EfbPayments::gateway_of_field( 'stripe' ) );
		self::assertSame( 'zarinpal', EfbPayments::gateway_of_field( 'persiaPay' ) );
		self::assertNull( EfbPayments::gateway_of_field( 'payRadio' ) );
		self::assertSame( 'persiaPay', EfbPayments::gateway_field( 'zarinpal' ) );
	}

	public function test_a_currency_the_gateway_cannot_take_falls_back_to_its_default(): void {
		self::assertSame( 'eur', EfbPayments::currency( 'stripe', 'EUR' ) );
		self::assertSame( 'usd', EfbPayments::currency( 'stripe', 'doge' ) );
		self::assertSame( 'irr', EfbPayments::currency( 'zarinpal', 'usd' ) );
		self::assertTrue( EfbPayments::supports_currency( 'paypal', 'gbp' ) );
		self::assertFalse( EfbPayments::supports_currency( 'paypal', 'inr' ) );
		self::assertFalse( EfbPayments::supports_currency( 'nope', 'usd' ) );
	}

	public function test_prices_are_read_the_way_people_type_them_and_bad_ones_are_refused(): void {
		self::assertSame( 20.0, EfbPayments::price( 20 ) );
		self::assertSame( 19.99, EfbPayments::price( '$19.99' ) );
		self::assertSame( 1200.0, EfbPayments::price( '1,200' ) );
		self::assertSame( 250.0, EfbPayments::price( '۲۵۰' ) );
		self::assertSame( 0.5, EfbPayments::price( '0.5' ) );
		foreach ( array( 0, -5, '0', '', 'free', null, array( 1 ), 1000001, 'NaN', true ) as $bad ) {
			self::assertNull( EfbPayments::price( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_only_the_three_gateway_add_ons_are_read_and_only_as_on_or_off(): void {
		$active = EfbPayments::active_gateways( array( 'AdnSPF' => 1, 'AdnPAP' => '0', 'AdnPPF' => 2, 'AdnOF' => 1, 'Evil' => 1 ) );
		self::assertSame( array( 'stripe' => true, 'paypal' => false, 'zarinpal' => true ), $active );
		self::assertSame( array( 'stripe' => false, 'paypal' => false, 'zarinpal' => false ), EfbPayments::active_gateways( 'garbage' ) );
	}

	public function test_saved_keys_are_detected_without_ever_returning_them(): void {
		$stripe_saved   = '{"stripePKey":"pk_test_abcdef123","stripeSKey":"sk_test_abcdef123","x":1}';
		$escaped        = '{\"stripePKey\":\"pk_test_abcdef123\",\"stripeSKey\":\"sk_test_abcdef123\"}';
		$secret_missing = '{"stripePKey":"pk_test_abcdef123","stripeSKey":""}';
		$too_short      = '{"stripePKey":"pk","stripeSKey":"sk"}';

		self::assertTrue( EfbPayments::keys_saved( 'stripe', $stripe_saved ) );
		self::assertTrue( EfbPayments::keys_saved( 'stripe', $escaped ) );
		self::assertFalse( EfbPayments::keys_saved( 'stripe', $secret_missing ) );
		self::assertFalse( EfbPayments::keys_saved( 'stripe', $too_short ) );
		self::assertFalse( EfbPayments::keys_saved( 'paypal', $stripe_saved ) );
		self::assertNull( EfbPayments::keys_saved( 'zarinpal', $stripe_saved ), 'ZarinPal keeps its code outside the shared settings' );

		$status = EfbPayments::status( array( 'AdnSPF' => 1 ), $stripe_saved );
		self::assertTrue( $status['stripe']['addon_active'] );
		self::assertTrue( $status['stripe']['keys_saved'] );
		self::assertFalse( $status['paypal']['addon_active'] );
		$block = EfbPayments::prompt_block( $status );
		self::assertStringContainsString( 'Stripe (stripe): add-on active, keys saved', $block );
		self::assertStringNotContainsString( 'pk_test', $block );
		self::assertStringContainsString( 'ZarinPal (zarinpal): add-on not active, keys unknown', $block );
	}
}
