<?php

class TestingWorldpayGateway extends WorldpayGateway {

	protected $adapterClass = 'TestingWorldpayAdapter';

	public function __construct() {
		GatewayPage::__construct(); //DANGER: See main class comments.
		// Don't want untranslated 'TestingWorldpayGateway' to foul our tests,
		// don't want to waste translators' time
		$this->mName = 'WorldpayGateway';
	}

}
