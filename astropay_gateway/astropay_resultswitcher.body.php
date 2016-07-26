<?php

class AstroPayGatewayResult extends GatewayPage {

	protected $gatewayName = 'astropay';

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
