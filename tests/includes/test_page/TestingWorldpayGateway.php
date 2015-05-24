<?php

class TestingWorldpayGateway extends WorldpayGateway {

	public function __construct() {
		$this->adapter = new TestingWorldpayAdapter();
		GatewayPage::__construct(); //DANGER: See main class comments.
	}

}
