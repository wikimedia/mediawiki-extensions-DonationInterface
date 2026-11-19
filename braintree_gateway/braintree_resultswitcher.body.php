<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class BraintreeGatewayResult extends ResultSwitcher {

	use BraintreePageTrait;

	/** @inheritDoc */
	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

	/**
	 * Overrides parent function to return false.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showContinueButton()
	 */
	public function showContinueButton() {
		return false;
	}

	protected function getAdapter(): BraintreeAdapter {
		return $this->adapter;
	}

	protected function setClientVariablesFromBaseClass( array &$vars ): void {
		parent::setClientVariables( $vars );
	}
}
