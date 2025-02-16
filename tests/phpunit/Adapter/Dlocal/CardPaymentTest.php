<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\CardPaymentProvider;
use SmashPig\PaymentProviders\dlocal\ErrorMapper;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Dlocal
 * @coversNothing
 */
class CardPaymentTest extends BaseDlocalTestCase {

	/** @var CardPaymentProvider */
	protected $cardPaymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->cardPaymentProvider
		);
	}

	public function testDoCardPayment(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );
		$authID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c6515";
		$captureID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c7200";
		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );
		$expectedApprovePaymentParams = $this->getApprovePaymentParams( $testDonorData, $authID );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'AUTHORIZED' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $authID )
			);

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $expectedApprovePaymentParams )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( 'PAID' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $captureID )
			);

		$result = $DlocalAdapter->doPayment();
		$this->assertFalse( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
	}

	public function testDoCardPaymentWithRecurring(): void {
		$testDonorData = $this->getTestDonorCardData();
		$testDonorData['recurring'] = 1;

		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		$authID = 'D-2486-91e73695-3e0a-4a77-8594-f2220f8c6515';
		$captureID = 'D-2486-91e73695-3e0a-4a77-8594-f2220f8c7200';
		$cardID = 'CID-d69850ea-37fe-4392-abdd-402cca966e51';

		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );

		$expectedApprovePaymentParams = $this->getApprovePaymentParams( $testDonorData, $authID );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'AUTHORIZED' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $authID )
					->setRecurringPaymentToken( $cardID )
			);

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $expectedApprovePaymentParams )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( 'PAID' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $captureID )
			);

		$result = $DlocalAdapter->doPayment();
		$this->assertFalse( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );

		$donationsMsg = $messages['donations'][0];
		$this->assertEquals( $cardID, $donationsMsg['recurring_payment_token'] );
		$this->assertEquals( $testDonorData['fiscal_number'], $donationsMsg['fiscal_number'] );
	}

	public function testDoCardPaymentCreatePaymentFail(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'REJECTED ' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->addErrors(
						new PaymentError(
							ErrorMapper::$paymentStatusErrorCodes['300'],
							"The payment was rejected.",
							LogLevel::ERROR
						)
					)
			);

		$this->cardPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );
		$result = $DlocalAdapter->doPayment();
		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 0, $messages['donations'] );
	}

	public function testDoCardPaymentValidationError(): void {
		$testDonorData = $this->getTestDonorCardData();
		$adapter = $this->getFreshGatewayObject( $testDonorData );
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn(
				( new CreatePaymentResponse() )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->addValidationError(
						new ValidationError(
							'fiscal_number',
							null,
							[],
							'Invalid parameter: payer.document'
						)
					)
			);
		$result = $adapter->doPayment();
		$this->assertTrue( $result->getRefresh() );
		$this->assertCount( 1, $result->getErrors() );
		$this->assertSame( 'fiscal_number', $result->getErrors()[0]->getField() );
	}

	public function testDoPaymentsInitQueueCountSuccessfulPayment(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );
		$authID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c6515";
		$captureID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c7200";
		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );
		$expectedApprovePaymentParams = $this->getApprovePaymentParams( $testDonorData, $authID );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'AUTHORIZED' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $authID )
			);

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $expectedApprovePaymentParams )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( 'PAID' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $captureID )
			);

		$result = $DlocalAdapter->doPayment();
		$this->assertFalse( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
		$this->assertCount( 1, $messages['payments-init'] );
	}

	public function testDoPaymentsInitQueueCountFailedPayment(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );
		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'REJECTED ' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->addErrors(
						new PaymentError(
							ErrorMapper::$paymentStatusErrorCodes['300'],
							"The payment was rejected.",
							LogLevel::ERROR
						)
					)
			);

		$this->cardPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );

		$result = $DlocalAdapter->doPayment();
		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['payments-init'] );
		$this->assertCount( 0, $messages['donations'] );
	}

	public function testDoPaymentsAntiFraudQueueCountSuccessfulPayment(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );
		$authID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c6515";
		$captureID = "D-2486-91e73695-3e0a-4a77-8594-f2220f8c7200";
		$contributionTrackingID = $testDonorData['contribution_tracking_id'];
		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );
		$expectedApprovePaymentParams = $this->getApprovePaymentParams( $testDonorData, $authID );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'AUTHORIZED' )
					->setStatus( FinalStatus::PENDING_POKE )
					->setSuccessful( true )
					->setGatewayTxnId( $authID )
			);

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $expectedApprovePaymentParams )
			->willReturn(
				( new ApprovePaymentResponse() )
					->setRawStatus( 'PAID' )
					->setStatus( FinalStatus::COMPLETE )
					->setSuccessful( true )
					->setGatewayTxnId( $captureID )
			);

		$result = $DlocalAdapter->doPayment();
		$this->assertFalse( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
		$this->assertCount( 1, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
			'validation_action' => 'process',
			'risk_score' => 0,
			'score_breakdown' => [ 'initial' => 0 ],
			'user_ip' => '127.0.0.1',
			'gateway' => 'dlocal',
			'contribution_tracking_id' => $contributionTrackingID,
			'order_id' => $testDonorData['order_id'],
			'payment_method' => 'cc',
		];
		$this->assertArraySubmapSame(
			$expectedAntifraudInitial,
			$messages['payments-antifraud'][0]
		);
	}

	public function testDoPaymentsAntiFraudQueueCountFailedPayment(): void {
		$testDonorData = $this->getTestDonorCardData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );
		$contributionTrackingID = $testDonorData['contribution_tracking_id'];

		$expectedCapturePaymentParams = $this->getCreatePaymentParams( $DlocalAdapter );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedCapturePaymentParams )
			->willReturn(
				( new CreatePaymentResponse() )
					->setRawStatus( 'REJECTED ' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->addErrors(
						new PaymentError(
							ErrorMapper::$paymentStatusErrorCodes['300'],
							"The payment was rejected.",
							LogLevel::ERROR
						)
					)
			);

		$this->cardPaymentProvider->expects( $this->never() )
			->method( 'approvePayment' );
		$result = $DlocalAdapter->doPayment();
		$this->assertTrue( $result->isFailed() );

		$messages = self::getAllQueueMessages();
		$this->assertCount( 0, $messages['donations'] );
		$this->assertCount( 1, $messages['payments-antifraud'] );
		$expectedAntifraudInitial = [
			'validation_action' => 'process',
			'risk_score' => 0,
			'score_breakdown' => [ 'initial' => 0 ],
			'user_ip' => '127.0.0.1',
			'gateway' => 'dlocal',
			'contribution_tracking_id' => $contributionTrackingID,
			'order_id' => $testDonorData['order_id'],
			'payment_method' => 'cc',
		];
		$this->assertArraySubmapSame(
			$expectedAntifraudInitial,
			$messages['payments-antifraud'][0]
		);
	}

	/**
	 * We've had instances of dlocal redirecting donors back to us with old order_id in the query string, even though the
	 * donor has no associated browser session. This results in a new ct_id being generated alongside the old order_id
	 * and when used downstream, it's triggering failmail in payment attempts as the old order_id is sent to
	 * dlocal and treated as a duplicate, which dlocal can't handle.
	 *
	 * This test confirms that when this scenario occurs, we will reset the order_id using the standard pattern
	 * of $contribution_tracking_id.$sequence, e.g. 12345.1, to avoid the mismatch errors.
	 *
	 * See https://phabricator.wikimedia.org/T334905 for more info
	 */
	public function testMismatchOrderIdIsReset(): void {
		$testDonorData = $this->getTestDonorCardData();
		$testDonorData['contribution_tracking_id'] = 54321;

		// deliberately set the order_id to not match the contribution_tracking_id
		$badOrderId = 12345;
		$testDonorData['order_id'] = $badOrderId;

		// instantiate the dLocal adapter and send in our setup hack flag and bad order_id
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		// fetch the order id generated during the adapter setup (bad order id should have been detected and reset)
		$orderId = $DlocalAdapter->getData_Unstaged_Escaped( 'order_id' );

		// confirm that the adapter order_id has been reset and is NOT the one sent in to the adapter constructor above.
		$goodOrderId = '54321.1';
		$this->assertEquals( $goodOrderId, $orderId );
	}

	/**
	 * @return array
	 */
	protected function getTestDonorCardData(): array {
		$testDonorData = $this->getDonorTestData( 'IN' );
		$contributionTrackingId = (string)mt_rand( 1000000, 10000000 );
		$orderId = $contributionTrackingId . '.1';
		$testDonorData['payment_method'] = 'cc';
		$testDonorData['payment_submethod'] = 'visa';
		$testDonorData['payment_token'] = 'D' . '-' . mt_rand( 1000000, 10000000 );
		$testDonorData['user_ip'] = '127.0.0.1';
		$testDonorData['contribution_tracking_id'] = $contributionTrackingId;
		$testDonorData['amount'] = '1.55';
		$testDonorData['postal_code'] = '23111';
		$testDonorData['city'] = 'Mumbai';
		$testDonorData['order_id'] = $orderId;
		$testDonorData['fiscal_number'] = '123456789';

		return $testDonorData;
	}

	/**
	 * @param GatewayAdapter $gateway
	 * @return array
	 */
	protected function getCreatePaymentParams( GatewayAdapter $gateway ): array {
		$testDonorData = $gateway->getData_Unstaged_Escaped();
		$params = [];
		$params['payment_token'] = $testDonorData['payment_token'];
		$params['payment_submethod'] = $testDonorData['payment_submethod'];
		$params['amount'] = $testDonorData['amount'];
		$params['city'] = $testDonorData['city'];
		$params['country'] = $testDonorData['country'];
		$params['currency'] = $testDonorData['currency'];
		$params['description'] = WmfFramework::formatMessage( 'donate_interface-donation-description' );
		$params['email'] = $testDonorData['email'];
		$params['first_name'] = $testDonorData['first_name'];
		$params['last_name'] = $testDonorData['last_name'];
		$params['order_id'] = $testDonorData['order_id'];
		$params['postal_code'] = $testDonorData['postal_code'];
		$params['user_ip'] = $testDonorData['user_ip'];
		$params['street_address'] = $testDonorData['street_address'];
		$params['fiscal_number'] = $testDonorData['fiscal_number'];

		$returnUrlQueryParams = [
			'order_id' => $params['order_id'],
			'wmf_token' => $gateway->token_getSaltedSessionToken(),
			'amount' => $testDonorData['amount'],
			'currency' => $testDonorData['currency'],
			'payment_method' => $testDonorData['payment_method'],
			'payment_submethod' => $testDonorData['payment_submethod'],
			'wmf_source' => $testDonorData['utm_source'] ?? '..cc'
		];

		$recurring = $testDonorData['recurring'] ?? null;
		if ( $recurring ) {
			$params['recurring'] = $recurring;
			$returnUrlQueryParams['recurring'] = $recurring;
		}

		$expectedReturnUrl = Title::newFromText(
			'Special:DlocalGatewayResult'
		)->getFullURL( $returnUrlQueryParams );

		$params['return_url'] = $expectedReturnUrl;
		return $params;
	}

	/**
	 * @param array $testDonorData
	 * @param string $authID
	 * @return array
	 */
	protected function getApprovePaymentParams( $testDonorData, $authID ): array {
		return [
			'amount' => $testDonorData['amount'],
			'currency' => $testDonorData['currency'],
			'gateway_txn_id' => $authID,
			'order_id' => $testDonorData['order_id']
		];
	}
}
