<?php

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

/**
 * AdyenCheckoutGateway
 *
 * Special page that uses the Adyen Checkout web implementation to accept donations
 * @property AdyenCheckoutAdapter $adapter
 */
class AdyenCheckoutGateway extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( $out ): void {
		$script = $this->adapter->getAccountConfig( 'Script' );
		$css = $this->adapter->getAccountConfig( 'Css' );
		if ( $this->adapter->getPaymentMethod() == 'google' ) {
			$googleScript = $this->adapter->getAccountConfig( 'GoogleScript' );
			$out->addLink(
				[
					'href' => $googleScript,
					'rel' => 'preload',
					'as' => 'script',
				]
			);
		}
		// We preload the Adyen script here, but add the actual script tag in our adyen.js
		// so we can follow its loading using onload and onerror attributes.
		$out->addLink(
			[
				'href' => $script['src'],
				'integrity' => $script['integrity'],
				'rel' => 'preload',
				'as' => 'script',
				'crossorigin' => 'anonymous',
			]
		);
		$out->addLink(
			[
				'rel' => 'stylesheet',
				'href' => $css['src'],
				'integrity' => $css['integrity'],
				'crossorigin' => 'anonymous'
			]
		);
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$vars['adyenConfiguration'] = $this->adapter->getCheckoutConfiguration();
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'adyen',
			[ 'showError' => true ],
			$this->getConfig()
		);
		if ( $this->adapter->getPaymentSubmethod() ) {
			$vars['payment_submethod'] = $this->adapter->getPaymentSubmethod();
		}
		$vars['DonationInterfaceFailUrl'] = $failPage;
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
		$vars['DonationInterfaceThreeDecimalCurrencies'] = CurrencyRoundingHelper::$threeDecimalCurrencies;
		$vars['DonationInterfaceNoDecimalCurrencies'] = CurrencyRoundingHelper::$noDecimalCurrencies;
		$vars['DonationInterfaceOtherWaysURL'] = $this->adapter->localizeGlobal( 'OtherWaysURL' );
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
