<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use GravyAdapter;
use MediaWiki\Output\OutputPage;

/**
 * The Gravy config for the ComboWiki frontend, and a DNS prefetch for the Apple Pay script.
 */
class GravyClientConfig implements GatewayClientConfig {

	public function __construct(
		private readonly GravyAdapter $adapter,
		private readonly array $paymentMethods
	) {
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
			'apple' => $this->adapter->getAccountConfig( 'AppleScript' ),
			'google' => $this->adapter->getAccountConfig( 'GoogleScript' ),
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
