<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * The Braintree config for the ComboWiki frontend: a client token and the Braintree
 * scripts for the Braintree payment methods on ComboWiki.
 */
class BraintreeClientConfig extends GatewayClientConfig {

	/**
	 * Cached client token, so the gateway is called once even if the vars are built twice.
	 * See T415720.
	 */
	private ?string $clientToken = null;

	/**
	 * @param string[] $paymentMethods The payment methods on ComboWiki that Braintree handles,
	 *  e.g. [ 'paypal', 'venmo' ]
	 * @param Config $mwConfig
	 */
	public function __construct(
		private readonly array $paymentMethods,
		Config $mwConfig
	) {
		$this->config = $mwConfig;
	}

	protected function getConfigPrefix(): string {
		return 'Braintree';
	}

	/** @inheritDoc */
	public function getVars(): array {
		return [
			'braintreeConfiguration' => [
				'clientToken' => $this->getClientToken(),
				'scripts' => $this->getScripts(),
			],
		];
	}

	/**
	 * Preloads the Braintree scripts, so they are ready when the frontend adds them.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	public function addResources( OutputPage $out ): void {
		foreach ( $this->getScripts() as $script ) {
			$out->addLink( [
				'href' => $script,
				'rel' => 'preload',
				'as' => 'script',
			] );
		}
	}

	/**
	 * @return string
	 */
	private function getClientToken(): string {
		if ( !$this->clientToken ) {
			$provider = PaymentProviderFactory::getDefaultProvider();
			'@phan-var \SmashPig\PaymentProviders\Braintree\PaymentProvider $provider';
			$createPaymentSessionResponse = $provider->createPaymentSession();
			$this->clientToken = $createPaymentSessionResponse->getPaymentSession();
		}
		return $this->clientToken;
	}

	/**
	 * The Braintree client script, the script for each Braintree payment method on ComboWiki,
	 * and the device data script, in the order the frontend loads them.
	 *
	 * @return string[]
	 */
	private function getScripts(): array {
		$scripts = [ $this->getAccountConfig( 'clientScript' ) ];
		if ( in_array( 'paypal', $this->paymentMethods, true ) ) {
			$scripts[] = $this->getAccountConfig( 'paypalCheckoutV6Script' );
		}
		if ( in_array( 'venmo', $this->paymentMethods, true ) ) {
			$scripts[] = $this->getAccountConfig( 'venmoScript' );
		}
		$scripts[] = $this->getAccountConfig( 'deviceScript' );
		return $scripts;
	}
}
