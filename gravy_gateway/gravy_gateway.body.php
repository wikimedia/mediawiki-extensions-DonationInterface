<?php

/**
 * GravyGateway
 *
 * Special page that uses the Gravy implementation to accept donations
 */
class GravyGateway extends GatewayPage {

	protected $gatewayIdentifier = GravyAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		$secureFields = $this->adapter->getAccountConfig( 'secureFields' );
		$out->addScript( "<script src=\"{$secureFields}\"></script>" );
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'Gravy',
			[ 'showError' => true ],
			$this->getConfig()
		);

		$vars['DonationInterfaceFailUrl'] = $failPage;
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
