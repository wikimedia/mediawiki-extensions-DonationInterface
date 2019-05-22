<?php

use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

class DonationInterfaceApiTestCase extends ApiTestCase {
	public $smashPigGlobalConfig;

	public function setUp() {
		parent::setUp();
		$this->smashPigGlobalConfig = TestingGlobalConfiguration::create();
		TestingContext::init( $this->smashPigGlobalConfig );
		$ctx = TestingContext::get();
		$ctx->setSourceType( 'payments' );
		$ctx->setSourceName( 'DonationInterface' );
		DonationLoggerFactory::$overrideLogger = new TestingDonationLogger();
	}

	public function tearDown() {
		DonationInterfaceTestCase::resetAllEnv();
		parent::tearDown();
	}

	protected function setInitialFiltersToFail() {
		$this->setMwGlobals( [
			'wgDonationInterfaceCustomFiltersInitialFunctions' => [
				'getScoreUtmSourceMap' => 100
			],
			'wgDonationInterfaceUtmSourceMap' => [
				'/.*/' => 100,
			],
		] );
	}
}
