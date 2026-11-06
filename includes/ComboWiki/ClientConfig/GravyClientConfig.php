<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;
use Psr\Log\LoggerInterface;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * The Gravy config for the ComboWiki frontend, and a DNS prefetch for the Apple Pay script.
 */
class GravyClientConfig extends GatewayClientConfig {

	/**
	 * Payment methods whose frontend form needs a checkout session before it can be set up
	 */
	private const CHECKOUT_SESSION_METHODS = [
		'cc',
	];

	private ?string $sessionID = null;

	public function __construct(
		private readonly array $routingParams,
		private readonly array $paymentMethods,
		Config $mwConfig,
		private readonly LoggerInterface $logger
	) {
		$this->config = $mwConfig;
	}

	protected function getConfigPrefix(): string {
		return 'Gravy';
	}

	/** @inheritDoc */
	public function getVars(): array {
		$params = [
			'gravyConfiguration' => [
				'gravyID' => $this->getAccountConfig( 'gravyID' ),
				'locale' => $this->getLocale( $this->routingParams['language'], $this->routingParams['country'] ),
				'environment' => $this->getAccountConfig( 'environment' ),
				'googleEnvironment' => $this->getAccountConfig( 'googleEnvironment' ),
				'merchantAccountID' => $this->getAccountConfig( 'merchantAccountID' ),
				'gravyGooglePayMerchantId' => $this->getAccountConfig( 'gravyGooglePayMerchantId' ),
				'googleMerchantId' => $this->getAccountConfig( 'GoogleMerchantId' ),
				'googleAllowedNetworks' => $this->getGoogleAllowedNetworks(
					$this->routingParams['country'],
					$this->routingParams['currency'],
				),
				'secureFieldsJsScript' => $this->getAccountConfig( 'secureFieldsJS' ),
				'secureFieldsCSS' => $this->getAccountConfig( 'secureFieldsCSS' ),
				'googleScript' => $this->getAccountConfig( 'GoogleScript' ),
				'appleScript' => $this->getAccountConfig( 'AppleScript' ),
			]
		];
		if ( in_array(
			( $this->routingParams['payment_method'] ?? null ), self::CHECKOUT_SESSION_METHODS, true
		) ) {
			$sessionID = $this->getSessionID();
			if ( $sessionID ) {
				// TODO: Store gravy-specific session ID under gravyConfiguration
				$params['comboWiki']['gateway_session_id'] = $this->getSessionID();
			}
		}
		return $params;
	}

	/** @inheritDoc */
	public function addResources( OutputPage $out ): void {
		$walletScripts = [
			'apple' => $this->getAccountConfig( 'AppleScript' ),
			'google' => $this->getAccountConfig( 'GoogleScript' ),
		];
		foreach ( array_unique( $this->paymentMethods ) as $paymentMethod ) {
			$href = $walletScripts[$paymentMethod] ?? null;
			// Only this gateway's wallets use these scripts, whichever gateway the page defaults to
			if ( $href ) {
				$out->addLink( [
					'rel' => 'preload',
					'as' => 'script',
					'href' => $href
				] );
			}
		}
	}

	/**
	 * @return string|null
	 */
	private function getSessionID(): ?string {
		if ( !$this->sessionID ) {
			$provider = PaymentProviderFactory::getDefaultProvider();
			'@phan-var \SmashPig\PaymentProviders\Gravy\CardPaymentProvider $provider';
			try {
				$createPaymentSessionResponse = $provider->createPaymentSession();
			} catch ( \Exception $e ) {
				// Leave it to the form to create a session, rather than fail the page
				$this->logger->error( 'Creating checkout session failed: ' . $e->getMessage() );
				return null;
			}
			if ( !$createPaymentSessionResponse->isSuccessful() ) {
				$this->logger->error(
					'Creating checkout session failed. Raw response: ' . $createPaymentSessionResponse->getRawResponse()
				);
				return null;
			}
			$this->sessionID = $createPaymentSessionResponse->getPaymentSession();
		}
		return $this->sessionID;
	}
}
