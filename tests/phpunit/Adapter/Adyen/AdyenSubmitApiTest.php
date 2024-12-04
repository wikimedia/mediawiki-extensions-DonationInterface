<?php
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Adyen\ApplePayPaymentProvider;
use SmashPig\PaymentProviders\Adyen\GooglePayPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group DonationInterfaceApi
 */
class AdyenSubmitApiTest extends DonationInterfaceApiTestCase {

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $applepayPaymentProvider;

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $googlepayPaymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$providerConfig = TestingProviderConfiguration::createForProvider( 'adyen', $globalConfig );
		$ctx->providerConfigurationOverride = $providerConfig;
		$this->applepayPaymentProvider = $this->createMock( ApplePayPaymentProvider::class );
		$this->googlepayPaymentProvider = $this->createMock( GooglePayPaymentProvider::class );

		$providerConfig->overrideObjectInstance( 'payment-provider/apple', $this->applepayPaymentProvider );
		$providerConfig->overrideObjectInstance( 'payment-provider/google', $this->googlepayPaymentProvider );
	}

	public function testGoodSubmitApplePay() {
		$init = $this->getSubmitPaymentRequest();
		unset( $init['recurring'] );
		$trxnId = "RANDOM_TRANSACTION_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams();
		$this->applepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		$expectedCtMessage = $this->getExpectedCtMessage();
		// assert contribution tracking message
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$this->assertNotEmpty( $contributionTracking_message, 'Missing Contribution Tracking message' );
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donation message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId );
		// assert donation message
		$this->assertNotEmpty( $message, 'Missing Donations message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );
	}

	public function testGoodSubmitApplePayRecurring() {
		$init = $this->getSubmitPaymentRequest();
		$trxnId = "RANDOM_TRANSACTION_ID";
		$recurringToken = "RANDOM_RECURRING_TOKEN";
		$processorContactId = "RANDOM_CONTACT_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams();
		$expectedParams['recurring'] = 1;
		$this->applepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );

				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
					->setRecurringPaymentToken( $recurringToken )
					->setProcessorContactID( $processorContactId )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$expectedCtMessage = $this->getExpectedCtMessage();
		$expectedCtMessage['is_recurring'] = '1';
		// assert contribution tracking message
		$this->assertNotNull( $contributionTracking_message );
		$this->assertNotEmpty( $contributionTracking_message, 'Missing Contribution Tracking message' );
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donations message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId );
		$expectedDonationMessage['processor_contact_id'] = $processorContactId;
		$expectedDonationMessage['recurring'] = '1';
		$expectedDonationMessage['recurring_payment_token'] = $recurringToken;
		// assert donation message
		$this->assertNotEmpty( $message, 'Missing Donations message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );
	}

	public function testGoodSubmitApplePayAuthAndCapture() {
		$init = $this->getSubmitPaymentRequest();
		$trxnId = "RANDOM_TRANSACTION_ID";
		$recurringToken = "RANDOM_RECURRING_TOKEN";
		$processorContactId = "RANDOM_CONTACT_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams();
		$expectedParams['recurring'] = 1;
		$this->applepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::PENDING_POKE )
					->setRecurringPaymentToken( $recurringToken )
					->setProcessorContactID( $processorContactId )
			);

		$expectedApprovePaymentParams = $this->getExpectedApprovePaymentParams( $trxnId );
		$this->applepayPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $this->callback( function ( $params ) use ( $expectedApprovePaymentParams ) {
				$this->assertEquals( $expectedApprovePaymentParams, $params, 'Approve payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNotNull( $contributionTracking_message, 'Donation should push one transaction to CT queue' );
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$expectedCtMessage = $this->getExpectedCtMessage();
		$expectedCtMessage['is_recurring'] = '1';

		// assert contribution tracking message
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		// get donations message
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotEmpty( $message, 'Missing Donations message' );
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donations message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId );
		$expectedDonationMessage['processor_contact_id'] = $processorContactId;
		$expectedDonationMessage['recurring'] = '1';
		$expectedDonationMessage['recurring_payment_token'] = $recurringToken;

		// assert donation message
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );

		// get payments init message
		$paymentsInit_message = QueueWrapper::getQueue( 'payments-init' )->pop();
		$this->assertNotEmpty( $paymentsInit_message, 'Missing Payments Init message' );
		DonationInterfaceTestCase::unsetVariableFields( $paymentsInit_message );
		$expectedPaymentsInit = $this->getExpectedPaymentsInit( $trxnId );
		unset( $paymentsInit_message['server'] );
		$this->assertEquals( $expectedPaymentsInit, $paymentsInit_message, 'Payments init message mismatch' );
	}

	public function testSubmitApplePayAmountTooSmall() {
		$init = $this->getSubmitPaymentRequest();
		$init['amount'] = 0.01;
		$this->applepayPaymentProvider->expects( $this->never() )
			->method( 'createPayment' );

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];

		// error response was sent
		$this->assertSame( 'error', $result['status'] );
		$this->assertNotEmpty( $result['error_message'], 'Should have returned an error' );
		$this->assertSame( '1.1', $result['order_id'], 'Error should be in amount' );

		// assert no message was sent to queue
		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message, 'There should be no message in the contribution tracking queue' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'There should be no message in the donations queue' );
		$paymentsInit_message = QueueWrapper::getQueue( 'payments-init' )->pop();
		$this->assertNull( $paymentsInit_message, 'There should be no message in the payments init queue' );
	}

	public function testGoodSubmitGooglePay() {
		$init = $this->getSubmitPaymentRequest();
		$init['payment_method'] = 'paywithgoogle';
		unset( $init['recurring'] );
		$trxnId = "RANDOM_TRANSACTION_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams( 'google', 'Android' );

		$this->googlepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		$expectedCtMessage = $this->getExpectedCtMessage( 'google', 'Android' );

		// assert contribution tracking message
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$this->assertNotEmpty( $contributionTracking_message, 'Missing Contribution Tracking message' );
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donation message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId, 'google' );

		// assert donation message
		$this->assertNotEmpty( $message, 'Missing Donations message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );
	}

	public function testGoodSubmitGooglePayRecurring() {
		$init = $this->getSubmitPaymentRequest();
		$init['payment_method'] = 'paywithgoogle';
		$trxnId = "RANDOM_TRANSACTION_ID";
		$recurringToken = "RANDOM_RECURRING_TOKEN";
		$processorContactId = "RANDOM_CONTACT_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams( 'google', 'Android' );
		$expectedParams['recurring'] = 1;

		$this->googlepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
					->setRecurringPaymentToken( $recurringToken )
					->setProcessorContactID( $processorContactId )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		$expectedCtMessage = $this->getExpectedCtMessage( 'google', 'Android' );
		$expectedCtMessage['is_recurring'] = '1';

		// assert contribution tracking message
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$this->assertNotEmpty( $contributionTracking_message, 'Missing Contribution Tracking message' );
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		// get donations message
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donations message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId, 'google' );
		$expectedDonationMessage['processor_contact_id'] = $processorContactId;
		$expectedDonationMessage['recurring'] = '1';
		$expectedDonationMessage['recurring_payment_token'] = $recurringToken;

		// assert donation message
		$this->assertNotEmpty( $message, 'Missing Donations message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );
	}

	public function testGoodSubmitGooglePayAuthAndCapture() {
		$init = $this->getSubmitPaymentRequest();
		$init['payment_method'] = 'paywithgoogle';
		$trxnId = "RANDOM_TRANSACTION_ID";
		$recurringToken = "RANDOM_RECURRING_TOKEN";
		$processorContactId = "RANDOM_CONTACT_ID";
		$expectedParams = $this->getExpectedCreatePaymentParams( 'google', 'Android' );
		$expectedParams['recurring'] = 1;

		$this->googlepayPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $expectedParams ) {
				$this->assertEquals( $expectedParams, $params, 'Create payment params mismatch' );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::PENDING_POKE )
					->setRecurringPaymentToken( $recurringToken )
					->setProcessorContactID( $processorContactId )
			);

		$expectedApprovePaymentParams = $this->getExpectedApprovePaymentParams( $trxnId );
		$this->googlepayPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $this->callback( function ( $params ) use ( $expectedApprovePaymentParams ) {
				$this->assertEquals( $expectedApprovePaymentParams, $params, 'Approve payment params mismatch' );

				return true;
			} ) )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setGatewayTxnId( $trxnId )
					->setSuccessful( true )
					->setStatus( FinalStatus::COMPLETE )
			);

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];
		$this->assertSame( 'success', $result['status'] );

		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$contributionTracking_message_empty = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message_empty, 'Donation should only yield one tracking message in the queue' );
		$expectedCtMessage = $this->getExpectedCtMessage( 'google', 'Android' );
		$expectedCtMessage['is_recurring'] = '1';

		// assert contribution tracking message
		DonationInterfaceTestCase::unsetVariableFields( $contributionTracking_message );
		$this->assertNotEmpty( $contributionTracking_message, 'Missing Contribution Tracking message' );
		$this->assertNotEmpty( $contributionTracking_message['ts'], 'Missing Contribution Tracking message timestamp' );
		unset( $contributionTracking_message['ts'] );
		$this->assertEquals( $expectedCtMessage, $contributionTracking_message, 'Contribution tracking message mismatch' );

		// get donations message
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$donation_message_empty = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $donation_message_empty, 'Donation should only yield one donations message in the queue' );
		$expectedDonationMessage = $this->getExpectedDonationMessage( $trxnId, 'google' );
		$expectedDonationMessage['processor_contact_id'] = $processorContactId;
		$expectedDonationMessage['recurring'] = '1';
		$expectedDonationMessage['recurring_payment_token'] = $recurringToken;

		// assert donation message
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$this->assertEquals( $expectedDonationMessage, $message, 'Donation message mismatch' );

		$paymentsInit_message = QueueWrapper::getQueue( 'payments-init' )->pop();
		$this->assertNotEmpty( $paymentsInit_message, 'Missing Payments Init message' );
		DonationInterfaceTestCase::unsetVariableFields( $paymentsInit_message );
		$expectedPaymentsInit = $this->getExpectedPaymentsInit( $trxnId, 'google' );
		unset( $paymentsInit_message['server'] );
		$this->assertEquals( $expectedPaymentsInit, $paymentsInit_message, 'Payments init message mismatch' );
	}

	public function testSubmitGooglePayAmountTooSmall() {
		$init = $this->getSubmitPaymentRequest();
		$init['payment_method'] = 'paywithgoogle';
		$init['amount'] = 0.01;
		$this->googlepayPaymentProvider->expects( $this->never() )
			->method( 'createPayment' );
		$this->googlepayPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['response'];

		// error response was sent
		$this->assertSame( 'error', $result['status'] );
		$this->assertNotEmpty( $result['error_message'], 'Should have returned an error' );
		$this->assertSame( '1.1', $result['order_id'], 'Error should be in amount' );

		// assert no message was sent to queue
		$contributionTracking_message = QueueWrapper::getQueue( 'contribution-tracking' )->pop();
		$this->assertNull( $contributionTracking_message, 'There should be no message in the contribution tracking queue' );
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNull( $message, 'There should be no message in the donations queue' );
		$paymentsInit_message = QueueWrapper::getQueue( 'payments-init' )->pop();
		$this->assertNull( $paymentsInit_message, 'There should be no message in the payments init queue' );
	}

	protected function getSubmitPaymentRequest(): array {
		$user_ip = '127.0.0.1';
		$init = [
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'applepay',
			'payment_token' => 'RANDOM_TOKEN',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'donor_country' => 'US',
			'language' => 'en',
			'payment_network' => 'visa',
			'email' => 'testymctester@example.org',
			'user_ip' => $user_ip,
			'first_name' => 'Testy',
			'last_name' => 'McTester',
			'full_name' => 'Testy McTester',
			'recurring' => 1,
			"app_version" => "1.0.0",
			"banner" => "appmenu",
			"city" => "Sample City",
			"opt_in" => true,
			"pay_the_fee" => true,
			"postal_code" => "12345",
			"state_province" => "Sample State",
			"street_address" => "123 Mock St",
			"order_id" => "1.1",
			"utm_campaign" => "iOS",
			"payment_submethod" => "visa"
		];
		$init['gateway'] = 'adyen';
		$init['action'] = 'submitPayment';
		$init['format'] = 'json';

		return $init;
	}

	protected function getExpectedCreatePaymentParams( $method = 'apple', $campaign = "iOS" ): array {
		return [
			"amount" => "1.55",
			"app_version" => "1.0.0",
			"banner" => "appmenu",
			"city" => "Sample City",
			"country" => "US",
			"currency" => "USD",
			"donor_country" => "US",
			"email" => "testymctester@example.org",
			"first_name" => "Testy",
			"full_name" => "Testy McTester",
			"language" => "en",
			"last_name" => "McTester",
			"recurring" => false,
			"payment_token" => "RANDOM_TOKEN",
			"opt_in" => true,
			"pay_the_fee" => true,
			"payment_method" => $method,
			"payment_network" => "visa",
			"postal_code" => "12345",
			"state_province" => "Sample State",
			"street_address" => "123 Mock St",
			"order_id" => "1.1",
			"utm_campaign" => $campaign,
			"payment_submethod" => "visa"
		];
	}

	protected function getExpectedCtMessage( $method = 'apple', $campaign = 'iOS' ) {
		return [
			'country' => 'US',
			'gateway' => 'adyen',
			'language' => 'en',
			'currency' => 'USD',
			'payment_method' => $method,
			'payment_submethod' => 'visa',
			'amount' => '1.55',
			'utm_key' => '',
			'utm_campaign' => $campaign,
			'utm_source' => 'appmenu.inapp.' . $method,
			'utm_medium' => 'WikipediaApp',
			'banner' => 'appmenu',
			'browser' => 'app',
			'browser_version' => '1.0.0',
			'form_amount' => 'USD 1.55',
			'id' => '1',
			'is_recurring' => null,
			'os' => $campaign,
			'source_name' => 'DonationInterface',
			'source_type' => 'payments'
		];
	}

	protected function getExpectedDonationMessage( $trxnId, $method = 'apple' ) {
		return [
			'country' => 'US',
			'gateway' => 'adyen',
			'gateway_txn_id' => $trxnId,
			'language' => 'en',
			'currency' => 'USD',
			'email' => 'testymctester@example.org',
			'gross' => '1.55',
			'payment_method' => $method,
			'payment_submethod' => 'visa',
			'user_ip' => '127.0.0.1',
			'contribution_tracking_id' => '1',
			'order_id' => '1.1',
			'city' => 'Sample City',
			'first_name' => 'Testy',
			'full_name' => 'Testy McTester',
			'last_name' => 'McTester',
			'postal_code' => '12345',
			'opt_in' => '1',
			'street_address' => '123 Mock St',
			'state_province' => 'Sample State',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments'
		];
	}

	protected function getExpectedPaymentsInit( $trxnId, $method = 'apple' ) {
		return [
			'country' => 'US',
			'gateway' => 'adyen',
			'gateway_txn_id' => $trxnId,
			'currency' => 'USD',
			'amount' => '1.55',
			'contribution_tracking_id' => '1',
			'payment_method' => $method,
			'payment_submethod' => 'visa',
			'validation_action' => ValidationAction::PROCESS,
			'payments_final_status' => FinalStatus::COMPLETE,
			'order_id' => '1.1',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments'
		];
	}

	protected function getExpectedApprovePaymentParams( $trxnId ) {
		return [
			'amount' => '1.55',
			'currency' => 'USD',
			'gateway_txn_id' => $trxnId
		];
	}
}
