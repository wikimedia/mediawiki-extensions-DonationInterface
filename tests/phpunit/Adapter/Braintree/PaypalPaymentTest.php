<?php

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\PaypalPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Braintree
 */
class PaypalPaymentTest extends BaseBraintreeTestCase {

	protected $testAdapterClass = BraintreeAdapter::class;

	/**
	 * @var \SmashPig\Tests\TestingProviderConfiguration
	 */
	protected $providerConfig;

	/**
	 * @var \SmashPig\PaymentProviders\Braintree\PaypalPaymentProvider
	 */
	protected $paypalPaymentProvider;

	public function setUp(): void {
		parent::setUp();

		$this->paypalPaymentProvider = $this->getMockBuilder(
			PaypalPaymentProvider::class
		)->disableOriginalConstructor()->getMock();

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/paypal',
			$this->paypalPaymentProvider
		);
	}

	public function testPaypalRoundCertainCurrencyPayment() {
		$init = $this->getTestDonor( 'paypal' );
		$init['currency'] = "TWD";
		$init['amount'] = 130.8;
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedPaymentToken = $init['payment_token'];
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedGatewayId = (string)mt_rand( 1000000, 10000000 );

		$donorDetailsResponse = new DonorDetails();
		$donorDetailsResponse->setFirstName( "John" );
		$donorDetailsResponse->setLastName( "Doe" );

		$createPaymentResponse = ( new CreatePaymentResponse() )
			->setRawStatus( 'SETTLING' )
			->setStatus( FinalStatus::COMPLETE )
			->setSuccessful( true )
			->setGatewayTxnId( $expectedGatewayId );

		$createPaymentResponse->setDonorDetails( $donorDetailsResponse );

		$this->paypalPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'email' => 'nobody@wikimedia.org',
				'amount' => '130',
				'country' => 'US',
				'currency' => 'TWD',
				'description' => 'Wikimedia Foundation',
				'order_id' => $expectedMerchantRef,
				'user_ip' => '127.0.0.1',
				'payment_token' => $expectedPaymentToken
			] )
			->willReturn( $createPaymentResponse );

		$gateway->doPayment();
	}

	public function testPaypalDoPayment() {
		$init = $this->getTestDonor( "paypal" );
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );

		$expectedPaymentToken = $init['payment_token'];
		$expectedContributionTrackingId = $init['contribution_tracking_id'];
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedGatewayId = (string)mt_rand( 1000000, 10000000 );

		$donorDetailsResponse = new DonorDetails();
		$donorDetailsResponse->setFirstName( "John" );
		$donorDetailsResponse->setLastName( "Doe" );

		$createPaymentResponse = ( new CreatePaymentResponse() )
			->setRawStatus( 'SETTLING' )
			->setStatus( FinalStatus::COMPLETE )
			->setSuccessful( true )
			->setGatewayTxnId( $expectedGatewayId );

		$createPaymentResponse->setDonorDetails( $donorDetailsResponse );

		$this->paypalPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'email' => 'nobody@wikimedia.org',
				'amount' => '1.55',
				'country' => 'US',
				'currency' => 'USD',
				'description' => 'Wikimedia Foundation',
				'order_id' => $expectedMerchantRef,
				'user_ip' => '127.0.0.1',
				'payment_token' => $expectedPaymentToken
			] )
			->willReturn( $createPaymentResponse );

		$result = $gateway->doPayment();
		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );

		$messages = self::getAllQueueMessages();

		$this->assertCount( 1, $messages['payments-antifraud'] );
		$this->assertCount( 1, $messages['payments-init'] );
		$this->assertCount( 1, $messages['donations'] );
		// No pending message when we immediately capture the donation
		$this->assertCount( 0, $messages['pending'] );

		$expectedPaymentsInitQueueMessage = [
			'validation_action' => 'process',
			'payments_final_status' => 'complete',
			'country' => 'US',
			'currency' => 'USD',
			'gateway' => 'braintree',
			'payment_method' => 'paypal',
			'gateway_txn_id' => $expectedGatewayId,
			'order_id' => $expectedMerchantRef,
			'contribution_tracking_id' => $expectedContributionTrackingId,
		];

		$this->assertArraySubmapSame(
			$expectedPaymentsInitQueueMessage,
			$messages['payments-init'][0]
		);

		$expectedDonationsQueueMessage = [
			'gateway_txn_id' => $expectedGatewayId,
			'order_id' => $expectedMerchantRef,
			'contribution_tracking_id' => $expectedContributionTrackingId,
			'gateway' => 'braintree',
			'payment_method' => 'paypal',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'language' => 'en',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'nobody@wikimedia.org',
			'gross' => '1.55',
			'user_ip' => '127.0.0.1',
		];

		$this->assertArraySubmapSame(
			$expectedDonationsQueueMessage,
			$messages['donations'][0]
		);
	}

	public function testPaypalDoPaymentInvalidPaymentToken() {
		$init = $this->getTestDonor( "paypal" );
		$init['amount'] = '1.55';
		$init['payment_token'] = "NOT_THE_RIGHT_PAYMENT_ID_TOKEN";
		$gateway = $this->getFreshGatewayObject( $init );

		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$unexpectedPaymentToken = $init['payment_token'];

		$failedCreatePaymentResponse = ( new CreatePaymentResponse() )
			->setStatus( FinalStatus::FAILED )
			->setSuccessful( false )
			->addValidationError(
				new ValidationError( 'payment_method', null, [], 'Unknown or expired single-use payment method.' )
			);

		$this->paypalPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'email' => 'nobody@wikimedia.org',
				'amount' => '1.55',
				'country' => 'US',
				'currency' => 'USD',
				'description' => 'Wikimedia Foundation',
				'order_id' => $expectedMerchantRef,
				'user_ip' => '127.0.0.1',
				'payment_token' => $unexpectedPaymentToken
			] )
			->willReturn( $failedCreatePaymentResponse );

		$result = $gateway->doPayment();
		$this->assertTrue( $result->isFailed() );
		$this->assertNotEmpty( $result->getErrors() );

		$messages = self::getAllQueueMessages();

		$this->assertCount( 1, $messages['payments-antifraud'] );
		$this->assertCount( 0, $messages['payments-init'] );
		$this->assertCount( 0, $messages['donations'] );
		$this->assertCount( 0, $messages['pending'] );
	}
}
