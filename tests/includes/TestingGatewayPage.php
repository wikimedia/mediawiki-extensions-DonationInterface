<?php

class TestingGatewayPage extends GatewayPage {
	public function __construct() {
		$this->logger = DonationLoggerFactory::getLogger();
		//nothing!
	}
	protected function handleRequest() {
		//also nothing!
	}
}
