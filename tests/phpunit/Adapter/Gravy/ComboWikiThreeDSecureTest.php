<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Special\Donate;
use MediaWiki\Title\Title;
use PHPUnit\Framework\MockObject\MockObject;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

/**
 * A ComboWiki card donation that needs a 3DS challenge, from the payment
 * through the return to Special:DonateGatewayResult and back to Special:Donate.
 * The responses use values from Gravy sandbox logs of manual testing.
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 * @group ComboWiki
 * @covers \DonateGatewayResult
 */
class ComboWikiThreeDSecureTest extends BaseGravyTestCase {

	private const THANK_YOU_PAGE = 'https://thankyou.example.org/wiki/Thank_You';

	private const TRANSACTION_ID = 'b428b2da-306f-438c-aadd-7c181d58fe4f';

	private const RECONCILIATION_ID = '5TxDWiWKSCcDFokmHzkiQJ';

	private const PAYMENT_METHOD_ID = '3213937d-baae-4859-a904-458b4214c928';

	private const APPROVAL_URL = 'https://cdn.sandbox.wikimedia.gr4vy.app/connectors/adyen/card.html'
		. '?url=https%3A%2F%2Fcheckoutshopper-test.adyen.com%2Fcheckoutshopper%2FthreeDS%2FcheckoutRedirect'
		. '&environment=test&clientKey=None';

	/** What the di_donate_gravy API submits for a ComboWiki card donation */
	private const PAYMENT_ATTEMPT = [
		'amount' => '10.00',
		'appeal' => 'JimmyQuote',
		'color_depth' => '24',
		'contribution_tracking_id' => '1335',
		'country' => 'US',
		'currency' => 'USD',
		'email' => 'nobody@wikimedia.org',
		'first_name' => 'Firstname',
		'gateway' => 'gravy',
		'gateway_session_id' => '48c85434-baf9-4f99-ae97-97ea32c43d94',
		'language' => 'en',
		'last_name' => 'Surname',
		'opt_in' => '0',
		'order_id' => '1335.9',
		'payment_method' => 'cc',
		'payment_submethod' => '',
		'recurring' => '',
		'result_page' => 'combowiki',
		'screen_height' => '1080',
		'screen_width' => '1920',
		'time_zone_offset' => '-60',
		'user_ip' => '127.0.0.1',
		'utm_source' => '..cc',
	];

	/** @var MockObject|CardPaymentProvider */
	private $cardPaymentProvider;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'DonationInterfaceMonthlyConvertCountries' => [ 'US' ],
			'DonationInterfaceThankYouPage' => self::THANK_YOU_PAGE,
		] );
		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );
		$this->providerConfig->overrideObjectInstance( 'payment-provider/cc', $this->cardPaymentProvider );
	}

	public function testDonorWhoPassesChallengeIsOfferedMonthlyConvertOnDonate(): void {
		$adapter = $this->startPaymentThatNeedsChallenge();
		$orderId = $adapter->getData_Unstaged_Escaped( 'order_id' );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->with( [ 'gateway_txn_id' => self::TRANSACTION_ID ] )
			->willReturn( $this->getAuthorizationSucceededResponse() );
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [ 'currency' => 'USD', 'amount' => '10.00', 'gateway_txn_id' => self::TRANSACTION_ID ] )
			->willReturn( $this->getCapturePendingResponse() );

		$redirect = $this->returnFromChallenge(
			$orderId, 'authorization_succeeded', $adapter->token_getSaltedSessionToken()
		);

		$this->assertStringContainsString( 'Special:Donate', $redirect );
		$this->assertStringContainsString( 'monthlyConvert=1', $redirect );
		$this->assertStringContainsString( 'order_id=' . $orderId, $redirect );
		$this->assertStringContainsString( 'gateway=gravy', $redirect );
		$backup = RequestContext::getMain()->getRequest()->getSessionData( GatewayAdapter::DONOR_BKUP );
		$this->assertSame(
			$orderId,
			$backup['order_id'],
			'The adapter should back the donation up for monthly convert'
		);

		// Following the redirect, Special:Donate offers monthly convert for this donation
		$vars = $this->loadDonatePage( $redirect );
		$this->assertTrue( $vars['comboWiki']['monthlyConvertReturn'] );
		$this->assertSame( $orderId, $vars['comboWiki']['order_id'] );
		$this->assertSame( '10.00', $vars['comboWiki']['amount'] );
	}

	public function testDonorWhoPassesChallengeWithoutMonthlyConvertGoesToThankYouPage(): void {
		$this->overrideConfigValue( 'DonationInterfaceMonthlyConvertCountries', [] );
		$adapter = $this->startPaymentThatNeedsChallenge( false );

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn( $this->getAuthorizationSucceededResponse() );
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( $this->getCapturePendingResponse() );

		$redirect = $this->returnFromChallenge(
			$adapter->getData_Unstaged_Escaped( 'order_id' ),
			'authorization_succeeded',
			$adapter->token_getSaltedSessionToken()
		);

		$this->assertStringStartsWith( self::THANK_YOU_PAGE . '/en', $redirect );
		$this->assertNull(
			RequestContext::getMain()->getRequest()->getSessionData( GatewayAdapter::DONOR_BKUP ),
			'Nothing should be backed up when monthly convert is not offered'
		);
	}

	public function testDonorWhoFailsChallengeSeesErrorOnDonate(): void {
		$adapter = $this->startPaymentThatNeedsChallenge();

		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'getLatestPaymentStatus' )
			->willReturn(
				( new PaymentProviderExtendedResponse() )
					->setRawStatus( 'authorization_failed' )
					->setStatus( FinalStatus::FAILED )
					->setSuccessful( false )
					->setGatewayTxnId( self::TRANSACTION_ID )
					->setRawResponse( [
						'status' => 'authorization_failed',
						'error_code' => 'requires_buyer_authentication',
						'raw_response_code' => '11',
						'raw_response_description' => '3D Not Authenticated',
						'three_d_secure' => [
							'version' => '2.2.0',
							'status' => 'declined',
							'method' => 'challenge',
						],
					] )
			);
		$this->cardPaymentProvider->expects( $this->never() )->method( 'approvePayment' );

		$redirect = $this->returnFromChallenge(
			$adapter->getData_Unstaged_Escaped( 'order_id' ),
			'authorization_failed',
			$adapter->token_getSaltedSessionToken()
		);

		$this->assertStringContainsString( 'Special:Donate', $redirect );
		$this->assertStringContainsString( 'paymentFailed=1', $redirect );
		$this->assertStringNotContainsString( 'monthlyConvert', $redirect );
	}

	// A wrong token on the return isn't tested here: GatewayAdapter::token_checkTokens()
	// caches its result in a static for the whole PHP process, so it would depend on
	// test order. DonateGatewayResultTest calls DonateGatewayResult::displayFailPage() directly instead.

	/**
	 * Submit the payment the way the di_donate_gravy API does for a ComboWiki
	 * donor, with Gravy asking for a 3DS challenge, and check Gravy is told to
	 * send the donor back to Special:DonateGatewayResult afterwards.
	 *
	 * @param bool $canOfferMonthlyConvert Whether the card should be stored, so the
	 *  donation can be converted to monthly later
	 */
	private function startPaymentThatNeedsChallenge( bool $canOfferMonthlyConvert = true ): GatewayAdapter {
		$this->setUpRequest( [] );
		$adapter = $this->getFreshGatewayObject( self::PAYMENT_ATTEMPT );

		$createPaymentParams = null;
		$this->cardPaymentProvider->expects( $this->once() )
			->method( 'createPayment' )
			->willReturnCallback( function ( array $params ) use ( &$createPaymentParams ) {
				$createPaymentParams = $params;
				return $this->getBuyerApprovalPendingResponse();
			} );

		$result = $adapter->doPayment();

		$this->assertSame( '10.00', $createPaymentParams['amount'] );
		$this->assertSame( 'USD', $createPaymentParams['currency'] );
		$this->assertSame( self::PAYMENT_ATTEMPT['gateway_session_id'], $createPaymentParams['gateway_session_id'] );
		$this->assertSame( $adapter->getData_Unstaged_Escaped( 'order_id' ), $createPaymentParams['order_id'] );
		$this->assertStringContainsString( 'Special:DonateGatewayResult', $createPaymentParams['return_url'] );
		$this->assertStringContainsString( 'gateway=gravy', $createPaymentParams['return_url'] );
		if ( $canOfferMonthlyConvert ) {
			$this->assertSame(
				RecurringModel::CARD_ON_FILE,
				$createPaymentParams['recurring_model'],
				'The card should be stored, so the donation can be converted to monthly'
			);
		} else {
			$this->assertArrayNotHasKey( 'recurring_model', $createPaymentParams, 'The card should not be stored' );
		}

		$this->assertSame(
			self::APPROVAL_URL,
			$result->getRedirect(),
			'The donor should be sent to the 3DS challenge'
		);
		return $adapter;
	}

	/**
	 * Come back from the challenge with the query string Gravy adds to the
	 * return URL, and return where Special:DonateGatewayResult sends the donor
	 */
	private function returnFromChallenge( string $orderId, string $transactionStatus, string $token ): string {
		$this->setUpRequest( [
			'title' => 'Special:DonateGatewayResult',
			'order_id' => $orderId,
			'wmf_token' => $token,
			'amount' => '10.00',
			'currency' => 'USD',
			'payment_method' => 'cc',
			'gateway' => 'gravy',
			'wmf_source' => '..cc',
			'transaction_id' => self::TRANSACTION_ID,
			'transaction_status' => $transactionStatus,
			'payment_method_id' => self::PAYMENT_METHOD_ID,
		], RequestContext::getMain()->getRequest()->getSessionArray() );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Special:DonateGatewayResult' ) );

		$resultPage = new DonateGatewayResult();
		$resultPage->execute( null );
		return $resultPage->getOutput()->getRedirect();
	}

	/**
	 * Load Special:Donate from a redirect URL, in the same session, and return its client variables
	 */
	private function loadDonatePage( string $url ): array {
		parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
		$this->setUpRequest( $params, RequestContext::getMain()->getRequest()->getSessionArray() );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Special:Donate' ) );

		$services = $this->getServiceContainer();
		$donate = new Donate(
			$services->getService( 'DonationInterface.GatewayConfigurationFactory' ),
			$services->getService( 'DonationInterface.GatewayRouter' ),
			$services->getService( 'DonationInterface.LoggerFactory' ),
		);
		$donate->execute( null );
		$vars = [];
		$donate->setClientVariables( $vars );
		return $vars;
	}

	/**
	 * Gravy's response to createPayment when the card needs a 3DS challenge
	 */
	private function getBuyerApprovalPendingResponse(): CreatePaymentResponse {
		return ( new CreatePaymentResponse() )
			->setRawStatus( 'buyer_approval_pending' )
			->setStatus( FinalStatus::PENDING )
			->setSuccessful( true )
			->setGatewayTxnId( self::TRANSACTION_ID )
			->setRedirectUrl( self::APPROVAL_URL )
			->setBackendProcessor( 'adyen' )
			->setPaymentOrchestratorReconciliationId( self::RECONCILIATION_ID )
			->setRecurringPaymentToken( self::PAYMENT_METHOD_ID );
	}

	/**
	 * Gravy's transaction status after the donor passes the challenge
	 */
	private function getAuthorizationSucceededResponse(): PaymentProviderExtendedResponse {
		return ( new PaymentProviderExtendedResponse() )
			->setRawStatus( 'authorization_succeeded' )
			->setStatus( FinalStatus::PENDING_POKE )
			->setSuccessful( true )
			->setGatewayTxnId( self::TRANSACTION_ID )
			->setBackendProcessor( 'adyen' )
			->setBackendProcessorTransactionId( 'Z9L5WVBG683XH575' )
			->setPaymentOrchestratorReconciliationId( self::RECONCILIATION_ID )
			->setRecurringPaymentToken( self::PAYMENT_METHOD_ID );
	}

	/**
	 * Gravy's response to approvePayment for the authorized transaction
	 */
	private function getCapturePendingResponse(): ApprovePaymentResponse {
		return ( new ApprovePaymentResponse() )
			->setRawStatus( 'capture_pending' )
			->setStatus( FinalStatus::PENDING )
			->setSuccessful( true )
			->setGatewayTxnId( self::TRANSACTION_ID )
			->setBackendProcessor( 'adyen' )
			->setBackendProcessorTransactionId( 'DS9H9GJH4DJXKN75' )
			->setPaymentOrchestratorReconciliationId( self::RECONCILIATION_ID );
	}
}
