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

	protected function getAccountArray(): array {
		$key = $this->getConfigPrefix() . 'GatewayAccountInfo';
		$accountArray = $this->config->get( $key );
		if ( !is_array( $accountArray ) ) {
			throw new \RuntimeException( 'Account configuration ' . $key . ' is not an array.' );
		}
		return $accountArray;
	}

	protected function getAccountConfig( string $element ): mixed {
		$accounts = $this->getAccountArray();
		$firstAccount = array_shift( $accounts );
		if ( !is_array( $firstAccount ) ) {
			throw new \RuntimeException(
				'Account configuration for ' . $this->getConfigPrefix() . ' must be an array.'
			);
		}
		return $firstAccount[$element];
	}

	protected function getAccountName(): string {
		$accounts = $this->getAccountArray();
		$accountNames = array_keys( $accounts );
		return array_shift( $accountNames );
	}

	protected function getGoogleAllowedNetworks( string $country, string $currency ): array {
		$networks = [ 'AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA' ];
		// https://developers.google.com/pay/api/web/guides/resources/supported-payment-networks#interac
		if ( $country === 'CA' && $currency === 'CAD' ) {
			$networks[] = 'INTERAC';
		}
		return $networks;
	}

	protected function getLocale( string $language, string $country ): string {
		// Keep only the base language code, e.g. "pt_BR" or "pt-BR" -> "pt"
		$baseLanguage = preg_split( '/[_-]/', $language, 2 )[0];
		return "{$baseLanguage}-{$country}";
	}
}
