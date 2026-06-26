<?php

use MediaWiki\Extension\DonationInterface\Tests\SmashPigEnvironmentTrait;
use MediaWiki\Session\Token;
use MediaWiki\Tests\Api\ApiTestCase;

class DonationInterfaceApiTestCase extends ApiTestCase {

	use SmashPigEnvironmentTrait;

	/** @var string */
	protected $clearToken = 'blahblah';
	/** @var string */
	protected $saltedToken;

	protected function setUp(): void {
		$this->setUpSmashPigContext();
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
		$this->resetEnvironment();
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
