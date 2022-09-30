<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\ValidationAction;
use Wikimedia\TestingAccessWrapper;

/**
 * @group DonationInterface
 * @group FraudFilters
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
	/**
	 * @var Gateway_Extras_CustomFilters_IP_Velocity
	 */
	protected $ipFilter;
	protected $oldCache;

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
		$this->setMwGlobals( [
			'wgMainCacheType' => CACHE_DB,
			'wgDonationInterfaceEnableIPVelocityFilter' => true,
			'wgDonationInterfaceIPVelocityFailDuration' => 150,
			'wgDonationInterfaceIPVelocityTimeout' => 200,
			'wgDonationInterfaceIPVelocityFailScore' => 100,
			'wgDonationInterfaceIPVelocityThreshhold' => 3,
			'wgDonationInterfaceIPDenyFailScore' => 100,
		] );
		if ( !empty( ObjectCache::$instances[CACHE_DB] ) ) {
			$this->oldCache = ObjectCache::$instances[CACHE_DB];
		}
		$this->cache = new HashBagOStuff();
		ObjectCache::$instances[CACHE_DB] = $this->cache;
		$this->gatewayAdapter = new TestingGenericAdapter( [
			'batch_mode' => false
		] );
		$this->cfo = Gateway_Extras_CustomFilters::singleton(
			$this->gatewayAdapter
		);
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
		ObjectCache::$instances[CACHE_DB] = $this->oldCache;
	}

	public function testStoresTimestampOnPostProcess() {
		Gateway_Extras_CustomFilters_IP_Velocity::onPostProcess(
			$this->gatewayAdapter
		);
		$cached = $this->cache->get( '127.0.0.1' );
		$this->assertCount( 1, $cached );
		// Time should be close to now
		$diff = time() - $cached[0];
		$this->assertTrue( $diff < 2 );
	}

	public function testInitialFilter() {
		Gateway_Extras_CustomFilters_IP_Velocity::onPostProcess(
			$this->gatewayAdapter
		);
		$cached = $this->cache->get( '127.0.0.1' );
		$this->assertCount( 1, $cached );
		// Time should be close to now
		$diff = time() - $cached[0];
		$this->assertTrue( $diff < 2 );
	}

	public function testIPDenyListRejection() {
		// add test IP to deny list
		$this->setMwGlobals( [ 'wgDonationInterfaceIPDenyList' => [ '127.0.0.1' ], ] );

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
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];
		$this->assertEquals( $expected, $message );
	}
}
