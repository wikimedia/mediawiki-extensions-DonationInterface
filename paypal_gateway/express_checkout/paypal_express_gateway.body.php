<?php

class PaypalExpressGateway extends GatewayPage {
	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	/**
	 * Show the special page
	 */
	protected function handleRequest() {
		// FIXME: is this necessary?
		$this->getOutput()->allowClickjacking();
		parent::handleRequest();
	}

}
