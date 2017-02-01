<?php

class PaypalExpressGateway extends GatewayPage {
	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	/**
	 * Show the special page
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();
		$this->getOutput()->addModules( 'ext.donationInterface.forms' );

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
