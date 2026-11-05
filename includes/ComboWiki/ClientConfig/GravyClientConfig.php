<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use GravyAdapter;
use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;

/**
 * The Gravy config for the ComboWiki frontend, and a DNS prefetch for the Apple Pay script.
 */
class GravyClientConfig extends GatewayClientConfig {

	public function __construct(
		private readonly GravyAdapter $adapter,
		private readonly array $paymentMethods,
		Config $mwConfig
	) {
		$this->config = $mwConfig;
	}

	protected function getConfigPrefix(): string {
		return 'Gravy';
	}

	/** @inheritDoc */
	public function getVars(): array {
		return [
			'gravyConfiguration' => $this->adapter->getGravyConfiguration(),
		];
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
}
