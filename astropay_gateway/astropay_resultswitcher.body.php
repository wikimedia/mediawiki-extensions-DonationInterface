<?php

class AstropayGatewayResult extends GatewayPage {

	public function __construct() {
		$this->adapter = new AstropayAdapter();
		parent::__construct();
	}

	protected function handleRequest() {
		$this->adapter->setCurrentTransaction( 'ProcessReturn' );

		$params = $this->getRequest()->getValues();
		$this->adapter->addResponseData( $params );

		$this->handleResultRequest();
	}
}
