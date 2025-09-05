<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\ValidationAction;
use Wikimedia\TestingAccessWrapper;

/**
 * @group DonationInterface
 * @group FraudFilters
 * @covers \Gateway_Extras_CustomFilters_IP_Velocity
 */
class IPVelocityTest extends DonationInterfaceTestCase {

	/**
	 * @var BagOStuff
	 */
	protected $cache;
	/**
	 * @var Gateway_Extras_CustomFilters
	 */
	protected $cfo;

	protected function setUp(): void {
		parent::setUp();

		global $wgDonationInterfaceIPVelocityToxicDuration,
			$wgDonationInterfaceIPVelocityFailDuration,
			$wgDonationInterfaceIPAllowList,
			$wgDonationInterfaceIPDenyList;

		$wgDonationInterfaceIPVelocityToxicDuration = 1000;
		$wgDonationInterfaceIPVelocityFailDuration = 500;

		$wgDonationInterfaceIPAllowList = [ '1.2.3.4' ];
		$wgDonationInterfaceIPDenyList = [ '5.6.7.8' ];
		$this->overrideConfigValues( [
			'DonationInterfaceEnableIPVelocityFilter' => true,
			'DonationInterfaceIPVelocityFailDuration' => 150,
			'DonationInterfaceIPVelocityTimeout' => 200,
			'DonationInterfaceIPVelocityFailScore' => 100,
			'DonationInterfaceIPVelocityThreshhold' => 3,
			'DonationInterfaceIPDenyFailScore' => 100,
		] );
		$this->cache = new HashBagOStuff();
		$this->setMainCache( $this->cache );
		$this->gatewayAdapter = new TestingGenericAdapter();
	}

	protected function tearDown(): void {
		parent::tearDown();

		global $wgDonationInterfaceIPVelocityToxicDuration,
			$wgDonationInterfaceIPVelocityFailDuration,
			$wgDonationInterfaceIPAllowList,
			$wgDonationInterfaceIPDenyList;

		unset( $wgDonationInterfaceIPVelocityToxicDuration );
		unset( $wgDonationInterfaceIPVelocityFailDuration );
		unset( $wgDonationInterfaceIPAllowList );
		unset( $wgDonationInterfaceIPDenyList );
	}

	public function testInitialFilter() {
		Gateway_Extras_CustomFilters_IP_Velocity::onInitialFilter(
			$this->gatewayAdapter,
			Gateway_Extras_CustomFilters::singleton( $this->gatewayAdapter )
		);
		$cached = $this->cache->get( '127.0.0.1' );
		$this->assertCount( 1, $cached );
		// Time should be close to now
		$diff = time() - $cached[0];
		$this->assertTrue( $diff < 2 );
	}

	public function testIPDenyListRejection() {
		// add test IP to deny list
		$this->overrideConfigValue( 'DonationInterfaceIPDenyList', [ '127.0.0.1' ] );

		// set up adapter and run antifraud filters
		$options = $this->getDonorTestData();
		$options['email'] = 'fraudster@example.org';
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );
		$gateway->runAntifraudFilters();

		// test rejection
		$this->assertEquals(
			ValidationAction::REJECT,
			$gateway->getValidationAction(),
			'IPDenyList match not rejected'
		);

		// test risk score is over reject threshold (90)
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals(
			120,
			$exposed->risk_score,
			'RiskScore is not as expected for IPDenyList match'
		);

		// confirm antifraud queue message is as expected
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = [
			'validation_action' => ValidationAction::REJECT,
			'risk_score' => 120,
			'score_breakdown' => [
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 0,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'IPDenyList' => 100,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => null,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'ingenico',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];
		$this->assertEquals( $expected, $message );
	}

	public function testIPVelocityRelayAllowed(): void {
		$relayIP = '172.224.226.15';

		// Set up custom IP in request context
		$this->setUpRequestWithIP( $relayIP );

		// Configure relay list and thresholds
		$this->overrideConfigValues( [
			'DonationInterfaceIPRelayList' => [ '172.224.226.0/27' ],
			'DonationInterfaceIPVelocityThreshhold' => 3,
			'DonationInterfaceIPVelocityRelayThreshold' => 10, // Higher threshold for relays
			'DonationInterfaceCustomFiltersActionRanges' => [
				ValidationAction::PROCESS => [ 0, 80 ],
				ValidationAction::REVIEW => [ 80, 90 ],
				ValidationAction::REJECT => [ 90, 100 ],
			],
		] );

		// Write 5 attempts directly to cache which IP Velocity filter checks.
		// This exceeds the standard threshold (3) but is below the relay threshold (10).
		$timestamps = [
			time() - 0,
			time() - 1,
			time() - 2,
			time() - 3,
			time() - 4
		];
		$this->cache->set( $relayIP, $timestamps );

		// Set up gateway adapter
		$options = self::getDonorTestData();
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );

		// Call onGatewayReady to trigger initial phase filters including IP velocity
		Gateway_Extras_CustomFilters::onGatewayReady( $gateway );
		$gateway->runAntifraudFilters();

		// Should not be rejected because relay IP uses higher threshold
		$this->assertEquals(
		ValidationAction::PROCESS,
		  $gateway->getValidationAction(),
		  'Relay IP with 5 attempts should not be rejected (relay threshold is 10)'
		);

		// Confirm risk score is below rejection threshold
		$accessibleGateway = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertTrue(
		  $accessibleGateway->risk_score < 90,
		  'Relay IP should have removed velocity penalty'
		);

		// Check the queue message to confirm score breakdown
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$this->assertArrayHasKey( 'score_breakdown', $message, 'Message should have score breakdown' );
		if ( isset( $message['score_breakdown']['IPVelocityFilter'] ) ) {
			$this->assertSame(
				0,
				$message['score_breakdown']['IPVelocityFilter'],
				'Relay IP should not get velocity penalty with higher threshold'
			);
		}
	}

	public function testIPVelocityRelayBlocking(): void {
		$relayIP = '172.224.226.15';

		// Set up custom IP in request context
		$this->setUpRequestWithIP( $relayIP );

		// Configure relay list and thresholds
		$this->overrideConfigValues( [
			'DonationInterfaceIPRelayList' => [ '172.224.226.0/27' ],
			'DonationInterfaceIPVelocityThreshhold' => 3,
			'DonationInterfaceIPVelocityRelayThreshold' => 10, // Higher threshold for relays
			'DonationInterfaceCustomFiltersActionRanges' => [
				ValidationAction::PROCESS => [ 0, 80 ],
				ValidationAction::REVIEW => [ 80, 90 ],
				ValidationAction::REJECT => [ 90, 100 ],
			],
		] );

		// Write 12 attempts directly to cache which IP Velocity filter checks.
		// This exceeds the standard threshold (3) and the relay threshold (10).
		$timestamps = [
		  time() - 0,
		  time() - 1,
		  time() - 2,
		  time() - 3,
		  time() - 4,
		  time() - 5,
		  time() - 6,
		  time() - 7,
		  time() - 8,
		  time() - 9,
		  time() - 10,
		  time() - 11
		];
		$this->cache->set( $relayIP, $timestamps );

		// Set up gateway adapter
		$options = self::getDonorTestData();
		$options['payment_method'] = 'cc';
		$gateway = $this->getFreshGatewayObject( $options );

		// Call onGatewayReady to trigger initial phase filters including IP velocity
		Gateway_Extras_CustomFilters::onGatewayReady( $gateway );
		$gateway->runAntifraudFilters();

		// Should be rejected because relay IP hits exceeds the higher threshold
		$this->assertEquals(
			ValidationAction::REJECT,
			$gateway->getValidationAction(),
			'Relay IP with 12 attempts should be rejected (exceeds relay threshold of 10)'
		);

		// Confirm risk score is above rejection threshold
		$accessibleGateway = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertTrue(
		  $accessibleGateway->risk_score > 90,
		  'Relay IP exceeding relay threshold should be given rejection score'
		);

		// Check the queue message to confirm score breakdown
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$this->assertArrayHasKey( 'score_breakdown', $message );
		$this->assertArrayHasKey( 'IPVelocityFilter', $message['score_breakdown'] );
		$this->assertEquals(
			100,
			$message['score_breakdown']['IPVelocityFilter'],
			'Relay IP exceeding relay threshold should get velocity penalty'
		);
	}

	/**
	 * Set up a FauxRequest with a custom IP address for testing
	 */
	private function setUpRequestWithIP( string $ip ): void {
		$request = new \MediaWiki\Request\FauxRequest();
		$request->setIP( $ip );
		RequestContext::getMain()->setRequest( $request );
	}
}
