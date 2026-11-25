<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbPayments;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';
require_once __DIR__ . '/fixtures/efb-field-templates-fake.php';

final class EfbPaymentsTest extends TestCase {

	protected function setUp(): void {
		// EFB gives the framework field of a moved gateway whose add-on is on (PAY-OUT).
		$GLOBALS['aisa_test_field_templates'] = array( 'stripecheckout', 'paypalcheckout', 'zarinpalgate' );
	}

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
		unset( $GLOBALS['aisa_test_field_templates'] );
	}

	/**
	 * The payment status of a site with these gateway add-ons on, as EFB's registry reports it.
	 *
	 * @param array<string,int> $addons
	 * @return array<string,array<string,mixed>>
	 */
	private static function status_for( array $addons, string $plan = 'pro' ): array {
		$on = array();
		foreach ( array( 'AdnSPF', 'AdnPAP', 'AdnPPF' ) as $code ) {
			$on[ $code ] = ! empty( $addons[ $code ] );
		}
		EfbRegistryFixture::apply( EfbRegistryFixture::site( $plan, $on ) );

		return EfbPayments::status( $addons, '' );
	}

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

		// Without EFB's registry the browser's add-on flags are read, but nothing vouches for the add-on contract.
		$status = EfbPayments::status( array( 'AdnSPF' => 1 ), $stripe_saved );
		self::assertTrue( $status['stripe']['addon_active'] );
		self::assertTrue( $status['stripe']['keys_saved'] );
		self::assertFalse( $status['paypal']['addon_active'] );
		self::assertFalse( $status['stripe']['known'] );
		$block = EfbPayments::prompt_block( $status );
		self::assertStringContainsString( 'Stripe (stripe): not reported by this Easy Form Builder', $block );
		self::assertStringNotContainsString( 'pk_test', $block );

		// With it, the registry answers (payment_keys: presence only, never a key). QA5-007: a moved Stripe's keys are its
		// framework gateway's (payment_keys.stripecheckout), not the older field's.
		$caps                                   = EfbRegistryFixture::site( 'free_plus', array( 'AdnSPF' => true ) );
		$caps['payment_keys']['stripe']         = false;
		$caps['payment_keys']['stripecheckout'] = true;
		EfbRegistryFixture::apply( $caps );
		$block = EfbPayments::prompt_block( EfbPayments::status( array(), null ) );
		self::assertStringContainsString( 'Stripe (stripe): add-on active, plan ok, keys saved', $block );
		self::assertStringContainsString( 'PayPal (paypal): add-on not active, plan ok', $block );
		self::assertStringContainsString( 'ZarinPal (zarinpal): add-on not active, plan needs pro', $block );
	}

	/** @return array<string,array<string,mixed>> */
	private function payment_status( array $addons = array( 'AdnSPF' => 1, 'AdnPAP' => 1 ) ): array {
		return self::status_for( $addons );
	}

	public function test_a_complete_request_becomes_a_clean_payment(): void {
		$result = EfbPayments::validate_payment(
			array(
				'gateway'     => 'Stripe',
				'currency'    => 'EUR',
				'choice_type' => 'select',
				'label'       => 'Choose a package',
				'items'       => array( array( 'label' => 'Basic', 'price' => '$20' ), array( 'label' => '<b>Pro</b>', 'price' => 50 ) ),
			),
			$this->payment_status(),
			true
		);
		self::assertTrue( $result['ok'] );
		self::assertSame( 'stripe', $result['payment']['gateway'] );
		self::assertSame( 'eur', $result['payment']['currency'] );
		self::assertSame( 'select', $result['payment']['choice_type'] );
		self::assertSame( array( array( 'label' => 'Basic', 'price' => 20.0 ), array( 'label' => 'Pro', 'price' => 50.0 ) ), $result['payment']['items'] );
		self::assertSame( '', $result['payment']['amount_label'] );
	}

	public function test_a_free_amount_is_allowed_when_no_options_are_priced(): void {
		$result = EfbPayments::validate_payment( array( 'gateway' => 'paypal', 'amount_label' => 'Donation amount' ), $this->payment_status(), true );
		self::assertTrue( $result['ok'] );
		self::assertSame( 'Donation amount', $result['payment']['amount_label'] );
		self::assertSame( 'usd', $result['payment']['currency'] );
		self::assertSame( array(), $result['payment']['items'] );
	}

	/** @return array<string,array{0:array<string,mixed>,1:array<string,array<string,mixed>>,2:string,3:string}> */
	public static function refusals(): array {
		$on = array( 'AdnSPF' => 1, 'AdnPAP' => 1 );

		return array(
			'unknown gateway'  => array( array( 'gateway' => 'bitcoin', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), $on, 'pro', 'payment_gateway_unknown' ),
			'no gateway'       => array( array( 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), $on, 'pro', 'payment_gateway_unknown' ),
			'free licence'     => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), $on, 'free', 'payment_requires_pro:stripe:free_plus' ),
			'add-on off'       => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), array(), 'pro', 'payment_addon_inactive:stripe' ),
			'other add-on off' => array( array( 'gateway' => 'zarinpal', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), $on, 'pro', 'payment_addon_inactive:zarinpal' ),
			'bad currency'     => array( array( 'gateway' => 'paypal', 'currency' => 'inr', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), $on, 'pro', 'payment_currency_unsupported:paypal:inr' ),
			'no price at all'  => array( array( 'gateway' => 'stripe' ), $on, 'pro', 'payment_needs_price' ),
			'item without price' => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Basic' ) ) ), $on, 'pro', 'payment_needs_price' ),
			'zero price'       => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Basic', 'price' => 0 ) ) ), $on, 'pro', 'payment_needs_price' ),
			'negative price'   => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Basic', 'price' => -3 ) ) ), $on, 'pro', 'payment_needs_price' ),
			'price in words'   => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Basic', 'price' => 'cheap' ) ) ), $on, 'pro', 'payment_needs_price' ),
			'empty label'      => array( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => ' ', 'price' => 5 ) ) ), $on, 'pro', 'payment_needs_price' ),
		);
	}

	/**
	 * @dataProvider refusals
	 * @param array<string,mixed> $raw
	 * @param array<string,mixed> $addons
	 */
	public function test_an_incomplete_or_impossible_request_is_refused_with_a_reason( array $raw, array $addons, string $plan, string $reason ): void {
		$result = EfbPayments::validate_payment( $raw, self::status_for( $addons, $plan ), 'free' !== $plan );
		self::assertFalse( $result['ok'] );
		self::assertSame( $reason, $result['reason'] );
	}

	public function test_form_settings_match_what_the_builder_writes(): void {
		self::assertSame( array( 'type' => 'payment', 'getway' => 'stripe', 'currency' => 'gbp', 'paymentmethod' => 'charge' ), EfbPayments::form_settings( 'stripe', 'GBP' ) );
		self::assertSame( array( 'type' => 'payment', 'getway' => 'paypal', 'currency' => 'usd', 'paymentmethod' => 'charge' ), EfbPayments::form_settings( 'paypal', 'doge' ) );
		self::assertSame( array( 'type' => 'payment', 'getway' => 'persiaPay', 'currency' => 'irr', 'paymentmethod' => 'charge', 'persiaPay' => 'zarinPal' ), EfbPayments::form_settings( 'zarinpal', 'usd' ) );
	}

	public function test_rial_prices_may_be_large(): void {
		self::assertNull( EfbPayments::price( 2000000 ), 'a two-million dollar option is a typo' );
		self::assertSame( 2000000.0, EfbPayments::price( '۲٬۰۰۰٬۰۰۰', 'IRR' ) );
		$result = EfbPayments::validate_payment( array( 'gateway' => 'zarinpal', 'items' => array( array( 'label' => 'Cake', 'price' => 2000000 ) ) ), self::status_for( array( 'AdnPPF' => 1 ) ), true );
		self::assertTrue( $result['ok'] );
		self::assertSame( 'irr', $result['payment']['currency'] );
	}

	public function test_without_efbs_registry_no_payment_is_assumed(): void {
		EfbRegistryFixture::apply( null );
		$result = EfbPayments::validate_payment( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), EfbPayments::status( array( 'AdnSPF' => 1 ), '' ), true );
		self::assertFalse( $result['ok'] );
		self::assertSame( 'payment_unverified:stripe', $result['reason'] );
	}

	public function test_a_legacy_gateway_add_on_from_before_the_move_asks_for_an_update(): void {
		// An add-on older than this core: on, but its framework field is not registered (payment_gateways state update).
		$caps                                       = EfbRegistryFixture::site( 'free_plus', array( 'AdnSPF' => true ) );
		$caps['split']['addons']['AdnSPF']['state'] = 'update';
		$caps['payment_gateways']['stripecheckout'] = array(
			'addon'       => 'AdnSPF',
			'installed'   => true,
			'active'      => false,
			'plan'        => 'free_plus',
			'state'       => 'update',
			'label'       => 'Stripe Payment',
			'field_type'  => 'stripecheckout',
			'legacy_type' => 'stripe',
		);
		unset( $caps['legacy_gateways'] );
		EfbRegistryFixture::apply( $caps );
		$result = EfbPayments::validate_payment( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'A', 'price' => 5 ) ) ), EfbPayments::status( array(), null ), true );
		self::assertSame( 'payment_addon_update:stripe', $result['reason'] );
	}

	public function test_stripe_needs_free_plus_and_persia_pay_needs_pro_as_efb_says(): void {
		$free = self::status_for( array( 'AdnSPF' => 1 ), 'free' );
		self::assertFalse( $free['stripe']['plan_ok'] );
		self::assertSame( 'free_plus', $free['stripe']['package'] );
		$plus = self::status_for( array( 'AdnSPF' => 1, 'AdnPPF' => 1 ), 'free_plus' );
		self::assertTrue( $plus['stripe']['plan_ok'] );
		self::assertTrue( $plus['stripe']['addon_active'] );
		self::assertFalse( $plus['zarinpal']['plan_ok'] );
		self::assertSame( 'pro', $plus['zarinpal']['package'] );
	}

	public function test_a_payment_framework_gateway_comes_from_its_add_on(): void {
		// Mollie's add-on off: the gateway is not registered; its add-on entry says the plan.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$status = EfbPayments::status( array(), null );
		self::assertSame( 'mollie', EfbPayments::resolve_gateway( 'Mollie' ) );
		self::assertSame( 'tappay', EfbPayments::resolve_gateway( 'Tap Payments' ) );
		self::assertFalse( $status['mollie']['addon_active'] );
		self::assertSame( 'AdnMOL', $status['mollie']['addon'] );
		$off = EfbPayments::validate_payment( array( 'gateway' => 'mollie', 'items' => array( array( 'label' => 'Ticket', 'price' => 10 ) ) ), $status, true );
		self::assertSame( 'payment_addon_inactive:mollie', $off['reason'] );

		// On: registered by the add-on with its own label and currencies.
		$caps                                       = EfbRegistryFixture::site( 'free_plus', array( 'AdnMOL' => true ) );
		$caps['features']['field:mollie']           = array(
			'kind'          => 'field',
			'package'       => 'free_plus',
			'addon'         => 'AdnMOL',
			'available_now' => true,
			'reason'        => null,
			'form_patch'    => array(
				'type'   => 'payment',
				'getway' => 'mollie',
			),
		);
		$caps['payment_gateways']['mollie']         = array(
			'addon'      => 'AdnMOL',
			'label'      => 'Mollie',
			'currencies' => array( 'EUR', 'USD' ),
			'configured' => array(
				'test' => false,
				'live' => false,
			),
			'mode'       => 'test',
		);
		$caps['payment_keys']['mollie']             = false;
		EfbRegistryFixture::apply( $caps );
		$status = EfbPayments::status( array(), null );
		self::assertTrue( $status['mollie']['addon_active'] );
		self::assertFalse( $status['mollie']['keys_saved'] );
		self::assertSame( 'EUR', EfbPayments::currency( 'mollie', 'eur' ) );
		self::assertTrue( EfbPayments::supports_currency( 'mollie', 'usd' ) );
		self::assertFalse( EfbPayments::supports_currency( 'mollie', 'irr' ) );
		self::assertSame(
			array(
				'type'          => 'payment',
				'getway'        => 'mollie',
				'paymentmethod' => 'charge',
				'currency'      => 'USD',
			),
			EfbPayments::form_settings( 'mollie', 'usd' )
		);
		// Without EFB's own field template (no get_efbFunction() here) the gateway field cannot be built: refused.
		$result = EfbPayments::validate_payment( array( 'gateway' => 'mollie', 'currency' => 'EUR', 'items' => array( array( 'label' => 'Ticket', 'price' => 10 ) ) ), $status, true );
		self::assertSame( 'payment_template_missing:mollie', $result['reason'] );
	}

	/**
	 * QA5-007: Stripe, PayPal and ZarinPal moved to EFB's payment framework (PAY-OUT). A new form uses the field EFB's
	 * registry names for them (legacy_gateways.targets / payment_gateways legacy_type), built like a PAY00 gateway, and
	 * the keys link opens EFB's gateway settings, never the older payments list.
	 */
	public function test_new_stripe_paypal_and_zarinpal_forms_use_the_registrys_framework_field(): void {
		$caps = EfbRegistryFixture::site( 'pro', array( 'AdnSPF' => true, 'AdnPAP' => true, 'AdnPPF' => true ) );
		EfbRegistryFixture::apply( $caps );
		self::assertSame( 'stripecheckout', EfbPayments::legacy_slug( 'stripe' ) );
		self::assertSame( 'paypalcheckout', EfbPayments::gateway_field( 'paypal' ) );
		self::assertSame( 'zarinpalgate', EfbPayments::gateway_field( 'zarinpal' ) );
		self::assertSame( 'stripe', EfbPayments::gateway_of_field( 'stripecheckout' ) );
		self::assertTrue( EfbPayments::is_pay00( 'stripe' ) );
		self::assertSame(
			array(
				'type'          => 'payment',
				'getway'        => 'stripecheckout',
				'paymentmethod' => 'charge',
				'currency'      => 'eur',
			),
			EfbPayments::form_settings( 'stripe', 'EUR' )
		);
		$rows = EfbPayments::gateway_rows( 'stripe', 'Pay by card', array() );
		self::assertSame( 'stripecheckout', $rows[0]['type'] );
		self::assertSame( 'Pay by card', $rows[0]['name'] );

		$status = EfbPayments::status( array(), null );
		self::assertTrue( $status['stripe']['addon_active'] );
		self::assertSame( 'stripecheckout', $status['stripe']['field'] );
		self::assertStringContainsString( 'page=Emsfb_payments&tab=gateways', $status['stripe']['settings_url'] );
		self::assertStringNotContainsString( 'Emsfb_stripe_payment', $status['stripe']['settings_url'] );
		$ok = EfbPayments::validate_payment( array( 'gateway' => 'stripe', 'currency' => 'usd', 'items' => array( array( 'label' => 'Seat', 'price' => 40 ) ) ), $status, true );
		self::assertTrue( $ok['ok'] );

		// The converter builds the framework field and EFB's form patch, never the deprecated "stripe" field.
		$items = ( new \AISAAutoPilot\Efb\EfbFormConverter() )->convert(
			array(
				'title'   => 'Workshop',
				'fields'  => array(
					array(
						'type'     => 'text',
						'label'    => 'Name',
						'required' => true,
						'options'  => array(),
					),
				),
				'payment' => $ok['payment'],
			)
		);
		$types = array_column( $items, 'type' );
		self::assertContains( 'stripecheckout', $types );
		self::assertNotContains( 'stripe', $types );
		self::assertSame( 'stripecheckout', $items[0]['getway'] );

		// The add-on off: EFB still names the framework field; the payment is refused with the add-on to install.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		self::assertSame( 'stripecheckout', EfbPayments::legacy_slug( 'stripe' ) );
		$off = EfbPayments::validate_payment( array( 'gateway' => 'stripe', 'items' => array( array( 'label' => 'Seat', 'price' => 40 ) ) ), EfbPayments::status( array(), null ), true );
		self::assertSame( 'payment_addon_inactive:stripe', $off['reason'] );
	}
}
