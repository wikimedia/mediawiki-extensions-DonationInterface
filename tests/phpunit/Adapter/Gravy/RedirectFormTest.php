<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\RedirectPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 */
class RedirectFormTest extends BaseGravyTestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|RedirectPaymentProvider
	 */
	protected $redirectPaymentProvider;

	protected function setUp(): void {
		parent::setUp();

		$this->redirectPaymentProvider = $this->createMock( RedirectPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/venmo',
			$this->redirectPaymentProvider
		);
	}

	/**
	 * Integration test to verify that the authorize and capture transactions
	 * send the expected parameters to the SmashPig library objects and that
	 * they return the expected result when the API calls are successful.
	 */
	public function testDoPaymentVenmo() {
		$init = $this->getTestDonorData();
		$init['amount'] = '1.55';
		$init['payment_method'] = 'venmo';
		$init['payment_submethod'] = '';
		$gateway = $this->getFreshGatewayObject( $init );
		$gravyTransactionId = 'ASD' . mt_rand( 100000, 1000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:GravyGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
			'amount' => $init['amount'],
			'currency' => $init['currency'],
			'payment_method' => $init['payment_method'],
			'payment_submethod' => $init['payment_submethod'],
			'wmf_source' => '..venmo'
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
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Firstname',
				'last_name' => 'Surname',
				'postal_code' => '94105',
				'street_address' => '123 Fake Street',
				'return_url' => $expectedReturnUrl,
				'payment_method' => 'venmo',
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'authorization_succeeded' )
					->setStatus( FinalStatus::PENDING )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setRedirectUrl( $approval_url )
			);

		$result = $gateway->doPayment();
		$gateway->logPending();
		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$this->assertSame( $approval_url, $result->getRedirect() );

		$queueMessage = QueueWrapper::getQueue( 'pending' )->pop();

		$this->assertNotNull( $queueMessage );
		SourceFields::removeFromMessage( $queueMessage );
		$this->assertArraySubmapSame( [
			"gateway_txn_id" => $gravyTransactionId,
			"response" => false,
			"gateway_account" => "WikimediaDonations",
			"fee" => 0,
			"contribution_tracking_id" => $init['contribution_tracking_id'],
			"utm_source" => "..venmo",
			"language" => "en",
			"email" => $init['email'],
			"first_name" => $init['first_name'],
			"last_name" => $init['last_name'],
			"street_address" => $init['street_address'],
			"country" => "US",
			"postal_code" => $init['postal_code'],
			"gateway" => "gravy",
			"order_id" => $expectedMerchantRef,
			"recurring" => "",
			"payment_method" => "venmo",
			"payment_submethod" => "",
			"currency" => "USD",
			"gross" => "1.55",
			"user_ip" => "127.0.0.1",
		], $queueMessage );
	}

	public function testProcessDonorReturnVenmo() {
		$init = $this->getTestDonorData();
		$init['amount'] = '1.55';
		$init['payment_method'] = 'venmo';
		$init['payment_submethod'] = '';
		$gateway = $this->getFreshGatewayObject( $init );
		$gravyTransactionId = 'ASD' . mt_rand( 100000, 1000000 );
		$braintreeTransactionId = 'ZXC' . mt_rand( 100000, 1000000 );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:GravyGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
			'amount' => $init['amount'],
			'currency' => $init['currency'],
			'payment_method' => $init['payment_method'],
			'payment_submethod' => $init['payment_submethod'],
			'wmf_source' => '..venmo'
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
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Firstname',
				'last_name' => 'Surname',
				'postal_code' => '94105',
				'street_address' => '123 Fake Street',
				'return_url' => $expectedReturnUrl,
				'payment_method' => 'venmo',
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
			"amount" => $init['amount'],
			"currency" => "USD",
			"payment_method" => "venmo",
			"utm_source" => "..venmo",
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
				( new PaymentDetailResponse() )
					->setRawStatus( 'authorization_succeeded' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setDonorDetails(
						( new DonorDetails() )
						->setUserName( 'testy-venmo-tester' )
					)
					->setBackendProcessor( 'braintree' )
					->setBackendProcessorTransactionId( $braintreeTransactionId )
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
			);

		$result = $gateway->processDonorReturn( $queryString );

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$queueMessage = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $queueMessage );
		SourceFields::removeFromMessage( $queueMessage );
		$this->assertArraySubmapSame( [
			'gross' => '1.55',
			'backend_processor' => 'braintree',
			'backend_processor_txn_id' => $braintreeTransactionId,
			'currency' => 'USD',
			'gateway' => 'gravy',
			'gateway_txn_id' => $gravyTransactionId,
			'user_ip' => '127.0.0.1',
			'order_id' => $expectedMerchantRef,
			'email' => $init['email'],
			'first_name' => $init['first_name'],
			'last_name' => $init['last_name'],
			'postal_code' => $init['postal_code'],
			'street_address' => $init['street_address'],
			'utm_source' => '..venmo',
			'external_identifier' => 'testy-venmo-tester',
			'payment_method' => $init['payment_method'],
		], $queueMessage );
	}

	/**
	 * @return array
	 */
	protected function getTestDonorData(): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		unset( $init['city'] );
		unset( $init['state_province'] );
		return $init;
	}
}
