<?php

class GlobalCollectGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = GlobalCollectAdapter::IDENTIFIER;

	protected function handleRequest () {
		$this->handleResultRequest();
	}

	/**
	 * Overriding so the answer is correct in case we refactor handleRequest
	 * to use base class's handleResultRequest method.
	 */
	protected function isReturnFramed() {
		return true;
	}
}
