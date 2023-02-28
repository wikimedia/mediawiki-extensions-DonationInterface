<?php

/**
 * DlocalGateway
 *
 * Special page that uses the Dlocal implementation to accept donations
 */
class DlocalGateway extends GatewayPage {

	protected $gatewayIdentifier = DlocalAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		if ( $this->isDirectPaymentFlow() ) {
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
	}

	public function setClientVariables( &$vars ): void {
		parent::setClientVariables( $vars );
		$vars['dlocalScript'] = $this->adapter->getAccountConfig( 'dlocalScript' );
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
	}

	/**
	 * we only accept cc as direct payment flow for now
	 *
	 * @return bool
	 */
	public function isDirectPaymentFlow() {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'cc';
	}

	/**
	 * Overrides parent function to return false if direct.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showSubmethodButtons()
	 */
	public function showSubmethodButtons() {
		return !$this->isDirectPaymentFlow();
	}

	/**
	 * Overrides parent function to return false if direct.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showContinueButton()
	 */
	public function showContinueButton() {
		return !$this->isDirectPaymentFlow();
	}
}
