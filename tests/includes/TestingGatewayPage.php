<?php

class TestingGatewayPage extends GatewayPage {

	protected $adapterClass = TESTS_ADAPTER_DEFAULT;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'TestingGenericAdapter' );
		//nothing!
	}

	protected function handleRequest() {
		//also nothing!
	}
}
