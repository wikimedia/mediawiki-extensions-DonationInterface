<?php
use SmashPig\PaymentData\ReferenceData\CurrencyRates;

/**
 * Dynamically generate the javascript currency rates
 */
class CurrencyRatesModule extends MediaWiki\ResourceLoader\Module {

	/**
	 * @see MediaWiki\ResourceLoader\Module::getScript()
	 * @inheritDoc
	 */
	public function getScript( MediaWiki\ResourceLoader\Context $context ) {
		return 'mw.config.set( "wgDonationInterfaceCurrencyRates", ' .
			Xml::encodeJsVar( CurrencyRates::getCurrencyRates() ) . ' );';
	}

	/**
	 * @see MediaWiki\ResourceLoader\Module::enableModuleContentVersion()
	 * @return bool
	 */
	public function enableModuleContentVersion() {
		return true;
	}
}
