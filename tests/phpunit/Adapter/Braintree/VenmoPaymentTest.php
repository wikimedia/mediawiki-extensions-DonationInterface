<?php

use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\VenmoPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Braintree
 */
class VenmoPaymentTest extends BaseBraintreeTestCase {
	protected $testAdapterClass = BraintreeAdapter::class;

	/**
	 * @var \SmashPig\Tests\TestingProviderConfiguration
	 */
	protected $providerConfig;

	/**
	 * @var \SmashPig\PaymentProviders\Braintree\VenmoPaymentProvider
	 */
	protected $venmoPaymentProvider;

	public function setUp(): void {
		parent::setUp();

		$this->venmoPaymentProvider = $this->createMock( VenmoPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/venmo',
			$this->venmoPaymentProvider
		);
	}

	/**
	 * Integration test to verify that the authorize and capture transactions
	 * send the expected parameters to the SmashPig library objects and that
	 * they return the expected result when the API calls are successful.
	 */
	public function testVenmoDoPayment() {
		$init = $this->getTestDonor( "venmo" );
		$init['amount'] = '1';
		$gateway = $this->getFreshGatewayObject( $init );
		$expectedPaymentToken = $init['payment_token'];
		$expectedContributionTrackingId = $init['contribution_tracking_id'];
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedGatewayId = (string)mt_rand( 1000000, 10000000 );

		$donorDetailsResponse = new DonorDetails();
		$donorDetailsResponse->setFirstName( "John" );
		$donorDetailsResponse->setLastName( "Doe" );
		$donorDetailsResponse->setEmail( "nobody@wikimedia.org" );

		$createPaymentResponse = ( new CreatePaymentResponse() )
			->setRawStatus( 'SETTLING' )
			->setStatus( FinalStatus::COMPLETE )
			->setSuccessful( true )
			->setGatewayTxnId( $expectedGatewayId );

		$createPaymentResponse->setDonorDetails( $donorDetailsResponse );

		$this->venmoPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'email' => 'nobody@wikimedia.org',
				'amount' => '1.00',
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
		$this->assertCount( 0, $messages['pending'] );

		$expectedPaymentsInitQueueMessage = [
			'validation_action' => 'process',
			'payments_final_status' => 'complete',
			'country' => 'US',
			'currency' => 'USD',
			'gateway' => 'braintree',
			'payment_method' => 'venmo',
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
			'payment_method' => 'venmo',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'language' => 'en',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'nobody@wikimedia.org',
			'gross' => '1.00',
			'user_ip' => '127.0.0.1',
		];

		$this->assertArraySubmapSame(
			$expectedDonationsQueueMessage,
			$messages['donations'][0]
		);
	}
}
