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

	private $normalResponses = array(
		'setOrderReferenceDetails' => array (
			'SetOrderReferenceDetailsResult' => array (
				'OrderReferenceDetails' => array (
					'OrderReferenceStatus' => array (
						'State' => 'Draft',
					),
					'ExpirationTimestamp' => '2016-02-20T21:03:47.077Z',
					'SellerOrderAttributes' => array (),
					'OrderTotal' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '10.00',
					),
					'ReleaseEnvironment' => 'Sandbox',
					'SellerNote' => 'Donation to the Wikimedia Foundation',
					'AmazonOrderReferenceId' => 'S01-0391295-0674065',
					'CreationTimestamp' => '2015-08-24T21:03:47.077Z',
				),
			),
			'ResponseMetadata' => array (
				'RequestId' => 'fce0cb84-1aa9-4f82-83a1-acf64ece1b56',
			),
			'ResponseStatus' => '200',
		),
		'confirmOrderReference' => array (
			'ResponseMetadata' => array (
				'RequestId' => 'bbda771d-1739-448e-bdff-d79881dea668',
			),
			'ResponseStatus' => '200',
		),
		'getOrderReferenceDetails' => array (
			'GetOrderReferenceDetailsResult' => array (
				'OrderReferenceDetails' => array (
					'OrderReferenceStatus' => array (
						'LastUpdateTimestamp' => '2015-08-24T21:50:01.773Z',
						'State' => 'Open',
					),
					'ExpirationTimestamp' => '2016-02-20T21:03:47.077Z',
					'IdList' => array (),
					'SellerOrderAttributes' => array (),
					'OrderTotal' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '10.00',
					),
					'Buyer' => array (
						'Name' => 'Testy Test',
						'Email' => 'nobody@wikimedia.org',
					),
					'ReleaseEnvironment' => 'Sandbox',
					'SellerNote' => 'Donation to the Wikimedia Foundation',
					'AmazonOrderReferenceId' => 'S01-0391295-0674065',
					'CreationTimestamp' => '2015-08-24T21:03:47.077Z',
				),
			),
			'ResponseMetadata' => array (
				'RequestId' => 'd2759fcb-fdb0-4e93-8b9f-c3156d6bc5fd',
			),
			'ResponseStatus' => '200',
		),
		'authorize' => array (
			'AuthorizeResult' => array (
				'AuthorizationDetails' => array (
					'AuthorizationAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '10.00',
					),
					'CapturedAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0',
					),
					'SoftDescriptor' => 'AMZ*Wikimedia Founda',
					'ExpirationTimestamp' => '2015-09-23T21:50:58.221Z',
					'IdList' => array (
						'member' => 'S01-0391295-0674065-C095112',
					),
					'AuthorizationStatus' => array (
						'LastUpdateTimestamp' => '2015-08-24T21:50:58.221Z',
						'ReasonCode' => 'MaxCapturesProcessed',
						'State' => 'Closed',
					),
					'AuthorizationFee' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0.00',
					),
					'CaptureNow' => 'true',
					'SellerAuthorizationNote' => array (),
					'CreationTimestamp' => '2015-08-24T21:50:58.221Z',
					'AmazonAuthorizationId' => 'S01-0391295-0674065-A095112',
					'AuthorizationReferenceId' => '31243-0',
				),
			),
			'ResponseMetadata' => array (
				'RequestId' => '785d061e-e152-4180-80d1-3a492d16e24e',
			),
			'ResponseStatus' => '200',
		),
		'getCaptureDetails' => array (
			'GetCaptureDetailsResult' => array (
				'CaptureDetails' => array (
					'CaptureReferenceId' => '31243-0',
					'CaptureFee' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0.00',
					),
					'AmazonCaptureId' => 'S01-0391295-0674065-C095112',
					'CreationTimestamp' => '2015-08-24T21:50:58.321Z',
					'SoftDescriptor' => 'AMZ*Wikimedia Founda',
					'IdList' => array (),
					'CaptureStatus' => array (
						'LastUpdateTimestamp' => '2015-08-24T21:50:58.321Z',
						'State' => 'Completed',
					),
					'SellerCaptureNote' => array (),
					'RefundedAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0',
					),
					'CaptureAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '10.00',
					),
				),
			),
			'ResponseMetadata' => array (
				'RequestId' => 'f863dd05-99f3-4e9c-b0b6-561d65281cd7',
			),
			'ResponseStatus' => '200',
		),
	);

	public function setUp() {
		parent::setUp();

		$this->resetClient();

		$this->setMwGlobals( array(
			'wgAmazonGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'amazon' => array(
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
				),
				'amazon-recurring' => array(
					'gateway' => 'amazon',
					'payment_methods' => array('amazon' => 'ALL'),
					'redirect',
					'recurring',
				),
			),
			'wgAmazonGatewayAccountInfo' => array( 'test' => array(
				'SellerID' => 'ABCDEFGHIJKL',
				'ClientID' => 'amzn1.application-oa2-client.1a2b3c4d5e',
				'ClientSecret' => '12432g134e3421a41234b1341c324123d',
				'MWSAccessKey' => 'N0NSENSEXYZ',
				'MWSSecretKey' => 'iuasd/2jhaslk2j49lkaALksdJLsJLas+',
				'Region' => 'us',
				'WidgetScriptURL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
				'ReturnURL' => "https://example.org/index.php/Special:AmazonGateway?debug=true",
			) ),
		) );
	}

	public function tearDown() {
		TestingAmazonAdapter::$fakeGlobals = array();
		parent::tearDown();
	}

	protected function resetClient() {
		TestingAmazonAdapter::$client = new MockAmazonClient();
		foreach ( $this->normalResponses as $function => $response ) {
			TestingAmazonAdapter::$client->returns[$function][] = $response;
		}
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
		$rates = CurrencyRates::getCurrencyRates();
		$cadRate = $rates['CAD'];

		$expectedAmount = floor( $init['amount'] / $cadRate );

		TestingAmazonAdapter::$fakeGlobals = array(
			'FallbackCurrency' => 'USD',
			'NotifyOnConvert' => true,
		);

		$expectedNotification = wfMessage(
			'donate_interface-fallback-currency-notice',
			'USD'
		)->inLanguage( $language )->text();

		$that = $this; //needed for PHP pre-5.4
		$convertTest = function( $amountString ) use ( $expectedAmount, $that ) {
			$actual = explode( ' ', trim( $amountString ) );
			$that->assertTrue( is_numeric( $actual[0] ) );
			$difference = abs( floatval( $actual[0] ) - $expectedAmount );
			$that->assertTrue( $difference <= 1 );
			$that->assertEquals( 'USD', $actual[1] );
		};

		$assertNodes = array(
			'selected-amount' => array( 'innerhtml' => $convertTest ),
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$expectedNotification.*/"
			)
		);
		$this->verifyFormOutput( 'TestingAmazonGateway', $init, $assertNodes, false );
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

	/**
	 * Check that the adapter makes the correct calls for successful donations
	 */
	function testDoPaymentSuccess() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['fname'] );
		unset( $init['lname'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		// FIXME: PaymentResult->isFailed returns null for false
		$this->assertTrue( !( $result->isFailed() ), 'Result should not be failed when responses are good' );
		$this->assertEquals( 'Testy', $gateway->getData_Unstaged_Escaped( 'fname' ), 'Did not populate first name from Amazon data' );
		$this->assertEquals( 'Test', $gateway->getData_Unstaged_Escaped( 'lname' ), 'Did not populate last name from Amazon data' );
		$this->assertEquals( 'nobody@wikimedia.org', $gateway->getData_Unstaged_Escaped( 'email' ), 'Did not populate email from Amazon data' );
		$mockClient = TestingAmazonAdapter::$client;
		$setOrderReferenceDetailsArgs = $mockClient->calls['setOrderReferenceDetails'][0];
		$oid = str_replace( '.', '-', $gateway->getData_Unstaged_Escaped( 'order_id' ) );
		$this->assertEquals( $oid, $setOrderReferenceDetailsArgs['seller_order_reference_id'], 'Did not set order id on order reference' );
		$this->assertEquals( $init['amount'], $setOrderReferenceDetailsArgs['amount'], 'Did not set amount on order reference' );
		$this->assertEquals( $init['currency_code'], $setOrderReferenceDetailsArgs['currency_code'], 'Did not set currency code on order reference' );
	}

	/**
	 * Check that declined authorization is reflected in the result's errors
	 */
	function testDoPaymentDeclined() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['fname'] );
		unset( $init['lname'] );
		$mockClient = TestingAmazonAdapter::$client;
		$mockClient->returns['authorize'][0] = array (
			'AuthorizeResult' => array (
				'AuthorizationDetails' => array (
					'AuthorizationAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '10.00',
					),
					'CapturedAmount' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0',
					),
					'SoftDescriptor' => 'AMZ*Wikimedia Founda',
					'ExpirationTimestamp' => '2015-09-24T18:24:37.748Z',
					'AuthorizationStatus' => array (
						'LastUpdateTimestamp' => '2015-08-25T18:24:37.748Z',
						'State' => 'Declined',
						'ReasonCode' => 'InvalidPaymentMethod',
					),
					'AuthorizationFee' => array (
						'CurrencyCode' => 'USD',
						'Amount' => '0.00',
					),
					'CaptureNow' => 'true',
					'CreationTimestamp' => '2015-08-25T18:24:37.748Z',
					'AmazonAuthorizationId' => 'S01-2525143-9142520-A075353',
					'AuthorizationReferenceId' => '31243-0',
				),
			),
			'ResponseMetadata' => array (
				'RequestId' => '0122bebe-b8c3-4435-8169-8b3a92ba1800',
			),
			'ResponseStatus' => '200',
		);

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertTrue( $result->getRefresh(), 'Result should be a refresh on error' );
		$errors = $result->getErrors();
		$this->assertTrue( isset( $errors['InvalidPaymentMethod'] ), 'InvalidPaymentMethod error should be set' );
	}
}
