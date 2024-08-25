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

	/** @inheritDoc */
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
		$init = $this->getTestDonor( "paypal" );
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

	/**
	 * Integration test to verify that payment is blocked
	 * when the cached attempt count hits the IP Velocity Threshold.
	 */
	public function testDoPaymentMultipleAttempt(): void {
		$this->setMwGlobalsForIPVelocityFilterTest( 2 );

		$init = $this->getTestDonor( "paypal" );
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );

		$expectedPaymentToken = $init['payment_token'];
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedGatewayId = (string)mt_rand( 1000000, 10000000 );

		$donorDetailsResponse = new DonorDetails();

		$createPaymentResponse = ( new CreatePaymentResponse() )
				->setRawStatus( 'SETTLING' )
				->setStatus( FinalStatus::COMPLETE )
				->setSuccessful( true )
				->setGatewayTxnId( $expectedGatewayId );

		$createPaymentResponse->setDonorDetails( $donorDetailsResponse );

		$this->paypalPaymentProvider->expects( $this->exactly( 2 ) )
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

		for ( $i = 0; $i < 3; $i++ ) {
			$result = $gateway->doPayment();
		}

		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();

		$this->assertCount( 3, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
				'validation_action' => 'process',
				'score_breakdown' => [ 'initial' => 0 ],
				'user_ip' => '127.0.0.1',
				'gateway' => 'braintree',
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $expectedMerchantRef,
				'payment_method' => 'paypal',
		];
		$this->assertArraySubmapSame(
				$expectedAntifraudInitial,
				$messages['payments-antifraud'][0]
		);
		$expectedAntiFraudProcess = [
						'gateway_txn_id' => $expectedGatewayId,
						'risk_score' => 0,
						'score_breakdown' => [
								'initial' => 0
						]
				] + $expectedAntifraudInitial;
		$this->assertArraySubmapSame(
				$expectedAntiFraudProcess,
				$messages['payments-antifraud'][1]
		);
		$expectedAntiFraudProcess = [
						'validation_action' => \SmashPig\PaymentData\ValidationAction::REJECT,
						'gateway_txn_id' => $expectedGatewayId,
						'score_breakdown' => [ 'IPVelocityFilter' => 80 ],
				] + $expectedAntifraudInitial;
		$this->assertArraySubmapSame(
				$expectedAntiFraudProcess,
				$messages['payments-antifraud'][2]
		);
		$this->assertCount( 2, $messages['payments-init'] );
	}

	/**
	 * Integration test to verify that the createPayment method isn't called
	 * when user_ip is added to the deny list
	 *
	 */
	public function testDoPaymentAttemptBlockedDueToIPInDenyList() {
		$threshold = 2;
		$this->setMwGlobalsForIPVelocityFilterTest( $threshold );

		$init = $this->getTestDonor( "paypal" );
		$init['amount'] = '1.55';
		$gateway = $this->getFreshGatewayObject( $init );

		$expectedPaymentToken = $init['payment_token'];
		$expectedContributionTrackingId = $init['contribution_tracking_id'];
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedGatewayId = (string)mt_rand( 1000000, 10000000 );

		$donorDetailsResponse = new DonorDetails();

		$createPaymentResponse = ( new CreatePaymentResponse() )
				->setRawStatus( 'SETTLING' )
				->setStatus( FinalStatus::COMPLETE )
				->setSuccessful( true )
				->setGatewayTxnId( $expectedGatewayId );

		$createPaymentResponse->setDonorDetails( $donorDetailsResponse );

		$this->paypalPaymentProvider->expects( $this->exactly( 2 ) )
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

		for ( $i = 1; $i <= 3; $i++ ) {
			if ( $i === 3 ) {
				$this->overrideConfigValue( 'DonationInterfaceIPDenyList', [ '127.0.0.1' ] );
			}
			$result = $gateway->doPayment();
		}

		$this->assertNotEquals( 0, count( $result->getErrors() ) );

		$messages = self::getAllQueueMessages();

		$this->assertCount( 3, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
				'validation_action' => 'process',
				'score_breakdown' => [ 'initial' => 0 ],
				'user_ip' => '127.0.0.1',
				'gateway' => 'braintree',
				'order_id' => $expectedMerchantRef
		];
		$this->assertArraySubmapSame(
				$expectedAntifraudInitial,
				$messages['payments-antifraud'][0]
		);
		$expectedAntiFraudProcess = [
				'validation_action' => \SmashPig\PaymentData\ValidationAction::REJECT,
				'score_breakdown' => [ 'IPDenyList' => 100 ],
				] + $expectedAntifraudInitial;
		$this->assertArraySubmapSame(
				$expectedAntiFraudProcess,
				$messages['payments-antifraud'][2]
		);
		$this->assertCount( 2, $messages['payments-init'] );
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

	protected function getTestDonor( $payment_method ): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'paypal';
		$init['payment_token'] = '65a502e5-2d09-02bd-545f-1cf6e15867c9';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		unset( $init['city'] );
		unset( $init['state_province'] );
		unset( $init['street_address'] );
		unset( $init['postal_code'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );
		return $init;
	}
}
