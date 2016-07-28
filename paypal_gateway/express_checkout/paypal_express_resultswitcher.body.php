<?php

class PaypalExpressGatewayResult extends GatewayPage {

	protected $gatewayName = 'paypal_ec';

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
