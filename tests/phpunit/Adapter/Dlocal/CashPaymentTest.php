<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\HostedPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Dlocal
 */
class CashPaymentTest extends BaseDlocalTestCase {

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	protected $hostedPaymentProvider;

	public function setUp(): void {
		parent::setUp();
		$this->testAdapterClass = TestingDlocalAdapter::class;
		$this->hostedPaymentProvider = $this->createMock( HostedPaymentProvider::class );
		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cash',
			$this->hostedPaymentProvider
		);
	}

	/**
	 * @covers \DlocalAdapter::doPayment
	 */
	public function testCashPaymentTriggersRedirect(): void {
		$testDonorData = self::getDlocalDonorTestData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		$testRedirectUrl = "https://sandbox.dlocal.com/collect/select_payment_method?id=M-2ad123b3-7a88-453b-9b92-566d490e30ce&xtid=CATH-ST-1675442706-678241113";
		$mockCreateHostedPaymentResponse = new CreatePaymentResponse();
		$mockCreateHostedPaymentResponse->setRawStatus( 'PENDING' )
			->setStatus( FinalStatus::PENDING )
			->setSuccessful( true )
			->setGatewayTxnId( "D-2486-590840f8-b68a-4802-a507-575daca6f2a5" )
			->setRedirectUrl( $testRedirectUrl );

		$this->hostedPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->with( $this->callback( function ( $params ) use ( $testDonorData ) {
				$this->assertEquals( $params['amount'], $testDonorData['amount'] );
				$this->assertEquals( $params['currency'], $testDonorData['currency'] );
				$this->assertEquals( $params['country'], $testDonorData['country'] );
				$this->assertEquals( $params['order_id'], $testDonorData['order_id'] );
				$this->assertEquals( $params['first_name'], $testDonorData['first_name'] );
				$this->assertEquals( $params['last_name'], $testDonorData['last_name'] );
				$this->assertEquals( $params['email'], $testDonorData['email'] );
				$this->assertEquals( $params['fiscal_number'], $testDonorData['fiscal_number'] );
				$this->assertEquals( $params['payment_submethod'], $testDonorData['payment_submethod'] );
				return true;
			} ) )
			->willReturn( $mockCreateHostedPaymentResponse );

		$expectedRedirectPaymentResult = PaymentResult::newRedirect( $testRedirectUrl );
		$actualResult = $DlocalAdapter->doPayment();
		$this->assertEquals( $expectedRedirectPaymentResult, $actualResult );
		$this->assertEquals( $actualResult->getRedirect(), $testRedirectUrl );
	}

	public function testCashPaymentDonorReturnPaid(): void {
		$testDonorData = self::getDlocalDonorTestData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		// this data is sent over with the donor when they are redirected back to our site from dlocal servers
		$mockRedirectReturnValue = file_get_contents( __DIR__ . '/../../includes/Responses/dlocal/CashPaymentRedirectPaid.response' );
		$mockRedirectReturnValueToArray = [];
		parse_str( $mockRedirectReturnValue, $mockRedirectReturnValueToArray );

		// this is the smashpig getLatestPaymentStatus response set up to simulate a paid status result
		$mockPaymentDetailResponse = new PaymentDetailResponse();
		$mockPaymentDetailResponse->setGatewayTxnId( $mockRedirectReturnValueToArray['payment_id'] );
		$mockPaymentDetailResponse->setRawStatus( 'PAID' );
		$mockPaymentDetailResponse->setStatus( FinalStatus::COMPLETE );
		$mockPaymentDetailResponse->setSuccessful( true );

		$expectedGetHostedPaymentDetailsParams = [
			'gateway_txn_id' => $mockRedirectReturnValueToArray['payment_id'],
		];

		$this->hostedPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( $expectedGetHostedPaymentDetailsParams )
			->willReturn( $mockPaymentDetailResponse );

		// process the return
		$result = $DlocalAdapter->processDonorReturn( $mockRedirectReturnValueToArray );

		// confirm the result is successful
		$this->assertFalse( $result->isFailed() );
		$status = $DlocalAdapter->getFinalStatus();
		$this->assertEquals( FinalStatus::COMPLETE, $status );

		// confirm the donation queue message is added
		$messages = self::getAllQueueMessages();
		$this->assertCount( 1, $messages['donations'] );
	}

	public function testCashPaymentDonorReturnRejected(): void {
		$testDonorData = self::getDlocalDonorTestData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		// this data is sent over with the donor when they are redirected back to our site from dlocal servers
		$mockRedirectReturnValue = file_get_contents( __DIR__ . '/../../includes/Responses/dlocal/CashPaymentRedirectRejected.response' );
		$mockRedirectReturnValueToArray = [];
		parse_str( $mockRedirectReturnValue, $mockRedirectReturnValueToArray );

		// this is the smashpig getLatestPaymentStatus response set up to simulate a paid status result
		$mockPaymentDetailResponse = new PaymentDetailResponse();
		$mockPaymentDetailResponse->setGatewayTxnId( $mockRedirectReturnValueToArray['payment_id'] );
		$mockPaymentDetailResponse->setRawStatus( 'REJECTED' );
		$mockPaymentDetailResponse->setStatus( FinalStatus::FAILED );
		$mockPaymentDetailResponse->setSuccessful( false );

		$expectedGetHostedPaymentDetailsParams = [
			'gateway_txn_id' => $mockRedirectReturnValueToArray['payment_id'],
		];

		$this->hostedPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( $expectedGetHostedPaymentDetailsParams )
			->willReturn( $mockPaymentDetailResponse );

		// process the return
		$result = $DlocalAdapter->processDonorReturn( $mockRedirectReturnValueToArray );
		$this->assertTrue( $result->isFailed() );
		$status = $DlocalAdapter->getFinalStatus();
		$this->assertEquals( FinalStatus::FAILED, $status );

		// confirm the donation queue message is not added
		$messages = self::getAllQueueMessages();
		$this->assertCount( 0, $messages['donations'] );
	}

	public function testCashPaymentDonorReturnCancelled(): void {
		$testDonorData = self::getDlocalDonorTestData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		// this data is sent over with the donor when they are redirected back to our site from dlocal servers
		$mockRedirectReturnValue = file_get_contents( __DIR__ . '/../../includes/Responses/dlocal/CashPaymentRedirectCancelled.response' );
		$mockRedirectReturnValueToArray = [];
		parse_str( $mockRedirectReturnValue, $mockRedirectReturnValueToArray );

		// this is the smashpig getLatestPaymentStatus response set up to simulate a paid status result
		$mockPaymentDetailResponse = new PaymentDetailResponse();
		$mockPaymentDetailResponse->setGatewayTxnId( $mockRedirectReturnValueToArray['payment_id'] );
		$mockPaymentDetailResponse->setRawStatus( 'CANCELLED' );
		$mockPaymentDetailResponse->setStatus( FinalStatus::FAILED );
		$mockPaymentDetailResponse->setSuccessful( false );

		$expectedGetHostedPaymentDetailsParams = [
			'gateway_txn_id' => $mockRedirectReturnValueToArray['payment_id'],
		];

		$this->hostedPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( $expectedGetHostedPaymentDetailsParams )
			->willReturn( $mockPaymentDetailResponse );

		// process the return
		$result = $DlocalAdapter->processDonorReturn( $mockRedirectReturnValueToArray );
		$this->assertTrue( $result->isFailed() );
		$status = $DlocalAdapter->getFinalStatus();
		$this->assertEquals( FinalStatus::FAILED, $status );

		// confirm the donation queue message is not added
		$messages = self::getAllQueueMessages();
		$this->assertCount( 0, $messages['donations'] );
	}

	public function testCashPaymentDonorReturnPending(): void {
		$testDonorData = self::getDlocalDonorTestData();
		$DlocalAdapter = $this->getFreshGatewayObject( $testDonorData );

		// this data is sent over with the donor when they are redirected back to our site from dlocal servers
		$mockRedirectReturnValue = file_get_contents( __DIR__ . '/../../includes/Responses/dlocal/CashPaymentRedirectPending.response' );
		$mockRedirectReturnValueToArray = [];
		parse_str( $mockRedirectReturnValue, $mockRedirectReturnValueToArray );

		// this is the smashpig getLatestPaymentStatus response set up to simulate a paid status result
		$mockPaymentDetailResponse = new PaymentDetailResponse();
		$mockPaymentDetailResponse->setGatewayTxnId( $mockRedirectReturnValueToArray['payment_id'] );
		$mockPaymentDetailResponse->setRawStatus( 'PENDING' );
		$mockPaymentDetailResponse->setStatus( FinalStatus::PENDING );
		$mockPaymentDetailResponse->setSuccessful( true );

		$expectedGetHostedPaymentDetailsParams = [
			'gateway_txn_id' => $mockRedirectReturnValueToArray['payment_id'],
		];

		$this->hostedPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( $expectedGetHostedPaymentDetailsParams )
			->willReturn( $mockPaymentDetailResponse );

		// process the return
		$result = $DlocalAdapter->processDonorReturn( $mockRedirectReturnValueToArray );
		$this->assertFalse( $result->isFailed() );
		$status = $DlocalAdapter->getFinalStatus();
		$this->assertEquals( FinalStatus::PENDING, $status );

		// confirm the donation queue message is not added
		$messages = self::getAllQueueMessages();
		$this->assertCount( 0, $messages['donations'] );
	}

	protected static function getDlocalDonorTestData(): array {
		$testDonorData = self::getDonorTestData( 'MX' );
		$testDonorData['payment_method'] = 'cash';
		$testDonorData['payment_submethod'] = 'test_cash_payment_method';
		$testDonorData['fiscal_number'] = '42243309114';
		$testDonorData['payment_token'] = 'D' . '-' . random_int( 1000000, 10000000 );
		$testDonorData['user_ip'] = '127.0.0.1';
		$testDonorData['contribution_tracking_id'] = random_int( 1000000, 10000000 );
		$testDonorData['amount'] = '1.55';
		$testDonorData['postal_code'] = '23111';
		$testDonorData['order_id'] = $testDonorData['contribution_tracking_id'] . '.1';

		return $testDonorData;
	}

}
