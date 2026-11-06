<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use MediaWiki\Config\Config;
use MediaWiki\Output\OutputPage;

/**
 * What ComboWiki sends to the frontend for one enabled gateway, such as the
 * gateway SDK configuration, and what it adds to the page head for it.
 */
abstract class GatewayClientConfig {

	protected Config $config;

	/**
	 * @return array Variables for the frontend, merged into the ComboWiki config vars
	 */
	abstract public function getVars(): array;

	/**
	 * Adds the links and preloads the gateway needs in the page <head>,
	 * e.g. a stylesheet or a dns-prefetch.
	 *
	 * Some resources are only needed for certain payment methods, so the
	 * supportedPaymentMethods parameter is an optional argument.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	abstract public function addResources( OutputPage $out ): void;

	abstract protected function getConfigPrefix(): string;

	protected function getAccountConfig( string $element ): mixed {
		$key = $this->getConfigPrefix() . 'GatewayAccountInfo';
		$accounts = $this->config->get( $key );
		$firstAccount = array_shift( $accounts );
		if ( !is_array( $firstAccount ) ) {
			throw new \RuntimeException( 'Missing config array ' . $key );
		}
		return $firstAccount[$element];
	}
}
