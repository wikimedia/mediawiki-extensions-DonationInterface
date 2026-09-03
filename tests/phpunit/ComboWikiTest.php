<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataIntegrator;
use MediaWiki\Extension\DonationInterface\Special\ComboWiki;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group ComboWiki
 * @group Database
 * @covers \MediaWiki\Extension\DonationInterface\Special\ComboWiki
 */
class ComboWikiTest extends DonationInterfaceTestCase {

	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject|CardPaymentProvider
	 */
	private $cardPaymentProvider;

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'DlocalGatewayEnabled' => true,
			'BraintreeGatewayEnabled' => true,
			'PaypalExpressGatewayEnabled' => true,
			'AdyenCheckoutGatewayEnabled' => true,
			'AmazonGatewayEnabled' => true,
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'ingenico' => 'IngenicoAdapter',
				'amazon' => 'AmazonAdapter',
				'adyen' => 'AdyenCheckoutAdapter',
				'paypal_ec' => 'PaypalExpressAdapter',
				'braintree' => 'BraintreeAdapter',
				'dlocal' => 'DlocalAdapter',
				'gravy' => 'GravyAdapter',
			],
			'DonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc' ],
					'gateways' => [ 'gravy' ],
				],
				[
					'conditions' => [ 'payment_method' => 'paypal' ],
					'gateways' => [ 'paypal_ec' ],
				],
				[
					'conditions' => [ 'payment_method' => 'venmo' ],
					'gateways' => [ 'braintree' ],
				],
			],
		] );

		// Mock GravyAdapter::getCheckoutSession(), matching the pattern used in SecureFieldsCardTest.
		$providerConfig = $this->setSmashPigProvider( 'gravy' );
		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );
		$providerConfig->overrideObjectInstance( 'payment-provider/cc', $this->cardPaymentProvider );
		$this->cardPaymentProvider->method( 'createPaymentSession' )->willReturn(
			( new CreatePaymentSessionResponse() )->setSuccessful( true )->setPaymentSession( 'lorem-ipsum' )
		);
	}

	public function testChoosesGravyForCreditCard(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'gravy'
		);
	}

	public function testChoosesPaypalForPaypal(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'paypal',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'paypal_ec'
		);
	}

	public function testChoosesBraintreeForVenmo(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'venmo',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'braintree'
		);
	}

	public function testExplicitlyRequestedSupportedGatewayOverridesPriorityRules(): void {
		// The 'cc' priority rule would normally route to gravy, but adyen also
		// supports cc/US/USD, so an explicit gateway request should win.
		$this->assertChosenGateway(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
				'gateway' => 'adyen',
			],
			'adyen'
		);
	}

	public function testReturnsNullGatewayWhenNoneSupported(): void {
		$this->overrideConfigValues( [
			'DonationInterfaceGatewayAdapters' => [],
		] );

		$this->assertChosenGateway(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			null
		);
	}

	public function testRoutingParamsFallBackToDefaults(): void {
		$vars = $this->executeAndGetClientVariables( [] );

		$params = $vars['comboWiki']['params'];
		$this->assertEquals( 'US', $params['country'] );
		$this->assertEquals( 'USD', $params['currency'] );
		$this->assertEquals( 'cc', $params['payment_method'] );
	}

	public function testGravyClientVariablesIncludeGravySessionData(): void {
		$vars = $this->executeAndGetClientVariables( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		] );

		$this->assertEquals( 'gravy', $vars['comboWiki']['gateway'] );
		$this->assertArrayHasKey( 'gravyConfiguration', $vars );
		$this->assertArrayHasKey( 'wmf_token', $vars );
		$this->assertArrayHasKey( 'DonationInterfaceThankYouPage', $vars );
	}

	public function testNonGravyClientVariablesExcludeGravyConfig(): void {
		$vars = $this->executeAndGetClientVariables( [
			'payment_method' => 'paypal',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		] );

		$this->assertEquals( 'paypal_ec', $vars['comboWiki']['gateway'] );
		$this->assertArrayNotHasKey( 'gravyConfiguration', $vars );
		$this->assertArrayNotHasKey( 'wmf_token', $vars );
	}

	public function testAdapterInitializationDoesNotDuplicateContributionTrackingRecord(): void {
		// ComboWiki::execute() calls ContributionTrackingHelper::handleTrackingData(),
		// which pushes exactly one contribution-tracking record, then constructs a
		// GravyAdapter for the 'gravy' path. Building that adapter must not trigger
		// a second, independent contribution-tracking record via DonationData's own
		// handleContributionTrackingID()/saveContributionTrackingData() logic.
		$vars = $this->executeAndGetClientVariables( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		] );

		$this->assertEquals( 'gravy', $vars['comboWiki']['gateway'] );

		$queue = QueueWrapper::getQueue( 'contribution-tracking' );
		$this->assertNotNull(
			$queue->pop(),
			'Expected one contribution-tracking record from ContributionTrackingHelper'
		);
		$this->assertNull(
			$queue->pop(),
			'GravyAdapter construction inside ComboWiki::execute() pushed a second, duplicate contribution-tracking record'
		);
	}

	public function testAdapterReusesOrderIdComputedByOrderIdHandler(): void {
		// OrderIdHandler computes order_id from the contribution_tracking_id already
		// on file and stores it on ComboWiki's data object before the adapter is
		// built. The adapter must reuse that value rather than generating ifts own
		// order_id (which would happen if it minted a fresh contribution_tracking_id
		// during construction, per the bug covered by
		// testAdapterInitializationDoesNotDuplicateContributionTrackingRecord).
		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$storedDetails = $request->getSession()->get( DataIntegrator::$DONATION_DETAILS_SESSION_KEY );
		$expectedOrderId = $storedDetails['order_id'] ?? null;
		$this->assertNotEmpty(
			$expectedOrderId,
			'OrderIdHandler should have set an order_id on the ComboWiki data object'
		);

		$unwrapped = TestingAccessWrapper::newFromObject( $comboWiki );
		$adapter = $unwrapped->adapter;
		$this->assertNotNull( $adapter, 'Expected a GravyAdapter to have been constructed' );

		$this->assertEquals(
			$expectedOrderId,
			$adapter->getData_Unstaged_Escaped( 'order_id' ),
			'GravyAdapter should reuse the order_id already generated by OrderIdHandler, not generate its own'
		);
	}

	public function testOrderIdSequenceIncrementsForNextTransactionAfterSuccess(): void {
		// GravyAdapter::doPayment() calls incrementSequenceNumber() right after
		// callCreatePayment(), regardless of the outcome, so the next order_id
		// generated in the same session reflects the incremented sequence
		// (@see gateway_common/gateway.adapter.php ensureUniqueOrderID()/generateOrderID()).
		//
		// PENDING_POKE makes requiresApproval() true, so handleCreatedPayment()
		// takes the authorize-then-capture branch and calls callApprovePayment().
		// A successful capture carries the transaction all the way through
		// finalizeInternalStatus( FinalStatus::COMPLETE ), which fully clears the
		// session (@see gateway_common/gateway.adapter.php finalizeInternalStatus()/
		// session_resetForNewAttempt( true )) -- this is why contribution_tracking_id
		// is expected to differ for the next transaction below.
		$this->cardPaymentProvider->method( 'createPayment' )->willReturn(
			( new CreatePaymentResponse() )
				->setSuccessful( true )
				->setStatus( FinalStatus::PENDING_POKE )
		);
		$this->cardPaymentProvider->method( 'approvePayment' )->willReturn(
			( new ApprovePaymentResponse() )
				->setSuccessful( true )
				->setStatus( FinalStatus::COMPLETE )
		);

		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$firstComboWiki = new ComboWiki();
		$firstComboWiki->execute( null );
		$firstAdapter = TestingAccessWrapper::newFromObject( $firstComboWiki )->adapter;
		$firstOrderId = $firstAdapter->getData_Unstaged_Escaped( 'order_id' );

		$result = $firstAdapter->doPayment();
		$this->assertFalse( $result->isFailed(), 'Expected the simulated transaction to succeed' );

		// Simulate the donor starting a new transaction in the same session
		// after the previous one completed (e.g. ComboWiki is loaded again).
		$secondComboWiki = new ComboWiki();
		$secondComboWiki->execute( null );
		$secondAdapter = TestingAccessWrapper::newFromObject( $secondComboWiki )->adapter;
		$secondOrderId = $secondAdapter->getData_Unstaged_Escaped( 'order_id' );

		$this->assertNotEquals(
			$firstOrderId,
			$secondOrderId,
			'Expected a new order_id to be generated for the next transaction attempt'
		);

		[ $trackingId, $firstSequence ] = explode( '.', $firstOrderId );
		[ $secondTrackingId, $secondSequence ] = explode( '.', $secondOrderId );

		$this->assertNotSame(
			$trackingId,
			$secondTrackingId,
			'contribution_tracking_id should differ because the completed transaction cleared the session'
		);
		$this->assertSame(
			(int)$firstSequence + 1,
			(int)$secondSequence,
			'The sequence portion of order_id should increment by 1 after a successful transaction'
		);
	}

	public function testOrderIdSequenceIncrementsForNextTransactionAfterCreatePaymentCompletes(): void {
		// Some payment methods redirect the donor away (e.g. ACH) before the
		// transaction can complete. requiresRedirect() being true makes doPayment()
		// return early, before finalizeInternalStatus() ever runs (@see
		// gravy_gateway/gravy.adapter.php doPayment()), so the session survives
		// that first call intact. The transaction only actually finishes once the
		// donor comes back and processDonorReturn() calls handleCreatedPayment()
		// with the now-COMPLETE status from getLatestPaymentStatus() -- that's what
		// fully clears the session (@see gateway_common/gateway.adapter.php
		// finalizeInternalStatus()/session_resetForNewAttempt( true )), so the same
		// assertions as testOrderIdSequenceIncrementsForNextTransactionAfterSuccess
		// should hold here too.
		$this->cardPaymentProvider->method( 'createPayment' )->willReturn(
			( new CreatePaymentResponse() )
				->setSuccessful( true )
				->setRedirectUrl( 'https://example.org/redirect' )
		);
		$this->cardPaymentProvider->method( 'getLatestPaymentStatus' )->willReturn(
			( new PaymentProviderExtendedResponse() )
				->setSuccessful( true )
				->setStatus( FinalStatus::COMPLETE )
		);
		$this->cardPaymentProvider->expects( $this->never() )->method( 'approvePayment' );

		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$firstComboWiki = new ComboWiki();
		$firstComboWiki->execute( null );
		$firstAdapter = TestingAccessWrapper::newFromObject( $firstComboWiki )->adapter;
		$firstOrderId = $firstAdapter->getData_Unstaged_Escaped( 'order_id' );

		$redirectResult = $firstAdapter->doPayment();
		$this->assertFalse( $redirectResult->isFailed(), 'Expected the initial createPayment call to redirect, not fail' );

		$returnResult = $firstAdapter->processDonorReturn( [] );
		$this->assertFalse( $returnResult->isFailed(), 'Expected the donor return to complete the transaction successfully' );

		// Simulate the donor starting a new transaction in the same session
		// after the previous one completed (e.g. ComboWiki is loaded again).
		$secondComboWiki = new ComboWiki();
		$secondComboWiki->execute( null );
		$secondAdapter = TestingAccessWrapper::newFromObject( $secondComboWiki )->adapter;
		$secondOrderId = $secondAdapter->getData_Unstaged_Escaped( 'order_id' );

		$this->assertNotEquals(
			$firstOrderId,
			$secondOrderId,
			'Expected a new order_id to be generated for the next transaction attempt'
		);

		[ $trackingId, $firstSequence ] = explode( '.', $firstOrderId );
		[ $secondTrackingId, $secondSequence ] = explode( '.', $secondOrderId );

		$this->assertNotSame(
			$trackingId,
			$secondTrackingId,
			'contribution_tracking_id should differ because the completed transaction cleared the session'
		);
		$this->assertSame(
			(int)$firstSequence + 1,
			(int)$secondSequence,
			'The sequence portion of order_id should increment by 1 after a successful transaction'
		);
	}

	public function testOrderIdSequenceIncrementsButContributionTrackingIdUnchangedAfterFailedTransaction(): void {
		// A declined/failed payment still reaches callCreatePayment(), so it still
		// increments the sequence number: incrementSequenceNumber() runs
		// unconditionally right after callCreatePayment(), before the response's
		// success is even checked (@see gravy_gateway/gravy.adapter.php doPayment()).
		// But FAILED/CANCELLED/REVISED statuses leave $force = false in
		// finalizeInternalStatus() (@see gateway_common/gateway.adapter.php), so
		// session_resetForNewAttempt( false ) only soft-resets the order_id inside
		// the 'Donor' session array -- it never calls session_unsetAllData() and
		// never touches the top-level contribution_tracking_id session key. So,
		// unlike a completed transaction, contribution_tracking_id should survive
		// into the next transaction attempt while the sequence still moves on.
		$this->cardPaymentProvider->method( 'createPayment' )->willReturn(
			( new CreatePaymentResponse() )
				->setSuccessful( false )
				->setStatus( FinalStatus::FAILED )
		);

		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$firstComboWiki = new ComboWiki();
		$firstComboWiki->execute( null );
		$firstAdapter = TestingAccessWrapper::newFromObject( $firstComboWiki )->adapter;
		$firstOrderId = $firstAdapter->getData_Unstaged_Escaped( 'order_id' );

		$result = $firstAdapter->doPayment();
		$this->assertTrue( $result->isFailed(), 'Expected the simulated transaction to fail' );

		// Simulate the donor retrying in the same session after the decline.
		$secondComboWiki = new ComboWiki();
		$secondComboWiki->execute( null );
		$secondAdapter = TestingAccessWrapper::newFromObject( $secondComboWiki )->adapter;
		$secondOrderId = $secondAdapter->getData_Unstaged_Escaped( 'order_id' );

		$this->assertNotEquals(
			$firstOrderId,
			$secondOrderId,
			'Expected a new order_id to be generated for the retry attempt'
		);

		[ $trackingId, $firstSequence ] = explode( '.', $firstOrderId );
		[ $secondTrackingId, $secondSequence ] = explode( '.', $secondOrderId );

		$this->assertSame(
			$trackingId,
			$secondTrackingId,
			'contribution_tracking_id should stay the same because a failed transaction does not clear the session'
		);
		$this->assertSame(
			(int)$firstSequence + 1,
			(int)$secondSequence,
			'The sequence portion of order_id should still increment by 1 after a failed transaction'
		);
	}

	public function testDonationDetailsStoredInSession(): void {
		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$storedDetails = $request->getSession()->get( DataIntegrator::$DONATION_DETAILS_SESSION_KEY );
		$this->assertIsArray( $storedDetails );
		$this->assertEquals( 'US', $storedDetails['country'] );
		$this->assertEquals( 'gravy', $storedDetails['gateway'] );
	}

	public function testSessionContributionTrackingIdAndReferrerAreNotOverwrittenByRequest(): void {
		// DataIntegrator::integrateDataFromSession() treats 'referrer' and
		// 'contribution_tracking_id' as fields the session should always win for,
		// unlike most other fields (which only fall back to session when the
		// request didn't supply a value). This keeps a returning donor's tracking
		// id and original referrer stable across multiple page loads/attempts,
		// rather than letting a later request (e.g. a retried form submission with
		// different query params) clobber them.
		// @see includes/ComboWiki/DataIntegrator.php integrateDataFromSession(),
		// the $overwrite = [ 'referrer', 'contribution_tracking_id' ] array.
		$sessionContributionTrackingId = '111111';
		$sessionReferrer = 'en.wikipedia.org/wiki/Session_Page';

		$context = RequestContext::getMain();
		$request = new FauxRequest(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
				'contribution_tracking_id' => '222222',
				'referrer' => 'en.wikipedia.org/wiki/Request_Page',
			],
			false,
			[
				DataIntegrator::$DONATION_DETAILS_SESSION_KEY => [
					'contribution_tracking_id' => $sessionContributionTrackingId,
					'referrer' => $sessionReferrer,
				],
			]
		);
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$dataObject = TestingAccessWrapper::newFromObject( $comboWiki )->dataObject;

		$this->assertSame(
			$sessionContributionTrackingId,
			$dataObject->getValue( 'contribution_tracking_id' ),
			'contribution_tracking_id already in session should win over the value on the incoming request'
		);
		$this->assertSame(
			$sessionReferrer,
			$dataObject->getValue( 'referrer' ),
			'referrer already in session should win over the value on the incoming request'
		);
	}

	public function testUserIpAndReferrerCannotBeSetViaQueryParameters(): void {
		// order_id, user_ip, and referrer must never be settable by the donor via
		// the request: order_id is always server-computed by OrderIdHandler,
		// user_ip must reflect the real connection IP (fraud/geolocation signal),
		// and referrer should reflect where the donor actually came from, not an
		// arbitrary value spoofed in the URL.
		// @see includes/ComboWiki/DataIntegrator.php setUserIp() (real IP via
		// WebRequest::getIP()) and setReferrer() (real value via the Referer header).
		$context = RequestContext::getMain();
		$request = new FauxRequest(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
				// Attempt to spoof these three fields via the query string.
				'order_id' => 'request-supplied-order-id',
				'user_ip' => '6.6.6.6',
				'referer' => 'https://example.com',
			],
			false
		);
		$request->setHeader( 'REFERER', 'https://en.wikipedia.org/wiki/Real_Referring_Page' );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$dataObject = TestingAccessWrapper::newFromObject( $comboWiki )->dataObject;

		$this->assertNotSame(
			'6.6.6.6',
			$dataObject->getValue( 'user_ip' ),
			'user_ip must not be settable via the query string -- it must reflect the real connection IP'
		);
		$this->assertSame(
			'127.0.0.1',
			$dataObject->getValue( 'user_ip' ),
			'user_ip should be the real request IP, not a spoofed one'
		);

		$this->assertNotSame(
			'https://evil.example.com/spoofed',
			$dataObject->getValue( 'referrer' ),
			'referrer must not be settable via the query string -- it must reflect the real Referer header'
		);
		$this->assertSame(
			'en.wikipedia.org/wiki/Real_Referring_Page',
			$dataObject->getValue( 'referrer' ),
			'referrer should be derived from the real Referer header, not the query string'
		);
	}

	public function testWmfParametersMapToUtmParameters(): void {
		// Browsers strip utm_* query params, so campaigns send the wmf_* versions
		// instead. DataIntegrator::setDataFromQueryParameters() is meant to map
		// wmf_campaign/wmf_medium/wmf_source in the URL onto the utm_campaign/
		// utm_medium/utm_source fields the rest of the codebase reads internally.
		// @see includes/ComboWiki/DataIntegrator.php setDataFromQueryParameters()
		$context = RequestContext::getMain();
		$request = new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
			'wmf_campaign' => 'wmf_test_campaign',
			'wmf_medium' => 'wmf_test_medium',
			'wmf_source' => 'wmf_test_source',
		], false );
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$dataObject = TestingAccessWrapper::newFromObject( $comboWiki )->dataObject;

		$this->assertSame(
			'wmf_test_campaign',
			$dataObject->getValue( 'utm_campaign' ),
			'wmf_campaign in the URL should be mapped onto utm_campaign'
		);
		$this->assertSame(
			'wmf_test_medium',
			$dataObject->getValue( 'utm_medium' ),
			'wmf_medium in the URL should be mapped onto utm_medium'
		);
		$this->assertSame(
			'wmf_test_source..cc',
			$dataObject->getValue( 'utm_source' ),
			'wmf_source in the URL should be mapped onto utm_source'
		);
	}

	public function testPostDataOverwritesOverlappingQueryParameters(): void {
		// DataIntegrator::populateData() calls setDataFromQueryParameters() before
		// setDataFromPostParameters(), and both unconditionally call setValue(), so
		// whichever runs last wins for any field present in both -- POST should win,
		// as the more specific/current submission.
		// Note: FauxRequest::getRawInput() (includes/Request/FauxRequest.php) is
		// hard-coded to return '', so a plain FauxRequest can never exercise
		// setDataFromPostParameters(); we override it here to supply a real JSON
		// POST body, matching what DataIntegrator actually reads.
		// @see includes/ComboWiki/DataIntegrator.php populateData(),
		// setDataFromQueryParameters(), setDataFromPostParameters()
		$request = new class(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
				// Overlapping fields also present on the query string.
				'email' => 'query@example.com',
				'amount' => '1.00',
			],
			false
		) extends FauxRequest {
			public function getRawInput() {
				return json_encode( [
					'email' => 'post@example.com',
					'amount' => '99.00',
				] );
			}
		};

		$context = RequestContext::getMain();
		$context->setRequest( $request );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$dataObject = TestingAccessWrapper::newFromObject( $comboWiki )->dataObject;

		$this->assertSame(
			'post@example.com',
			$dataObject->getValue( 'email' ),
			'POST data should overwrite the overlapping query parameter for email'
		);
		$this->assertSame(
			'99.00',
			$dataObject->getValue( 'amount' ),
			'POST data should overwrite the overlapping query parameter for amount'
		);
	}

	public function testAddStylesScriptsAndViewportRegistersModules(): void {
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
		], false ) );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );
		$out = $comboWiki->getOutput();

		$this->assertContains( 'ext.donationInterface.comboWiki', $out->getModules() );
		$this->assertContains( 'donationInterface.skinOverrideStyles', $out->getModuleStyles() );
		$this->assertContains( 'ext.donationInterface.comboWikiStyles', $out->getModuleStyles() );

		$jsConfigVars = $out->getJsConfigVars();
		$this->assertArrayHasKey( 'script_path', $jsConfigVars );
		$this->assertArrayHasKey( 'assets_path', $jsConfigVars );
	}

	public function testVueFrontendModuleAndConfigAreLoaded(): void {
		// Assert that the required fields are passed down to the VueApp frontend
		// after contribution tracking fields are generated, normalization and
		// adapter initialization is completed.
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( [
			'payment_method' => 'cc',
			'amount' => 10,
			'country' => 'US',
			'currency' => 'USD',
			'recurring' => '0',
			'frequency_unit' => 'month'
		], false ) );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );

		$this->assertContains(
			'ext.donationInterface.comboWiki',
			$comboWiki->getOutput()->getModules(),
			'The Vue app bundle module (which contains init.js) must be registered for the frontend to load'
		);

		$vars = [];
		$comboWiki->setClientVariables( $vars );

		$this->assertArrayHasKey(
			'comboWiki',
			$vars,
			'init.js reads mw.config.get( "comboWiki" ) to get the params it mounts the Vue app with'
		);
		$this->assertArrayHasKey(
			'params',
			$vars['comboWiki'],
			'The App component is provided "params" from vars.comboWiki.params'
		);

		// init.js does `vueApp.provide( 'params', comboWikiConfig.params )`, so every
		// field App.vue and its children read off the injected 'params' must be present.
		$params = $vars['comboWiki']['params'];
		foreach ( [
			'amount',
			'country',
			'currency',
			'frequency_unit',
			'payment_method',
			'payment_submethod',
			'recurring',
			'variant',
			'language',
			'gateway',
		] as $requiredParam ) {
			$this->assertArrayHasKey(
				$requiredParam,
				$params,
				"comboWiki.params.$requiredParam must be passed down to the Vue frontend"
			);
		}
		$this->assertSame( 'US', $params['country'] );
		$this->assertSame( 'USD', $params['currency'] );
		$this->assertSame( '10.00', $params['amount'] );
		$this->assertSame( 'cc', $params['payment_method'] );
		$this->assertSame( 'gravy', $params['gateway'] );
		$this->assertSame( 'month', $params['frequency_unit'] );

		$this->assertArrayHasKey( 'language', $vars['comboWiki'] );
		$this->assertSame( 'gravy', $vars['comboWiki']['gateway'] );

		// The gravy path additionally needs these to mount the payment form itself.
		foreach ( [ 'gravyConfiguration', 'wmf_token', 'DonationInterfaceThankYouPage' ] as $requiredKey ) {
			$this->assertArrayHasKey(
				$requiredKey,
				$vars,
				"$requiredKey must be passed down to the Vue frontend for the gravy gateway"
			);
		}
	}

	private function assertChosenGateway( array $params, ?string $expectedGateway ): void {
		$vars = $this->executeAndGetClientVariables( $params );

		$this->assertEquals( $expectedGateway, $vars['comboWiki']['gateway'] );
	}

	private function executeAndGetClientVariables( array $params ): array {
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( $params, false ) );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$comboWiki->execute( null );
		$vars = [];
		$comboWiki->setClientVariables( $vars );

		return $vars;
	}
}
