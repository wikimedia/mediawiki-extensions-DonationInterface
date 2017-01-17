<?php

class AstroPayGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = AstroPayAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->handleResultRequest();
	}
}
