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
 */

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group Amazon
 */
class DonationInterface_Adapter_Amazon_TestCase extends DonationInterfaceTestCase {

	public function __construct() {
		parent::__construct();
		$this->testAdapterClass = 'TestingAmazonAdapter';
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected when all necessary data is present.
	 */
	function testDoTransactionDonate() {
		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );

		//@TODO: Refactor the hell out of the Amazon adapter so it looks like... anything else we have, and it remotely testable.
		//In the meantime, though...
		$gateway->do_transaction( 'Donate' );
		$ret = $gateway->_buildRequestParams();

		$expected = array (
			'accessKey' => 'testkey',
			'amount' => $init['amount'],
			'collectShippingAddress' => '0',
			'description' => 'Donation to the Wikimedia Foundation',
			'immediateReturn' => '1',
			'ipnUrl' => 'https://test.wikimedia.org/amazon',
			'isDonationWidget' => '1',
			'processImmediate' => '1',
			'referenceId' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'returnUrl' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'signatureMethod' => 'HmacSHA256',
			'signatureVersion' => '2',
		);

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Amazon order_id is null, and we actually need one for the return URL follow-through" );
		$this->assertEquals( $expected, $ret, 'Amazon "Donate" transaction not building the expected request params' );
	}

	/**
	 * Integration test to verify that the DonateMonthly transaction works as expected when all necessary data is present.
	 */
	function testDoTransactionDonateMonthly() {
		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );

		//@TODO: Refactor the hell out of the Amazon adapter so it looks like... anything else we have, and it remotely testable.
		//In the meantime, though...
		$gateway->do_transaction( 'DonateMonthly' );
		$ret = $gateway->_buildRequestParams();

		$expected = array (
			'accessKey' => 'testkey',
			'amount' => $init['amount'],
			'collectShippingAddress' => '0',
			'description' => 'Monthly donation to the Wikimedia Foundation',
			'immediateReturn' => '1',
			'ipnUrl' => 'https://test.wikimedia.org/amazon',
			'processImmediate' => '1',
			'referenceId' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'returnUrl' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'signatureMethod' => 'HmacSHA256',
			'signatureVersion' => '2',
			'recurringFrequency' => '1 month',
		);

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Amazon order_id is null, and we actually need one for the return URL follow-through" );
		$this->assertEquals( $expected, $ret, 'Amazon "DonateMonthly" transaction not building the expected request params' );
	}

}
