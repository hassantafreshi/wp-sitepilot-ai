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
			'notification_email' => 'forms@example.test',
			'fields' => array(
				array( 'key' => 'name', 'type' => 'text', 'label' => 'Name' ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email' ),
			),
		);
		$this->assertSame( array(), $this->connector->validate_action( $plan ) );
	}

	public function test_missing_title_reported(): void {
		$plan = array(
			'title'  => '',
			'notification_email' => 'forms@example.test',
			'fields' => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name' ) ),
		);
		$this->assertNotEmpty( $this->connector->validate_action( $plan ) );
	}

	public function test_forbidden_type_reported(): void {
		$plan = array(
			'title'  => 'Bad',
			'notification_email' => 'forms@example.test',
			'fields' => array( array( 'key' => 'card', 'type' => 'payment', 'label' => 'Card' ) ),
		);
		$this->assertNotEmpty( $this->connector->validate_action( $plan ) );
	}

	public function test_capabilities_and_availability(): void {
		$this->assertContains( 'form.create', $this->connector->get_capabilities() );
		$this->assertTrue( $this->connector->is_available() );
	}

	public function test_notification_email_is_required(): void {
		$this->assertNotEmpty(
			$this->connector->validate_action(
				array( 'title' => 'Contact', 'fields' => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Name' ) ) )
			)
		);
	}
}
