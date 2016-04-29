<?php

class PaypalExpressGatewayResult extends GatewayPage {

	protected $adapterClass = 'PaypalExpressAdapter';

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
