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
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group Amazon
 */
class DonationInterface_Adapter_Amazon_Test extends DonationInterfaceTestCase {

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingAmazonAdapter';
	}

	public function tearDown() {
		TestingAmazonAdapter::$fakeGlobals = array();
		parent::tearDown();
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
			'returnUrl' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?ffname=amazon&order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'signatureMethod' => 'HmacSHA256',
			'signatureVersion' => '2',
		);

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Amazon order_id is null, and we actually need one for the return URL follow-through" );
		$this->assertEquals( $expected, $ret, 'Amazon "Donate" transaction not building the expected request params' );
	}

	/**
	 * Make sure the order ID is appended correctly if the ReturnURL already has
	 * querystring parameters
	 */
	function testReturnURLAppendQuerystring() {
		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );
		TestingAmazonAdapter::$fakeGlobals = array(
			'ReturnURL' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?platypus=awesome'
		);

		$gateway->do_transaction( 'Donate' );
		$ret = $gateway->_buildRequestParams();
		$expected = 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?platypus=awesome&ffname=amazon&order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' );
		 
		$this->assertEquals( $expected, $ret['returnUrl'], 'Amazon "Donate" transaction not building the expected returnUrl' );
	}

	/**
	 * Integration test to verify that the Donate transaction works as expected
	 * in Canada (English and French) when all necessary data is present.
	 *
	 * @dataProvider canadaLanguageProvider
	 */
	function testDoTransactionDonate_CA( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		$init['language'] = $language;
		$init['currency_code'] = 'USD';
		$this->setLanguage( $language );
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( $language )->text();

		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'Donate' );
		$ret = $gateway->_buildRequestParams();

		$expected = array (
			'accessKey' => 'testkey',
			'amount' => $init['amount'],
			'collectShippingAddress' => '0',
			'description' => $donateText,
			'immediateReturn' => '1',
			'ipnUrl' => 'https://test.wikimedia.org/amazon',
			'isDonationWidget' => '1',
			'processImmediate' => '1',
			'referenceId' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'returnUrl' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?ffname=amazon&order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'signatureMethod' => 'HmacSHA256',
			'signatureVersion' => '2',
		);

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Amazon order_id is null, and we actually need one for the return URL follow-through" );
		$this->assertEquals( $expected, $ret, 'Amazon "Donate" transaction not building the expected request params' );
	}

	/**
	 * Integration test to verify that the Amazon gateway converts Canadian
	 * dollars before redirecting
	 *
	 * @dataProvider canadaLanguageProvider
	 */
	function testCanadianDollarConversion( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'amazon';
		$init['ffname'] = 'amazon';
		$init['language'] = $language;
		$init['redirect'] = 1;
		$donateText = wfMessage( 'donate_interface-donation-description' )->inLanguage( $language )->text();

		$rates = CurrencyRates::getCurrencyRates();
		$cadRate = $rates['CAD'];

		$expectedAmount = floor( $init['amount'] / $cadRate );

		TestingAmazonAdapter::$fakeGlobals = array(
			'FallbackCurrency' => 'USD',
			'NotifyOnConvert' => false,
		);
		$that = $this; //needed for PHP pre-5.4
		$redirectTest = function( $location ) use ( $expectedAmount, $donateText, $that ) {
			$actual = array();
			parse_str( $location, $actual );
			$that->assertTrue( is_numeric( $actual['amount'] ) );
			$difference = abs( floatval( $actual['amount'] ) - $expectedAmount );
			$that->assertTrue( $difference <= 1 );
			$that->assertEquals( $donateText, $actual['description'] );
		};

		$assertNodes = array(
			'headers' => array(
				'Location' => $redirectTest,
			)
		);
		$this->verifyFormOutput( 'TestingAmazonGateway', $init, $assertNodes, false );
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
			'returnUrl' => 'https://payments.wikimedia.org/index.php/Special:AmazonGateway?ffname=amazon&order_id=' . $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'signatureMethod' => 'HmacSHA256',
			'signatureVersion' => '2',
			'recurringFrequency' => '1 month',
		);

		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), "Amazon order_id is null, and we actually need one for the return URL follow-through" );
		$this->assertEquals( $expected, $ret, 'Amazon "DonateMonthly" transaction not building the expected request params' );
	}

	/**
	 * Verify that the Amazon adapter populates return data from URI.
	 */
	function testAmazonGatewayReturn() {
		$url_vars = array (
			'transactionAmount' => 'USD 123',
			'buyerEmail' => 'suzy@reba.net',
			'transactionDate' => '2014-07-13',
			'buyerName' => 'Suzy Greenberg',
			'paymentMethod' => 'Credit Card',
			'referenceId' => '12345',
		);
		$fake_request = new TestingRequest( $url_vars, false );
		$this->setMwGlobals( array ( 'wgRequest' => $fake_request ) );

		$init = $this->getDonorTestData();
		$gateway = $this->getFreshGatewayObject( $init );

		$gateway->do_transaction( 'ProcessAmazonReturn' );
		$ret = $gateway->getData_Unstaged_Escaped();

		$expected = array (
			'currency_code' => 'USD',
			'amount' => '123.00',
			'email' => 'suzy@reba.net',
			'fname' => 'Suzy',
			'lname' => 'Greenberg',
			'payment_submethod' => 'amazon_cc',
			'contribution_tracking_id' => '12345',
			'date_collect' => '2014-07-13',
		);

		foreach ($expected as $key => $value) {
			$this->assertEquals( $value, $ret[$key], 'Amazon "ProcessAmazonReturn" transaction not populating data from the URI as expected' );	
		}
	}

	/**
	 * Integration test to verify that the Amazon gateway shows an error message when validation fails.
	 */
	function testShowFormOnError() {
		$init = $this->getDonorTestData();
		$init['OTT'] = 'SALT123456789';
		$init['amount'] = '-100.00';
		$init['ffname'] = 'amazon';
		$_SESSION['Donor'] = $init;
		$errorMessage = wfMessage('donate_interface-error-msg-field-correction', wfMessage('donate_interface-error-msg-amount')->text())->text();
		$assertNodes = array(
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			)
		);

		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false );
	}

}
