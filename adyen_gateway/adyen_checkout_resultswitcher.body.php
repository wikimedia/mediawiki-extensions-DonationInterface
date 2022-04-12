<?php

/**
 * Handles donor return from methods involving a redirect, such as iDEAL.
 */
class AdyenCheckoutGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

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
