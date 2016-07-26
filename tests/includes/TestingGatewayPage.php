<?php

class TestingGatewayPage extends GatewayPage {

	protected $gatewayName = 'globalcollect';

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'TestingGenericAdapter' );
		//nothing!
	}

	protected function handleRequest() {
		//also nothing!
	}
}
