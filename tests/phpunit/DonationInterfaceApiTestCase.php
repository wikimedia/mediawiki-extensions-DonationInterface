<?php

use MediaWiki\Session\Token;

class DonationInterfaceApiTestCase extends ApiTestCase {
	protected $clearToken = 'blahblah';
	protected $saltedToken;

	public function setUp(): void {
		DonationInterfaceTestCase::setUpSmashPigContext();
		parent::setUp();
		$this->saltedToken = md5( $this->clearToken ) . Token::SUFFIX;
		DonationLoggerFactory::$overrideLogger = new TestingDonationLogger();
	}

	public function tearDown(): void {
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
