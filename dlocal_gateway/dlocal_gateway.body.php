<?php

/**
 * DlocalGateway
 *
 * Special page that uses the Dlocal implementation to accept donations
 */
class DlocalGateway extends GatewayPage {

	protected $gatewayIdentifier = DlocalAdapter::IDENTIFIER;

}
