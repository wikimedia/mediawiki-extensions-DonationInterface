<?php

class TestingWorldpayGateway extends WorldpayGateway {

	public function __construct() {
		$this->adapter = new TestingWorldpayAdapter();
		GatewayPage::__construct(); //DANGER: See main class comments.
		// Don't want untranslated 'TestingWorldpayGateway' to foul our tests,
		// don't want to waste translators' time
		$this->mName = 'WorldpayGateway';
	}

}
