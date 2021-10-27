<?php

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

/**
 * AdyenCheckoutGateway
 *
 * Special page that uses the Adyen Checkout web implementation to accept donations
 */
class AdyenCheckoutGateway extends GatewayPage {

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

	public function execute( $par ) {
		parent::execute( $par );
		$out = $this->getOutput();
		$script = $this->adapter->getAccountConfig( 'Script' );
		$css = $this->adapter->getAccountConfig( 'Css' );
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
		$failPage = ResultPages::getFailPage( $this->adapter );
		if ( !filter_var( $failPage, FILTER_VALIDATE_URL ) ) {
			// It's a rapidfail form, but we need an actual URL:
			$failPage = GatewayFormChooser::buildPaymentsFormURL(
				$failPage,
				[ 'gateway' => 'adyen' ]
			);
		}
		$vars['DonationInterfaceFailUrl'] = $failPage;
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
