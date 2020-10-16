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

use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 */
class DonationInterface_Adapter_PayPal_Express_Test extends DonationInterfaceTestCase {

	protected $testAdapterClass = TestingPaypalExpressAdapter::class;

	public function setUp(): void {
		parent::setUp();
		TestingContext::get()->providerConfigurationOverride = TestingProviderConfiguration::createForProvider(
			'paypal', self::$smashPigGlobalConfig
		);
		$this->setMwGlobals( [
			'wgDonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'wgPaypalExpressGatewayEnabled' => true,
			'wgDonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
		] );
	}

	public function testPaymentSetup() {
		$init = [
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'contribution_tracking_id' => strval( mt_rand() ),
			'language' => 'fr',
		];
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'OK' );
		$result = $gateway->doPayment();
		$gateway->logPending(); // GatewayPage or the API calls this for redirects
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit',
			$result->getRedirect(),
			'Wrong redirect for PayPal EC payment setup'
		);
		$this->assertEquals( 1, count( $gateway->curled ), 'Should have made 1 API call' );
		$apiCall = $gateway->curled[0];
		$parsed = [];
		parse_str( $apiCall, $parsed );
		$actualReturn = $parsed['RETURNURL'];
		$parsedReturn = [];
		parse_str( parse_url( $actualReturn, PHP_URL_QUERY ), $parsedReturn );
		$this->assertEquals(
			[
				'title' => 'Special:PaypalExpressGatewayResult',
				'order_id' => $init['contribution_tracking_id'] . '.1',
				'wmf_token' => $gateway->token_getSaltedSessionToken()
			],
			$parsedReturn
		);
		unset( $parsed['RETURNURL'] );
		$expected = [
			'USER' => 'phpunittesting@wikimedia.org',
			'PWD' => '9876543210',
			'VERSION' => '204',
			'METHOD' => 'SetExpressCheckout',
			'CANCELURL' => 'https://example.com/tryAgain.php/fr',
			'REQCONFIRMSHIPPING' => '0',
			'NOSHIPPING' => '1',
			'LOCALECODE' => 'fr_US',
			'L_PAYMENTREQUEST_0_AMT0' => '1.55',
			'L_PAYMENTREQUEST_0_DESC0' => 'Donation to the Wikimedia Foundation',
			'PAYMENTREQUEST_0_AMT' => '1.55',
			'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
			'PAYMENTREQUEST_0_CUSTOM' => $init['contribution_tracking_id'] . '.1',
			'PAYMENTREQUEST_0_DESC' => 'Donation to the Wikimedia Foundation',
			'PAYMENTREQUEST_0_INVNUM' => $init['contribution_tracking_id'] . '.1',
			'PAYMENTREQUEST_0_ITEMAMT' => '1.55',
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			'SIGNATURE' => 'ABCDEFGHIJKLMNOPQRSTUV-ZXCVBNMLKJHGFDSAPOIUYTREWQ',
			'SOLUTIONTYPE' => 'Mark',
		];
		$this->assertEquals(
			$expected, $parsed
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotEmpty( $message, 'Missing pending message' );
		self::unsetVariableFields( $message );
		$expected = [
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => null,
			'language' => 'fr',
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'order_id' => $init['contribution_tracking_id'] . '.1',
			'utm_source' => 'CD1234_FR..paypal',
			'currency' => 'USD',
			'email' => '',
			'gross' => '1.55',
			'recurring' => '',
			'response' => false,
			'utm_medium' => 'sitenotice',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'gateway_session_id' => 'EC-8US12345X1234567U',
			'user_ip' => '127.0.0.1',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		];
		$this->assertEquals(
			$expected,
			$message,
			'PayPal EC setup sending wrong pending message'
		);
	}

	public function testPaymentSetupRecurring() {
		$init = [
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'recurring' => '1',
			'contribution_tracking_id' => strval( mt_rand() ),
			'language' => 'fr',
		];
		$gateway = $this->getFreshGatewayObject( $init );
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( 'OK' );
		$result = $gateway->doPayment();
		$gateway->logPending(); // GatewayPage or the API calls this for redirects
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit',
			$result->getRedirect(),
			'Wrong redirect for PayPal EC payment setup'
		);
		$this->assertEquals( 1, count( $gateway->curled ), 'Should have made 1 API call' );
		$apiCall = $gateway->curled[0];
		$parsed = [];
		parse_str( $apiCall, $parsed );
		$actualReturn = $parsed['RETURNURL'];
		$parsedReturn = [];
		parse_str( parse_url( $actualReturn, PHP_URL_QUERY ), $parsedReturn );
		$this->assertEquals(
			[
				'title' => 'Special:PaypalExpressGatewayResult',
				'order_id' => $init['contribution_tracking_id'] . '.1',
				'recurring' => '1',
				'wmf_token' => $gateway->token_getSaltedSessionToken()
			],
			$parsedReturn
		);
		unset( $parsed['RETURNURL'] );
		$expected = [
			'USER' => 'phpunittesting@wikimedia.org',
			'PWD' => '9876543210',
			'VERSION' => '204',
			'METHOD' => 'SetExpressCheckout',
			'CANCELURL' => 'https://example.com/tryAgain.php/fr',
			'REQCONFIRMSHIPPING' => '0',
			'NOSHIPPING' => '1',
			'LOCALECODE' => 'fr_US',
			'L_PAYMENTREQUEST_0_AMT0' => '1.55',
			'PAYMENTREQUEST_0_AMT' => '1.55',
			'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
			'L_BILLINGTYPE0' => 'RecurringPayments',
			'L_BILLINGAGREEMENTDESCRIPTION0' => 'Monthly donation to the Wikimedia Foundation',
			'L_BILLINGAGREEMENTCUSTOM0' => $init['contribution_tracking_id'] . '.1',
			'L_PAYMENTREQUEST_0_NAME0' => 'Monthly donation to the Wikimedia Foundation',
			'L_PAYMENTREQUEST_0_QTY0' => '1',
			'MAXAMT' => '1.55',
			'PAYMENTREQUEST_0_ITEMAMT' => '1.55',
			'SIGNATURE' => 'ABCDEFGHIJKLMNOPQRSTUV-ZXCVBNMLKJHGFDSAPOIUYTREWQ',
			'SOLUTIONTYPE' => 'Mark',
		];
		$this->assertEquals(
			$expected, $parsed
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotEmpty( $message, 'Missing pending message' );
		self::unsetVariableFields( $message );
		$expected = [
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => null,
			'language' => 'fr',
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'order_id' => $init['contribution_tracking_id'] . '.1',
			'utm_source' => 'CD1234_FR..rpaypal',
			'currency' => 'USD',
			'email' => '',
			'gross' => '1.55',
			'recurring' => '1',
			'response' => false,
			'utm_medium' => 'sitenotice',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'gateway_session_id' => 'EC-8US12345X1234567U',
			'user_ip' => '127.0.0.1',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		];
		$this->assertEquals(
			$expected,
			$message,
			'PayPal EC setup sending wrong pending message'
		);
	}

	/**
	 * Check that the adapter makes the correct calls for successful donations
	 * and sends a good queue message.
	 */
	public function testProcessDonorReturn() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init );
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( 'OK' );
		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD',
		] );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		self::unsetVariableFields( $message );
		$expected = [
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => '5EJ123456T987654S',
			'gateway_session_id' => 'EC-4V987654XA123456V',
			'language' => 'en',
			'order_id' => $init['contribution_tracking_id'] . '.1',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'response' => false,
			'user_ip' => '127.0.0.1',
			'utm_source' => '..paypal',
			'city' => 'San Francisco',
			'currency' => 'USD',
			'email' => 'donor@generous.net',
			'first_name' => 'Fezziwig',
			'gross' => '1.55',
			'last_name' => 'Fowl',
			'recurring' => '',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		];
		$this->assertEquals( $expected, $message );

		$this->assertNull(
			QueueWrapper::getQueue( 'donations' )->pop(),
			'Sending extra messages to donations queue!'
		);
	}

	public function testProcessDonorReturnRecurring() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['recurring'] = '1';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( 'Recurring-OK' );
		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD'
		] );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Recurring should not send a message to the donations queue' );
	}

	/**
	 * Check that we send the donor back to paypal to try a different source
	 */
	public function testProcessDonorReturnPaymentRetry() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '10486' );
		$result = $gateway->processDonorReturn( [
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		] );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
		$this->assertFalse( $result->isFailed() );
		$redirect = $result->getRedirect();
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-2D123456D9876543U&useraction=commit',
			$redirect
		);
	}

	/**
	 * Check that we don't send donors to the fail page for warnings
	 */
	public function testProcessDonorReturnWarning() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( [
			'OK', // For GetExpressCheckoutDetails
			'11607' // For DoExpressCheckoutPayment
		] );
		$result = $gateway->processDonorReturn( [
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		] );

		$this->assertFalse( $result->isFailed() );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the donations queue' );
		self::unsetVariableFields( $message );
		$expected = [
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => '33N12345BB123456D',
			'gateway_session_id' => 'EC-4V987654XA123456V',
			'language' => 'en',
			'order_id' => $init['contribution_tracking_id'] . '.1',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'response' => false,
			'user_ip' => '127.0.0.1',
			'utm_source' => '..paypal',
			'city' => 'San Francisco',
			'currency' => 'USD',
			'email' => 'donor@generous.net',
			'first_name' => 'Fezziwig',
			'gross' => '1.55',
			'last_name' => 'Fowl',
			'recurring' => '',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		];
		$this->assertEquals( $expected, $message );

		$this->assertNull(
			QueueWrapper::getQueue( 'donations' )->pop(),
			'Sending extra messages to donations queue!'
		);
		$matches = self::getLogMatches(
			LogLevel::WARNING, '/Transaction succeeded with warning.*/'
		);
		$this->assertNotEmpty( $matches );
	}

	public function testProcessDonorReturnRecurringRetry() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['recurring'] = '1';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway::setDummyGatewayResponseCode( '10486' );
		$result = $gateway->processDonorReturn( [
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		] );

		$this->assertNull(
			QueueWrapper::getQueue( 'donations' )->pop(),
			'Sending a spurious message to the donations queue!'
		);
		$this->assertFalse( $result->isFailed() );
		$redirect = $result->getRedirect();
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-2D123456D9876543U&useraction=commit',
			$redirect
		);
	}

	/**
	 * Check that it does not call doPayment when status is PaymentNotInitiated
	 */
	public function testProcessDonorReturnPaymentActionCompleted() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$options = [ 'batch_mode' => true ];
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init, $options );
		$gateway::setDummyGatewayResponseCode( 'Complete' );
		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD',
		] );

		$this->assertEquals( FinalStatus::COMPLETE, $gateway->getFinalStatus(), 'Should have Final Status Complete' );
		$this->assertEquals( 1, count( $gateway->curled ), 'Should only call curl once' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
	}

	/**
	 * Check that it does not call doPayment when the token has timed out
	 */
	public function testProcessDonorReturnTokenTimeout() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$options = [ 'batch_mode' => true ];
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init, $options );
		$gateway::setDummyGatewayResponseCode( 'Timeout' );
		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD',
		] );

		$this->assertEquals( FinalStatus::TIMEOUT, $gateway->getFinalStatus(), 'Should have Final Status Timeout' );
		$this->assertEquals( 1, count( $gateway->curled ), 'Should only call curl once' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
	}

	/**
	 * The result switcher should redirect the donor to the thank you page and mark the token as
	 * processed.
	 */
	public function testResultSwitcher() {
		$init = $this->getDonorTestData( 'US' );
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( 'OK' );
		$init['contribution_tracking_id'] = '45931210';
		$init['gateway_session_id'] = mt_rand();
		$init['language'] = 'pt';
		$session = [ 'Donor' => $init ];

		$request = [
			'token' => $init['gateway_session_id'],
			'PayerID' => 'ASdASDAS',
			'language' => $init['language'] // FIXME: mashing up request vars and other stuff in verifyFormOutput
		];
		$assertNodes = [
			'headers' => [
				'Location' => function ( $location ) use ( $init ) {
					// Do this after the real processing to avoid side effects
					$gateway = $this->getFreshGatewayObject( $init );
					$url = ResultPages::getThankYouPage( $gateway );
					$this->assertEquals( $url, $location );
				}
			]
		];

		$this->verifyFormOutput( 'PaypalExpressGatewayResult', $request, $assertNodes, false, $session );
		$key = 'processed_request-' . $request['token'];
		$processed = wfGetMainCache()->get( $key );
		$this->assertTrue( $processed );

		// Make sure we logged the expected cURL attempts
		$messages = self::getLogMatches( 'info', '/Preparing to send GetExpressCheckoutDetails transaction to Paypal Express Checkout/' );
		$this->assertNotEmpty( $messages );
		$messages = self::getLogMatches( 'info', '/Preparing to send DoExpressCheckoutPayment transaction to Paypal Express Checkout/' );
		$this->assertNotEmpty( $messages );
	}

	/**
	 * The result switcher should redirect the donor to the thank you page without
	 * re-processing the donation.
	 */
	public function testResultSwitcherRepeat() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['gateway_session_id'] = mt_rand();
		$init['language'] = 'pt';
		$session = [
			'Donor' => $init
		];

		$key = 'processed_request-' . $init['gateway_session_id'];
		wfGetMainCache()->add( $key, true, 100 );

		$request = [
			'token' => $init['gateway_session_id'],
			'PayerID' => 'ASdASDAS',
			'language' => $init['language'] // FIXME: mashing up request vars and other stuff in verifyFormOutput
		];
		$assertNodes = [
			'headers' => [
				'Location' => function ( $location ) use ( $init ) {
					// Do this after the real processing to avoid side effects
					$gateway = $this->getFreshGatewayObject( $init );
					$url = ResultPages::getThankYouPage( $gateway );
					$this->assertEquals( $url, $location );
				}
			]
		];

		$this->verifyFormOutput( 'PaypalExpressGatewayResult', $request, $assertNodes, false, $session );

		// We should not have logged any cURL attempts
		$messages = self::getLogMatches( 'info', '/Preparing to send .*/' );
		$this->assertEmpty( $messages );
	}

	public function testShouldRectifyOrphan() {
		$message = $this->createOrphan( [ 'gateway' => 'paypal', 'payment_method' => 'paypal' ] );
		$this->gatewayAdapter = $this->getFreshGatewayObject( $message );
		$result = $this->gatewayAdapter->shouldRectifyOrphan();
		$this->assertEquals( $result, true, 'shouldRectifyOrphan returning false.' );
	}

	/**
	 * We should take the country from the donor info response, and transform
	 * it into a real code if it's a PayPal bogon.
	 */
	public function testUnstageCountry() {
		$init = $this->getDonorTestData( 'US' );
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( [ 'C2', 'OK' ] );
		$init['contribution_tracking_id'] = '45931210';
		$init['gateway_session_id'] = mt_rand();
		$init['language'] = 'pt';
		$session = [ 'Donor' => $init ];

		$request = [
			'token' => $init['gateway_session_id'],
			'PayerID' => 'ASdASDAS',
			'language' => $init['language'] // FIXME: mashing up request vars and other stuff in verifyFormOutput
		];
		$this->setUpRequest( $request, $session );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->processDonorReturn( $request );
		$savedCountry = $gateway->getData_Unstaged_Escaped( 'country' );
		$this->assertEquals( 'CN', $savedCountry );
	}
}
