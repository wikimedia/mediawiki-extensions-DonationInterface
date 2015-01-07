<?php

class TestingWorldPayGateway extends WorldPayGateway {

	public function __construct() {
		$this->adapter = new TestingWorldPayAdapter();
		GatewayPage::__construct(); //DANGER: See main class comments.
	}

}
