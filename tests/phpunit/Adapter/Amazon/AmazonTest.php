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

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;
use SmashPig\PaymentProviders\Amazon\Tests\AmazonTestConfiguration;
use SmashPig\Tests\TestingContext;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Amazon
 */
class DonationInterface_Adapter_Amazon_Test extends DonationInterfaceTestCase {

	protected $testAdapterClass = AmazonAdapter::class;
	/**
	 * @var \SmashPig\PaymentProviders\Amazon\Tests\AmazonTestConfiguration
	 */
	protected $providerConfig;

	public static function setUpAmazonTestingContext() {
		$config = AmazonTestConfiguration::instance(
			self::$smashPigGlobalConfig
		);
		$config->override( [
			'payments-client' => [
				'constructor-parameters' => [
					0 => [
						'response-directory' =>
							__DIR__ . '/../../includes/Responses/amazon'
					]
				]
			]
		] );
		TestingContext::get()->providerConfigurationOverride = $config;
		return $config;
	}

	public function setUp(): void {
		parent::setUp();
		$this->providerConfig = self::setUpAmazonTestingContext();

		$this->setMwGlobals( [
			'wgAmazonGatewayEnabled' => true,
		] );
	}

	/**
	 * Integration test to verify that the Amazon gateway converts Canadian
	 * dollars before redirecting
	 *
	 * FIXME: Merge with currency fallback tests?
	 *
	 * @dataProvider canadaLanguageProvider
	 * @param string $language language code to test
	 */
	public function testCanadianDollarConversion( $language ) {
		$init = $this->getDonorTestData( 'CA' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'amazon';
		$init['language'] = $language;
		$rates = CurrencyRates::getCurrencyRates();
		$cadRate = $rates['CAD'];

		$expectedAmount = floor( $init['amount'] / $cadRate );
		$this->setMwGlobals( [
			'wgAmazonGatewayFallbackCurrency' => 'USD',
			'wgAmazonGatewayNotifyOnConvert' => true,
		] );

		$expectedNotification = wfMessage(
			'donate_interface-fallback-currency-notice',
			'USD'
		)->inLanguage( $language )->text();

		$locale = $init['language'] . '_' . $init['country'];
		$expectedDisplayAmount = Amount::format( $expectedAmount, 'USD', $locale );

		$convertTest = function ( $amountString ) use ( $expectedDisplayAmount ) {
			$this->assertEquals( $expectedDisplayAmount, trim( $amountString ), 'Displaying wrong amount' );
		};

		$assertNodes = [
			'selected-amount' => [ 'innerhtml' => $convertTest ],
			'mw-content-text' => [
				'innerhtmlmatches' => "/.*$expectedNotification.*/"
			]
		];
		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false );
	}

	/**
	 * Integration test to verify that the Amazon gateway shows an error message when validation fails.
	 */
	public function testShowFormOnError() {
		$init = $this->getDonorTestData();
		$init['OTT'] = 'SALT123456789';
		$init['amount'] = '-100.00';
		$session = [ 'Donor' => $init ];
		$errorMessage = wfMessage( 'donate_interface-error-msg-invalid-amount' )->text();
		$assertNodes = [
			'mw-content-text' => [
				'innerhtmlmatches' => "/.*$errorMessage.*/"
			]
		];

		$this->verifyFormOutput( 'AmazonGateway', $init, $assertNodes, false, $session );
	}

	/**
	 * Check that the adapter makes the correct calls for successful donations
	 */
	public function testDoPaymentSuccess() {
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
		$this->assertEquals( 'Testy Test', $gateway->getData_Unstaged_Escaped( 'full_name' ), 'Did not populate full name from Amazon data' );
		$this->assertEquals( 'nobody@wikimedia.org', $gateway->getData_Unstaged_Escaped( 'email' ), 'Did not populate email from Amazon data' );
		$mockClient = $this->providerConfig->object( 'payments-client' );
		$setOrderReferenceDetailsArgs = $mockClient->calls['setOrderReferenceDetails'][0];
		$oid = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$this->assertEquals( $oid, $setOrderReferenceDetailsArgs['seller_order_id'], 'Did not set order id on order reference' );
		$this->assertEquals( $init['amount'], $setOrderReferenceDetailsArgs['amount'], 'Did not set amount on order reference' );

		$this->assertEquals( $init['currency'], $setOrderReferenceDetailsArgs['currency_code'], 'Did not set currency code on order reference' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		$this->assertEquals( 'S01-0391295-0674065-C095112', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
		$this->assertEquals( 'Testy Test', $message['full_name'] );
	}

	/**
	 * Check that declined authorization is reflected in the result's errors
	 */
	public function testDoPaymentDeclined() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = $this->providerConfig->object( 'payments-client' );
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
	public function testFailOnAmazonRejected() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = $this->providerConfig->object( 'payments-client' );
		$mockClient->returns['authorize'][] = 'AmazonRejected';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertTrue( $result->isFailed(), 'Result should be failed' );
		// Could assert something about errors after rebasing onto master
		// $errors = $result->getErrors();
		// $this->assertTrue( isset( $errors['AmazonRejected'] ), 'AmazonRejected error should be set' );
	}

	/**
	 * When the transaction times out, show the thank you page and call the
	 * payment 'pending'.
	 */
	public function testTransactionTimedOut() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = $this->providerConfig->object( 'payments-client' );
		$mockClient->returns['authorize'][] = 'TransactionTimedOut';

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$this->assertEquals( FinalStatus::PENDING, $gateway->getFinalStatus() );
		$this->assertFalse( $result->isFailed() );
		$donationMessage = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donationMessage );
		$paymentInitMessages = QueueWrapper::getQueue( 'payments-init' )->pop();
		$this->assertEquals( FinalStatus::PENDING, $paymentInitMessages['payments_final_status'] );
		$pendingMessage = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $pendingMessage );
		$this->assertEquals( 'Testy Test', $pendingMessage['full_name'] );
	}

	/**
	 * When the SDK throws an exceptions, we should handle it.
	 */
	public function testClientException() {
		$init = $this->getDonorTestData( 'US' );
		$init['amount'] = '10.00';
		$init['order_reference_id'] = mt_rand( 0, 10000000 ); // provided by client-side widget IRL
		// We don't get any profile data up front
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );

		$mockClient = $this->providerConfig->object( 'payments-client' );
		$mockClient->exceptions['authorize'][] = new Exception( 'Test' );

		$gateway = $this->getFreshGatewayObject( $init );
		$result = $gateway->doPayment();

		$errors = $result->getErrors();

		$this->assertEquals(
			ErrorCode::NO_RESPONSE,
			$errors[0]->getErrorCode(),
			'NO_RESPONSE error should be set'
		);
	}

	/**
	 * Check the adapter makes the correct calls for successful monthly donations
	 */
	public function testDoRecurringPaymentSuccess() {
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
		$this->assertEquals( 'Testy Test', $gateway->getData_Unstaged_Escaped( 'full_name' ), 'Did not populate full name from Amazon data' );
		$this->assertEquals( 'nobody@wikimedia.org', $gateway->getData_Unstaged_Escaped( 'email' ), 'Did not populate email from Amazon data' );
		$mockClient = $this->providerConfig->object( 'payments-client' );
		$setBillingAgreementDetailsArgs = $mockClient->calls['setBillingAgreementDetails'][0];
		$oid = $gateway->getData_Unstaged_Escaped( 'order_id' );
		$this->assertEquals( $oid, $setBillingAgreementDetailsArgs['seller_billing_agreement_id'], 'Did not set order id on billing agreement' );
		$authorizeOnBillingAgreementDetailsArgs = $mockClient->calls['authorizeOnBillingAgreement'][0];
		$this->assertEquals( $init['amount'], $authorizeOnBillingAgreementDetailsArgs['authorization_amount'], 'Did not authorize correct amount' );
		$this->assertEquals( $init['currency'], $authorizeOnBillingAgreementDetailsArgs['currency_code'], 'Did not authorize correct currency code' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		$this->assertEquals( 'S01-5318994-6362993-C004044', $message['gateway_txn_id'], 'Queue message has wrong txn ID' );
		$this->assertEquals( $init['subscr_id'], $message['subscr_id'], 'Queue message has wrong subscription ID' );
		$this->assertEquals( 'Testy Test', $message['full_name'] );
	}
}
