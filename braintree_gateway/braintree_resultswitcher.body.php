<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class BraintreeGatewayResult extends ResultSwitcher {

	/** @inheritDoc */
	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

}
