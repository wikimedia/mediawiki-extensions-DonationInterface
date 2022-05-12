<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class BraintreeGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

}
