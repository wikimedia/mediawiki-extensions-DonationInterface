<?php

class AdyenGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = AdyenAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->handleResultRequest();
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
