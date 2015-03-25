<?php

class AdyenGatewayResult extends GatewayPage {

	public function __construct() {
		$this->adapter = new AdyenAdapter();
		parent::__construct();
	}

	protected function handleRequest() {
		$this->handleResultRequest();
	}
}
