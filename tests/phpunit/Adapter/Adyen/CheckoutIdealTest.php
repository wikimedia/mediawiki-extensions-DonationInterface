<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\IdealBankTransferPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Adyen
 * @group AdyenCheckout
 * @group RealTimeBankTransfer
 */
class CheckoutIdealTest extends BaseAdyenCheckoutTestCase {
	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|IdealBankTransferPaymentProvider
	 */
	protected $idealPaymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$this->setLanguage( 'nl' );

		$this->idealPaymentProvider = $this->createMock( IdealBankTransferPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/rtbt',
			$this->idealPaymentProvider
		);
	}

	public function testDoPaymentIdeal() {
		$init = $this->getTestDonorIdealData();

		$gateway = $this->getFreshGatewayObject( $init );
		$expectedMerchantRef = $init['contribution_tracking_id'] . '.1';
		$expectedReturnUrl = Title::newFromText(
			'Special:AdyenCheckoutGatewayResult'
		)->getFullURL( [
			'order_id' => $expectedMerchantRef,
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
		] );
		$redirect = 'https://checkoutshopper-test.adyen.com/checkoutshopper/checkoutPaymentRedirect?redirectData=' . $this->redirectResult;
		$this->idealPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => '4.55',
				'country' => 'NL',
				'currency' => 'EUR',
				'description' => 'Wikimedia 877 600 9454',
				'email' => 'nobody@wikimedia.org',
				'first_name' => 'Voornaam',
				'issuer_id' => '12345',
				'last_name' => 'Achternaam',
				'order_id' => $expectedMerchantRef,
				'postal_code' => '0',
				'return_url' => $expectedReturnUrl,
				'street_address' => 'N0NE PROVIDED',
				'user_ip' => '127.0.0.1'
			] )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'RedirectShopper' )
					->setStatus( FinalStatus::PENDING )
					->setSuccessful( true )
					->setRedirectUrl( $redirect )
			);
		$this->idealPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$result = $gateway->doPayment();

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );
		$this->assertEquals( $redirect, $result->getRedirect() );

		$messages = $this->getAllQueueMessages();
		// We're just redirecting the donor at this point, so we shouldn't send a
		// message to the donations queue
		$this->assertCount( 0, $messages['donations'] );
		// No pending message is sent in doPayment - that happens in the API
		$this->assertCount( 0, $messages['pending'] );
		// Since the auth result is an immediate redirect, we should only run the
		// 'initial' round of antifraud.
		$this->assertCount( 1, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
			'validation_action' => 'process',
			'risk_score' => 0,
			'score_breakdown' => [ 'initial' => 0 ],
			'user_ip' => '127.0.0.1',
			'gateway' => 'adyen',
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'order_id' => $expectedMerchantRef,
			'payment_method' => 'rtbt',
		];
		$this->assertArraySubmapSame(
			$expectedAntifraudInitial,
			$messages['payments-antifraud'][0]
		);
	}

	public function testDonorReturnIdealSuccess() {
		$init = $this->getTestDonorIdealData();
		$init['order_id'] = $init['contribution_tracking_id'] . '.1';
		$session = [
			'Donor' => $init,
			'risk_scores' => [
				'getScoreUtmMedium' => 10,
			]
		];
		$queryString = [
			'order_id' => $init['order_id'],
			'wmf_token' => $this->saltedToken,
			'redirectResult' => $this->redirectResult
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$this->setUpRequest( $queryString, $session );

		$this->idealPaymentProvider->expects( $this->once() )
			->method( 'getHostedPaymentDetails' )
			->with( $this->redirectResult )
			->willReturn(
				( new PaymentDetailResponse() )
					->setRawStatus( 'Authorized' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $pspReferenceAuth )
			);
		// approvePayment is not needed for iDEAL
		$this->idealPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( $queryString );

		$this->assertFalse( $result->isFailed() );
		$this->assertSame( [], $result->getErrors() );

		$messages = $this->getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
		$expectedQueueMessage = [
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'NL',
			'currency' => 'EUR',
			'email' => 'nobody@wikimedia.org',
			'first_name' => 'Voornaam',
			'gateway' => 'adyen',
			'gateway_txn_id' => $pspReferenceAuth,
			'gross' => '4.55',
			'language' => 'nl',
			'last_name' => 'Achternaam',
			'order_id' => $init['order_id'],
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'user_ip' => '127.0.0.1'
		];
		$this->assertArraySubmapSame(
			$expectedQueueMessage,
			$messages['donations'][0]
		);
		// No pending message, we just came back from redirect
		$this->assertCount( 0, $messages['pending'] );
		// No antifraud message for iDEAL - after they come back
		// from redirect we have no chance to veto the payment.
		$this->assertCount( 0, $messages['payments-antifraud'] );
		$this->assertCount( 1, $messages['payments-init'] );
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'complete',
				'payment_submethod' => 'rtbt_ideal',
				'country' => 'NL',
				'amount' => '4.55',
				'currency' => 'EUR',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $init['order_id'],
				'payment_method' => 'rtbt',
			],
			$messages['payments-init'][0]
		);
	}

	/**
	 * Test error handling on iDEAL decline
	 */
	public function testDonorReturnIdealFailure() {
		$init = $this->getTestDonorIdealData();
		$init['order_id'] = $init['contribution_tracking_id'] . '.1';
		$session = [
			'Donor' => $init,
			'risk_scores' => [
				'getScoreUtmMedium' => 10,
			]
		];
		$queryString = [
			'order_id' => $init['order_id'],
			'wmf_token' => $this->saltedToken,
			'redirectResult' => $this->redirectResult
		];
		$pspReferenceAuth = 'ASD' . mt_rand( 100000, 1000000 );
		$this->setUpRequest( $queryString, $session );

		$this->idealPaymentProvider->expects( $this->once() )
			->method( 'getHostedPaymentDetails' )
			->with( $this->redirectResult )
			->willReturn(
				( new PaymentDetailResponse() )
					->setRawStatus( 'Refused' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->setGatewayTxnId( $pspReferenceAuth )
			);
		$this->idealPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$gateway = $this->getFreshGatewayObject( [] );
		$result = $gateway->processDonorReturn( $queryString );

		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		// Failed donations should not go to the donations queue
		$this->assertCount( 0, $messages['donations'] );
		// No pending message - we just came back from redirect
		$this->assertCount( 0, $messages['pending'] );
		// No antifraud message for iDEAL - after they come back
		// from redirect we have no chance to veto the payment.
		$this->assertCount( 0, $messages['payments-antifraud'] );
		$this->assertArraySubmapSame(
			[
				'validation_action' => 'process',
				'payments_final_status' => 'failed',
				'payment_submethod' => 'rtbt_ideal',
				'country' => 'NL',
				'amount' => '4.55',
				'currency' => 'EUR',
				'gateway' => 'adyen',
				'gateway_txn_id' => $pspReferenceAuth,
				'contribution_tracking_id' => $init['contribution_tracking_id'],
				'order_id' => $init['order_id'],
				'payment_method' => 'rtbt',
			],
			$messages['payments-init'][0]
		);
	}

	/**
	 * @return array
	 */
	protected function getTestDonorIdealData(): array {
		$init = $this->getDonorTestData( 'NL' );
		$init['payment_method'] = 'rtbt';
		$init['payment_submethod'] = 'rtbt_ideal';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		$init['issuer_id'] = '12345';
		$init['email'] = 'nobody@wikimedia.org';
		unset( $init['city'] );
		unset( $init['postal_code'] );
		unset( $init['state_province'] );
		unset( $init['street_address'] );
		return $init;
	}

}
