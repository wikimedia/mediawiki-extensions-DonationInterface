<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class GravyGatewayResult extends ResultSwitcher {

	/** @inheritDoc */
	protected $gatewayIdentifier = GravyAdapter::IDENTIFIER;

	public function showSubmethodButtons(): bool {
		return false;
	}
}
