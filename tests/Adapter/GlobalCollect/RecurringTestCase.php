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
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 */
class DonationInterface_Adapter_GlobalCollect_RecurringTestCase extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingGlobalCollectAdapter';
	}

	function tearDown() {
		TestingGlobalCollectAdapter::clearGlobalsCache();
		parent::tearDown();
	}

	/**
	 * Can make a recurring payment
	 *
	 * @covers GlobalCollectAdapter::transactionRecurring_Charge
	 */
	public function testRecurringCharge() {
		$init = array(
			'amount' => '2345',
			'effort_id' => 2,
			'order_id' => '9998890004',
			'currency_code' => 'EUR',
			'payment_product' => '',
		);
		$gateway = $this->getFreshGatewayObject( $init );

		// FIXME: I don't understand whether the literal code should correspond to anything in GC
		$gateway->setDummyGatewayResponseCode( 'recurring' );

		$result = $gateway->do_transaction( 'Recurring_Charge' );

		$this->assertTrue( isset( $result['status'] ) && $result['status'] === true );
		$this->assertRegExp( '/SET_PAYMENT/', $result['result'] );
	}
}
