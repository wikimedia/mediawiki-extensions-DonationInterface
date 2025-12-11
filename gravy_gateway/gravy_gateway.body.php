<?php

use MediaWiki\Output\OutputPage;

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
		global $wgGravyGatewayID, $wgGravyGatewayEnvironment, $wgGravyRedirectPaypal;
		$out->addJsConfigVars( 'wgGravyEnvironment', $wgGravyGatewayEnvironment );
		$out->addJsConfigVars( 'wgGravyId', $wgGravyGatewayID );
		$out->addJsConfigVars( 'wgGravyRedirectPaypal', $wgGravyRedirectPaypal );
		$this->setupPaymentMethodResources( $out );
		$this->setupRedirectFlowResources( $out );
	}

	private function setupPaymentMethodResources( OutputPage $out ): void {
		if ( $this->isCreditCard() ) {
			$this->setupCreditCardResources( $out );
		} elseif ( $this->isGooglePay() ) {
			$this->setupGooglePayResources( $out );
		} elseif ( $this->isApplePay() ) {
			$this->setupApplePayResources();
		}
	}

	/**
	 * Setup resources for credit card payments
	 *
	 * @param OutputPage $out
	 */
	private function setupCreditCardResources( OutputPage $out ): void {
		$secureFieldsJS = $this->adapter->getAccountConfig( 'secureFieldsJS' );
		$secureFieldsCSS = $this->adapter->getAccountConfig( 'secureFieldsCSS' );
		$out->addStyle( $secureFieldsCSS );
		$this->preloadScript( $secureFieldsJS );
	}

	/**
	 * Setup resources for Google Pay payments
	 *
	 * @param OutputPage $out
	 */
	private function setupGooglePayResources( OutputPage $out ): void {
		global $wgGravyGooglePayMerchantID;
		$googlePayJS = $this->adapter->getAccountConfig( 'GoogleScript' );
		if ( $wgGravyGooglePayMerchantID ) {
			$out->addJsConfigVars( 'wgGravyGooglePayMerchantID', $wgGravyGooglePayMerchantID );
		}
		$this->preloadScript( $googlePayJS );
	}

	/**
	 * Setup resources for Apple Pay payments
	 */
	private function setupApplePayResources(): void {
		$applePayJS = $this->adapter->getAccountConfig( 'AppleScript' );
		$this->preloadScript( $applePayJS );
	}

	/**
	 * Setup resources for redirect payment flows
	 *
	 * @param OutputPage $out
	 */
	private function setupRedirectFlowResources( OutputPage $out ): void {
		if ( $this->isRedirectPaymentFlow() ) {
			// If the payment flow is a redirect, we need to show the redirect text for donor
			$out->addJsConfigVars( 'showRedirectText', true );
		}
	}

	/** @inheritDoc */
	public function setClientVariables( &$vars ): void {
		parent::setClientVariables( $vars );
		// @phan-suppress-next-line PhanUndeclaredMethod get getCheckoutConfiguration is only declared in the Gravy and Adyen adapter
		$vars['gravyConfiguration'] = $this->adapter->getGravyConfiguration();
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
	 * @return bool
	 */
	public function isRedirectPaymentFlow(): bool {
		return in_array(
			$this->adapter->getData_Unstaged_Escaped( 'payment_method' ),
			[ 'bt', 'cash' ],
			true
		);
	}

	public function showSubmethodButtons(): bool {
		return !( $this->isCreditCard() || $this->isGooglePay() || $this->isApplePay() || $this->isACH() );
	}

	public function showContinueButton(): bool {
		return !( $this->isCreditCard() || $this->isGooglePay() || $this->isApplePay() );
	}

	/**
	 * @return bool
	 */
	private function isCreditCard(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'cc';
	}

	/**
	 * @return bool
	 */
	private function isACH(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'dd' &&
			$this->adapter->getData_Unstaged_Escaped( 'payment_submethod' ) === 'ach';
	}

	/**
	 * @return bool
	 */
	private function isGooglePay(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'google';
	}

	/**
	 * @return bool
	 */
	private function isApplePay(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'apple';
	}

	/**
	 * Get the checkout session id for secure fields
	 *
	 * @return string | null
	 */
	private function getCheckoutSessionId(): ?string {
		// @phan-suppress-next-line PhanUndeclaredMethod the getCheckoutSession method is defined in the gravy adapter class
		$session = $this->adapter->getCheckoutSession();
		if ( $session->isSuccessful() ) {
			return $session->getPaymentSession();
		}
		return null;
	}

}
