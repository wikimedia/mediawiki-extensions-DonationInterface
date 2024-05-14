<?php

/**
 * BraintreeGateway
 *
 * Special page that uses the Braintree implementation to accept donations
 * @property BraintreeAdapter $adapter
 */
class BraintreeGateway extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		$clientScript = $this->adapter->getAccountConfig( 'clientScript' );
		$paypalScript = $this->adapter->getAccountConfig( 'paypalScript' );
		$venmoScript = $this->adapter->getAccountConfig( 'venmoScript' );
		$deviceScript = $this->adapter->getAccountConfig( 'deviceScript' );
		$out->addScript( "<script src=\"{$clientScript}\"></script>" );
		$out->addScript( "<script src=\"{$paypalScript}\"></script>" );
		$out->addScript( "<script src=\"{$venmoScript}\"></script>" );
		$out->addScript( "<script src=\"{$deviceScript}\"></script>" );
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'braintree',
			[ 'showError' => true ],
			$this->getConfig()
		);

		$vars['DonationInterfaceFailUrl'] = $failPage;
		$vars['clientToken'] = $this->adapter->getClientToken();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
	}

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
}
