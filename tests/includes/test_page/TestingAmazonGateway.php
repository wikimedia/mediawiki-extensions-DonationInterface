<?php

class TestingAmazonGateway extends AmazonGateway {
	public function __construct() {
		$this->adapter = new TestingAmazonAdapter();
		GatewayPage::__construct();
	}
}
