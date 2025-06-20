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
		global $wgGravyGatewayID, $wgGravyGatewayEnvironment, $wgGravyGooglePayMerchantID, $wgGravyRedirectPaypal;
		$out->addJsConfigVars( 'wgGravyEnvironment', $wgGravyGatewayEnvironment );
		$out->addJsConfigVars( 'wgGravyId', $wgGravyGatewayID );
		$out->addJsConfigVars( 'wgGravyRedirectPaypal', $wgGravyRedirectPaypal );
		// Ensure script is only loaded for cc payments
		if ( $this->isCreditCard() ) {
			$secureFieldsJS = $this->adapter->getAccountConfig( 'secureFieldsJS' );
			$secureFieldsCSS = $this->adapter->getAccountConfig( 'secureFieldsCSS' );
			$out->addStyle( $secureFieldsCSS );
			$out->addLink(
				[
					'href' => $secureFieldsJS,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		} elseif ( $this->isGooglePay() ) {
			$googlePayJS = $this->adapter->getAccountConfig( 'GoogleScript' );
			$out->addJsConfigVars( 'wgGravyGooglePayMerchantID', $wgGravyGooglePayMerchantID );
			$out->addLink(
				[
					'href' => $googlePayJS,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		} elseif ( $this->isApplePay() ) {
			$applePayJS = $this->adapter->getAccountConfig( 'AppleScript' );
			$out->addLink(
				[
					'href' => $applePayJS,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		}
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
	private function isACH(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'dd' &&
			$this->adapter->getData_Unstaged_Escaped( 'payment_submethod' ) === 'ach';
	}

	/**
	 *
	 * @return bool
	 */
	private function isGooglePay(): bool {
		return $this->adapter->getData_Unstaged_Escaped( 'payment_method' ) === 'google';
	}

	/**
	 *
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
