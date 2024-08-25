<?php

class TestingGatewayPage extends GatewayPage {

	/** @inheritDoc */
	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'TestingGenericAdapter' );
		// nothing!
	}

	public function getPageTitle( $subpage = false ) {
		return RequestContext::getMain()->getTitle();
	}

	protected function handleRequest() {
		// also nothing!
	}
}
