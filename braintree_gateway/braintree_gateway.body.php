<?php

/**
 * BraintreeGateway
 *
 * Special page that uses the Braintree implementation to accept donations
 */
class BraintreeGateway extends GatewayPage {

	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

	public function execute( $par ) {
		parent::execute( $par );
		$out = $this->getOutput();
		$clientScript = $this->adapter->getAccountConfig( 'clientScript' );
		$paypalScript = $this->adapter->getAccountConfig( 'paypalScript' );
		$deviceScript = $this->adapter->getAccountConfig( 'deviceScript' );
		$out->addScript( "<script src=\"{$clientScript}\"></script>" );
		$out->addScript( "<script src=\"{$paypalScript}\"></script>" );
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
