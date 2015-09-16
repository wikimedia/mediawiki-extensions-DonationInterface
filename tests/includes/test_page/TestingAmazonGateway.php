<?php

class TestingAmazonGateway extends AmazonGateway {
	public function __construct() {
		$this->adapter = new TestingAmazonAdapter();
		GatewayPage::__construct();
		$this->mName = 'AmazonGateway'; // So as not to add a useless l10n message
	}
}
