<?php

/**
 * Dynamically generate the javacsript currency rates
 */
class CurrencyRatesModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		// FIXME: get rid of global var
		return 'window.wgCurrencyMinimums = ' .
			Xml::encodeJsVar( CurrencyRates::getCurrencyRates() ) . ';' .
			'mw.config.set( "wgDonationInterfaceCurrencyRates", window.wgCurrencyMinimums );';
	}

	/**
	 * @see ResourceLoaderModule::getModifiedTime()
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return strtotime( CurrencyRates::$lastUpdated );
	}
}
