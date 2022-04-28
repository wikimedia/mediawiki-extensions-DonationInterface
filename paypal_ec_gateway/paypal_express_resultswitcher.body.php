<?php

class PaypalExpressGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = PaypalExpressAdapter::IDENTIFIER;

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
