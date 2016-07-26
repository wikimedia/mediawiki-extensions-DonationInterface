<?php

class AdyenGatewayResult extends GatewayPage {

	protected $gatewayName = 'adyen';

	protected function handleRequest() {
		$this->handleResultRequest();
	}

	protected function isReturnFramed() {
		return true;
	}
}
