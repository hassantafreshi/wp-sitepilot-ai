<?php
/**
 * The connector registry takes a connector that only links (the coming Easy Form Builder customer portal connector,
 * `portal.link`) without any change to the form connectors or their callers; EFB's F20-core contract is readable.
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Connectors\ConnectorBase;
use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Efb\EfbAddons;
use AISAAutoPilot\Efb\EfbCapabilities;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';

/** A stand-in for a future portal connector: base methods only. */
final class PortalConnectorStub implements ConnectorBase {
	public bool $available = true;

	public function get_id(): string {
		return 'efb_customer_portal';
	}

	public function get_name(): string {
		return 'Customer portal';
	}

	public function is_available(): bool {
		return $this->available;
	}

	public function get_capabilities(): array {
		return array( 'portal.link' );
	}

	public function get_health(): array {
		return array(
			'available' => $this->available,
			'version'   => '',
			'notes'     => '',
		);
	}
}

final class ConnectorRegistryPortalTest extends TestCase {

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
	}

	public function test_a_link_only_connector_registers_beside_the_form_connectors(): void {
		$registry = new ConnectorRegistry();
		$native   = new NativeFormConnector();
		$registry->register( $native );
		$portal = new PortalConnectorStub();
		$registry->register( $portal );

		self::assertContains( 'portal.link', ConnectorRegistry::CAPABILITIES );
		self::assertSame( 'aisaautopilot_register_connectors', ConnectorRegistry::REGISTER_ACTION );
		// Form callers see only the connectors that create forms.
		self::assertSame( array( $native ), $registry->all() );
		self::assertNull( $registry->get( 'efb_customer_portal' ) );
		self::assertSame( $portal, $registry->connector( 'efb_customer_portal' ) );
		self::assertCount( 2, $registry->all_connectors() );
		self::assertSame( array( $portal ), $registry->with_capability( 'portal.link' ) );
		self::assertNull( $registry->resolve( 'portal.link' ), 'resolve() is for form actions only' );

		$portal->available = false;
		self::assertSame( array(), $registry->with_capability( 'portal.link' ) );
	}

	public function test_a_built_in_id_cannot_be_replaced(): void {
		$registry = new ConnectorRegistry();
		$native   = new NativeFormConnector();
		$registry->register( $native );
		$registry->register( new NativeFormConnector() );
		self::assertSame( $native, $registry->get( 'native_form' ) );
	}

	public function test_efbs_portal_contract_is_readable_for_a_future_connector(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$contract = EfbCapabilities::portal_contract();
		self::assertSame( 1, $contract['version'] );
		self::assertTrue( $contract['core'] );
		self::assertSame( 'AdnPRT', EfbCapabilities::portal()['addon'] );
		self::assertFalse( EfbCapabilities::portal()['ready'] );
	}

	public function test_install_offers_name_the_add_on_and_plan_from_efb(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$offers = EfbAddons::offers( array( 'payment_addon_inactive:stripe', 'logic_addon_inactive' ) );
		self::assertSame( 'AdnSPF', $offers[0]['code'] );
		self::assertSame( 'free_plus', $offers[0]['plan'], 'Stripe installs on Free Plus (the old list said Pro).' );
		self::assertTrue( $offers[0]['allowed'] );
		self::assertSame( 'AdnSMF', $offers[1]['code'] );
		self::assertStringContainsString( 'efb_upgrade=addon%3AAdnSMF', $offers[1]['upgrade_url'] );

		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$persia = EfbAddons::offers( array( 'payment_addon_inactive:zarinpal' ) )[0];
		self::assertSame( 'pro', $persia['plan'] );
		self::assertFalse( $persia['allowed'] );
	}
}
