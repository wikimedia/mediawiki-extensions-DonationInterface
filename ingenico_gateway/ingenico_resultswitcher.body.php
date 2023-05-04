<?php

class IngenicoGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	protected function isReturnFramed() {
		return true;
	}
}
