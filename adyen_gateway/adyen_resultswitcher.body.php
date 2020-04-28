<?php

class AdyenGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = AdyenAdapter::IDENTIFIER;

	public function execute( $par ) {
		$debugLogger = DonationLoggerFactory::getLoggerForType( 'AdyenAdapter' );
		$debugLogger->info(
			'Handling Adyen resultswitcher request with parameters: ' .
			print_r( $this->getRequest()->getValues(), true )
		);
		return parent::execute( $par );
	}

	protected function isReturnFramed() {
		$skinCode = $this->getRequest()->getVal( 'skinCode' );
		$skinConfig = $this->adapter->getAccountConfig( 'Skins' );
		if ( array_key_exists( $skinCode, $skinConfig ) ) {
			return $skinConfig[$skinCode]['Name'] === 'base';
		}
		throw new RuntimeException( "Skin code $skinCode not configured." );
	}
}
