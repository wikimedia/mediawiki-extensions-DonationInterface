<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class GravyGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = GravyAdapter::IDENTIFIER;

}
