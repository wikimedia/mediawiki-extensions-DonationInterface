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
		global $wgGravyGatewayID, $wgGravyGatewayEnvironment, $wgGravyGooglePayMerchantID;
		$out->addJsConfigVars( 'wgGravyEnvironment', $wgGravyGatewayEnvironment );
		$out->addJsConfigVars( 'wgGravyId', $wgGravyGatewayID );
		// Ensure script is only loaded for cc payments
		if ( $this->isCreditCard() ) {
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
		} elseif ( $this->isGooglePay() ) {
			$googlePayJS = $this->adapter->getAccountConfig( 'GooglePayJS' );
			$out->addJsConfigVars( 'wgGravyGooglePayMerchantID', $wgGravyGooglePayMerchantID );
			$out->addJsConfigVars( 'googleScriptLink', $googlePayJS );
			$out->addLink(
				[
					'href' => $googlePayJS,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		}
	}

	public function setClientVariables( &$vars ): void {
		parent::setClientVariables( $vars );
		// @phan-suppress-next-line PhanUndeclaredMethod get getCheckoutConfiguration is only declared in the Gravy and Adyen adapter
		$vars['gravyConfiguration'] = $this->adapter->getCheckoutConfiguration();
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
	public function showContinueButton(): bool {
		return !( $this->isCreditCard() || $this->isGooglePay() );
	}

	/**
	 *
	 * @return bool
	 */
	private function isCreditCard(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'cc';
	}

	/**
	 *
	 * @return bool
	 */
	private function isGooglePay(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'google';
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
	public function showSubmethodButtons(): bool {
		return false;
	}

}
