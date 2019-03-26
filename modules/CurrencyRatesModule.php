<?php
use SmashPig\PaymentData\ReferenceData\CurrencyRates;

/**
 * Dynamically generate the javascript currency rates
 */
class CurrencyRatesModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::getScript()
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return 'mw.config.set( "wgDonationInterfaceCurrencyRates", ' .
			Xml::encodeJsVar( CurrencyRates::getCurrencyRates() ) . ' );';
	}

	/**
	 * @see ResourceLoaderModule::enableModuleContentVersion()
	 * @return bool
	 */
	public function enableModuleContentVersion() {
		return true;
	}
}
