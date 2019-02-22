<?php

/**
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

	public function setUp() {
		parent::setUp();

		global $wgDonationInterfaceIPVelocityToxicDuration,
			$wgDonationInterfaceIPVelocityFailDuration,
			$wgDonationInterfaceIPWhitelist,
			$wgDonationInterfaceIPBlacklist;

		$wgDonationInterfaceIPVelocityToxicDuration = 1000;
		$wgDonationInterfaceIPVelocityFailDuration = 500;

		$wgDonationInterfaceIPWhitelist = [ '1.2.3.4' ];
		$wgDonationInterfaceIPBlacklist = [ '5.6.7.8' ];
		$this->setMwGlobals( [
			'wgMainCacheType' => CACHE_DB,
			'wgDonationInterfaceEnableIPVelocityFilter' => true,
			'wgDonationInterfaceIPVelocityFailDuration' => 150,
			'wgDonationInterfaceIPVelocityTimeout' => 200,
			'wgDonationInterfaceIPVelocityFailScore' => 100,
			'wgDonationInterfaceIPVelocityThreshhold' => 3,
		] );
		$this->oldCache = ObjectCache::$instances[CACHE_DB];
		$this->cache = new HashBagOStuff();
		ObjectCache::$instances[CACHE_DB] = $this->cache;
		$this->gatewayAdapter = new TestingGenericAdapter( [
			'batch_mode' => false
		] );
		$this->cfo = Gateway_Extras_CustomFilters::singleton(
			$this->gatewayAdapter
		);
	}

	public function tearDown() {
		parent::tearDown();

		global $wgDonationInterfaceIPVelocityToxicDuration,
			   $wgDonationInterfaceIPVelocityFailDuration,
			   $wgDonationInterfaceIPWhitelist,
			   $wgDonationInterfaceIPBlacklist;

		unset( $wgDonationInterfaceIPVelocityToxicDuration );
		unset( $wgDonationInterfaceIPVelocityFailDuration );
		unset( $wgDonationInterfaceIPWhitelist );
		unset( $wgDonationInterfaceIPBlacklist );
		ObjectCache::$instances[CACHE_DB] = $this->oldCache;
	}

	public function testStoresTimestampOnPostProcess() {
		Gateway_Extras_CustomFilters_IP_Velocity::onPostProcess(
			$this->gatewayAdapter
		);
		$cached = $this->cache->get( '127.0.0.1' );
		$this->assertEquals( 1, count( $cached ) );
		// Time should be close to now
		$diff = time() - $cached[0];
		$this->assertTrue( $diff < 2 );
	}

	public function testInitialFilter() {
		Gateway_Extras_CustomFilters_IP_Velocity::onPostProcess(
			$this->gatewayAdapter
		);
		$cached = $this->cache->get( '127.0.0.1' );
		$this->assertEquals( 1, count( $cached ) );
		// Time should be close to now
		$diff = time() - $cached[0];
		$this->assertTrue( $diff < 2 );
	}
}
