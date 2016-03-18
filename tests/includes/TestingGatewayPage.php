<?php

class TestingGatewayPage extends GatewayPage {
	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'TestingGenericAdapter' );
		//nothing!
	}

	protected function handleRequest() {
		//also nothing!
	}
}
