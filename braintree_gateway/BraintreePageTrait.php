<?php

use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;

trait BraintreePageTrait {

	/**
	 * Cached client token to prevent duplicate API calls
	 * @var string|null
	 */
	private ?string $clientToken = null;

	abstract protected function preloadScript(
		string $url,
		bool $crossOriginAnonymous = false,
		?string $integrity = null
	): void;

	/**
	 * @return MediaWiki\Config\Config
	 */
	abstract public function getConfig();

	abstract protected function getAdapter(): BraintreeAdapter;

	protected function addGatewaySpecificResources( OutputPage $out ): void {
		foreach ( $this->getScriptsToLoad() as $script ) {
			$this->preloadScript( $script );
		}
	}

	protected function getScriptsToLoad(): array {
		$adapter = $this->getAdapter();
		$clientScript = $adapter->getAccountConfig( 'clientScript' );
		$paypalScript = $adapter->getAccountConfig( 'paypalCheckoutV6Script' );

		$venmoScript = $adapter->getAccountConfig( 'venmoScript' );
		$deviceScript = $adapter->getAccountConfig( 'deviceScript' );

		$paymentMethod = $adapter->getData_Unstaged_Escaped( 'payment_method' );

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

	abstract protected function setClientVariablesFromBaseClass( array &$vars ): void;

	/** @inheritDoc */
	public function setClientVariables( &$vars ) {
		$this->setClientVariablesFromBaseClass( $vars );
		$failPage = GatewayChooser::buildGatewayPageUrl(
			'braintree',
			[ 'showError' => true ],
			$this->getConfig()
		);

		$vars['DonationInterfaceFailUrl'] = $failPage;
		$vars['clientToken'] = $this->getClientToken();
		$adapter = $this->getAdapter();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $adapter );
		$vars['scriptsToLoad'] = $this->getScriptsToLoad();

		$returnParams = [
			'amount' => $adapter->getData_Unstaged_Escaped( 'amount' ),
			'currency' => $adapter->getData_Unstaged_Escaped( 'currency' ),
			'order_id' => $adapter->getData_Unstaged_Escaped( 'order_id' ),
			'wmf_token' => $adapter->token_getSaltedSessionToken(),
		];
		if ( $adapter->getData_Unstaged_Escaped( 'recurring' ) ) {
			$returnParams['recurring'] = 1;
			$returnParams['frequency_unit'] = $adapter->getData_Unstaged_Escaped( 'frequency_unit' ) ?? 'month';
		}

		$vars['DonationInterfaceReturnUrl'] =
			Title::newFromText( 'Special:BraintreeGatewayResult' )
				->getFullURL( $returnParams, false, PROTO_HTTPS );
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

		$this->clientToken = $this->getAdapter()->getClientToken();
		return $this->clientToken;
	}
}
