<?php

/**
 * BraintreeGateway
 *
 * Special page that uses the Braintree implementation to accept donations
 */
class BraintreeGateway extends GatewayPage {

	protected $gatewayIdentifier = BraintreeAdapter::IDENTIFIER;

}
