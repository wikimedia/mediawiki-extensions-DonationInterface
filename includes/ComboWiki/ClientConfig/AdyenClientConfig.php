<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use AdyenCheckoutAdapter;
use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;

/**
 * The Adyen Checkout config for the ComboWiki frontend, with the payment methods
 * Adyen offers for the donation.
 */
class AdyenClientConfig extends GatewayClientConfig {

	/**
	 * @param AdyenCheckoutAdapter $adapter
	 * @param array $routingParams The ComboWiki routing params, for the country, currency,
	 *  amount and language of the payment methods lookup
	 * @param Config $mwConfig
	 */
	public function __construct(
		private readonly AdyenCheckoutAdapter $adapter,
		private readonly array $routingParams,
		Config $mwConfig
	) {
		$this->config = $mwConfig;
	}

	protected function getConfigPrefix(): string {
		return 'AdyenCheckout';
	}

	/**
	 * Google Pay requires the googleEnvironment to be set to either TEST or PRODUCTION.
	 * Here we map the Adyen environment to the Google environment.
	 * - If the Adyen environment is test, set the googleEnvironment to TEST.
	 * - If the Adyen environment is live, set the googleEnvironment to PRODUCTION.
	 *
	 * @param array $adyenConfiguration The Adyen configuration object
	 * @return array The Adyen configuration object with the googleEnvironment set
	 */
	private function addGoogleEnvironment( array $adyenConfiguration ): array {
		$adyenEnvironment = $adyenConfiguration['environment'];
		$adyenConfiguration['googleEnvironment'] = $adyenEnvironment === 'test' ? 'TEST' : 'PRODUCTION';
		return $adyenConfiguration;
	}

	/** @inheritDoc */
	public function getVars(): array {
		$adyenConfiguration = $this->adapter->getCheckoutConfiguration( [
			'country' => $this->routingParams['country'],
			'currency' => $this->routingParams['currency'],
			'amount' => $this->routingParams['amount'],
			'language' => $this->routingParams['language'],
		] );
		$adyenConfiguration = $this->addGoogleEnvironment( $adyenConfiguration );
		return [
			'adyenConfiguration' => $adyenConfiguration,
		];
	}

	/**
	 * Adds the Adyen stylesheet, which sizes the card fields and places the card brand icons.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	public function addResources( OutputPage $out ): void {
		$adyenCss = $this->getAccountConfig( 'Css' );
		$out->addLink( [
			'rel' => 'stylesheet',
			'href' => $adyenCss['src'],
			'integrity' => $adyenCss['integrity'],
			'crossorigin' => 'anonymous'
		] );
	}
}
