<?php

class PaypalExpressGateway extends GatewayPage {
	/** @inheritDoc */
	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	/**
	 * Overrides parent function to return false.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showSubmethodButtons()
	 */
	public function showSubmethodButtons() {
		return false;
	}
}
