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

/**
 * @group       DonationInterface
 * @group       QueueHandling
 *
 * @category	UnitTesting
 * @package		Fundraising_QueueHandling
 */
class DonationQueueTest extends DonationInterfaceTestCase {
	protected $transaction;
	protected $queue_name;
	protected $expected_message;

	public function setUp() {
		parent::setUp();

		DonationInterface::initializeSmashPig( 'default' );
		$this->queue_name = 'test-' . mt_rand();

		$this->setMwGlobals( array(
			'wgDonationInterfaceQueues' => array(
				$this->queue_name => array(),
			),
		) );

		$this->transaction = array(
			'gross' => '1.24',
			'fee' => '0',
			'city' => 'Dunburger',
			'contribution_tracking_id' => mt_rand(),
			'country' => 'US',
			'currency' => 'USD',
			'date' => time(),
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Jen',
			'gateway_account' => 'default',
			'gateway' => 'testgateway',
			'gateway_txn_id' => mt_rand(),
			'order_id' => mt_rand(),
			'language' => 'en',
			'last_name' => 'Russ',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'response' => 'Gateway response something',
			'state_province' => 'AK',
			'street_address' => '1 Fake St.',
			'user_ip' => '127.0.0.1',
			'utm_source' => 'testing',
			'postal_code' => '12345',
		);

		$this->expected_message = array(
			'contribution_tracking_id' => $this->transaction['contribution_tracking_id'],
			'utm_source' => 'testing',
			'language' => 'en',
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Jen',
			'last_name' => 'Russ',
			'street_address' => '1 Fake St.',
			'city' => 'Dunburger',
			'state_province' => 'AK',
			'country' => 'US',
			'postal_code' => '12345',
			'gateway' => 'testgateway',
			'gateway_account' => 'default',
			'gateway_txn_id' => $this->transaction['gateway_txn_id'],
			'order_id' => $this->transaction['order_id'],
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'response' => 'Gateway response something',
			'currency' => 'USD',
			'fee' => '0',
			'gross' => '1.24',
			'user_ip' => '127.0.0.1',
			'date' => (int)$this->transaction['date'],
			'source_host' => WmfFramework::getHostname(),
			'source_name' => 'DonationInterface',
			'source_run_id' => getmypid(),
			'source_type' => 'payments',
			'source_version' => DonationQueue::getVersionStamp(),
		);
	}

	public function testPushMessage() {
		DonationQueue::instance()->push( $this->transaction, $this->queue_name );

		$actual = DonationQueue::instance()->pop( $this->queue_name );
		unset( $actual['source_enqueued_time'] );

		$this->assertEquals( $this->expected_message, $actual );
	}

	/**
	 * After pushing 2, pop should return the first.
	 */
	public function testIsFifoQueue() {
		DonationQueue::instance()->push( $this->transaction, $this->queue_name );

		$transaction2 = $this->transaction;
		$transaction2['order_id'] = mt_rand();

		DonationQueue::instance()->push( $transaction2, $this->queue_name );

		$actual = DonationQueue::instance()->pop( $this->queue_name );
		unset( $actual['source_enqueued_time'] );

		$this->assertEquals( $this->expected_message, $actual );
	}
}
