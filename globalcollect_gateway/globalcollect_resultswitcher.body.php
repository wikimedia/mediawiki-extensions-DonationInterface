<?php

class GlobalCollectGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = GlobalCollectAdapter::IDENTIFIER;

	protected function isReturnFramed() {
		return true;
	}
}
