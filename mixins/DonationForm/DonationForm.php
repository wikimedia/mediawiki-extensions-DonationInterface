<?php
/**
 * This is a WIP, do not use.
 */
class DonationForm implements IBannerMixin {
	protected $controller;

	function register( MixinController $controller ) {
		$this->controller = $controller;

		$controller->registerMagicWord( 'amount', array( $this, 'getLocalizedAmount' ) );
		$controller->registerMagicWord( 'currency', array( $this, 'getCurrency' ) );
		$controller->registerMagicWord( 'minimum-amount', array( $this, 'getMinimumAmount' ) );
		$controller->registerMagicWord( "ask-amount", array( $this, 'getAskAmount' ) );
		$controller->registerMagicWord( "ask-value", array( $this, 'getAskValue' ) );
	}

	function getCurrency() {
		return $this->controller->getFrContext()->getCountry() . "$";
	}

	function getMinimumAmount() {
		$currency = $this->getCurrency();
		//FIXME: round
		return 1.0 / ExchangeRates::getConversion( $currency );
	}

	function getLocalizedAmount( $amount ) {
		return "$amount ZROW";
	}

	function getAskAmount( $level ) {
		return $this->getLocalizedAmount( $this->getAskValue( $level ) );
	}

	function getAskValue( $level ) {
		return $level * 10;
	}
}
