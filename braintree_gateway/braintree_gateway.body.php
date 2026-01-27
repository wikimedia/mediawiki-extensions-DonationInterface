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

	/**
	 * Cached client token to prevent duplicate API calls
	 * @var string|null
	 */
	private ?string $clientToken = null;

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
		$vars['clientToken'] = $this->getClientToken();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
		$vars['scriptsToLoad'] = $this->getScriptsToLoad();
	}

	/**
	 * Get the Braintree client token, caching the result to prevent duplicate API calls.
	 *
	 * @return string|null
	 */
	private function getClientToken(): ?string {
		// Return cached value if we've already fetched a token.
		// See https://phabricator.wikimedia.org/T415720 to understand
		// how that can happen.
		if ( $this->clientToken !== null ) {
			return $this->clientToken;
		}

		$this->clientToken = $this->adapter->getClientToken();
		return $this->clientToken;
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
