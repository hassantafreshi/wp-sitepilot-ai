<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Execution\ActionStore;

final class ActionStoreTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['aisaautopilot_test_transients'] = array();
		$GLOBALS['aisaautopilot_test_added_options'] = array();
		$GLOBALS['aisaautopilot_test_user_id'] = 7;
	}

	public function test_wrong_user_cannot_consume_another_users_proposal(): void {
		$store = new ActionStore();
		$id = $store->create( array( 'title' => 'Contact' ), 'native_form' );

		self::assertSame( 'forbidden', $store->claim( $id, 8 )['status'] );
		self::assertNotNull( $store->get( $id ), 'A forbidden approval must not delete the proposal.' );
		$claim = $store->claim( $id, 7 );
		self::assertSame( 'claimed', $claim['status'] );
		self::assertSame( 'native_form', $claim['record']['connector_id'] );
		self::assertNull( $store->get( $id ) );
	}
}
