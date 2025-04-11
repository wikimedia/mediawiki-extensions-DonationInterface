<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\RedirectPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 * @coversNothing
 */
class PaypalTest extends BaseGravyTestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|RedirectPaymentProvider
	 */
	protected $redirectPaymentProvider;

	protected function setUp(): void {
		parent::setUp();

		$this->redirectPaymentProvider = $this->createMock( RedirectPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/paypal',
			$this->redirectPaymentProvider
		);
	}

	/**
	 * Based on the tests in RedirectFormTest
	 * We are getting the donors info from Paypal, so no data is sent in
	 */
	public function testProcessDonorReturnPaypal() {
		$init = $this->getTestDonorData();
		$init['amount'] = '1.55';
		$init['payment_method'] = 'paypal';
		$init['payment_submethod'] = '';
		$gateway = $this->getFreshGatewayObject( $init );
		$gravyTransactionId = 'ASD' . mt_rand( 100000, 1000000 );
		$paypalTransactionId = 'ZXC' . mt_rand( 100000, 1000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$responseData = $this->getDonorTestData();
		$expectedReturnUrl = Title::newFromText(
			'Special:GravyGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
			'amount' => $init['amount'],
			'currency' => $init['currency'],
			'payment_method' => $init['payment_method'],
			'payment_submethod' => $init['payment_submethod'],
			'wmf_source' => '..paypal'
		] );
		$approval_url = 'https://test-approval-url.com';
		$this->redirectPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'country' => 'US',
				'currency' => 'USD',
				'user_ip' => '127.0.0.1',
				'description' => 'Wikimedia Foundation',
				'order_id' => $expectedMerchantRef,
				'amount' => '1.55',
				'return_url' => $expectedReturnUrl,
				'payment_method' => 'paypal'
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'authorization_succeeded' )
					->setStatus( FinalStatus::PENDING )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setRedirectUrl( $approval_url )
			);
		$queryString = [
			"title" => "Special:GravyGatewayResult",
			"order_id" => $expectedMerchantRef,
			"wmf_token" => "random-token",
			"amount" => '1.55',
			"currency" => "USD",
			"payment_method" => "paypal",
			"wmf_source" => "..paypal",
			"transaction_id" => $gravyTransactionId,
			"transaction_status" => "authorization_succeeded"
		];
		$result = $gateway->doPayment();

		$this->redirectPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [
				'gateway_txn_id' => $gravyTransactionId
			] )
			->willReturn(
				( new PaymentProviderExtendedResponse() )
					->setRawStatus( 'authorization_succeeded' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setBackendProcessor( 'paypal' )
					->setBackendProcessorTransactionId( $paypalTransactionId )
					->setDonorDetails(
						( new DonorDetails() )
							->setEmail( $responseData['email'] )
							->setFirstName( $responseData['first_name'] )
							->setLastName( $responseData['last_name'] )
							->setBillingAddress(
								( new \SmashPig\PaymentData\Address() )
									->setStreetAddress( $responseData['street_address'] )
									->setCity( $responseData['city'] )
									->setPostalCode( $responseData['postal_code'] )
									->setStateOrProvinceCode( $responseData['state_province'] )
							)
					)

			);

		$this->redirectPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'currency' => 'USD',
				'amount' => '1.55',
				'gateway_txn_id' => $gravyTransactionId
			] )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( 'capture_succeeded' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setBackendProcessor( 'paypal' )
					->setBackendProcessorTransactionId( $paypalTransactionId )
			);

		$result = $gateway->processDonorReturn( $queryString );

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$queueMessage = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $queueMessage );
		SourceFields::removeFromMessage( $queueMessage );
		$this->assertArraySubmapSame( [
			'gross' => '1.55',
			'backend_processor' => 'paypal',
			'backend_processor_txn_id' => $paypalTransactionId,
			'currency' => 'USD',
			'gateway' => 'gravy',
			'gateway_txn_id' => $gravyTransactionId,
			'user_ip' => '127.0.0.1',
			'order_id' => $expectedMerchantRef,
			'email' => $responseData['email'],
			'first_name' => $responseData['first_name'],
			'last_name' => $responseData['last_name'],
			'street_address' => $responseData['street_address'],
			'city' => $responseData['city'],
			'state_province' => $responseData['state_province'],
			'postal_code' => $responseData['postal_code'],
			'utm_source' => '..paypal',
			'payment_method' => $init['payment_method'],
		], $queueMessage );
	}

	/**
	 * @return array
	 */
	protected function getTestDonorData(): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'paypal';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		// Remove all the contact info and email as it will come back on the response
		unset( $init['email'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );
		unset( $init['street_address'] );
		unset( $init['city'] );
		unset( $init['postal_code'] );
		unset( $init['state_province'] );
		return $init;
	}
}
