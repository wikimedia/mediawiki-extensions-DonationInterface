<?php

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

/**
 * AdyenCheckoutGateway
 *
 * Special page that uses the Adyen Checkout web implementation to accept donations
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
			$out->addScript( '<script src="https://pay.google.com/gp/p/js/pay.js"></script>' );
		}
		$out->addScript(
			"<script src=\"{$script['src']}\" " .
			"integrity=\"{$script['integrity']}\" " .
			'crossorigin="anonymous"></script>'
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
