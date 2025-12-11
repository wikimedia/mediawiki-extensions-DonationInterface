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
		return [
			$this->adapter->getAccountConfig( 'clientScript' ),
			$this->adapter->getAccountConfig( 'paypalScript' ),
			$this->adapter->getAccountConfig( 'venmoScript' ),
			$this->adapter->getAccountConfig( 'deviceScript' ),
		];
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
