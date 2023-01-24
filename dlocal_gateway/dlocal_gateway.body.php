<?php

/**
 * DlocalGateway
 *
 * Special page that uses the Dlocal implementation to accept donations
 */
class DlocalGateway extends GatewayPage {

	protected $gatewayIdentifier = DlocalAdapter::IDENTIFIER;

	public function execute( $par ) {
		parent::execute( $par );
		$out = $this->getOutput();
		$dlocalScript = $this->adapter->getAccountConfig( 'dlocalScript' );
		$smartFieldApiKey = $this->adapter->getAccountConfig( 'smartFieldApiKey' );
		$out->addJsConfigVars( 'wgDlocalSmartFieldApiKey', $smartFieldApiKey );
		$out->addLink(
			[
				'src' => $dlocalScript,
				'rel' => 'preload',
				'as' => 'script',
			]
		);
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$vars['dlocalScript'] = $this->adapter->getAccountConfig( 'dlocalScript' );
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
	}

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
