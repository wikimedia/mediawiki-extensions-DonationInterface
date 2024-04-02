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
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\Address;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;
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

	protected function getGoodPaymentDetailResponse(): PaymentDetailResponse {
		return ( new PaymentDetailResponse() )
			->setSuccessful( true )
			->setRawResponse(
				'TOKEN=EC%2d4V987654XA123456V&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=' .
				'PaymentActionNotInitiated&TIMESTAMP=2017%2d02%2d01T20%3a07%3a14Z&CORRELATIONID=' .
				'd70c9a334455e&ACK=Success&VERSION=204&BUILD=28806785&EMAIL=donor%40generous%2enet&PAYERID=' .
				'8R297FE87CD8S&PAYERSTATUS=unverified&FIRSTNAME=Fezziwig&LASTNAME=Fowl&COUNTRYCODE=US&' .
				'BILLINGNAME=Fezziwig%20Fowl&STREET=123%20Notta%20Way&CITY=Whoville&STATE=OR&ZIP=97211&' .
				'COUNTRY=US&COUNTRYNAME=United%20States&ADDRESSID=PayPal&ADDRESSSTATUS=Confirmed&' .
				'CURRENCYCODE=USD&AMT=4%2e55&ITEMAMT=4%2e55&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=' .
				'45931210&DESC=Donation%20to%20the%20Wikimedia%20Foundation&INVNUM=45931210%2e1&NOTIFYURL=' .
				'http%3a%2f%2ffundraising%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&INSURANCEAMT=0&' .
				'SHIPDISCAMT=0&INSURANCEOPTIONOFFERED=false&L_QTY0=1&L_TAXAMT0=0&L_AMT0=4%2e55&L_DESC0=' .
				'Donation%20to%20the%20Wikimedia%20Foundation&PAYMENTREQUEST_0_CURRENCYCODE=USD&' .
				'PAYMENTREQUEST_0_AMT=4%2e55&PAYMENTREQUEST_0_ITEMAMT=4%2e55&PAYMENTREQUEST_0_SHIPPINGAMT=0' .
				'&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT=0&PAYMENTREQUEST_0_CUSTOM=45931210&' .
				'PAYMENTREQUEST_0_DESC=Donation%20to%20the%20Wikimedia%20Foundation&PAYMENTREQUEST_0_INVNUM=' .
				'45931210%2e1&PAYMENTREQUEST_0_NOTIFYURL=http%3a%2f%2ffundraising%2ewikimedia%2eorg%2f' .
				'IPNListener_Standalone%2ephp&PAYMENTREQUEST_0_INSURANCEAMT=0&PAYMENTREQUEST_0_SHIPDISCAMT=' .
				'0&PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=receiver%40wikimedia%2eorg&' .
				'PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&' .
				'L_PAYMENTREQUEST_0_QTY0=1&L_PAYMENTREQUEST_0_TAXAMT0=0&L_PAYMENTREQUEST_0_AMT0=4%2e55&' .
				'L_PAYMENTREQUEST_0_DESC0=Donation%20to%20the%20Wikimedia%20Foundation&' .
				'PAYMENTREQUESTINFO_0_ERRORCODE=0'
			)
			->setProcessorContactID( '8R297FE87CD8S' )
			->setStatus( FinalStatus::PENDING_POKE )
			->setDonorDetails(
				( new DonorDetails() )
					->setEmail( 'donor@generous.net' )
					->setFirstName( 'Fezziwig' )
					->setLastName( 'Fowl' )
			);
	}

	protected function getGoodApprovePaymentResponse() {
		return ( new ApprovePaymentResponse() )
			->setRawResponse(
				'TOKEN=EC%2d4V987654XA123456V&SUCCESSPAGEREDIRECTREQUESTED=false&TIMESTAMP=' .
				'2017%2d01%2d30T22%3a33%3a43Z&CORRELATIONID=434c98b240b6&ACK=Success&VERSION=204&BUILD=' .
				'28806785&INSURANCEOPTIONSELECTED=false&SHIPPINGOPTIONISDEFAULT=false&' .
				'PAYMENTINFO_0_TRANSACTIONID=5EJ123456T987654S&PAYMENTINFO_0_TRANSACTIONTYPE=expresscheckout&' .
				'PAYMENTINFO_0_PAYMENTTYPE=instant&PAYMENTINFO_0_ORDERTIME=2017%2d01%2d30T22%3a33%3a42Z&' .
				'PAYMENTINFO_0_AMT=1%2e55&PAYMENTINFO_0_FEEAMT=43&PAYMENTINFO_0_TAXAMT=0&' .
				'PAYMENTINFO_0_CURRENCYCODE=USD&PAYMENTINFO_0_PAYMENTSTATUS=Completed&' .
				'PAYMENTINFO_0_PENDINGREASON=None&PAYMENTINFO_0_REASONCODE=None&' .
				'PAYMENTINFO_0_PROTECTIONELIGIBILITY=Ineligible&PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE=None&' .
				'PAYMENTINFO_0_SECUREMERCHANTACCOUNTID=EZ123ABCDEFG1&PAYMENTINFO_0_ERRORCODE=0&' .
				'PAYMENTINFO_0_ACK=Success'
			)
			->setSuccessful( true )
			->setStatus( FinalStatus::COMPLETE )
			->setGatewayTxnId( '5EJ123456T987654S' );
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
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-4V987654XA123456V'
			] )
			->willReturn( $this->getGoodPaymentDetailResponse() );
		$this->provider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => '4.55',
				'currency' => 'USD',
				'gateway_session_id' => 'EC-4V987654XA123456V',
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'order_id' => '45931210.1',
				'processor_contact_id' => '8R297FE87CD8S',
			] )
			->willReturn(
				$this->getGoodApprovePaymentResponse()
			);

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
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-4V987654XA123456V'
			] )
			->willReturn(
				( new PaymentDetailResponse() )
					->setSuccessful( true )
					->setRawResponse(
						'TOKEN=EC%2d4V987654XA123456V&BILLINGAGREEMENTACCEPTEDSTATUS=1&CHECKOUTSTATUS=' .
						'PaymentActionNotInitiated&TIMESTAMP=2017%2d04%2d18T16%3a43%3a34Z&CORRELATIONID=' .
						'38adebcd6cbf8&ACK=Success&VERSION=204&BUILD=32574509&EMAIL=donor%40generous%2enet&' .
						'PAYERID=8R297FE87CD8S&PAYERSTATUS=unverified&FIRSTNAME=Fezziwig&LASTNAME=Fowl&COUNTRYCODE=US' .
						'&BILLINGNAME=Fezziwig%20Fowl&STREET=123%20Notta%20Way&CITY=Whoville&STATE=OR&ZIP=97211&' .
						'COUNTRY=US&COUNTRYNAME=United%20States&ADDRESSID=PayPal&ADDRESSSTATUS=Confirmed&' .
						'CURRENCYCODE=USD&AMT=1%2e55&ITEMAMT=1%2e55&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&' .
						'NOTIFYURL=http%3a%2f%2ffundraising%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&' .
						'INSURANCEAMT=0&SHIPDISCAMT=0&INSURANCEOPTIONOFFERED=false&L_NAME0=Monthly%20donation%20to%20' .
						'the%20Wikimedia%20Foundation&L_QTY0=1&L_TAXAMT0=0&L_AMT0=1%2e55&' .
						'PAYMENTREQUEST_0_CURRENCYCODE=USD&PAYMENTREQUEST_0_AMT=1%2e55&PAYMENTREQUEST_0_ITEMAMT=' .
						'1%2e55&PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT' .
						'=0&PAYMENTREQUEST_0_NOTIFYURL=http%3a%2f%2ffundraising%2ewikimedia%2eorg%2f' .
						'IPNListener_Standalone%2ephp&PAYMENTREQUEST_0_INSURANCEAMT=0&PAYMENTREQUEST_0_SHIPDISCAMT=0' .
						'&PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=tle%40wikimedia%2eorg&' .
						'PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&L_PAYMENTREQUEST_0_NAME0=Monthly%20donation%20' .
						'to%20the%20Wikimedia%20Foundation&L_PAYMENTREQUEST_0_QTY0=1&L_PAYMENTREQUEST_0_TAXAMT0=0&' .
						'L_PAYMENTREQUEST_0_AMT0=1%2e55&PAYMENTREQUESTINFO_0_ERRORCODE=0'
					)
					->setProcessorContactID( '8R297FE87CD8S' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setDonorDetails(
						( new DonorDetails() )
							->setEmail( 'donor@generous.net' )
							->setFirstName( 'Fezziwig' )
							->setLastName( 'Fowl' )
					)
			);
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

		$redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-2D123456D9876543U&useraction=commit';
		$gateway = $this->getFreshGatewayObject( $init );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-2D123456D9876543U'
			] )
			->willReturn(
				( new PaymentDetailResponse() )
					->setRawResponse(
						'TOKEN=EC%2d2D123456D9876543U&SUCCESSPAGEREDIRECTREQUESTED=false&' .
						'TIMESTAMP=2017%2d04%2d20T16%3a59%3a06Z&CORRELATIONID=537ffff0fefa&ACK=Failure&VERSION=204&' .
						'BUILD=32574509&L_ERRORCODE0=10486&L_SHORTMESSAGE0=This%20transaction%20couldn%27t%20be%20' .
						'completed%2e&L_LONGMESSAGE0=This%20transaction%20couldn%27t%20be%20completed%2e%20Please%20' .
						'redirect%20your%20customer%20to%20PayPal%2e&L_SEVERITYCODE0=Error'
					)
					->setSuccessful( false )
					->setStatus( FinalStatus::FAILED )
					->setRedirectUrl( $redirect )
					->setErrors( [
						new PaymentError(
							ErrorCode::DECLINED,
							'This transaction couldn\'t be completed. Please redirect your customer to PayPal',
							LogLevel::ERROR
						)
					] )
			);

		$result = $gateway->processDonorReturn( [
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		] );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
		$this->assertFalse( $result->isFailed() );
		$this->assertEquals( $redirect, $result->getRedirect() );
	}

	/**
	 * Check that we don't send donors to the fail page for warnings
	 */
	public function testProcessDonorReturnWarning() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-4V987654XA123456V'
			] )
			->willReturn( $this->getGoodPaymentDetailResponse() );

		$this->provider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => '4.55',
				'currency' => 'USD',
				'gateway_session_id' => 'EC-4V987654XA123456V',
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'order_id' => '45931210.1',
				'processor_contact_id' => '8R297FE87CD8S',
			] )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawResponse(
						'TOKEN=EC%2d4V987654XA123456V&SUCCESSPAGEREDIRECTREQUESTED=false&TIMESTAMP=' .
						'2017%2d01%2d30T22%3a33%3a43Z&CORRELATIONID=434c98b240b6&ACK=SuccessWithWarning&VERSION=204&' .
						'BUILD=38544305&L_ERRORCODE0=11607&L_SHORTMESSAGE0=Duplicate%20Request&L_LONGMESSAGE0=' .
						'A%20successful%20transaction%20has%20already%20been%20completed%20for%20this%20token%2e&' .
						'L_SEVERITYCODE0=Warning&INSURANCEOPTIONSELECTED=false&SHIPPINGOPTIONISDEFAULT=false&' .
						'PAYMENTINFO_0_TRANSACTIONID=33N12345BB123456D&PAYMENTINFO_0_TRANSACTIONTYPE=expresscheckout&' .
						'PAYMENTINFO_0_PAYMENTTYPE=instant&PAYMENTINFO_0_ORDERTIME=2017%2d09%2d05T21%3a25%3a48Z&' .
						'PAYMENTINFO_0_AMT=1500&PAYMENTINFO_0_FEEAMT=81&PAYMENTINFO_0_TAXAMT=0&' .
						'PAYMENTINFO_0_CURRENCYCODE=JPY&PAYMENTINFO_0_PAYMENTSTATUS=Completed&' .
						'PAYMENTINFO_0_PENDINGREASON=None&PAYMENTINFO_0_REASONCODE=None&' .
						'PAYMENTINFO_0_PROTECTIONELIGIBILITY=Ineligible&PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE=None&' .
						'PAYMENTINFO_0_SELLERPAYPALACCOUNTID=abc%40example%2eorg&' .
						'PAYMENTINFO_0_SECUREMERCHANTACCOUNTID=EZ123ABCDEFG1&PAYMENTINFO_0_ERRORCODE=0&' .
						'PAYMENTINFO_0_ACK=Success'
					)
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
					->addErrors(
						new PaymentError(
							ErrorCode::DUPLICATE_ORDER_ID,
							'11607: A successful transaction has already been completed for this token',
							LogLevel::ERROR
						)
					)
					->setGatewayTxnId( '33N12345BB123456D' )
			);

		$result = $gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
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
	}

	/**
	 * Test what happens when the status comes back good but the CreateRecurringPaymentsProfile comes back
	 * with an error telling us to redirect the donor to PayPal
	 */
	public function testProcessDonorReturnRecurringRetry() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['recurring'] = '1';
		$this->setUpRequest( $init, [ 'Donor' => $init ] );
		$gateway = $this->getFreshGatewayObject( $init );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-2D123456D9876543U'
			] )
			->willReturn(
				( new PaymentDetailResponse() )
				->setRawResponse(
					'TOKEN=EC%2d2D123456D9876543U&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=' .
					'PaymentActionNotInitiated&TIMESTAMP=2017%2d02%2d01T20%3a07%3a14Z&CORRELATIONID=d70c9a334455e&' .
					'ACK=Success&VERSION=204&BUILD=28806785&EMAIL=donor%40generous%2enet&PAYERID=8R297FE87CD8S&' .
					'PAYERSTATUS=unverified&FIRSTNAME=Fezziwig&LASTNAME=Fowl&COUNTRYCODE=US&BILLINGNAME=' .
					'Fezziwig%20Fowl&STREET=123%20Notta%20Way&CITY=Whoville&STATE=OR&ZIP=97211&COUNTRY=US&' .
					'COUNTRYNAME=United%20States&ADDRESSID=PayPal&ADDRESSSTATUS=Confirmed&CURRENCYCODE=USD&' .
					'AMT=1%2e55&ITEMAMT=1%2e55&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=45931210&DESC=Donation%20' .
					'to%20the%20Wikimedia%20Foundation&INVNUM=45931210%2e1&NOTIFYURL=http%3a%2f%2ffundraising' .
					'%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&INSURANCEAMT=0&SHIPDISCAMT=0&' .
					'INSURANCEOPTIONOFFERED=false&L_QTY0=1&L_TAXAMT0=0&L_AMT0=1%2e55&L_DESC0=Donation%20to%20the%20' .
					'Wikimedia%20Foundation&PAYMENTREQUEST_0_CURRENCYCODE=USD&PAYMENTREQUEST_0_AMT=1%2e55&' .
					'PAYMENTREQUEST_0_ITEMAMT=1%2e55&PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&' .
					'PAYMENTREQUEST_0_TAXAMT=0&PAYMENTREQUEST_0_CUSTOM=45931210&PAYMENTREQUEST_0_DESC=Donation%20to' .
					'%20the%20Wikimedia%20Foundation&PAYMENTREQUEST_0_INVNUM=45931210%2e1&PAYMENTREQUEST_0_NOTIFYURL=' .
					'http%3a%2f%2ffundraising%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&' .
					'PAYMENTREQUEST_0_INSURANCEAMT=0&PAYMENTREQUEST_0_SHIPDISCAMT=0&' .
					'PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=receiver%40wikimedia%2eorg&' .
					'PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&' .
					'L_PAYMENTREQUEST_0_QTY0=1&L_PAYMENTREQUEST_0_TAXAMT0=0&L_PAYMENTREQUEST_0_AMT0=1%2e55&' .
					'L_PAYMENTREQUEST_0_DESC0=Donation%20to%20the%20Wikimedia%20Foundation&' .
					'PAYMENTREQUESTINFO_0_ERRORCODE=0'
				)
				->setSuccessful( true )
				->setStatus( FinalStatus::PENDING_POKE )
				->setDonorDetails(
					( new DonorDetails() )
						->setEmail( 'donor@generous.net' )
						->setFirstName( 'Fezziwig' )
						->setLastName( 'Fowl' )
				)
			);
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
	 * Check that it does not call DoExpressCheckoutPayment when status is PaymentActionCompleted
	 */
	public function testProcessDonorReturnPaymentActionCompleted() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$options = [ 'batch_mode' => true ];
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init, $options );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-4V987654XA123456V'
			] )
			->willReturn(
				( new PaymentDetailResponse() )
				->setRawResponse(
					'TOKEN=EC%2d4V987654XA123456V&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=' .
					'PaymentActionCompleted&TIMESTAMP=2017%2d02%2d01T20%3a07%3a14Z&CORRELATIONID=d70c9a334455e&ACK=' .
					'Success&VERSION=204&BUILD=28806785&EMAIL=donor%40generous%2enet&PAYERID=8R297FE87CD8S&' .
					'PAYERSTATUS=unverified&FIRSTNAME=Fezziwig&LASTNAME=Fowl&COUNTRYCODE=US&BILLINGNAME=Fezziwig%20' .
					'Fowl&STREET=123%20Notta%20Way&CITY=Whoville&STATE=OR&ZIP=97211&COUNTRY=US&COUNTRYNAME=United%20' .
					'States&ADDRESSID=PayPal&ADDRESSSTATUS=Confirmed&CURRENCYCODE=USD&AMT=1%2e55&ITEMAMT=1%2e55&' .
					'SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=45931210&DESC=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&INVNUM=45931210%2e1&NOTIFYURL=http%3a%2f%2ffundraising%2ewikimedia%2eorg%2f' .
					'IPNListener_Standalone%2ephp&INSURANCEAMT=0&SHIPDISCAMT=0&INSURANCEOPTIONOFFERED=false&L_QTY0=1&' .
					'L_TAXAMT0=0&L_AMT0=1%2e55&L_DESC0=Donation%20to%20the%20Wikimedia%20Foundation&' .
					'PAYMENTREQUEST_0_CURRENCYCODE=USD&PAYMENTREQUEST_0_AMT=1%2e55&PAYMENTREQUEST_0_ITEMAMT=1%2e55&' .
					'PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT=0&' .
					'PAYMENTREQUEST_0_CUSTOM=45931210&PAYMENTREQUEST_0_DESC=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&PAYMENTREQUEST_0_INVNUM=45931210%2e1&PAYMENTREQUEST_0_NOTIFYURL=http%3a%2f%2f' .
					'fundraising%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&PAYMENTREQUEST_0_INSURANCEAMT=0&' .
					'PAYMENTREQUEST_0_SHIPDISCAMT=0&PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=' .
					'receiver%40wikimedia%2eorg&PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&' .
					'PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&L_PAYMENTREQUEST_0_QTY0=1&L_PAYMENTREQUEST_0_TAXAMT0=0&' .
					'L_PAYMENTREQUEST_0_AMT0=1%2e55&L_PAYMENTREQUEST_0_DESC0=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&PAYMENTREQUESTINFO_0_ERRORCODE=0'
				)
				->setStatus( FinalStatus::COMPLETE )
				->setSuccessful( true )
			);

		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD',
		] );

		$this->assertEquals( FinalStatus::COMPLETE, $gateway->getFinalStatus(), 'Should have Final Status Complete' );
		$this->assertCount( 0, $gateway->curled, 'Should not make a DoExpressCheckoutPayment call' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
	}

	/**
	 * Check that it does not call DoExpressCheckoutPayment when the token has timed out
	 */
	public function testProcessDonorReturnTokenTimeout() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$options = [ 'batch_mode' => true ];
		$this->setUpRequest( $init, [ 'Donor' => $init ] );

		$gateway = $this->getFreshGatewayObject( $init, $options );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => 'EC-4V987654XA123456V'
			] )
			->willReturn(
				( new PaymentDetailResponse() )
				->setRawResponse(
					'TOKEN=EC%2d50M26113274765456&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=' .
					'PaymentActionNotInitiated&TIMESTAMP=2018%2d01%2d12T21%3a22%3a19Z&CORRELATIONID=' .
					'97825e40f3857&ACK=Failure&VERSION=204&BUILD=40680762&L_ERRORCODE0=10411&L_SHORTMESSAGE0=This%20' .
					'Express%20Checkout%20session%20has%20expired%2e&L_LONGMESSAGE0=This%20Express%20Checkout%20' .
					'session%20has%20expired%2e%20%20Token%20value%20is%20no%20longer%20valid%2e&L_SEVERITYCODE0=' .
					'Error&CURRENCYCODE=JPY&AMT=1000&ITEMAMT=1000&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=' .
					'56383531&DESC=Donation%20to%20the%20Wikimedia%20Foundation&INVNUM=56383531%2e1&INSURANCEAMT=0&' .
					'SHIPDISCAMT=0&L_QTY0=1&L_TAXAMT0=0&L_AMT0=1000&L_DESC0=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&L_ITEMWEIGHTVALUE0=%20%20%200%2e00000&L_ITEMLENGTHVALUE0=%20%20%200%2e00000&' .
					'L_ITEMWIDTHVALUE0=%20%20%200%2e00000&L_ITEMHEIGHTVALUE0=%20%20%200%2e00000&' .
					'PAYMENTREQUEST_0_CURRENCYCODE=JPY&PAYMENTREQUEST_0_AMT=1000&PAYMENTREQUEST_0_ITEMAMT=1000&' .
					'PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT=0&' .
					'PAYMENTREQUEST_0_CUSTOM=56383531&PAYMENTREQUEST_0_DESC=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&PAYMENTREQUEST_0_INVNUM=56383531%2e1&PAYMENTREQUEST_0_INSURANCEAMT=0&' .
					'PAYMENTREQUEST_0_SHIPDISCAMT=0&PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&' .
					'PAYMENTREQUEST_0_ADDRESSNORMALIZATIONSTATUS=None&L_PAYMENTREQUEST_0_QTY0=1&' .
					'L_PAYMENTREQUEST_0_TAXAMT0=0&L_PAYMENTREQUEST_0_AMT0=1000&L_PAYMENTREQUEST_0_DESC0=Donation%20to' .
					'%20the%20Wikimedia%20Foundation&L_PAYMENTREQUEST_0_ITEMWEIGHTVALUE0=%20%20%200%2e00000&' .
					'L_PAYMENTREQUEST_0_ITEMLENGTHVALUE0=%20%20%200%2e00000&L_PAYMENTREQUEST_0_ITEMWIDTHVALUE0=' .
					'%20%20%200%2e00000&L_PAYMENTREQUEST_0_ITEMHEIGHTVALUE0=%20%20%200%2e00000&' .
					'PAYMENTREQUESTINFO_0_ERRORCODE=0'
				)
					->setStatus( FinalStatus::TIMEOUT )
					->setSuccessful( true )
			);

		$gateway->processDonorReturn( [
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD',
		] );

		$this->assertEquals( FinalStatus::TIMEOUT, $gateway->getFinalStatus(), 'Should have Final Status Timeout' );
		$this->assertCount( 0, $gateway->curled, 'Should not make a DoExpressCheckoutPayment call' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'Should not queue a message' );
	}

	/**
	 * The result switcher should redirect the donor to the thank you page and mark the token as
	 * processed.
	 */
	public function testResultSwitcher() {
		$init = $this->getDonorTestData( 'US' );
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
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_session_id' => $init['gateway_session_id']
			] )
			->willReturn( $this->getGoodPaymentDetailResponse() );

		$this->provider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => '4.55',
				'currency' => 'USD',
				'gateway_session_id' => $init['gateway_session_id'],
				'description' => wfMessage( 'donate_interface-donation-description' )->inLanguage( 'pt' )->text(),
				'order_id' => $init['contribution_tracking_id'] . '.1',
				'processor_contact_id' => '8R297FE87CD8S',
			] )
			->willReturn(
				$this->getGoodApprovePaymentResponse()
			);

		$this->verifyFormOutput( 'PaypalExpressGatewayResult', $request, $assertNodes, false, $session );
		$key = 'processed_request-' . $request['token'];
		$processed = ObjectCache::getLocalClusterInstance()->get( $key );
		$this->assertTrue( $processed );
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
	 * it into a real code if it's a PayPal bogon. This happens at the SmashPig
	 * level now, so this test just makes sure we copy the code from the Address
	 * property.
	 */
	public function testUnstageCountry() {
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
		$this->setUpRequest( $request, $session );
		$gateway = $this->getFreshGatewayObject( $init );
		$this->provider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with()
			->willReturn(
				( new PaymentDetailResponse() )
				->setRawResponse(
					'TOKEN=EC%2d4V987654XA123456V&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=' .
					'PaymentActionNotInitiated&TIMESTAMP=2017%2d02%2d01T20%3a07%3a14Z&CORRELATIONID=' .
					'd70c9a334455e&ACK=Success&VERSION=204&BUILD=28806785&EMAIL=donor%40generous%2enet&' .
					'PAYERID=8R297FE87CD8S&PAYERSTATUS=unverified&FIRSTNAME=Fezziwig&LASTNAME=Fowl&COUNTRYCODE=C2&' .
					'BILLINGNAME=Fezziwig%20Fowl&STREET=123%20Notta%20Way&CITY=Whoville&STATE=GD&ZIP=521000&COUNTRY=' .
					'C2&COUNTRYNAME=China&ADDRESSID=PayPal&ADDRESSSTATUS=Confirmed&CURRENCYCODE=CNY&AMT=1%2e55&' .
					'ITEMAMT=1%2e55&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=45931210&DESC=Donation%20to%20the' .
					'%20Wikimedia%20Foundation&INVNUM=45931210%2e1&NOTIFYURL=http%3a%2f%2ffundraising%2ewikimedia' .
					'%2eorg%2fIPNListener_Standalone%2ephp&INSURANCEAMT=0&SHIPDISCAMT=0&INSURANCEOPTIONOFFERED=false&' .
					'L_QTY0=1&L_TAXAMT0=0&L_AMT0=1%2e55&L_DESC0=Donation%20to%20the%20Wikimedia%20Foundation&' .
					'PAYMENTREQUEST_0_CURRENCYCODE=USD&PAYMENTREQUEST_0_AMT=1%2e55&PAYMENTREQUEST_0_ITEMAMT=1%2e55&' .
					'PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT=0&' .
					'PAYMENTREQUEST_0_CUSTOM=45931210&PAYMENTREQUEST_0_DESC=Donation%20to%20the%20Wikimedia%20' .
					'Foundation&PAYMENTREQUEST_0_INVNUM=45931210%2e1&PAYMENTREQUEST_0_NOTIFYURL=http%3a%2f%2f' .
					'fundraising%2ewikimedia%2eorg%2fIPNListener_Standalone%2ephp&PAYMENTREQUEST_0_INSURANCEAMT=0' .
					'&PAYMENTREQUEST_0_SHIPDISCAMT=0&PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=receiver%40wikimedia%2e' .
					'org&PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&' .
					'L_PAYMENTREQUEST_0_QTY0=1&L_PAYMENTREQUEST_0_TAXAMT0=0&L_PAYMENTREQUEST_0_AMT0=1%2e55&' .
					'L_PAYMENTREQUEST_0_DESC0=Donation%20to%20the%20Wikimedia%20Foundation&' .
					'PAYMENTREQUESTINFO_0_ERRORCODE=0'
				)
					->setProcessorContactID( '8R297FE87CD8S' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setDonorDetails(
						( new DonorDetails() )
							->setEmail( 'donor@generous.net' )
							->setFirstName( 'Fezziwig' )
							->setLastName( 'Fowl' )
							->setBillingAddress(
								( new Address() )
									->setStreetAddress( '123 Notta Way' )
									->setCity( 'Whoville' )
									->setCountryCode( 'CN' )
									->setStateOrProvinceCode( 'GD' )
									->setPostalCode( '521000' )
							)
					)
			);
		$gateway->processDonorReturn( $request );
		$savedCountry = $gateway->getData_Unstaged_Escaped( 'country' );
		$this->assertEquals( 'CN', $savedCountry );
	}
}
