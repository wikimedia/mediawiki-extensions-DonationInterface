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
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Astropay
 */
class DonationInterface_Adapter_Astropay_AstropayTest extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingAstropayAdapter';
	}

	function tearDown() {
		TestingAstropayAdapter::clearGlobalsCache();
		parent::tearDown();
	}

	/**
	 * Ensure we're setting the right url for each transaction
	 * @covers AstropayAdapter::getCurlBaseOpts
	 */
	function testCurlUrl() {
		$init = $this->getDonorTestData( 'BR' );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setCurrentTransaction( 'NewInvoice' );

		$result = $gateway->getCurlBaseOpts();

		$this->assertEquals(
			'https://sandbox.astropay.example.com/api_curl/streamline/NewInvoice',
			$result[CURLOPT_URL],
			'Not setting URL to transaction-specific value.'
		);
	}
}
