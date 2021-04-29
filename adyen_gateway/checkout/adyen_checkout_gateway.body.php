<?php

/**
 * AdyenCheckoutGateway
 *
 * Special page that uses the Adyen Checkout web implementation to accept donations
 */
class AdyenCheckoutGateway extends GatewayPage {

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

}
