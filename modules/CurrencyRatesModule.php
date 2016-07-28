<?php

/**
 * Dynamically generate the javascript currency rates
 */
class CurrencyRatesModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return
			'mw.config.set( "wgDonationInterfaceCurrencyRates", ' .
			Xml::encodeJsVar( CurrencyRates::getCurrencyRates() ) . ' );';
	}

	/**
	 * @see ResourceLoaderModule::getModifiedTime()
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return strtotime( CurrencyRates::$lastUpdated );
	}
}
