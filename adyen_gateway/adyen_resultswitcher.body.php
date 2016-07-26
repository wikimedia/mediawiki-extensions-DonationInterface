<?php

class AdyenGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = AdyenAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->handleResultRequest();
	}

	protected function isReturnFramed() {
		return true;
	}
}
