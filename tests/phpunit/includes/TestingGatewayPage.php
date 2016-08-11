<?php

class TestingGatewayPage extends GatewayPage {

	protected $gatewayName = 'globalcollect';

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'TestingGenericAdapter' );
		//nothing!
	}

	public function getPageTitle( $subpage = false ) {
		return RequestContext::getMain()->getTitle();
	}

	protected function handleRequest() {
		//also nothing!
	}
}
