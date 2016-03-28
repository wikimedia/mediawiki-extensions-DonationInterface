<?php

class AstropayGatewayResult extends GatewayPage {

	protected $adapterClass = 'AstropayAdapter';

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );
		$this->handleResultRequest();
	}
}
