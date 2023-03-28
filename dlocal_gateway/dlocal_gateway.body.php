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
			$out->addJsConfigVars( 'isDirectPaymentFlow', true );
			if ( $this->isCreditCard() ) {
				$dlocalScript = $this->adapter->getAccountConfig( 'dlocalScript' );
				$smartFieldApiKey = $this->adapter->getAccountConfig( 'smartFieldApiKey' );
				$out->addJsConfigVars( 'wgDlocalSmartFieldApiKey', $smartFieldApiKey );
				$out->addLink(
					[
						'href' => $dlocalScript,
						'rel' => 'preload',
						'as' => 'script',
					]
				);
			}
		}
	}

	public function setClientVariables( &$vars ): void {
		parent::setClientVariables( $vars );
		$vars['dlocalScript'] = $this->adapter->getAccountConfig( 'dlocalScript' );
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
	}

	/**
	 * we only accept cc and non-recurring upi as direct payment flow for now
	 *
	 * @return bool
	 */
	private function isCreditCard() {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'cc';
	}

	/**
	 * we only accept cc and non-recurring upi as direct payment flow for now
	 *
	 * @return bool
	 */
	public function isDirectPaymentFlow() {
		return ( $this->isCreditCard() ||
			( $this->adapter->getData_Unstaged_Escaped( 'payment_submethod' ) === 'upi' &&
				!$this->adapter->getData_Unstaged_Escaped( 'recurring' ) )
		);
	}

	/**
	 * Overrides parent function to return false if direct.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showSubmethodButtons()
	 */
	public function showSubmethodButtons() {
		return !$this->isCreditCard();
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
