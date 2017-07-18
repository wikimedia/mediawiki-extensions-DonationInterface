<?php

class PaypalExpressGateway extends GatewayPage {
	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	/**
	 * Show the special page
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();
		$this->getOutput()->addModules( 'ext.donationinterface.paypal.scripts' );

		$this->handleDonationRequest();
	}

}
