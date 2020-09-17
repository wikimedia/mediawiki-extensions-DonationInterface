<?php

use MediaWiki\Session\Token;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

class DonationInterfaceApiTestCase extends ApiTestCase {
	public $smashPigGlobalConfig;
	protected $clearToken = 'blahblah';
	protected $saltedToken;

	public function setUp() {
		parent::setUp();
		$this->saltedToken = md5( $this->clearToken ) . Token::SUFFIX;
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
