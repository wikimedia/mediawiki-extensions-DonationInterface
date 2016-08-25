<?php

class PaypalExpressGatewayResult extends GatewayPage {

	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
