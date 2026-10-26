<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use GravyAdapter;
use MediaWiki\Output\OutputPage;

/**
 * The Gravy config for the ComboWiki frontend, and a DNS prefetch for the Apple Pay script.
 */
class GravyClientConfig implements GatewayClientConfig {

	public function __construct( private readonly GravyAdapter $adapter ) {
	}

	/** @inheritDoc */
	public function getVars(): array {
		return [
			'gravyConfiguration' => $this->adapter->getGravyConfiguration(),
		];
	}

	/** @inheritDoc */
	public function addResources( OutputPage $out ): void {
		$applePayHref = $this->adapter->getAccountConfig( 'AppleScript' );
		$out->addLink( [
			'rel' => 'dns-prefetch',
			'href' => 'https://' . parse_url( $applePayHref, PHP_URL_HOST )
		] );
	}
}
