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

	public function setUp() {
		parent::setUp();

		TestingAmazonAdapter::$mockClient = new MockAmazonClient();

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
		) );
	}

	public function tearDown() {
		TestingAmazonAdapter::$fakeGlobals = array();
		parent::tearDown();
	}

	/**
	 * Integration test to verify that the Amazon gateway converts Canadian
	 * dollars before redirecting
	 *
	 * FIXME: Merge with currency fallback tests?
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

		$locale = $init['language'] . '_' . $init['country'];
		$expectedDisplayAmount = Amount::format( $expectedAmount, 'USD', $locale );

		$that = $this; //needed for PHP pre-5.4
		$convertTest = function( $amountString ) use ( $expectedDisplayAmount, $that ) {
			$that->assertEquals( $expectedDisplayAmount, trim( $amountString ), 'Displaying wrong amount' );
		};

		$assertNodes = array(
			'selected-amount' => array( 'innerhtml' => $convertTest ),
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$expectedNotification.*/"
			)
		);
		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false );
	}

	/**
	 * Integration test to verify that the Amazon gateway shows an error message when validation fails.
	 */
	function testShowFormOnError() {
		$init = $this->getDonorTestData();
		$init['OTT'] = 'SALT123456789';
		$init['amount'] = '-100.00';
		$init['ffname'] = 'amazon';
		$session = array( 'Donor' => $init );
		$errorMessage = wfMessage( 'donate_interface-error-msg-invalid-amount' )->text();
		$assertNodes = array(
			'mw-content-text' => array(
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			)
		);

		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false, $session );
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
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		$this->assertFalse( $result->isFailed(), 'Result should not be failed when responses are good' );
		$this->assertEquals( 'Testy', $gateway->getData_Unstaged_Escaped( 'first_name' ), 'Did not populate first name from Amazon data' );
		$this->assertEquals( 'Test', $gateway->getData_Unstaged_Escaped( 'last_name' ), 'Did not populate last name from Amazon data' );
		$this->assertEquals( 'nobody@wikimedia.org', $gateway->getData_Unstaged_Escaped( 'email' ), 'Did not populate email from Amazon data' );
		$mockClient = TestingAmazonAdapter::$mockClient;
		$setOrderReferenceDetailsArgs = $mockClient->calls['setOrderReferenceDetails'][0];
		$oid = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$this->assertEquals( $oid, $setOrderReferenceDetailsArgs['seller_order_id'], 'Did not set order id on order reference' );
		$this->assertEquals( $init['amount'], $setOrderReferenceDetailsArgs['amount'], 'Did not set amount on order reference' );
		$this->assertEquals( $init['currency_code'], $setOrderReferenceDetailsArgs['currency_code'], 'Did not set currency code on order reference' );
		$message = DonationQueue::instance()->pop( 'complete' );
		$this->assertNotNull( $message, 'Not sending a message to the complete queue' );
		$this->assertEquals( 'S01-0391295-0674065-C095112', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
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
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = TestingAmazonAdapter::$mockClient;
		$mockClient->returns['authorize'][] = 'InvalidPaymentMethod';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertTrue( $result->getRefresh(), 'Result should be a refresh on error' );
		$errors = $result->getErrors();
		$this->assertEquals(
			'InvalidPaymentMethod',
			$errors[0]->getErrorCode(),
			'InvalidPaymentMethod error should be set'
		);
	}

	/**
	 * This apparently indicates a shady enough txn that we should turn them away
	 */
	function testFailOnAmazonRejected() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = TestingAmazonAdapter::$mockClient;
		$mockClient->returns['authorize'][] = 'AmazonRejected';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed(), 'Result should be failed' );
		// Could assert something about errors after rebasing onto master
		// $errors = $result->getErrors();
		// $this->assertTrue( isset( $errors['AmazonRejected'] ), 'AmazonRejected error should be set' );
	}

	/**
	 * When the transaction times out, just gotta fail it till we work out an
	 * asynchronous authorization flow
	 */
	function testTransactionTimedOut() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = TestingAmazonAdapter::$mockClient;
		$mockClient->returns['authorize'][] = 'TransactionTimedOut';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed(), 'Result should be failed' );
	}

	/**
	 * When the SDK throws an exceptions, we should handle it.
	 */
	function testClientException() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = TestingAmazonAdapter::$mockClient;
		$mockClient->exceptions['authorize'][] = new Exception( 'Test' );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$errors = $result->getErrors();

		$this->assertEquals(
			ResponseCodes::NO_RESPONSE,
			$errors[0]->getErrorCode(),
			'NO_RESPONSE error should be set'
		);
	}

	/**
	 * Check the adapter makes the correct calls for successful monthly donations
	 */
	function testDoRecurringPaymentSuccess() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['recurring'] = '1';
		$init['subscr_id'] = 'C01-9650293-7351908';
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();
		// FIXME: PaymentResult->isFailed returns null for false
		$this->assertTrue( !( $result->isFailed() ), 'Result should not be failed when responses are good' );
		$this->assertEquals( 'Testy', $gateway->getData_Unstaged_Escaped( 'first_name' ), 'Did not populate first name from Amazon data' );
		$this->assertEquals( 'Test', $gateway->getData_Unstaged_Escaped( 'last_name' ), 'Did not populate last name from Amazon data' );
		$this->assertEquals( 'nobody@wikimedia.org', $gateway->getData_Unstaged_Escaped( 'email' ), 'Did not populate email from Amazon data' );
		$mockClient = TestingAmazonAdapter::$mockClient;
		$setBillingAgreementDetailsArgs = $mockClient->calls['setBillingAgreementDetails'][0];
		$oid = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$this->assertEquals( $oid, $setBillingAgreementDetailsArgs['seller_billing_agreement_id'], 'Did not set order id on billing agreement' );
		$authorizeOnBillingAgreementDetailsArgs = $mockClient->calls['authorizeOnBillingAgreement'][0];
		$this->assertEquals( $init['amount'], $authorizeOnBillingAgreementDetailsArgs['authorization_amount'], 'Did not authorize correct amount' );
		$this->assertEquals( $init['currency_code'], $authorizeOnBillingAgreementDetailsArgs['currency_code'], 'Did not authorize correct currency code' );
		$message = DonationQueue::instance()->pop( 'complete' );
		$this->assertNotNull( $message, 'Not sending a message to the complete queue' );
		$this->assertEquals( 'S01-5318994-6362993-C004044', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
		$this->assertEquals( $init['subscr_id'], $message['subscr_id'], 'Queue message has wrong subscription ID' );
	}
}
