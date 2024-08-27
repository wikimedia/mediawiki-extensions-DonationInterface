<?php

/**
 * Handles donor return from methods involving a redirect, such as 3DSecure.
 */
class DlocalGatewayResult extends ResultSwitcher {

	/** @inheritDoc */
	protected $gatewayIdentifier = DlocalAdapter::IDENTIFIER;

}
