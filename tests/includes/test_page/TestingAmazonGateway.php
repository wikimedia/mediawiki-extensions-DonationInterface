<?php

class TestingAmazonGateway extends AmazonGateway {

	protected $adapterClass = 'TestingAmazonAdapter';

	public function __construct() {
		GatewayPage::__construct();
		$this->mName = 'AmazonGateway'; // So as not to add a useless l10n message
	}
}
