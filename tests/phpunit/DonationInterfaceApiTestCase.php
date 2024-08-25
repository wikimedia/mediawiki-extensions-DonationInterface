<?php

use MediaWiki\Session\Token;

class DonationInterfaceApiTestCase extends ApiTestCase {
	/** @var string */
	protected $clearToken = 'blahblah';
	/** @var string */
	protected $saltedToken;

	protected function setUp(): void {
		DonationInterfaceTestCase::setUpSmashPigContext();
		parent::setUp();

		// TODO Use TestConfiguration.php instead?
		$this->overrideConfigValues( [
			'DonationInterface3DSRules' => [ 'INR' => [] ],
			'DonationInterfaceSalt' => 'test_salt',
		] );

		$this->saltedToken = md5( $this->clearToken . 'test_salt' ) . Token::SUFFIX;

		DonationLoggerFactory::$overrideLogger = new TestingDonationLogger();
	}

	protected function tearDown(): void {
		DonationInterfaceTestCase::resetAllEnv();
		parent::tearDown();
	}

	protected function setInitialFiltersToFail() {
		$this->setMwGlobals( DonationInterfaceTestCase::getAllGlobalVariants( [
			'CustomFiltersInitialFunctions' => [
				'getScoreUtmSourceMap' => 100
			],
			'UtmSourceMap' => [
				'/.*/' => 100,
			],
		] ) );
	}
}
