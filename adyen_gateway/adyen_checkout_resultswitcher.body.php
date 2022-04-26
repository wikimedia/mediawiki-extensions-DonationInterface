<?php

/**
 * Handles donor return from methods involving a redirect, such as iDEAL.
 */
class AdyenCheckoutGatewayResult extends ResultSwitcher {

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

}
