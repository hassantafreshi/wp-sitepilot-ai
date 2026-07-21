<?php
declare(strict_types=1);

namespace SitePilot\Tests;

use PHPUnit\Framework\TestCase;
use SitePilot\Connectors\NativeFormConnector;

final class ConnectorValidateTest extends TestCase {

	private NativeFormConnector $connector;

	protected function setUp(): void {
		$this->connector = new NativeFormConnector();
	}

	public function test_valid_plan_has_no_errors(): void {
		$plan = array(
			'title'  => 'Contact',
			'fields' => array(
				array( 'type' => 'text', 'label' => 'Name' ),
				array( 'type' => 'email', 'label' => 'Email' ),
			),
		);
		$this->assertSame( array(), $this->connector->validate_action( $plan ) );
	}

	public function test_missing_title_reported(): void {
		$plan = array(
			'title'  => '',
			'fields' => array( array( 'type' => 'text', 'label' => 'Name' ) ),
		);
		$this->assertNotEmpty( $this->connector->validate_action( $plan ) );
	}

	public function test_forbidden_type_reported(): void {
		$plan = array(
			'title'  => 'Bad',
			'fields' => array( array( 'type' => 'payment', 'label' => 'Card' ) ),
		);
		$this->assertNotEmpty( $this->connector->validate_action( $plan ) );
	}

	public function test_capabilities_and_availability(): void {
		$this->assertContains( 'form.create', $this->connector->get_capabilities() );
		$this->assertTrue( $this->connector->is_available() );
	}
}
