<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @covers \RecurringConversionApi
 */
class RecurringConversionApiTest extends DonationInterfaceApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'GravyGatewayEnabled' => true,
		] );

		$ctx = TestingContext::get();
		$providerConfig = TestingProviderConfiguration::createForProvider(
			'gravy', $ctx->getGlobalConfiguration()
		);
		$ctx->providerConfigurationOverride = $providerConfig;
		// The conversion only queues messages, so it should never call the processor
		$providerConfig->overrideObjectInstance(
			'payment-provider/cc', $this->createNoOpMock( CardPaymentProvider::class )
		);
	}

	/**
	 * The conversion is built from the Donor backup that the completed one-time
	 * donation left in session, and both session keys are cleared afterwards.
	 */
	public function testConvertsDonationFromBackup(): void {
		$backup = $this->getDonorBackup( '12340.1' );

		$apiResult = $this->doApiRequest( $this->getApiParams(), $this->getSession( $backup ) );
		$this->assertArrayNotHasKey( 'errors', $apiResult[0]['result'] );

		$message = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertSame( 'subscr_signup', $message['txn_type'] );
		$this->assertSame( 'T1234-5432-9876', $message['recurring_payment_token'] );
		$this->assertSame( 'TXN-999-1234', $message['subscr_id'] );
		$this->assertSame( 'good@innocent.com', $message['email'] );
		$this->assertSame( '12340.1', $message['order_id'] );

		$sessionAfter = $apiResult[2];
		$this->assertNull( $sessionAfter[GatewayAdapter::DONOR_BKUP] ?? null );
		$this->assertNull( $sessionAfter[GatewayAdapter::DONOR] ?? null );
	}

	/**
	 * The first request to convert a donation claims its order ID. A second request
	 * that finds the same backup, e.g. from a double click racing the first one, must
	 * not queue a second subscr_signup.
	 */
	public function testClaimsOrderOnce(): void {
		$backup = $this->getDonorBackup( '12345.1' );
		// Both requests see the backup, as they would if they read the session at the same time
		$session = $this->getSession( $backup );

		$firstResult = $this->doApiRequest( $this->getApiParams(), $session );
		$this->assertArrayNotHasKey( 'errors', $firstResult[0]['result'] );
		$claimed = $this->getServiceContainer()->getObjectCacheFactory()->getLocalClusterInstance()
			->get( 'recurring_conversion-12345.1' );
		$this->assertTrue( $claimed );

		// A refused claim is only logged, the API returns no result
		$secondResult = $this->doApiRequest( $this->getApiParams(), $session );
		$this->assertArrayNotHasKey( 'result', $secondResult[0] );

		$queue = QueueWrapper::getQueue( 'recurring' );
		$this->assertSame( 'subscr_signup', $queue->pop()['txn_type'] );
		$this->assertNull( $queue->pop(), 'Second request should not queue another message' );
	}

	/**
	 * A decline claims the order ID too, so a conversion can't follow it.
	 */
	public function testDeclineClaimsOrder(): void {
		$backup = $this->getDonorBackup( '12346.1' );
		$session = $this->getSession( $backup );

		// doRecurringConversion() reads the decline flag from $_REQUEST, which FauxRequest doesn't set
		$_REQUEST['declineMonthlyConvert'] = '1';
		try {
			$declineResult = $this->doApiRequest(
				$this->getApiParams() + [ 'declineMonthlyConvert' => '1' ], $session
			);
		} finally {
			unset( $_REQUEST['declineMonthlyConvert'] );
		}
		$this->assertArrayNotHasKey( 'errors', $declineResult[0]['result'] );

		$convertResult = $this->doApiRequest( $this->getApiParams(), $session );
		$this->assertArrayNotHasKey( 'result', $convertResult[0] );

		$queue = QueueWrapper::getQueue( 'recurring' );
		$this->assertSame( 'monthly_convert_decline', $queue->pop()['txn_type'] );
		$this->assertNull( $queue->pop(), 'Conversion after a decline should not be queued' );
	}

	/**
	 * A backup with no order ID can't be claimed, so it isn't converted.
	 */
	public function testRefusesBackupWithoutOrderId(): void {
		$backup = $this->getDonorBackup( '' );
		unset( $backup['order_id'] );

		$apiResult = $this->doApiRequest( $this->getApiParams(), $this->getSession( $backup ) );
		$this->assertArrayNotHasKey( 'result', $apiResult[0] );
		$this->assertNull( QueueWrapper::getQueue( 'recurring' )->pop() );
		// The token was good, so the unusable backup is still cleared
		$this->assertNull( $apiResult[2][GatewayAdapter::DONOR_BKUP] ?? null );
	}

	/**
	 * A donation still in progress lives in 'Donor', not the backup. Without a
	 * backup there's no completed donation, so nothing should be converted.
	 */
	public function testIgnoresDonorWithoutBackup(): void {
		$session = [
			GatewayAdapter::DONOR => $this->getDonorBackup( '12347.1' ),
			'gravyEditToken' => $this->clearToken,
		];

		$apiResult = $this->doApiRequest( $this->getApiParams(), $session );
		$this->assertNotEmpty( $apiResult[0]['result']['errors'] );
		$this->assertNull( QueueWrapper::getQueue( 'recurring' )->pop() );
	}

	/**
	 * @param string $orderId
	 * @return array Donor data for a completed one-time donation that can be converted
	 */
	private function getDonorBackup( string $orderId ): array {
		$backup = DonationInterfaceTestCase::getDonorTestData();
		$backup['country'] = 'US';
		$backup['email'] = 'good@innocent.com';
		$backup['payment_method'] = 'cc';
		$backup['payment_submethod'] = 'visa';
		$backup['gateway'] = 'gravy';
		$backup['variant'] = 'monthlyConvert';
		$backup['recurring_payment_token'] = 'T1234-5432-9876';
		$backup['gateway_txn_id'] = 'TXN-999-1234';
		$backup['processor_contact_id'] = 'PC-1';
		$backup['contribution_tracking_id'] = explode( '.', $orderId )[0];
		$backup['order_id'] = $orderId;
		return $backup;
	}

	/**
	 * @param array $backup
	 * @return array
	 */
	private function getSession( array $backup ): array {
		return [
			GatewayAdapter::DONOR_BKUP => $backup,
			'gravyEditToken' => $this->clearToken,
		];
	}

	/**
	 * @return array
	 */
	private function getApiParams(): array {
		return [
			'amount' => '1.22',
			'action' => 'di_recurring_convert',
			'gateway' => 'gravy',
			'wmf_token' => $this->saltedToken,
		];
	}
}
