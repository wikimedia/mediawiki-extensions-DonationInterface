<?php

use SmashPig\Core\Context;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;

class DonationInterfaceApiTestCase extends ApiTestCase {

	public function setUp() {
		parent::setUp();
		$config = TestingGlobalConfiguration::create();
		TestingContext::init( $config );
		$this->setMwGlobals( array(
			'wgDonationInterfaceEnableQueue' => true,
			'wgDonationInterfaceDefaultQueueServer' => array(
				'type' => 'TestingQueue',
			),
		) );
	}

	public function tearDown() {
		parent::tearDown();
		Context::set( null );
		TestingQueue::clearAll();
	}
}
