<?php

class IngenicoGatewayResult extends ResultSwitcher {

	/** @inheritDoc */
	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	protected function isReturnFramed() {
		return true;
	}
}
