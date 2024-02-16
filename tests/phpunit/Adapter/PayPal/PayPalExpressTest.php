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
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 */
class DonationInterface_Adapter_PayPal_Express_Test extends DonationInterfaceTestCase {

	/**
	 * @var string
	 */
	protected $testAdapterClass = TestingPaypalExpressAdapter::class;

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $provider;

	protected function setUp(): void {
		parent::setUp();
		$providerConfig = TestingProviderConfiguration::createForProvider(
			'paypal', self::$smashPigGlobalConfig
		);
		$this->provider = $this->createMock( PaymentProvider::class );
		$providerConfig->overrideObjectInstance( 'payment-provider/paypal', $this->provider );
		TestingContext::get()->providerConfigurationOverride = $providerConfig;

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
		$redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) use ( $gateway, $init ) {
				$parsedReturn = [];
				parse_str( parse_url( $params['return_url'], PHP_URL_QUERY ), $parsedReturn );
				$this->assertEquals(
					[
						'title' => 'Special:PaypalExpressGatewayResult',
						'order_id' => $init['contribution_tracking_id'] . '.1',
						'wmf_token' => $gateway->token_getSaltedSessionToken()
					],
					$parsedReturn
				);
				unset( $params['return_url'] );
				$this->assertEquals( [
					'cancel_url' => 'https://example.com/tryAgain.php/fr',
					'language' => 'fr_US',
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
					'order_id' => $init['contribution_tracking_id'] . '.1',
					'amount' => '1.55',
					'currency' => 'USD',
				], $params );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setRawResponse(
						'TOKEN=EC%2d8US12345X1234567U&TIMESTAMP=2017%2d05%2d18T14%3a53%3a29Z&CORRELATIONID=' .
						'6d987654a7aed&ACK=Success&VERSION=204&BUILD=33490839'
					)
					->setSuccessful( true )
					->setPaymentSession( 'EC-8US12345X1234567U' )
					->setRedirectUrl( $redirect )
			);
		$result = $gateway->doPayment();
		$gateway->logPending(); // GatewayPage or the API calls this for redirects
		$this->assertEquals(
			$redirect,
			$result->getRedirect(),
			'Wrong redirect for PayPal EC payment setup'
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
		$redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) use ( $gateway, $init ) {
				$parsedReturn = [];
				parse_str( parse_url( $params['return_url'], PHP_URL_QUERY ), $parsedReturn );
				$this->assertEquals(
					[
						'title' => 'Special:PaypalExpressGatewayResult',
						'order_id' => $init['contribution_tracking_id'] . '.1',
						'wmf_token' => $gateway->token_getSaltedSessionToken(),
						'recurring' => 1
					],
					$parsedReturn
				);
				unset( $params['return_url'] );
				$this->assertEquals( [
					'cancel_url' => 'https://example.com/tryAgain.php/fr',
					'language' => 'fr_US',
					'description' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
					'order_id' => $init['contribution_tracking_id'] . '.1',
					'amount' => '1.55',
					'currency' => 'USD',
					'recurring' => 1,
				], $params );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setRawResponse(
						'TOKEN=EC%2d8US12345X1234567U&TIMESTAMP=2017%2d05%2d18T14%3a53%3a29Z&CORRELATIONID=' .
						'6d987654a7aed&ACK=Success&VERSION=204&BUILD=33490839'
					)
					->setSuccessful( true )
					->setPaymentSession( 'EC-8US12345X1234567U' )
					->setRedirectUrl( $redirect )
			);
		$result = $gateway->doPayment();
		$gateway->logPending(); // GatewayPage or the API calls this for redirects
		$this->assertEquals(
			$redirect,
			$result->getRedirect(),
			'Wrong redirect for PayPal EC payment setup'
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
			'fee' => 0,
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
			'gross' => '4.55',
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
			'fee' => 0,
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
			'gross' => '4.55',
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
		$this->assertCount( 1, $gateway->curled, 'Should only call curl once' );
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
		$this->assertCount( 1, $gateway->curled, 'Should only call curl once' );
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
		$processed = ObjectCache::getLocalClusterInstance()->get( $key );
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
		ObjectCache::getLocalClusterInstance()->add( $key, true, 100 );

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
		$this->assertSame( [], $messages );
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
