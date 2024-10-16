<?php

/**
 * GravyGateway
 *
 * Special page that uses the Gravy implementation to accept donations
 */
class GravyGateway extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	/** @inheritDoc */
	protected $gatewayIdentifier = GravyAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		global $wgGravyGatewayID, $wgGravyGatewayEnvironment;
		// Ensure script is only loaded for cc payments
		if ( $this->isCreditCard() ) {
			$out->addJsConfigVars( 'wgGravyEnvironment', $wgGravyGatewayEnvironment );
			$out->addJsConfigVars( 'wgGravyId', $wgGravyGatewayID );
			$secureFieldsJS = $this->adapter->getAccountConfig( 'secureFieldsJS' );
			$secureFieldsCSS = $this->adapter->getAccountConfig( 'secureFieldsCSS' );
			$out->addJsConfigVars( 'secureFieldsScriptLink', $secureFieldsJS );
			$out->addStyle( $secureFieldsCSS );
			$out->addLink(
				[
					'href' => $secureFieldsJS,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		}
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		if ( $this->isCreditCard() ) {
			$checkoutSessionId = $this->getCheckoutSessionId();
			if ( $checkoutSessionId == null ) {
				$vars['DonationInterfaceSetClientVariablesError'] = true;
			} else {
				$vars['gravy_session_id'] = $checkoutSessionId;
			}
		}
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'gravy',
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
		return !$this->isCreditCard();
	}

	/**
	 *
	 * @return bool
	 */
	private function isCreditCard() {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'cc';
	}

	/**
	 * Get the checkout session id for secure fields
	 *
	 * @return string | null
	 */
	private function getCheckoutSessionId() {
		// @phan-suppress-next-line PhanUndeclaredMethod the getCheckoutSession method is defined in the gravy adapter class
		$session = $this->adapter->getCheckoutSession();
		if ( $session->isSuccessful() ) {
			return $session->getPaymentSession();
		}
		return null;
	}

	/**
	 * Overrides parent function to return false if direct.
	 *
	 * @return bool
	 *
	 * @see GatewayPage::showSubmethodButtons()
	 */
	public function showSubmethodButtons() {
		return false;
	}

}
