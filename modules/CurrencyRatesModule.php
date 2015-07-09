<?php

/**
 * Dynamically generate the javacsript currency rates
 */
class CurrencyRatesModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return 'window.wgCurrencyMinimums = ' .
			Xml::encodeJsVar( CurrencyRates::getCurrencyRates() ) . ';';
	}

	/**
	 * @see ResourceLoaderModule::getModifiedTime()
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return strtotime( CurrencyRates::$lastUpdated );
	}
}
