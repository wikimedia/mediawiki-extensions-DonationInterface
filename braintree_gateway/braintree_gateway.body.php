<?php

/**
 * BraintreeGateway
 *
 * Special page that uses the Braintree implementation to accept donations
 * @property BraintreeAdapter $adapter
 */
class BraintreeGateway extends GatewayPage {

	use BraintreePageTrait;

	/** @inheritDoc */
	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = false;

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
