<?php

class IngenicoGatewayResult extends ResultSwitcher {

	/** @inheritDoc */
	protected $gatewayIdentifier = IngenicoAdapter::IDENTIFIER;

	/** @inheritDoc */
	protected function isReturnFramed() {
		return true;
	}
}
