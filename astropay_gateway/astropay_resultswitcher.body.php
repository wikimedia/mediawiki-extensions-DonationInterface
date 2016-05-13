<?php

class AstroPayGatewayResult extends GatewayPage {

	protected $adapterClass = 'AstroPayAdapter';

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
