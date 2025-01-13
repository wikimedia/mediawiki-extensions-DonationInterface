<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ApplePayPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 */
class ApplePayTest extends BaseGravyTestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|ApplePayPaymentProvider
	 */
	protected $applePayPaymentProvider;

	protected function setUp(): void {
		parent::setUp();

		$this->applePayPaymentProvider = $this->createMock( ApplePayPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/apple',
			$this->applePayPaymentProvider
		);
	}

	/**
	 * Integration test to verify that the authorize and capture transactions
	 * send the expected parameters to the SmashPig library objects and that
	 * they return the expected result when the API calls are successful.
	 */
	public function testDoPayment() {
		$init = $this->getTestapplePayData();
		$init['amount'] = '1.55';
		$init['payment_token'] = 'random-token';
		$init['payment_submethod'] = 'visa';
		$gateway = $this->getFreshGatewayObject( $init );
		$gravyTransactionId = 'ASD' . mt_rand( 100000, 1000000 );
		$adyenTransactionId = 'ZXC' . mt_rand( 100000, 1000000 );
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
			'wmf_source' => '..apple_visa'
		] );

		$this->applePayPaymentProvider->expects( $this->once() )
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
				'payment_method' => $init['payment_method'],
				'payment_token' => $init['payment_token'],
				'payment_submethod' => $init['payment_submethod']
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'authorization_succeeded' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $gravyTransactionId )
					->setBackendProcessor( 'adyen' )
					->setBackendProcessorTransactionId( $adyenTransactionId )
			);

		$this->applePayPaymentProvider->expects( $this->once() )
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
					->setBackendProcessor( 'adyen' )
					->setBackendProcessorTransactionId( $adyenTransactionId )
			);

		$result = $gateway->doPayment();

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$queueMessage = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $queueMessage );
		SourceFields::removeFromMessage( $queueMessage );
		$this->assertArraySubmapSame( [
			'gross' => '1.55',
			'backend_processor' => 'adyen',
			'backend_processor_txn_id' => $adyenTransactionId,
			'currency' => 'USD',
			'gateway' => 'gravy',
			'gateway_txn_id' => $gravyTransactionId,
			'user_ip' => '127.0.0.1',
			'payment_submethod' => $init['payment_submethod'],
			'order_id' => $expectedMerchantRef,
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'postal_code' => '94105',
			'street_address' => '123 Fake Street',
			'utm_source' => '..apple_visa',
			'payment_method' => $init['payment_method'],
		], $queueMessage );
	}

	/**
	 * @return array
	 */
	protected function getTestapplePayData(): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = 'apple';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		unset( $init['city'] );
		unset( $init['state_province'] );
		return $init;
	}
}
