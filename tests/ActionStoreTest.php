<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Execution\ContentDraftExecutor;

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

	public function test_wrong_connector_cannot_consume_a_proposal(): void {
		$store = new ActionStore();
		$id    = $store->create( array( 'title' => 'Contact' ), 'native_form' );

		self::assertSame( 'wrong_connector', $store->claim( $id, 7, 'content_draft' )['status'] );
		self::assertNotNull( $store->get( $id ), 'A proposal opened through another product surface must remain available.' );

		$claim = $store->claim( $id, 7, 'native_form' );
		self::assertSame( 'claimed', $claim['status'] );
		self::assertSame( 'native_form', $claim['record']['connector_id'] );
	}

	public function test_content_executor_does_not_consume_a_wrong_connector_action(): void {
		$store = new ActionStore();
		$id    = $store->create( array( 'title' => 'Must remain reviewable' ), 'native_form' );

		$result = ( new ContentDraftExecutor( $store ) )->approve_and_execute( $id );

		self::assertSame( 'failed', $result['status'] );
		self::assertSame( 'invalid_action', $result['code'] );
		self::assertNotNull( $store->get( $id ), 'A Content Studio mismatch must leave the proposal available.' );
		self::assertArrayNotHasKey(
			'aisaautopilot_action_claim_' . str_replace( '-', '', $id ),
			$GLOBALS['aisaautopilot_test_added_options'],
			'A connector mismatch must not create a replay lock.'
		);

		$claim = $store->claim( $id, 7, 'native_form' );
		self::assertSame( 'claimed', $claim['status'] );
		self::assertNull( $store->get( $id ) );
	}
}
