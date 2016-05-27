<?php

class PaypalExpressGateway extends GatewayPage {
	protected $adapterClass = 'PaypalExpressAdapter';

	/**
	 * Show the special page
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();

		$this->handleDonationRequest();
	}

	/**
	 * Always attempt to pass through transparently.
	 *
	 * @see GatewayPage::isProcessImmediate()
	 */
	protected function isProcessImmediate() {
		return true;
	}
}
