<?php

use MediaWiki\Output\OutputPage;

/**
 * BraintreeGateway
 *
 * Special page that uses the Braintree implementation to accept donations
 * @property BraintreeAdapter $adapter
 */
class BraintreeGateway extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	/** @inheritDoc */
	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		foreach ( $this->getScriptsToLoad() as $script ) {
			$this->preloadScript( $script );
		}
	}

	protected function getScriptsToLoad(): array {
		$clientScript = $this->adapter->getAccountConfig( 'clientScript' );
		$paypalScript = $this->adapter->getAccountConfig( 'paypalScript' );
		$venmoScript = $this->adapter->getAccountConfig( 'venmoScript' );
		$deviceScript = $this->adapter->getAccountConfig( 'deviceScript' );

		$paymentMethod = $this->adapter->getData_Unstaged_Escaped( 'payment_method' );

		// Always load the core client script first
		$scripts = [ $clientScript ];

		// Only load the component script needed for this payment method
		if ( $paymentMethod === 'paypal' ) {
			$scripts[] = $paypalScript;
		} elseif ( $paymentMethod === 'venmo' ) {
			$scripts[] = $venmoScript;
		}

		// Device data collection is used across both payment methods
		$scripts[] = $deviceScript;

		return $scripts;
	}

	/** @inheritDoc */
	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'braintree',
			[ 'showError' => true ],
			$this->getConfig()
		);

		$vars['DonationInterfaceFailUrl'] = $failPage;
		$vars['clientToken'] = $this->adapter->getClientToken();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
		$vars['scriptsToLoad'] = $this->getScriptsToLoad();
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
