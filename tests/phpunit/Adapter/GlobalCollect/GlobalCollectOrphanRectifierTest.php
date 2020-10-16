<?php

/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

use SmashPig\Core\DataStores\PendingDatabase;

/**
 * @covers GlobalCollectOrphanRectifier
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group OrphanSlayer
 */
class DonationInterface_Adapter_GlobalCollect_Orphan_Rectifier_Test
	extends DonationInterfaceTestCase
{
	// TODO: Give vulgar names.
	// FIXME: Is 25 the normal unauthorized status?  Use the common one, whatever that is.
	const STATUS_PENDING = 25;
	const STATUS_PENDING_POKE = 600;
	const STATUS_COMPLETE = 800;

	// Arbitrary configuration for testing time logic.
	const TIME_BUFFER = 60;
	const TARGET_EXECUTE_TIME = 1200;

	public $pendingDb;

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgDonationInterfaceOrphanCron' => [
				'enable' => true,
				'target_execute_time' => self::TARGET_EXECUTE_TIME,
				'time_buffer' => self::TIME_BUFFER,
			],
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceGatewayAdapters' => [
				// We include the regular adapter in order to pass gateway validation D:
				'globalcollect' => TestingGlobalCollectOrphanAdapter::class,
				'globalcollect_orphan' => TestingGlobalCollectOrphanAdapter::class,
			],
		] );

		$this->pendingDb = PendingDatabase::get();
	}

	/**
	 * When leaving a message unprocessed and pending, don't try to process it
	 * again.
	 */
	public function testProcessOrphansStatusPending() {
		$orphan_pending = $this->createOrphan();

		$rectifier = new GlobalCollectOrphanRectifier();
		$this->gateway = $rectifier->getAdapter();
		TestingGlobalCollectOrphanAdapter::setDummyGatewayResponseCode( self::STATUS_PENDING );
		$rectifier->processOrphans();

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			'globalcollect', $orphan_pending['order_id'] );
		$this->assertNull( $fetched,
			'Message was popped.' );

		$this->assertGatewayCallsExactly( [
			'GET_ORDERSTATUS'
		] );
	}

	/**
	 * If a message is waiting for the API kiss of death, perform it.
	 */
	public function testProcessOrphansStatusPendingPoke() {
		$orphan_pending_poke = $this->createOrphan();

		$rectifier = new GlobalCollectOrphanRectifier();
		$this->gateway = $rectifier->getAdapter();
		TestingGlobalCollectOrphanAdapter::setDummyGatewayResponseCode( self::STATUS_PENDING_POKE );
		$rectifier->processOrphans();

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			'globalcollect', $orphan_pending_poke['order_id'] );
		$this->assertNull( $fetched,
			'Message was popped' );

		$this->assertGatewayCallsExactly( [
			'GET_ORDERSTATUS',
			'SET_PAYMENT',
		] );

		// TODO: test that we sent a completion message
	}

	/**
	 * Report a completed transaction.
	 */
	public function testProcessOrphansStatusComplete() {
		$orphan_complete = $this->createOrphan();

		$rectifier = new GlobalCollectOrphanRectifier();
		$this->gateway = $rectifier->getAdapter();
		TestingGlobalCollectOrphanAdapter::setDummyGatewayResponseCode( self::STATUS_COMPLETE );
		$rectifier->processOrphans();

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			'globalcollect', $orphan_complete['order_id'] );
		$this->assertNull( $fetched,
			'Message was popped' );

		$this->assertGatewayCallsExactly( [
			'GET_ORDERSTATUS',
		] );

		// TODO: test that we sent a completion message
	}

	/**
	 * Don't process recent messages.
	 */
	public function testTooRecentMessage() {
		$orphan_complete = $this->createOrphan( [
			'date' => time() - self::TIME_BUFFER + 30,
		] );

		$rectifier = new GlobalCollectOrphanRectifier();
		$this->gateway = $rectifier->getAdapter();
		$rectifier->processOrphans();

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			'globalcollect', $orphan_complete['order_id'] );
		$this->assertNotNull( $fetched,
			'Message was not popped' );

		$this->assertGatewayCallsExactly( [] );

		// TODO: Test that we:
		// * Logged the "done with old messages" line.
	}

	/**
	 * Create an orphaned tranaction and store it to the pending database.
	 */
	public function createOrphan( $overrides = [] ) {
		$message = parent::createOrphan( $overrides );
		$this->pendingDb->storeMessage( $message );
		return $message;
	}

	/**
	 * Assert whether we made exactly the expected gateway calls
	 *
	 * @param array $expected List of API action names, in the form they appear
	 * in the <ACTION> tag.
	 */
	protected function assertGatewayCallsExactly( $expected ) {
		$expected_num_calls = count( $expected );
		$this->assertEquals( $expected_num_calls, count( $this->gateway->curled ),
			"Ran exactly {$expected_num_calls} API calls" );
		foreach ( $expected as $index => $action ) {
			$this->assertRegExp( '/\b' . $action . '\b/', $this->gateway->curled[$index],
				"Call #" . ( $index + 1 ) . " was {$action}." );
		}
	}

	/**
	 * Dump the entire database state, for debugging.
	 */
	protected function debugDbContents() {
		$result = $this->pendingDb->getDatabase()->query(
			"select * from pending" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		var_export( $rows );
	}
}
