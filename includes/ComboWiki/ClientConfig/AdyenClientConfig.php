<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;
use Psr\Log\LoggerInterface;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * The Adyen Checkout config for the ComboWiki frontend, with the payment methods
 * Adyen offers for the donation.
 */
class AdyenClientConfig extends GatewayClientConfig {
	private LoggerInterface $logger;

	/**
	 * @param array $routingParams The ComboWiki routing params, for the country, currency,
	 *  amount and language of the payment methods lookup
	 * @param Config $mwConfig
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly array $routingParams,
		Config $mwConfig,
		LoggerInterface $logger
	) {
		$this->config = $mwConfig;
		$this->logger = $logger;
	}

	protected function getConfigPrefix(): string {
		return 'AdyenCheckout';
	}

	/** @inheritDoc */
	public function getVars(): array {
		return [
			'adyenConfiguration' => $this->getCheckoutConfiguration(),
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

	protected function getCheckoutConfiguration(): array {
		$provider = PaymentProviderFactory::getDefaultProvider();
		'@phan-var \SmashPig\PaymentProviders\Adyen\PaymentProvider $provider';

		// This should have all the payment methods available
		$paymentMethodResult = $provider->getPaymentMethods( [
			'country' => $this->routingParams['country'],
			'currency' => $this->routingParams['currency'],
			'amount' => $this->routingParams['amount'],
			'language' => $this->routingParams['language'],
		] );
		if ( $paymentMethodResult->hasErrors() ) {
			foreach ( $paymentMethodResult->getErrors() as $error ) {
				$this->logger->warning( "paymentMethod lookup error: {$error->getDebugMessage()}" );
			}
			foreach ( $paymentMethodResult->getValidationErrors() as $validationError ) {
				$this->logger->warning( "paymentMethod lookup validation error with parameter: {$validationError->getField()}" );
			}
			throw new \RuntimeException( "Errors returned from getPaymentMethods" );
		}
		$paymentMethodString = $paymentMethodResult->getRawResponse();
		$environment = $this->getAccountConfig( 'Environment' );

		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => $this->getLocale( $this->routingParams['language'], $this->routingParams['country'] ),
			'paymentMethodsResponse' => $paymentMethodString,
			'environment' => $environment,
			'googleEnvironment' => $environment === 'test' ? 'TEST' : 'PRODUCTION',
			'merchantAccountName' => $this->getAccountName(),
			'googleMerchantId' => $this->getAccountConfig( 'GoogleMerchantId' ),
			'googleAllowedNetworks' => $this->getGoogleAllowedNetworks(
				$this->routingParams['country'],
				$this->routingParams['currency'],
			),
			'script' => $this->getAccountConfig( 'Script' ),
			'googleScript' => $this->getAccountConfig( 'GoogleScript' ),
			'appleRequiredBillingContactFields' => [ 'name', 'postalAddress' ],
			'appleRequiredShippingContactFields' => [ 'name', 'email' ]
		];
	}
}
