<?php

class AdyenGatewayResult extends GatewayPage {

	protected $adapterClass = 'AdyenAdapter';

	protected function handleRequest() {
		$this->handleResultRequest();
	}

	protected function isReturnFramed() {
		return true;
	}
}
