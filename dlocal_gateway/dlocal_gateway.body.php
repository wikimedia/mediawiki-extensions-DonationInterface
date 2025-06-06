<?php

/**
 * DlocalGateway
 *
 * Special page that uses the Dlocal implementation to accept donations
 */
use MediaWiki\Output\OutputPage;
use SmashPig\PaymentProviders\dlocal\BankTransferPaymentProvider;
use SmashPig\PaymentProviders\dlocal\ReferenceData;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class DlocalGateway extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	/** @inheritDoc */
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
		} elseif ( $this->adapter->getData_Unstaged_Escaped( 'recurring' ) &&
			in_array( $this->adapter->getData_Unstaged_Escaped( 'payment_submethod' ), [ 'upi', 'paytmwallet' ], true ) ) {
			$paymentMethod = $this->adapter->getData_Unstaged_Escaped( 'payment_method' );
			$paymentProvider = PaymentProviderFactory::getProviderForMethod( $paymentMethod );
			if ( $paymentProvider instanceof BankTransferPaymentProvider ) {
				$isUpiSubscriptionFrequencyMonthly = $paymentProvider->isUpiSubscriptionFrequencyMonthly();
				if ( !$isUpiSubscriptionFrequencyMonthly ) {
					$out->addJsConfigVars( 'isOnDemand', true );
				}
			}
		}
	}

	/** @inheritDoc */
	public function setClientVariables( &$vars ): void {
		parent::setClientVariables( $vars );
		$vars['dlocalScript'] = $this->adapter->getAccountConfig( 'dlocalScript' );
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
		$supportedSubmethods = array_keys( $this->adapter->getAvailableSubmethods() );
		$simpleSubmethods = ReferenceData::getSimpleSubmethods();
		$codeMap = array_filter( $simpleSubmethods, static function ( $key ) use ( $supportedSubmethods ) {
			return in_array( $key, $supportedSubmethods );
		} );
		// india do not support recurring for any cc card type,
		// Uruguay do not support recurring for diners and mc-debit
		// since 3d secure is mandatory for India, South Africa, and debit cards in Brazil
		if ( $this->adapter->getData_Unstaged_Escaped( 'recurring' ) ) {
			$countryCode = $this->adapter->getData_Unstaged_Escaped( 'country' );
			if ( $countryCode === 'IN' ) {
				// todo: maybe some general error for cc recurring since no submethod supported?
				$codeMap = [];
			} elseif ( $countryCode === 'BR' || $countryCode === 'ZA' ) {
				unset( $codeMap['MD'] );
				unset( $codeMap['VD'] );
			} elseif ( $countryCode === 'UY' ) {
				unset( $codeMap['MD'] );
				unset( $codeMap['DC'] );
			}
		}
		$vars['codeMap'] = $codeMap;
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
				!$this->adapter->getData_Unstaged_Escaped( 'recurring' ) &&
				$this->adapter->getAccountConfig( 'enableINDirectBT' )
			)
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
