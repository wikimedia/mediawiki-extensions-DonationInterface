<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataIntegrator;
use MediaWiki\Extension\DonationInterface\Special\ComboWiki;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
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
		$this->cardPaymentProvider->method( 'createPayment' )->willReturn(
			( new CreatePaymentResponse() )
				->setSuccessful( true )
				->setRedirectUrl( 'https://example.org/redirect' )
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

		// Simulate the donor's next transaction attempt in the same session
		// (e.g. ComboWiki is loaded again after a redirect back).
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

		$this->assertSame( $trackingId, $secondTrackingId, 'contribution_tracking_id should stay the same' );
		$this->assertSame(
			(int)$firstSequence + 1,
			(int)$secondSequence,
			'The sequence portion of order_id should increment by 1 after a successful transaction'
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
