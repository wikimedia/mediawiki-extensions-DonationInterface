<?php

use SmashPig\Core\Context;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

class DonationInterfaceApiTestCase extends ApiTestCase {

	public function setUp() {
		parent::setUp();
		$config = TestingGlobalConfiguration::create();
		TestingContext::init( $config );
		$ctx = TestingContext::get();
		$ctx->setSourceType( 'payments' );
		$ctx->setSourceName( 'DonationInterface' );
		DonationLoggerFactory::$overrideLogger = new TestingDonationLogger();
	}

	public function tearDown() {
		DonationInterfaceTestCase::resetAllEnv();
		parent::tearDown();
	}
}
