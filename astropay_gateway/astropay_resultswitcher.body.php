<?php

class AstroPayGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = AstroPayAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
